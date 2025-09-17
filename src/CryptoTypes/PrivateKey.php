<?php
declare(strict_types=1);

namespace SymbolSdk\CryptoTypes;

final class PrivateKey {
    public const LENGTH = 32;

    public function __construct(private string $bytes) {
        if (strlen($bytes) !== self::LENGTH) {
            throw new \InvalidArgumentException('PrivateKey must be 32 bytes');
        }
    }

    public static function fromHex(string $hex): self {
        return new self(Hex::toBin($hex));
    }

    public static function fromRandom(): self {
        // Ed25519 seed 32 bytes
        return new self(random_bytes(self::LENGTH));
    }

    public function bytes(): string { return $this->bytes; }
    public function toHex(): string { return Hex::fromBin($this->bytes); }

    public function __destruct() {
        // メモリ零化（可能な範囲で）
        if (function_exists('sodium_memzero')) {
            \sodium_memzero($this->bytes);
        }
    }
}
