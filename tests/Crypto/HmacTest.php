<?php
declare(strict_types=1);

namespace Tests\Crypto;

use PHPUnit\Framework\TestCase;
use SymbolSdk\Crypto\Hmac;

final class HmacTest extends TestCase
{
    public function test_hmac_sha256_known(): void
    {
        // RFC 4231 Test Case 1
        $key  = str_repeat("\x0b", 20);        // 0x0b * 20 bytes
        $data = "Hi There";                     // プレーン文字列（バイナリではない!!）

        $this->assertSame(
            'b0344c61d8db38535ca8afceaf0bf12b881dc200c9833da726e9376c2e32cff7',
            bin2hex(\SymbolSdk\Crypto\Hmac::hmac_sha256($key, $data))
        );
    }

    public function test_hmac_sha256_rfc4231_case2(): void
    {
        // RFC 4231 Test Case 2: key="Jefe", data="what do ya want for nothing?"
        $key  = "Jefe";
        $data = "what do ya want for nothing?";

        $this->assertSame(
            '5bdcc146bf60754e6a042426089575c75a003f089d2739839dec58b964ec3843',
            bin2hex(\SymbolSdk\Crypto\Hmac::hmac_sha256($key, $data))
        );
    }

    public function test_hmac_sha3_256_smoke(): void
    {
        $out = Hmac::hmac_sha3_256('key', 'data');
        $this->assertSame(32, strlen($out));
        // 再現性
        $this->assertSame(bin2hex($out), bin2hex(Hmac::hmac_sha3_256('key', 'data')));
    }
}
