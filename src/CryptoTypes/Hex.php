<?php
declare(strict_types=1);

namespace SymbolSdk\CryptoTypes;

final class Hex {
    public static function toBin(string $hex): string {
        $hex = strtolower($hex);
        if (!ctype_xdigit($hex) || strlen($hex) % 2) {
            throw new \InvalidArgumentException('Invalid hex string');
        }
        return (string) hex2bin($hex);
    }
    public static function fromBin(string $bin): string {
        return bin2hex($bin);
    }
}
