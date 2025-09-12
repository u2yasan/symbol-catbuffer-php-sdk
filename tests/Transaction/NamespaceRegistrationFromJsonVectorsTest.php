<?php
declare(strict_types=1);

namespace SymbolSdk\Tests\Transaction;

use PHPUnit\Framework\TestCase;
use SymbolSdk\Tests\TestUtil\Vectors;
use SymbolSdk\Transaction\NamespaceRegistrationTransaction;

final class NamespaceRegistrationFromJsonVectorsTest extends TestCase
{
    /** @return array<string, array{hex: string}> */
    public static function provider(): array
    {
        $path = __DIR__ . '/../vectors/symbol/models/transactions.json';
        if (!is_file($path)) return ['__none__' => ['hex' => '']];

        $recs = Vectors::loadTransactions($path);

        $rows = array_merge(
            Vectors::filterBySchemaEquals($recs, 'NamespaceRegistrationTransactionV1'),
            Vectors::filterBySchemaEquals($recs, 'NamespaceRegistrationTransactionV2')
        );

        $out = [];
        foreach ($rows as $i => $r) {
            $hex = (string)$r['hex'];
            if ($hex !== '') $out["nsreg_$i"] = ['hex' => $hex];
        }
        return $out ?: ['__none__' => ['hex' => '']];
    }

    /** @dataProvider provider */
    public function testRoundTrip(string $hex): void
    {
        if ($hex === '') $this->markTestSkipped('no namespace registration vectors');

        $bin = hex2bin($hex);
        if ($bin === false) $this->markTestSkipped('invalid hex');

        $tx = NamespaceRegistrationTransaction::fromBinary($bin);
        $re = $tx->serialize();

        $this->assertSame($hex, bin2hex($re));
    }
}
