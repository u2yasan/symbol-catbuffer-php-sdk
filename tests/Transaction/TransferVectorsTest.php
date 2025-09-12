<?php

declare(strict_types=1);

namespace SymbolSdk\Tests\Transaction;

use PHPUnit\Framework\TestCase;
use SymbolSdk\Tests\TestUtil\Hex;

final class TransferVectorsTest extends TestCase
{
    /**
     * @return array<string, array{0: array{path: string, name: string}}|array{0: array{__skip__: true}}>
     */
    public static function providerVectors(): array
    {
        $base = __DIR__.'/../vectors/transfer';

        if (!\is_dir($base)) {
            return ['__skip__' => [['__skip__' => true]]];
        }

        /** @var list<string>|false $files */
        $files = \glob($base.'/*.hex');

        if (false === $files) {
            $files = [];
        }

        /** @var array<string, array{0: array{path: string, name: string}}> $out */
        $out = [];

        foreach ($files as $f) {
            $name = \basename($f, '.hex');
            $out[$name] = [['path' => $f, 'name' => $name]];
        }

        if (0 === \count($out)) {
            return ['__skip__' => [['__skip__' => true]]];
        }

        return $out;
    }

    /**
     * @dataProvider providerVectors
     *
     * @param array{path: string, name: string}|array{__skip__: true} $case
     */
    public function testDecodeReencodeEquals(array $case): void
    {
        if (isset($case['__skip__'])) {
            self::markTestSkipped('No transfer .hex vectors found.');
        }

        /** @var array{path: string, name: string} $case */
        $hex = Hex::fromFile($case['path']);
        self::assertSame($hex, Hex::fromString($hex));
    }
}
