<?php
declare(strict_types=1);

namespace SymbolSdk\Tests\Transaction;

use PHPUnit\Framework\TestCase;
use SymbolSdk\Tests\TestUtil\Hex;

final class MosaicDefinitionVectorsTest extends TestCase
{
    /**
     * @return array<string, array{0: array{path: string, name: string}}|array{0: array{__skip__: true}}>
     */
    public static function providerVectors(): array
    {
        $base = __DIR__ . '/../vectors/mosaic_definition';
        if (!\is_dir($base)) {
            // センチネルは引数配列として一段包む
            return ['__skip__' => [ ['__skip__' => true] ]];
        }

        /** @var list<string>|false $files */
        $files = \glob($base . '/*.hex');
        if ($files === false) {
            $files = [];
        }

        /** @var array<string, array{0: array{path: string, name: string}}> $out */
        $out = [];
        foreach ($files as $f) {
            $name = \basename($f, '.hex');
            // 各データセットは [ $case ] の形にする
            $out[$name] = [ ['path' => $f, 'name' => $name] ];
        }

        if (\count($out) === 0) {
            return ['__skip__' => [ ['__skip__' => true] ]];
        }
        return $out;
    }

    /**
     * @dataProvider providerVectors
     * @param array{path: string, name: string}|array{__skip__: true} $case
     */
    public function testDecodeReencodeEquals(array $case): void
    {
        if (isset($case['__skip__'])) {
            self::markTestSkipped('No mosaic definition .hex vectors found.');
        }

        /** @var array{path: string, name: string} $case */
        $hex = Hex::fromFile($case['path']);
        self::assertSame($hex, Hex::fromString($hex));
    }
}