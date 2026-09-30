<?php

namespace Tests\Integration;

use App\Models\Producto;
use App\Models\Rol;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class InventoryConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        if (getenv('RUN_MYSQL_CONCURRENCY') !== '1' || getenv('DB_CONNECTION') !== 'mysql'
            || ! str_ends_with((string) getenv('DB_DATABASE'), '_test')) {
            $this->markTestSkipped('Activar explícitamente MySQL y una base dedicada terminada en _test.');
        }
        parent::setUp();
    }

    public function test_only_one_simultaneous_order_can_consume_the_last_unit(): void
    {
        $this->seed();
        $role = Rol::where('codigo', 'cliente')->firstOrFail();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $a->roles()->attach($role->getKey());
        $b->roles()->attach($role->getKey());
        $product = Producto::factory()->create();
        app(InventoryService::class)->change($product->getKey(), 'ENTRADA', 1, $a, 'initial');
        $barrier = sys_get_temp_dir().DIRECTORY_SEPARATOR.'commerce-'.Str::uuid();
        File::makeDirectory($barrier, 0700, true);
        $workers = [];
        try {
            foreach ([$a, $b] as $actor) {
                $worker = new Process([PHP_BINARY, base_path('tests/Fixtures/order_worker.php')], base_path(), [
                    'APP_ENV' => 'testing', 'APP_DEBUG' => 'false',
                    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
                ]);
                $worker->setTimeout(30);
                $worker->setInput(json_encode([
                    'database' => config('database'), 'actor' => $actor->getKey(),
                    'product' => $product->getKey(), 'key' => (string) Str::uuid(), 'barrier' => $barrier,
                ], JSON_THROW_ON_ERROR));
                $worker->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 15;
            while (count(File::glob($barrier.'/*.ready')) < 2) {
                foreach ($workers as $worker) {
                    if (! $worker->isRunning()) {
                        $this->fail('Worker failed before barrier: '.$worker->getErrorOutput());
                    }
                }
                if (microtime(true) > $deadline) {
                    $this->fail('Concurrent workers did not reach the barrier.');
                }
                usleep(10000);
            }
            File::put($barrier.'/go', 'go');
            $statuses = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
                $statuses[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR)['status'];
            }
            sort($statuses);

            $this->assertSame(['conflict', 'success'], $statuses);
            $this->assertSame(0, $product->refresh()->stock);
            $this->assertSame(1, DB::table('ordenes')->count());
            $this->assertSame(1, DB::table('movimientos_inventario')->where('tipo', 'SALIDA')->count());
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            File::deleteDirectory($barrier);
            $this->truncateTablesForAllConnections();
        }
    }
}
