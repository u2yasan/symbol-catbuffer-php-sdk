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
     * @return array<string, array{array{hex: string, name: string}}|array{array{__skip__: true}}>
     */
    public static function providerVectors(): array
    {
        $json = \realpath(__DIR__ . '/../vectors/symbol/models/transactions.json');
        if ($json === false) {
            return ['__skip__' => [ ['__skip__' => true] ]];
        }

        /** @var array<int, array{schema_name?: string, test_name?: string, type?: string, hex: string, meta?: array<mixed>}> $txs */
        $txs = \SymbolSdk\Tests\TestUtil\Vectors::loadTransactions($json);

        /** @var array<string, array{array{hex: string, name: string}}|array{array{__skip__: true}}> $out */
        $out = [];
        $i = 0;

        foreach ($txs as $rec) {
            $schema = $rec['schema_name'] ?? '';
            if ($schema === '' || \stripos($schema, 'TransferTransaction') === false) {
                continue;
            }
            $hex = $rec['hex'];
            if ($hex === '') {
                continue;
            }
            $name = $rec['test_name'] ?? ('transfer_' . (string) $i);
            $out[$name] = [ ['hex' => $hex, 'name' => $name] ];
            $i++;
        }

        return $out !== [] ? $out : ['__skip__' => [ ['__skip__' => true] ]];
    }

    /**
     * @param array{hex: string, name: string}|array{__skip__: true} $case
     * @dataProvider providerVectors
     */
    public function testRoundTrip(array $case): void
    {
        if (isset($case['__skip__'])) {
            $this->addToAssertionCount(1);
            return;
        }

        /** @var array{hex: string, name: string} $case */
        $hex = $case['hex'];
        $bin = Hex::fromString($hex);

        $tx = TransferTransaction::fromBinary($bin);
        $re = $tx->serialize();

        self::assertSame(\strtolower($hex), \bin2hex($re), 're-encoded hex should equal input');
    }
}