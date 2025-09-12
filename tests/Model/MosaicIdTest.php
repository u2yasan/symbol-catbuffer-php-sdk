<?php

declare(strict_types=1);

namespace SymbolSdk\Tests\Model;

use PHPUnit\Framework\TestCase;
use SymbolSdk\Model\MosaicId;

final class MosaicIdTest extends TestCase
{
    /**
     * @return array<string, array{0:string}>
     */
    public static function providerHex(): array
    {
        return [
            'zero' => ['0000000000000000'],
            'one' => ['0100000000000000'],
            'mid' => ['cb00000000000000'],
            'max-32' => ['ffffffff00000000'],
            'max-64' => ['ffffffffffffffff'],
        ];
    }

    /**
     * @dataProvider providerHex
     */
    public function testRoundTrip(string $hex): void
    {
        $bin = \hex2bin($hex);
        self::assertIsString($bin);

        $mosaicId = MosaicId::fromBinary($bin);
        $re = $mosaicId->serialize();

        self::assertSame(\strtolower($hex), \strtolower(\bin2hex($re)));
    }
}
