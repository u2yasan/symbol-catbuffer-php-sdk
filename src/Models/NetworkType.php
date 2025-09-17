<?php
declare(strict_types=1);
namespace SymbolSdk\Models;

enum NetworkType: int
{
    case MAINNET = 104;
    case TESTNET = 152;
    case PRIVATE = 96;
    case PRIVATE_TEST = 144;

    public static function fromInt(int $value): self
    {
        return match ($value) {
            self::MAINNET->value => self::MAINNET,
            self::TESTNET->value => self::TESTNET,
            self::PRIVATE->value => self::PRIVATE,
            self::PRIVATE_TEST->value => self::PRIVATE_TEST,
            default => throw new \InvalidArgumentException("Unknown NetworkType value: {$value}"),
        };
    }

    public function value(): int
    {
        return $this->value;
    }
}
