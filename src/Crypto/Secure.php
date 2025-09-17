// src/Crypto/Secure.php
<?php
declare(strict_types=1);

namespace SymbolSdk\Crypto;

final class Secure
{
    public static function equals(string $a, string $b): bool {
        // sodium_memcmp は ext-sodium 依存、無ければ hash_equals
        if (function_exists('sodium_memcmp') && strlen($a) === strlen($b)) {
            return \sodium_memcmp($a, $b) === 0;
        }
        return hash_equals($a, $b);
    }

    public static function memzero(string &$s): void {
        if (function_exists('sodium_memzero')) {
            \sodium_memzero($s);
        } else {
            $s = str_repeat("\x00", strlen($s));
        }
    }
}
