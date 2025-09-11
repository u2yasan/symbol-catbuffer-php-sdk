<?php
declare(strict_types=1);
namespace SymbolSdk\Transaction;

use InvalidArgumentException;
use RuntimeException;

final readonly class MosaicDefinitionTransaction
{
    public const SIZE = 4 + 8 + 1 + 1 + 8;
    private const FLAG_SUPPLY_MUTABLE    = 0x01;
    private const FLAG_TRANSFERABLE      = 0x02;
    private const FLAG_RESTRICTABLE      = 0x04;
    private const FLAG_REVOKABLE         = 0x08;

    public function __construct(
        public int $nonce,
        public int $mosaicId,
        public int $flags,
        public int $divisibility,
        public int $duration
    ) {
        if ($nonce < 0 || $nonce > 0xFFFFFFFF) {
            throw new InvalidArgumentException('Invalid nonce');
        }
        if ($mosaicId < 0 || $mosaicId > 0xFFFFFFFFFFFFFFFF) {
            throw new InvalidArgumentException('Invalid mosaicId');
        }
        if ($flags < 0 || $flags > 0xFF) {
            throw new InvalidArgumentException('Invalid flags');
        }
        if ($divisibility < 0 || $divisibility > 0xFF) {
            throw new InvalidArgumentException('Invalid divisibility');
        }
        if ($duration < 0 || $duration > 0xFFFFFFFFFFFFFFFF) {
            throw new InvalidArgumentException('Invalid duration');
        }
    }

    public static function fromBinary(string $binary): self
    {
        if (strlen($binary) !== self::SIZE) {
            throw new \InvalidArgumentException('Invalid binary size');
        }
        $offset = 0;
        $chunk = substr($binary, $offset, 4);
        if (strlen($chunk) !== 4) {
            throw new \RuntimeException('Unexpected EOF while reading nonce (need 4 bytes).');
        }
        $u = unpack('Vvalue', $chunk);
        if ($u === false) {
            throw new \RuntimeException('unpack failed for nonce.');
        }
        $nonce = $u['value'];
        $offset += 4;
        $chunk = substr($binary, $offset, 8);
        if (strlen($chunk) !== 8) {
            throw new \RuntimeException('Unexpected EOF while reading mosaicId (need 8 bytes).');
        }
        $u = unpack('Pvalue', $chunk);
        if ($u === false) {
            throw new \RuntimeException('unpack failed for mosaicId.');
        }
        $mosaicId = $u['value'];
        $offset += 8;
        if (!isset($binary[$offset])) {
            throw new \RuntimeException('Unexpected EOF while reading flags (need 1 byte).');
        }
        $flags = ord($binary[$offset]);
        $offset += 1;
        if (!isset($binary[$offset])) {
            throw new \RuntimeException('Unexpected EOF while reading divisibility (need 1 byte).');
        }
        $divisibility = ord($binary[$offset]);
        $offset += 1;
        $chunk = substr($binary, $offset, 8);
        if (strlen($chunk) !== 8) {
            throw new \RuntimeException('Unexpected EOF while reading duration (need 8 bytes).');
        }
        $u = unpack('Pvalue', $chunk);
        if ($u === false) {
            throw new \RuntimeException('unpack failed for duration.');
        }
        $duration = $u['value'];
        $offset += 8;
        return new self($nonce, $mosaicId, $flags, $divisibility, $duration);
    }

    public function serialize(): string
    {
        return
            pack('V', $this->nonce) .
            pack('P', $this->mosaicId) .
            chr($this->flags) .
            chr($this->divisibility) .
            pack('P', $this->duration);
    }

    public function size(): int
    {
        return self::SIZE;
    }

    public function isSupplyMutable(): bool
    {
        return ($this->flags & self::FLAG_SUPPLY_MUTABLE) !== 0;
    }

    public function isTransferable(): bool
    {
        return ($this->flags & self::FLAG_TRANSFERABLE) !== 0;
    }

    public function isRestrictable(): bool
    {
        return ($this->flags & self::FLAG_RESTRICTABLE) !== 0;
    }

    public function isRevokable(): bool
    {
        return ($this->flags & self::FLAG_REVOKABLE) !== 0;
    }
}
