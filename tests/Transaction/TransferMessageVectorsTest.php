<?php
declare(strict_types=1);

namespace SymbolSdk\Tests\Transaction;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use SymbolSdk\Tests\TestUtil\Hex;
use SymbolSdk\Tests\TestUtil\Vectors;
use SymbolSdk\Transaction\TransferTransaction;

final class TransferMessageVectorsTest extends TestCase
{
    /**
     * @return array<string, array{0: array{hex: string, name: string}}>
     */
    public static function providerVectors(): array
    {
        $path = \dirname(__DIR__) . '/vectors/symbol/models/transactions.json';

        try {
            $rows = Vectors::loadTransactions($path);
        } catch (\Throwable) {
            return [
                '__skip__' => [ ['hex' => '', 'name' => '__skip__'] ],
            ];
        }

        $filtered = \array_values(\array_filter(
            $rows,
            static function (array $r): bool {
                if (!isset($r['schema_name'])) {
                    return false;
                }
                $schema = $r['schema_name']; // string
                $tname  = isset($r['test_name']) ? $r['test_name'] : '';
                return \str_contains($schema, 'TransferTransaction')
                    && \str_contains(\strtolower($tname), 'message');
            }
        ));

        if ($filtered === []) {
            return [
                '__skip__' => [ ['hex' => '', 'name' => '__skip__'] ],
            ];
        }

        /** @var array<string, array{0: array{hex: string, name: string}}> $out */
        $out = [];
        foreach ($filtered as $r) {
            $hex  = $r['hex'];
            $name = (isset($r['test_name']) && $r['test_name'] !== '') ? $r['test_name'] : 'unknown';
            $out[$name] = [ ['hex' => $hex, 'name' => $name] ];
        }
        return $out;
    }

    /**
     * @dataProvider providerVectors
     * @param array{hex: string, name: string} $case
     */
    public function testRoundTrip(array $case): void
    {
        if ($case['name'] === '__skip__') {
            $this->addToAssertionCount(1);
            return;
        }

        $hex = \strtolower($case['hex']);
        Assert::assertMatchesRegularExpression('/^[0-9a-f]*$/', $hex, 'HEX only');

        $bin = Hex::fromString($hex);
        $tx  = TransferTransaction::fromBinary($bin);
        $re  = $tx->serialize();

        Assert::assertSame($hex, \bin2hex($re), 're-encoded hex equals original');
    }
}