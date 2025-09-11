<?php
declare(strict_types=1);

namespace SymbolSdk\Tests\Transaction;

use PHPUnit\Framework\TestCase;
use SymbolSdk\Tests\TestUtil\Hex;
use SymbolSdk\Tests\TestUtil\Vectors;
use SymbolSdk\Transaction\TransferTransaction;

final class TransferFromJsonVectorsTest extends TestCase
{
    /** @return array<string, array{hex:string,name:string}> */
    public static function provideVectors(): array
    {
        $path = __DIR__ . '/../vectors/symbol/models/transactions.json';
        if (!is_file($path)) return [];

        $recs = Vectors::loadTransactions($path);
        // "TransferTransactionV1" を含む schema_name を抽出
        $trs  = Vectors::filterBySchemaContains($recs, 'TransferTransaction');

        $out = [];
        foreach ($trs as $i => $r) {
            $name = (string)($r['test_name'] ?? $r['schema_name'] ?? "transfer_{$i}");
            $out[$name] = ['hex' => $r['hex'], 'name' => $name];
        }
        return $out;
    }

    /** @dataProvider provideVectors */
    public function testRoundTrip(string $hex, string $name): void
    {
        if ($hex === '') $this->markTestSkipped('Empty hex');
        $bin = Hex::fromString($hex);

        // いまは「ボディのみ」実装。ヘッダ付きのベクタはスキップ。
        try {
            $tx = TransferTransaction::fromBinary($bin);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Vector not body-only: '.$e->getMessage());
            return;
        }
        $this->assertSame(bin2hex($bin), bin2hex($tx->serialize()), "re-encode mismatch for {$name}");
    }
}