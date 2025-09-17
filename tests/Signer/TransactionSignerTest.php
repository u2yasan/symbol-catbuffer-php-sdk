<?php
declare(strict_types=1);

namespace Tests\Signer;

use PHPUnit\Framework\TestCase;
use SymbolSdk\CryptoTypes\PrivateKey;
use SymbolSdk\CryptoTypes\KeyPair;
use SymbolSdk\Signer\TransactionSigner;

final class TransactionSignerTest extends TestCase
{
    public function test_sign_and_verify_arbitrary_bytes(): void
    {
        $seed = hex2bin('000102030405060708090a0b0c0d0e0f000102030405060708090a0b0c0d0e0f');
        $kp   = KeyPair::fromPrivateKey(new PrivateKey($seed));
        $msg  = random_bytes(64);

        $sig = TransactionSigner::signBytes($msg, $kp);
        $this->assertSame(64, strlen($sig));
        $this->assertTrue(TransactionSigner::verify($msg, $sig, $kp->publicKey()));
    }

    public function test_sign_bytes_hex(): void
    {
        $seed = hex2bin('f0f1f2f3f4f5f6f7f8f9fafbfcfdfeff000102030405060708090a0b0c0d0e0f');
        $kp   = KeyPair::fromPrivateKey(new PrivateKey($seed));
        $hex  = 'deadbeef';
        $sigHex = TransactionSigner::signBytesHex($hex, $kp);

        $this->assertSame(128, strlen($sigHex)); // 64B → 128 hex chars
    }
}
