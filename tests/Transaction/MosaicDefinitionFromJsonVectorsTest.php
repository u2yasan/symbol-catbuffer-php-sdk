<?php
declare(strict_types=1);

namespace SymbolSdk\Tests\Transaction;

use PHPUnit\Framework\TestCase;
use SymbolSdk\Tests\TestUtil\Hex;
use SymbolSdk\Tests\TestUtil\Vectors;
use SymbolSdk\Transaction\MosaicDefinitionTransaction;

final class MosaicDefinitionFromJsonVectorsTest extends TestCase
{
    /**
     * @return array<string, array{hex: string, name: string}>
     */
    public static function providerVectors(): array
    {
        $json = \realpath(__DIR__ . '/../vectors/symbol/models/transactions.json');
        if ($json === false) {
            // データなし → 空配列（__skip__ を返さない）
            return [];
        }

        /** @var array<int, array{schema_name?: string, test_name?: string, type?: string, hex: string, meta?: array<mixed>}> $txs */
        $txs = Vectors::loadTransactions($json);

        /** @var array<string, array{hex: string, name: string}> $out */
        $out = [];
        $i = 0;

        foreach ($txs as $rec) {
            $schema = $rec['schema_name'] ?? '';
            if ($schema === '' || \stripos($schema, 'MosaicDefinitionTransaction') === false) {
                continue;
            }

            $hex = $rec['hex'];
            if ($hex === '') {
                continue;
            }

            $name = $rec['test_name'] ?? ('mosaicdef_' . (string) $i);

            $out[$name] = [
                'hex'  => $hex,
                'name' => $name,
            ];
            $i++;
        }

        // 見つからなければ空配列（__skip__ は返さない）
        return $out;
    }

    /**
     * @param array{hex: string, name: string} $case
     * @dataProvider providerVectors
     */
    public function testRoundTrip(array $case): void
    {
        // dataProvider が空ならテストは走らない。ここに来るケースは必ず hex/name がある。
        /** @var array{hex: string, name: string} $case */

        $hex = $case['hex'];
        $bin = Hex::fromString($hex);

        $tx = MosaicDefinitionTransaction::fromBinary($bin);
        $re = $tx->serialize();

        self::assertSame(\strtolower($hex), \bin2hex($re), 're-encoded hex should equal input');
    }
}