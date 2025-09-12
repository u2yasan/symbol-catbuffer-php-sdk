<?php

declare(strict_types=1);

namespace SymbolSdk\Tests\Crypto;

use PHPUnit\Framework\TestCase;

final class HashVectorsTest extends TestCase
{
    public function testKeccak256Vectors(): void
    {
        $vecPath = \dirname(__DIR__).'/vectors/symbol/crypto/keccak_256.json';

        if (!\file_exists($vecPath)) {
            self::markTestSkipped('keccak_256 vectors not present.');
        }

        // 多くの PHP では keccak256 は未実装。無ければスキップ。
        $algos = \hash_algos();

        if (!\in_array('keccak256', $algos, true)) {
            self::markTestSkipped('hash() does not support keccak256 on this runtime.');
        }

        $json = \file_get_contents($vecPath);

        if (false === $json) {
            self::markTestSkipped('cannot read keccak_256.json');
        }

        try {
            /** @var mixed $decoded */
            $decoded = \json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            self::markTestSkipped('invalid keccak_256.json: '.$e->getMessage());
        }

        if (!\is_array($decoded) || 0 === \count($decoded)) {
            self::markTestSkipped('no keccak256 vectors found');
        }

        foreach ($decoded as $idx => $case) {
            if (!\is_array($case)
                || !\array_key_exists('input', $case)
                || !\array_key_exists('output', $case)
                || !\is_string($case['input'])
                || !\is_string($case['output'])) {
                self::fail("vector[$idx] invalid shape");
            }

            $in = $case['input'];
            $out = $case['output'];

            // 16進表現の妥当性（入力は任意長、出力は32バイト＝64ヘクス）
            self::assertMatchesRegularExpression('/^[0-9a-fA-F]*$/', $in, "vector[$idx].input is not hex");
            self::assertMatchesRegularExpression('/^[0-9a-fA-F]{64}$/', $out, "vector[$idx].output must be 32-byte hash hex");

            $msg = \hex2bin($in);

            if (false === $msg) {
                self::fail("vector[$idx].input hex2bin failed");
            }

            $got = \hash('keccak256', $msg, false); // hash() は string を返す契約
            self::assertSame(\strtolower($out), \strtolower($got), "vector[$idx] mismatch");
        }
    }
}
