<?php
declare(strict_types=1);

namespace SymbolSdk\Signer;

use SymbolSdk\CryptoTypes\KeyPair;
use SymbolSdk\CryptoTypes\PublicKey;
use SymbolSdk\Crypto\Ed25519\Signer as Ed25519;

final class TransactionSigner
{
    /** 任意バイト列を署名（64B生バイト返却） */
    public static function signBytes(string $bytesToSign, KeyPair $kp): string
    {
        return Ed25519::signWith($kp, $bytesToSign);
    }

    /** 任意バイト列(hex)を署名（hex返却） */
    public static function signBytesHex(string $bytesHex, KeyPair $kp): string
    {
        $bytes = (string) hex2bin($bytesHex);
        return bin2hex(self::signBytes($bytes, $kp));
    }

    /**
     * 署名 → 署名を埋め込んだ最終ペイロードを返す
     * $bytesToSignBuilder: (string $unsignedPayloadBin) => string $bytesToSign
     * $embedSignature:     (string $unsignedPayloadBin, string $sig64) => string $signedPayloadBin
     */
    public static function signAndEmbed(
        string $unsignedPayloadBin,
        KeyPair $kp,
        callable $bytesToSignBuilder,
        callable $embedSignature
    ): string {
        $bytesToSign = $bytesToSignBuilder($unsignedPayloadBin);
        $sig = self::signBytes($bytesToSign, $kp);
        return $embedSignature($unsignedPayloadBin, $sig);
    }

    /** 署名検証 */
    public static function verify(string $bytes, string $sig64, PublicKey $pk): bool
    {
        return Ed25519::verify($bytes, $sig64, $pk);
    }
}
