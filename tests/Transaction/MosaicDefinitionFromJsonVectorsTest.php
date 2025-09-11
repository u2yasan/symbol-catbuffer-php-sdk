<?php
declare(strict_types=1);

namespace SymbolSdk\Tests\Transaction;

use PHPUnit\Framework\TestCase;
use SymbolSdk\Tests\TestUtil\Vectors;
use SymbolSdk\Transaction\MosaicDefinitionTransaction;

final class MosaicDefinitionFromJsonVectorsTest extends TestCase
{
    public function testVectorsPresence(): void
    {
        $path = __DIR__ . '/../vectors/symbol/models/transactions.json';
        if (!is_file($path)) {
            $this->markTestSkipped('transactions.json not found at ' . $path);
        }

        $recs = Vectors::loadTransactions($path);
        $mrs  = Vectors::filterBySchemaContains($recs, 'MosaicDefinitionTransaction');
        if (!$mrs) {
            $this->markTestSkipped('No mosaic definition vectors found in transactions.json');
        }

        $this->assertTrue(true);
    }

    /**
     * @return array<string, array{hex: string}>
     */
    public static function providerMosaicDefinition(): array
    {
        $path = __DIR__ . '/../vectors/symbol/models/transactions.json';
        if (!is_file($path)) {
            return ['__none__' => ['hex' => '']];
        }

        $recs = Vectors::loadTransactions($path);
        $mrs  = Vectors::filterBySchemaContains($recs, 'MosaicDefinitionTransaction');

        $out = [];
        foreach ($mrs as $i => $rec) {
            // Vectors の注釈で hex は常に string
            /** @var string $hex */
            $hex = $rec['hex'];
            if ($hex !== '') {
                $out["mosaicdef_$i"] = ['hex' => $hex];
            }
        }
        return $out ?: ['__none__' => ['hex' => '']];
    }

    /**
     * @dataProvider providerMosaicDefinition
     */
    public function testRoundTrip(string $hex): void
    {
        if ($hex === '') {
            $this->markTestSkipped('No mosaic definition vectors present');
        }

        $bin = hex2bin($hex);
        if ($bin === false) {
            $this->markTestSkipped('Invalid hex in vectors');
        }

        $tx = MosaicDefinitionTransaction::fromBinary($bin);
        $re = $tx->serialize();

        $this->assertSame($hex, bin2hex($re));
    }
}
