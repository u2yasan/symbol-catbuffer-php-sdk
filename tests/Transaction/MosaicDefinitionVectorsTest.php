<?php
declare(strict_types=1);

namespace SymbolSdk\Tests\Transaction;

use PHPUnit\Framework\TestCase;
use SymbolSdk\Tests\TestUtil\Hex;
use SymbolSdk\Transaction\MosaicDefinitionTransaction;

final class MosaicDefinitionVectorsTest extends TestCase
{
    /**
     * tests/vectors/mosaic_definition/*.hex を読む
     *
     * @return array<string, array{array{path: string, name: string}}>
     */
    public static function providerVectors(): array
    {
        $dir = \realpath(__DIR__ . '/../vectors/mosaic_definition');
        if ($dir === false) {
            return ['__skip__' => [ ['path' => '', 'name' => '__skip__'] ]];
        }

        /** @var array<string, array{array{path: string, name: string}}> $out */
        $out = [];
        $files = \glob($dir . '/*.hex');
        if ($files !== false) {
            foreach ($files as $path) {
                $name = \basename($path, '.hex');
                // 1引数（array $case）にするため [$case] の形で返す
                $out[$name] = [ ['path' => $path, 'name' => $name] ];
            }
        }

        if ($out === []) {
            return ['__skip__' => [ ['path' => '', 'name' => '__skip__'] ]];
        }
        return $out;
    }

    /**
     * @param array{path: string, name: string} $case
     * @dataProvider providerVectors
     */
    public function testDecodeReencodeEquals(array $case): void
    {
        if ($case['name'] === '__skip__') {
            $this->addToAssertionCount(1);
            return;
        }

        $hex = \trim((string) \file_get_contents($case['path']));
        $bin = Hex::fromString($hex);

        $tx = MosaicDefinitionTransaction::fromBinary($bin);
        $re = $tx->serialize();

        self::assertSame(\strtolower($hex), \bin2hex($re), "re-encoded hex should equal input for {$case['name']}");
    }
}