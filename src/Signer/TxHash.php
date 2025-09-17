<?php
declare(strict_types=1);

namespace SymbolSdk\Signer;

use SymbolSdk\Crypto\Hash\Hasher;

final class TxHash
{
    /** 署名済み payload 全体の SHA3-256（raw 32B） */
    public static function sha3_256_of_payload(string $signedPayloadBin): string
    {
        return Hasher::sha3_256($signedPayloadBin);
    }

    public static function sha3_256_of_payload_hex(string $signedPayloadHex): string
    {
        return bin2hex(self::sha3_256_of_payload((string) hex2bin($signedPayloadHex)));
    }
}
