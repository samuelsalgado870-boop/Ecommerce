<?php

namespace Tests\Unit;

use App\Services\Money;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    #[TestWith(['0', 0, '0.00'])]
    #[TestWith(['0.01', 1, '0.01'])]
    #[TestWith(['12.35', 1235, '12.35'])]
    #[TestWith(['9999999999.99', 999999999999, '9999999999.99'])]
    public function test_money_is_converted_using_exact_integer_cents(string $input, int $cents, string $decimal): void
    {
        $this->assertSame($cents, Money::cents($input));
        $this->assertSame($decimal, Money::decimal($cents));
    }

    #[TestWith(['-1'])]
    #[TestWith(['1.001'])]
    #[TestWith(['1e2'])]
    #[TestWith(['10000000000.00'])]
    public function test_invalid_or_overflowing_money_is_rejected(string $input): void
    {
        $this->expectException(ValidationException::class);

        Money::cents($input);
    }
}