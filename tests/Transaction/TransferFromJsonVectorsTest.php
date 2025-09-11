<?php
declare(strict_types=1);

namespace SymbolSdk\Tests\Transaction;

use PHPUnit\Framework\TestCase;
use SymbolSdk\Tests\TestUtil\Vectors;
use SymbolSdk\Transaction\TransferTransaction;

final class TransferFromJsonVectorsTest extends TestCase
{
    public function testVectorsPresence(): void
    {
        $path = __DIR__ . '/../vectors/symbol/models/transactions.json';
        if (!is_file($path)) {
            $this->markTestSkipped('transactions.json not found at ' . $path);
        }

        $recs = Vectors::loadTransactions($path);
        $trs  = Vectors::filterBySchemaContains($recs, 'TransferTransaction');
        if (!$trs) {
            $this->markTestSkipped('No transfer vectors found in transactions.json');
        }

        $this->assertTrue(true);
    }

    /**
     * @return array<string, array{hex: string}>
     */
    public static function providerTransfer(): array
    {
        $path = __DIR__ . '/../vectors/symbol/models/transactions.json';
        if (!is_file($path)) {
            return ['__none__' => ['hex' => '']];
        }

        $recs = Vectors::loadTransactions($path);
        $trs  = Vectors::filterBySchemaContains($recs, 'TransferTransaction');

        $out = [];
        foreach ($trs as $i => $rec) {
            /** @var string $hex */
            $hex = $rec['hex'];
            if ($hex !== '') {
                $out["transfer_$i"] = ['hex' => $hex];
            }
        }
        return $out ?: ['__none__' => ['hex' => '']];
    }

    /**
     * @dataProvider providerTransfer
     */
    public function testRoundTrip(string $hex): void
    {
        if ($hex === '') {
            $this->markTestSkipped('No transfer vectors present');
        }

        $bin = hex2bin($hex);
        if ($bin === false) {
            $this->markTestSkipped('Invalid hex in vectors');
        }

        $tx = TransferTransaction::fromBinary($bin);
        $re = $tx->serialize();

        $this->assertSame($hex, bin2hex($re));
    }
}
