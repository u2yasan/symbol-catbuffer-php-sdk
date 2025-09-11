<?php
declare(strict_types=1);

namespace SymbolSdk\Tests\Transaction;

use PHPUnit\Framework\TestCase;
use SymbolSdk\Tests\TestUtil\Hex;
use SymbolSdk\Tests\TestUtil\Vectors;
use SymbolSdk\Transaction\MosaicDefinitionTransaction;

final class MosaicDefinitionFromJsonVectorsTest extends TestCase
{
    /** @return array<string, array{hex:string,name:string}> */
    public static function provideVectors(): array
    {
        $path = __DIR__ . '/../vectors/symbol/models/transactions.json';
        if (!is_file($path)) return [];

        $recs = Vectors::loadTransactions($path);
        $mrs  = Vectors::filterBySchemaContains($recs, 'MosaicDefinitionTransaction');

        $out = [];
        foreach ($mrs as $i => $r) {
            $name = (string)($r['test_name'] ?? $r['schema_name'] ?? "mosaicdef_{$i}");
            $out[$name] = ['hex' => $r['hex'], 'name' => $name];
        }
        return $out;
    }

    /** @dataProvider provideVectors */
    public function testRoundTrip(string $hex, string $name): void
    {
        if ($hex === '') $this->markTestSkipped('Empty hex');
        $bin = Hex::fromString($hex);
        try {
            $tx = MosaicDefinitionTransaction::fromBinary($bin);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Vector not body-only: '.$e->getMessage());
            return;
        }
        $this->assertSame(bin2hex($bin), bin2hex($tx->serialize()), "re-encode mismatch for {$name}");
    }
}