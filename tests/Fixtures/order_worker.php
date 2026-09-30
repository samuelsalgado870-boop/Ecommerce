<?php

use App\Exceptions\BusinessConflict;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
config(['database' => $input['database']]);
DB::purge();
$actor = User::findOrFail($input['actor']);
file_put_contents($input['barrier'].'/'.$input['actor'].'.ready', 'ready');
$deadline = microtime(true) + 15;
while (! is_file($input['barrier'].'/go')) {
    if (microtime(true) > $deadline) {
        fwrite(STDERR, 'Barrier timeout');
        exit(2);
    }
    usleep(10000);
}
try {
    $order = app(OrderService::class)->create($actor,
        [['id_producto' => $input['product'], 'cantidad' => 1]], $input['key']);
    echo json_encode(['status' => 'success', 'order' => $order->getKey()], JSON_THROW_ON_ERROR);
} catch (BusinessConflict) {
    echo json_encode(['status' => 'conflict'], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    // Do not emit query bindings or connection configuration to logs.
    fwrite(STDERR, get_class($exception));
    exit(3);
}