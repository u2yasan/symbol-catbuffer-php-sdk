<?php
declare(strict_types=1);

namespace SymbolSdk\Signer;

use SymbolSdk\CryptoTypes\KeyPair;

final class Cosignature
{
    /**
     * @param string $aggregateHash 32B（親Aggregateのハッシュ）
     * @return string 64B signature
     */
    public static function signAggregateHash(string $aggregateHash, KeyPair $kp): string
    {
        return \SymbolSdk\Crypto\Ed25519\Signer::signWith($kp, $aggregateHash);
    }
}
