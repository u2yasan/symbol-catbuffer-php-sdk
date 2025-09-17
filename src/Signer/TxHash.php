<?php
declare(strict_types=1);

namespace SymbolSdk\Signer;

use SymbolSdk\Crypto\Hash\Hasher;

final class TxHash
{
    /** @return string 32B hash */
    public static function sha3_256_of_payload(string $signedPayload): string
    {
        return Hasher::sha3_256($signedPayload);
    }
}
