<?php
declare(strict_types=1);

namespace Tests\Signer;

use PHPUnit\Framework\TestCase;
use SymbolSdk\Signer\TxHash;

final class TxHashTest extends TestCase
{
    public function test_sha3_256_of_payload(): void
    {
        $payload = random_bytes(200);
        $hRaw = TxHash::sha3_256_of_payload($payload);
        $hHex = TxHash::sha3_256_of_payload_hex(bin2hex($payload));

        $this->assertSame(32, strlen($hRaw));
        $this->assertSame(bin2hex($hRaw), $hHex);
    }
}
