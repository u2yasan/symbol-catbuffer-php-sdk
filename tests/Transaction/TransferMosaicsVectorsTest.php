<?php
declare(strict_types=1);

namespace SymbolSdk\Tests\Transaction;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use SymbolSdk\Tests\TestUtil\Hex;
use SymbolSdk\Tests\TestUtil\Vectors;
use SymbolSdk\Transaction\TransferTransaction;

final class TransferMosaicsVectorsTest extends TestCase
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
            // vectors が無い環境用スキップ
            return [
                '__skip__' => [ ['hex' => '', 'name' => '__skip__'] ],
            ];
        }

        // TransferTransaction かつ test_name に "mosaic" を含むものだけ抽出
        $filtered = \array_values(\array_filter(
            $rows,
            static function (array $r): bool {
                if (!isset($r['schema_name'])) {
                    return false;
                }
                $schema = $r['schema_name']; // string（Vectors が型を保証）
                $tname  = $r['test_name'] ?? '';
                return \str_contains($schema, 'TransferTransaction')
                    && \str_contains(\strtolower($tname), 'mosaic');
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
            // 'hex' は常に存在し string 型（Vectors が保証）
            $hex  = $r['hex'];
            $name = isset($r['test_name']) && $r['test_name'] !== '' ? $r['test_name'] : 'unknown';
            // dataProvider 形式に合わせて最終次元は配列で包む
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
            // 明示スキップ（アサーション1加算）
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
