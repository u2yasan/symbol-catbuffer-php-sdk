<?php
declare(strict_types=1);

namespace SymbolSdk\Tests\Transaction;

use PHPUnit\Framework\TestCase;
use SymbolSdk\Tests\TestUtil\Hex;
use SymbolSdk\Tests\TestUtil\Vectors;
use SymbolSdk\Transaction\TransferTransaction;

final class TransferFromJsonVectorsTest extends TestCase
{
    /**
     * @return array<string, array{hex: string, name: string}>
     */
    public static function providerVectors(): array
    {
        $json = \realpath(__DIR__ . '/../vectors/symbol/models/transactions.json');
        if ($json === false) {
            return []; // データなし → 空配列（__skip__ は使わない）
        }

        /** @var array<int, array{schema_name?: string, test_name?: string, type?: string, hex: string, meta?: array<mixed>}> $txs */
        $txs = Vectors::loadTransactions($json);

        /** @var array<string, array{hex: string, name: string}> $out */
        $out = [];
        $i = 0;

        foreach ($txs as $rec) {
            $schema = $rec['schema_name'] ?? '';
            if ($schema === '' || \stripos($schema, 'TransferTransaction') === false) {
                continue;
            }

            // 型上 hex は必須。空は除外。
            $hex = $rec['hex'];
            if ($hex === '') {
                continue;
            }

            $name = $rec['test_name'] ?? ('transfer_' . (string) $i);

            // 一段のみ格納（多重配列禁止）
            $out[$name] = [
                'hex'  => $hex,
                'name' => $name,
            ];
            $i++;
        }

        return $out; // 見つからなければ空配列
    }

    /**
     * @param array{hex: string, name: string} $case
     * @dataProvider providerVectors
     */
    public function testRoundTrip(array $case): void
    {
        // dataProvider が空ならテスト自体は走らない
        /** @var array{hex: string, name: string} $case */

        $hex = $case['hex'];
        $bin = Hex::fromString($hex);

        $tx = TransferTransaction::fromBinary($bin);
        $re = $tx->serialize();

        self::assertSame(\strtolower($hex), \bin2hex($re), 're-encoded hex should equal input');
    }
}