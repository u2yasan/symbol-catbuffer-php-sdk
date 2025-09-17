<?php
declare(strict_types=1);

namespace SymbolSdk\Crypto\Ed25519;

use SymbolSdk\CryptoTypes\KeyPair;
use SymbolSdk\CryptoTypes\PrivateKey;
use SymbolSdk\CryptoTypes\PublicKey;

final class Signer {
    /**
     * @return string signature(64 bytes)
     */
    public static function sign(string $message, PrivateKey $sk, ?PublicKey $pk = null): string {
        // sodiumは64B秘密鍵形式を要求 → seedから展開
        $kp = \sodium_crypto_sign_seed_keypair($sk->bytes());
        $sk64 = \sodium_crypto_sign_secretkey($kp);
        try {
            return \sodium_crypto_sign_detached($message, $sk64);
        } finally {
            if (function_exists('sodium_memzero')) {
                \sodium_memzero($sk64);
            }
        }
    }

    public static function signWith(KeyPair $kp, string $message): string {
        return self::sign($message, $kp->privateKey(), $kp->publicKey());
    }

    public static function verify(string $message, string $signature, PublicKey $pk): bool {
        return \sodium_crypto_sign_verify_detached($signature, $message, $pk->bytes());
    }
}
