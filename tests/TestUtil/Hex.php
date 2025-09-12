<?php

declare(strict_types=1);

namespace SymbolSdk\Tests\TestUtil;

final class Hex
{
    public static function fromFile(string $path): string
    {
        if (!\is_file($path)) {
            throw new \RuntimeException("Vector not found: {$path}");
        }
        $hex = \file_get_contents($path);

        if (false === $hex) {
            throw new \RuntimeException("Cannot read: {$path}");
        }
        $hex = \strtolower(\preg_replace('/[^0-9a-f]/i', '', $hex) ?? '');
        $bin = \hex2bin($hex);

        if (false === $bin) {
            throw new \RuntimeException("Invalid hex in: {$path}");
        }

        return $bin;
    }

    public static function fromString(string $hex): string
    {
        $hex = \strtolower(\preg_replace('/[^0-9a-f]/i', '', $hex) ?? '');
        $bin = \hex2bin($hex);

        if (false === $bin) {
            throw new \RuntimeException('Invalid hex string.');
        }

        return $bin;
    }
}
