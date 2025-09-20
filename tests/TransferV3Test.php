<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use SymbolSdk\CryptoTypes\PrivateKey;
use SymbolSdk\CryptoTypes\KeyPair;
use SymbolSdk\CatbufferFacade\Transfer;
use SymbolSdk\Signer\Ed25519;
use SymbolSdk\Signer\TxHash;

final class TransferV3Test extends TestCase {
    public function test_build_sign_embed_v3(): void {
        $kp = KeyPair::fromPrivateKey(PrivateKey::fromHex(getenv('SK')));
        $unsigned = Transfer::fromParamsWithMosaics(
            (int)getenv('NETWORK'),
            (int)getenv('DEADLINE_MS'),
            200000,
            (string)hex2bin(getenv('RECIPIENT_RAW24')),
            'hello symbol',
            [['id'=>0x6BED913FA20223F8,'amount'=>1000000]]
        );
        $this->assertSame(3, ord($unsigned[104])); // version=3
        $this->assertSame(1, ord($unsigned[150])); // mosaicsCount=1（151固定部なので 150=オフセット）

        $bytesToSign = Transfer::bytesToSign($unsigned, getenv('GEN_HASH'));
        $sig = Ed25519::sign($bytesToSign, $kp);
        $signed = Transfer::embedSignature($unsigned, $sig, $kp->publicKey());

        $this->assertNotSame(str_repeat("\x00",64), substr($signed,8,64));
        $this->assertNotSame(str_repeat("\x00",32), substr($signed,72,32));

        $hash = TxHash::ofPayload($signed, getenv('GEN_HASH'));
        $this->assertSame(32, strlen($hash));
    }
}
