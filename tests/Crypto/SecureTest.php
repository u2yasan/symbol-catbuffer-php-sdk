<?php
declare(strict_types=1);

namespace Tests\Crypto;

use PHPUnit\Framework\TestCase;
use SymbolSdk\Crypto\Secure;

final class SecureTest extends TestCase
{
    public function test_equals_ct(): void
    {
        $a = random_bytes(32);
        $b = $a;
        $c = random_bytes(32);
        $this->assertTrue(Secure::equals($a, $b));
        $this->assertFalse(Secure::equals($a, $c));
    }

    public function test_memzero(): void
    {
        $s = random_bytes(16);
        $len = strlen($s);
        Secure::memzero($s);
        $this->assertSame($len, strlen($s));
        $this->assertSame(str_repeat('00', $len), bin2hex($s));
    }
}
