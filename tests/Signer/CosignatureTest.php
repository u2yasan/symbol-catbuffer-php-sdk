<?php
declare(strict_types=1);

namespace Tests\Signer;

use PHPUnit\Framework\TestCase;
use SymbolSdk\CryptoTypes\PrivateKey;
use SymbolSdk\CryptoTypes\KeyPair;
use SymbolSdk\Signer\Cosignature;

final class CosignatureTest extends TestCase
{
    public function test_sign_aggregate_hash_and_dto(): void
    {
        $kp = KeyPair::fromPrivateKey(new PrivateKey(random_bytes(32)));
        $parentHash = random_bytes(32);

        $sig = Cosignature::signAggregateHash($parentHash, $kp);
        $this->assertSame(64, strlen($sig));

        $dto = Cosignature::toDtoHex($parentHash, $kp);
        $this->assertArrayHasKey('parentHash', $dto);
        $this->assertArrayHasKey('signature', $dto);
        $this->assertArrayHasKey('signerPublicKey', $dto);
        $this->assertSame(64, strlen($dto['parentHash']));
        $this->assertSame(128, strlen($dto['signature']));
        $this->assertSame(64, strlen($dto['signerPublicKey']));
    }
}
