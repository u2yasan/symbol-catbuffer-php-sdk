<?php
declare(strict_types=1);

namespace SymbolSdk\Signer;

use SymbolSdk\CryptoTypes\KeyPair;

final class Ed25519
{
    /**
     * 署名（libsodium 必須）
     * - sk32(=seed) の場合は seed から sk64 を生成
     * - sk64 の場合はそのまま使う
     * @return string 署名(64Bのバイト列)
     */
    public static function sign(string $message, KeyPair $kp): string
    {
        $sk = $kp->privateKey()->bytes(); // 32 or 64
        $pk = $kp->publicKey()->bytes();  // 32

        if (strlen($sk) === 32) {
            // seed から keypair を再生成し sk64 を取り出す（最も堅い）
            $pair = sodium_crypto_sign_seed_keypair($sk);
            $sk64 = sodium_crypto_sign_secretkey($pair); // 64B
            // （必要なら $pk と整合確認）$pkDer = sodium_crypto_sign_publickey($pair);
            // if ($pkDer !== $pk) { /* 必要なら例外やログ */ }
        } elseif (strlen($sk) === 64) {
            $sk64 = $sk;
        } else {
            throw new \InvalidArgumentException('private key must be 32 or 64 bytes');
        }

        return sodium_crypto_sign_detached($message, $sk64);
    }

    public static function verify(string $message, string $sig, KeyPair $kp): bool
    {
        return sodium_crypto_sign_verify_detached($sig, $message, $kp->publicKey()->bytes());
    }
}