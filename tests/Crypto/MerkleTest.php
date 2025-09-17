<?php
declare(strict_types=1);

namespace Tests\Crypto;

use PHPUnit\Framework\TestCase;
use SymbolSdk\Crypto\Merkle;

final class MerkleTest extends TestCase
{
    public function test_empty_is_zero_root(): void
    {
        $root = Merkle::merkle_hash_sha3_256([]);
        $this->assertSame(32, strlen($root));
        $this->assertSame(str_repeat('00', 32), bin2hex($root));
    }

    public function test_odd_leaves_duplicate_last(): void
    {
        $a = random_bytes(32);
        $b = random_bytes(32);
        $c = random_bytes(32);
        $r1 = Merkle::merkle_hash_sha3_256([$a,$b,$c]);
        $r2 = Merkle::merkle_hash_sha3_256([$a,$b,$c,$c]);
        $this->assertSame(bin2hex($r1), bin2hex($r2));
    }
}
