<?php
declare(strict_types=1);

namespace SymbolSdk\Crypto;

use SymbolSdk\Crypto\Hash\Hasher;

final class Merkle
{
    /**
     * @param list<string> $hashes list of 32B raw hashes
     * @return string 32B raw merkle root (sha3-256)
     */
    public static function merkle_hash_sha3_256(array $hashes): string
    {
        $n = count($hashes);
        if ($n === 0) {
            return str_repeat("\x00", 32);
        }
        // 入力検証
        foreach ($hashes as $h) {
            if (strlen($h) !== 32) {
                throw new \InvalidArgumentException('All leaves must be 32 bytes');
            }
        }
        $layer = $hashes;
        while (count($layer) > 1) {
            $next = [];
            $cnt = count($layer);
            for ($i = 0; $i < $cnt; $i += 2) {
                $left = $layer[$i];
                $right = $layer[$i + 1] ?? $layer[$i]; // 奇数なら最後を複製
                $next[] = Hasher::sha3_256($left . $right);
            }
            $layer = $next;
        }
        return $layer[0];
    }
}
