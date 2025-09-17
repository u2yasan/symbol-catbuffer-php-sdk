<?php
declare(strict_types=1);

namespace SymbolSdk\CryptoTypes;

final class PublicKey {
    public const LENGTH = 32;

    public function __construct(private string $bytes) {
        if (strlen($bytes) !== self::LENGTH) {
            throw new \InvalidArgumentException('PublicKey must be 32 bytes');
        }
    }

    public static function fromHex(string $hex): self {
        return new self(Hex::toBin($hex));
    }

    public function bytes(): string { return $this->bytes; }
    public function toHex(): string { return Hex::fromBin($this->bytes); }
}
