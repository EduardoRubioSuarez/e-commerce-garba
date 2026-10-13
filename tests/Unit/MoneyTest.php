<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_formats_cents_with_currency_sign_thousands_separator_and_two_decimals(): void
    {
        $this->assertSame('$0.00', Money::format(0));
        $this->assertSame('$0.05', Money::format(5));
        $this->assertSame('$150.80', Money::format(15080));
        $this->assertSame('$1,508.00', Money::format(150800));
        $this->assertSame('$13,572.00', Money::format(1357200));
        $this->assertSame('$1,234,567.89', Money::format(123456789));
        $this->assertSame('-$117.00', Money::format(-11700));
    }
}
