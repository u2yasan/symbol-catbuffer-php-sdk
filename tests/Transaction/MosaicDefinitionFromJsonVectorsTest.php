<?php

declare(strict_types=1);

namespace SymbolSdk\Tests\Transaction;

use PHPUnit\Framework\TestCase;
use SymbolSdk\Tests\TestUtil\Vectors;
use SymbolSdk\Transaction\MosaicDefinitionTransaction;

final class MosaicDefinitionFromJsonVectorsTest extends TestCase
{
    /**
     * @return array<string, array{hex:string, name:string}|array{__skip__:true}>
     */
    public static function providerVectors(): array
    {
        $jsonPath = \dirname(__DIR__).'/vectors/symbol/models/transactions.json';

        if (!\file_exists($jsonPath)) {
            // ベクタ未配置ならセンチネルのみ
            return ['__skip__' => ['__skip__' => true]];
        }

        $all = Vectors::loadTransactions($jsonPath);
        $picked = Vectors::filterBySchemaEquals($all, 'MosaicDefinitionTransactionV1');

        /** @var array<string, array{hex:string, name:string}> $out */
        $out = [];

        foreach ($picked as $i => $rec) {
            $hex = $rec['hex'];
            $name = $rec['test_name'] ?? ('mosaicdef_'.(string) $i);

            // 軽い HEX 妥当性（偶数桁は本体で確認）
            if (1 !== \preg_match('/^[0-9a-fA-F]+$/', $hex)) {
                continue;
            }

            $out[$name] = [['hex' => $hex, 'name' => $name]];
        }

        if (0 === \count($out)) {
            return ['__skip__' => [['__skip__' => true]]];
        }

        return $out;
    }

    /**
     * @dataProvider providerVectors
     *
     * @param array{hex:string, name:string}|array{__skip__:true} $case
     */
    public function testRoundTrip(array $case): void
    {
        if (isset($case['__skip__'])) {
            self::markTestSkipped('No mosaic definition vectors found.');
        }

        // ここからは hex/name ケースのみ
        /** @var array{hex:string, name:string} $case */
        $hex = $case['hex'];

        // 偶数桁の HEX を要求
        if ((\strlen($hex) % 2) !== 0 || 1 !== \preg_match('/^[0-9a-fA-F]+$/', $hex)) {
            self::markTestSkipped('Invalid hex vector: '.$case['name']);
        }

        $bin = \hex2bin($hex);

        if (false === $bin) {
            self::markTestSkipped('hex2bin failed: '.$case['name']);
        }

        $tx = MosaicDefinitionTransaction::fromBinary($bin);
        $out = $tx->serialize();

        self::assertSame(\strtolower($hex), \strtolower(\bin2hex($out)), 'Re-encoded hex must equal original: '.$case['name']);
    }
}
