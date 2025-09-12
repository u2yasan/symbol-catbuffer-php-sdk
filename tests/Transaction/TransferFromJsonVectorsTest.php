<?php
declare(strict_types=1);

namespace SymbolSdk\Tests\Transaction;

use PHPUnit\Framework\TestCase;
use SymbolSdk\Tests\TestUtil\Vectors;
use SymbolSdk\Transaction\TransferTransaction;

final class TransferFromJsonVectorsTest extends TestCase
{
    /**
     * @return array<string, array{hex: string, name: string}|array{__skip__: true}>
     */
    public static function providerVectors(): array
    {
        $jsonPath = \dirname(__DIR__) . '/vectors/symbol/models/transactions.json';
        if (!\file_exists($jsonPath)) {
            return ['__skip__' => ['__skip__' => true]];
        }

        $all    = Vectors::loadTransactions($jsonPath);
        $picked = Vectors::filterBySchemaEquals($all, 'TransferTransactionV1');

        /** @var array<string, array{hex: string, name: string}> $out */
        $out = [];
        foreach ($picked as $i => $rec) {
            // Vectors 側で hex は必ず string として格納される前提
            $hex  = $rec['hex'];
            $name = $rec['test_name'] ?? null;
            if ($name === null) {
                $name = 'transfer_' . (string) $i;
            }

            if (\preg_match('/^[0-9a-fA-F]+$/', $hex) !== 1) {
                continue;
            }

            $out[$name] = [ ['hex' => $hex, 'name' => $name] ];
        }

        if (\count($out) === 0) {
            return ['__skip__' => [ ['__skip__' => true] ]];
        }
        return $out;
    }

    /**
     * @dataProvider providerVectors
     * @param array{hex: string, name: string}|array{__skip__: true} $case
     */
    public function testRoundTrip(array $case): void
    {
        if (isset($case['__skip__'])) {
            self::markTestSkipped('No transfer vectors found.');
        }

        /** @var array{hex: string, name: string} $case */
        $hex = $case['hex'];
        if ((\strlen($hex) % 2) !== 0 || \preg_match('/^[0-9a-fA-F]+$/', $hex) !== 1) {
            self::markTestSkipped('Invalid hex vector: ' . $case['name']);
        }

        $bin = \hex2bin($hex);
        if ($bin === false) {
            self::markTestSkipped('hex2bin failed: ' . $case['name']);
        }

        $tx  = TransferTransaction::fromBinary($bin);
        $out = $tx->serialize();

        self::assertSame(\strtolower($hex), \strtolower(\bin2hex($out)), 'Re-encoded hex must equal original: ' . $case['name']);
    }
}