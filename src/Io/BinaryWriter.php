<?php

declare(strict_types=1);

namespace SymbolSdk\Io;

/**
 * Simple binary writer that appends to an internal buffer.
 * - Writes little-endian integers
 * - Writes uint64 from decimal-string (no BCMath required).
 */
final class BinaryWriter
{
    private string $buf;
    private int $offset;

    /**
     * @param string $initial optional initial buffer (appended to)
     */
    public function __construct(string $initial = '')
    {
        $this->buf = $initial;
        $this->offset = \strlen($initial);
    }

    public static function createEmpty(): self
    {
        return new self('');
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function toString(): string
    {
        return $this->buf;
    }

    /**
     * Append raw bytes as-is.
     */
    public function writeBytes(string $bytes): void
    {
        if ('' === $bytes) {
            return;
        }
        $this->buf .= $bytes;
        $this->offset += \strlen($bytes);
    }

    /**
     * Write uint8 (little endian).
     */
    public function writeU8(int $value): void
    {
        if ($value < 0 || $value > 0xFF) {
            throw new \InvalidArgumentException('writeU8 out of range: '.(string) $value);
        }
        $this->buf .= \chr($value);
        ++$this->offset;
    }

    /**
     * Write uint16 (little endian).
     */
    public function writeU16LE(int $value): void
    {
        if ($value < 0 || $value > 0xFFFF) {
            throw new \InvalidArgumentException('writeU16LE out of range: '.(string) $value);
        }
        $this->buf .= \pack('v', $value);
        $this->offset += 2;
    }

    /**
     * Write uint32 (little endian).
     */
    public function writeU32LE(int $value): void
    {
        if ($value < 0 || $value > 0xFFFFFFFF) {
            throw new \InvalidArgumentException('writeU32LE out of range: '.(string) $value);
        }
        $this->buf .= \pack('V', $value);
        $this->offset += 4;
    }

    /**
     * Write uint64 (little endian) from a decimal string.
     * e.g. "18446744073709551615" (max) → 0xFF..FF (8 bytes LE).
     *
     * @param non-empty-string $dec
     */
    public function writeU64LEDec(string $dec): void
    {
        if ('' === $dec || 1 !== \preg_match('/^[0-9]+$/', $dec)) {
            throw new \InvalidArgumentException('writeU64LEDec expects decimal string.');
        }

        // convert decimal string -> 8 LE bytes via repeated divmod by 256
        $bytes = [];
        $n = $dec;

        for ($i = 0; $i < 8; ++$i) {
            [$q, $r] = self::divmodDecBy($n, 256);
            /** @var int $ri */
            $ri = (int) $r;
            $bytes[] = \chr($ri);
            $n = $q;

            if ('0' === $n) {
                // fill remaining bytes with 0
                for ($j = $i + 1; $j < 8; ++$j) {
                    $bytes[] = "\x00";
                }
                break;
            }
        }

        if (8 !== \count($bytes)) {
            // if we didn’t break above, we must still have 8 bytes after loop
            while (\count($bytes) < 8) {
                $bytes[] = "\x00";
            }
        }

        // If quotient still > 0, it didn't fit into 64 bits.
        if ('0' !== $n) {
            throw new \InvalidArgumentException('writeU64LEDec overflow for: '.$dec);
        }

        $out = \implode('', $bytes);
        $this->buf .= $out;
        $this->offset += 8;
    }

    /**
     * Decimal string division by small integer (<= 256). Returns [quotient, remainder].
     *
     * @return array{0:string,1:int}
     */
    private static function divmodDecBy(string $num, int $by): array
    {
        if ($by < 2 || $by > 256) {
            throw new \InvalidArgumentException('divmodDecBy supports 2..256');
        }
        $len = \strlen($num);

        if (0 === $len) {
            return ['0', 0];
        }

        $carry = 0;
        $out = '';

        for ($i = 0; $i < $len; ++$i) {
            $digit = \ord($num[$i]) - 48; // '0' => 48

            if ($digit < 0 || $digit > 9) {
                throw new \InvalidArgumentException('Non-decimal in input.');
            }
            $acc = $carry * 10 + $digit;
            $q = \intdiv($acc, $by);
            $carry = $acc - $q * $by;

            if ('' !== $out || 0 !== $q) {
                $out .= (string) $q;
            }
        }

        if ('' === $out) {
            $out = '0';
        }

        return [$out, $carry];
    }
}
