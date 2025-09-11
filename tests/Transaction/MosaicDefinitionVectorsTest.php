<?php
declare(strict_types=1);

namespace SymbolSdk\Tests\Transaction;

use PHPUnit\Framework\TestCase;
use SymbolSdk\Tests\TestUtil\Hex;
use SymbolSdk\Transaction\MosaicDefinitionTransaction;

final class MosaicDefinitionVectorsTest extends TestCase
{
    /** @return array<string, array{file:string}> */
    public static function provideValidVectors(): array
    {
        $candidates = [
            'valid_std' => __DIR__ . '/../vectors/mosaic_definition/valid_std.hex',
        ];
        $out = [];
        foreach ($candidates as $name => $path) {
            if (is_file($path)) {
                $out[$name] = ['file' => $path];
            }
        }
        return $out ?: ['__none__' => ['file' => '']];
    }

    /** @dataProvider provideValidVectors */
    public function testDecodeReencodeEquals(string $file): void
    {
        if ($file === '' || !is_file($file)) {
            $this->markTestSkipped('No local .hex vectors under tests/vectors/mosaic_definition/.');
        }
        $bin = \SymbolSdk\Tests\TestUtil\Hex::fromFile($file);
        $tx  = \SymbolSdk\Transaction\MosaicDefinitionTransaction::fromBinary($bin);
        $re  = $tx->serialize();
        $this->assertSame(bin2hex($bin), bin2hex($re));
    }

    public function testVectorsPresence(): void
    {
        $any = is_file(__DIR__ . '/../vectors/mosaic_definition/valid_std.hex');
        if (!$any) {
            $this->markTestSkipped('No local .hex mosaic_definition vectors. Using JSON-based tests instead.');
        }
        $this->assertTrue(true);
    }
}