<?php
declare(strict_types=1);

namespace SymbolSdk\Signer;

use SymbolSdk\CryptoTypes\KeyPair;

final class TransactionSigner
{
    /**
     * @param string $bytesToSign  署名対象バイト列（catbufferが作る署名対象ペイロード）
     * @return string 64B signature
     */
    public static function signBytes(string $bytesToSign, KeyPair $kp): string
    {
        return \SymbolSdk\Crypto\Ed25519\Signer::signWith($kp, $bytesToSign);
    }
}
