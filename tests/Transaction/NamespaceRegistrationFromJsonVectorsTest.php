<?php
declare(strict_types=1);

namespace SymbolSdk\Tests\Transaction;

use PHPUnit\Framework\TestCase;
use SymbolSdk\Tests\TestUtil\Hex;
use SymbolSdk\Tests\TestUtil\Vectors;
use SymbolSdk\Transaction\NamespaceRegistrationTransaction;

final class NamespaceRegistrationFromJsonVectorsTest extends TestCase
{
    /**
     * 対象: tests/vectors/symbol/models/transactions.json
     *
     * @return array<string, array{hex: string, name: string}>
     */
    public static function providerVectors(): array
    {
        $json = \realpath(__DIR__ . '/../vectors/symbol/models/transactions.json');
        if ($json === false) {
            return []; // データ無し → 空配列
        }

        /** @var array<int, array{schema_name?: string, test_name?: string, type?: string, hex: string, meta?: array<mixed>}> $txs */
        $txs = Vectors::loadTransactions($json);

        /** @var array<string, array{hex: string, name: string}> $out */
        $out = [];
        $i = 0;

        foreach ($txs as $rec) {
            $schema = $rec['schema_name'] ?? '';
            if ($schema === '' || \stripos($schema, 'NamespaceRegistrationTransaction') === false) {
                continue;
            }

            // 型上 hex は必須。空は除外。
            $hex = $rec['hex'];
            if ($hex === '') {
                continue;
            }

            $name = $rec['test_name'] ?? ('nsreg_' . (string) $i);

            // 一段のみ（多重配列禁止）
            $out[$name] = [
                'hex'  => $hex,
                'name' => $name,
            ];
            $i++;
        }

        // 見つからなければ空配列
        return $out;
    }

    /**
     * @param array{hex: string, name: string} $case
     * @dataProvider providerVectors
     */
    public function testRoundTrip(array $case): void
    {
        // dataProvider が空のときはテスト自体が実行されない前提
        /** @var array{hex: string, name: string} $case */

        $hex = $case['hex'];
        $bin = Hex::fromString($hex);

        $tx = NamespaceRegistrationTransaction::fromBinary($bin);
        $re = $tx->serialize();

        self::assertSame(\strtolower($hex), \bin2hex($re), 're-encoded hex should equal input');
    }
}