<?php
declare(strict_types=1);

namespace Tests\Crypto\Hash;

use PHPUnit\Framework\TestCase;
use SymbolSdk\Crypto\Hash\Hasher;

/**
 * Hashers basic conformance tests
 *
 * - 既知ベクタ一致（SHA3-256 / Keccak-256 / RIPEMD160）
 * - keccak アルゴ名称の自動検出が機能すること
 * - raw binary（長さ）の検証
 */
final class HasherTest extends TestCase
{
    public function test_sha3_256_known_vectors(): void
    {
        // SHA3-256("abc")
        $this->assertSame(
            '3a985da74fe225b2045c172d6bd390bd855f086e3e9d525b46bfe24511431532',
            bin2hex(Hasher::sha3_256('abc')),
            'sha3-256("abc") mismatch'
        );

        // SHA3-256("") 空文字の既知値
        $this->assertSame(
            'a7ffc6f8bf1ed76651c14756a061d662f580ff4de43b49fa82d80a4b80f8434a',
            bin2hex(Hasher::sha3_256('')),
            'sha3-256("") mismatch'
        );
    }

    public function test_keccak_256_known_vectors(): void
    {
        // Keccak-256("") 既知値
        $this->assertSame(
            'c5d2460186f7233c927e7db2dcc703c0e500b653ca82273b7bfad8045d85a470',
            bin2hex(Hasher::keccak_256('')),
            'keccak-256("") mismatch'
        );

        // Keccak-256("abc") 既知値
        $this->assertSame(
            '4e03657aea45a94fc7d47ba826c8d667c0d1e6e33a64a036ec44f58fa12d6c45',
            bin2hex(Hasher::keccak_256('abc')),
            'keccak-256("abc") mismatch'
        );
    }

    public function test_ripemd160_known_vectors(): void
    {
        // RIPEMD160("") 既知値
        $this->assertSame(
            '9c1185a5c5e9fc54612808977ee8f548b2258d31',
            bin2hex(Hasher::ripemd160('')),
            'ripemd160("") mismatch'
        );

        // RIPEMD160("abc") 既知値
        $this->assertSame(
            '8eb208f7e05d987a9b044a8e98c6b087f15a0bfc',
            bin2hex(Hasher::ripemd160('abc')),
            'ripemd160("abc") mismatch'
        );
    }

    public function test_keccak_algo_is_detected_or_fallback(): void
    {
        // 初回呼び出しで検出 or fallback が確定する
        $out = Hasher::keccak_256('test');
        $this->assertSame(32, strlen($out), 'keccak_256 output must be 32 bytes');

        // 実装に resolvedKeccak256Algo() があれば検査（存在しない場合はスキップ）
        if (method_exists(Hasher::class, 'resolvedKeccak256Algo')) {
            /** @var ?string $algo */
            $algo = Hasher::resolvedKeccak256Algo();
            if ($algo === null || $algo === '') {
                // Fallback（composer 実装）を使用中
                $this->addToAssertionCount(1);
            } else {
                // ext 側のアルゴ名称が hash_algos() に含まれていること
                $this->assertContains($algo, hash_algos(), 'resolved keccak algo not in hash_algos()');
            }
        } else {
            $this->markTestSkipped('Hasher::resolvedKeccak256Algo() not implemented; skip algo check.');
        }
    }

    public function test_binary_lengths_are_expected(): void
    {
        $sha3 = Hasher::sha3_256('payload');
        $kcc  = Hasher::keccak_256('payload');
        $rip  = Hasher::ripemd160('payload');

        $this->assertSame(32, strlen($sha3), 'sha3_256 must return 32 bytes');
        $this->assertSame(32, strlen($kcc),  'keccak_256 must return 32 bytes');
        $this->assertSame(20, strlen($rip),  'ripemd160 must return 20 bytes');
    }

    /**
     * 追加のスモーク：複数入力で一貫性があること
     *
     * @dataProvider provideMessages
     */
    public function test_multiple_inputs_consistency(string $msg): void
    {
        $this->assertSame(bin2hex(Hasher::sha3_256($msg)), bin2hex(Hasher::sha3_256($msg)));
        $this->assertSame(bin2hex(Hasher::keccak_256($msg)), bin2hex(Hasher::keccak_256($msg)));
        $this->assertSame(bin2hex(Hasher::ripemd160($msg)), bin2hex(Hasher::ripemd160($msg)));
    }

    /** @return array<int, array{0:string}> */
    public static function provideMessages(): array
    {
        return [
            [''],
            ['a'],
            ['abc'],
            ['hello symbol'],
            [str_repeat('Z', 1024)],
            [random_bytes(16)], // binary
        ];
    }
}
