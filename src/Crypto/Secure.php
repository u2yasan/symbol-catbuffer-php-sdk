<?php
declare(strict_types=1);

namespace SymbolSdk\Crypto;

final class Secure
{
    public static function equals(string $a, string $b): bool {
        if (function_exists('sodium_memcmp') && strlen($a) === strlen($b)) {
            return \sodium_memcmp($a, $b) === 0;
        }
        return hash_equals($a, $b);
    }

    public static function memzero(string &$s): void {
        $len = strlen($s);
        if (function_exists('sodium_memzero')) {
            \sodium_memzero($s);         // ここで $s は NULL になる実装
            $s = str_repeat("\x00", $len); // 新しいゼロ文字列を代入（API一貫性のため）
        } else {
            $s = str_repeat("\x00", $len);
        }
    }
}