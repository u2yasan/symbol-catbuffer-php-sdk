<?php
declare(strict_types=1);

namespace SymbolSdk\Crypto;

final class Hmac
{
    /** raw binary */
    public static function hmac_sha256(string $key, string $data): string {
        return hash_hmac('sha256', $data, $key, true);
    }
    /** raw binary */
    public static function hmac_sha3_256(string $key, string $data): string {
        return hash_hmac('sha3-256', $data, $key, true);
    }
}
