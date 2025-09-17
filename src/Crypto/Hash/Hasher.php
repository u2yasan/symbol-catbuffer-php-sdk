<?php
declare(strict_types=1);

namespace SymbolSdk\Crypto\Hash;

use kornrunner\Keccak as KeccakLib; 

final class Hasher
{
    /** @var null|string 'keccak256' | 'keccak-256' | ''(=fallback) */
    private static ?string $keccakAlgo = null;

    /** raw binary */
    public static function sha3_256(string $data): string
    {
        return hash('sha3-256', $data, true);
    }

    /** raw binary */
    public static function keccak_256(string $data): string
    {
        if (self::$keccakAlgo === null) {
            $algos = hash_algos();
            if (in_array('keccak256', $algos, true)) {
                self::$keccakAlgo = 'keccak256';
            } elseif (in_array('keccak-256', $algos, true)) {
                self::$keccakAlgo = 'keccak-256';
            } else {
                self::$keccakAlgo = ''; // fallback to composer lib
            }
        }

        if (self::$keccakAlgo !== '') {
            return hash(self::$keccakAlgo, $data, true);
        }

        // Fallback: kornrunner (hex string -> binary)
        return hex2bin(KeccakLib::hash($data, 256));
    }

    /** raw binary */
    public static function ripemd160(string $data): string
    {
        return hash('ripemd160', $data, true);
    }

    /** for tests/logs */
    public static function resolvedKeccak256Algo(): ?string
    {
        return self::$keccakAlgo;
    }
}