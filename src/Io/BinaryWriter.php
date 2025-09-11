<?php
declare(strict_types=1);
namespace SymbolSdk\Io;

/**
 * Symbol Catbuffer writer (Little Endian).
 * 
 * - Immutable public API
 * - Implements: buffer/size, U8/U16/U32/U64_LE, bytes, var bytes, vector
 */
final class BinaryWriter
{
    /**
     * @var string
     */
    private string $buffer;

    /**
     * @var int Append offset (number of written bytes)
     */
    private int $offset;

    /**
     * Constructor. Initializes empty buffer.
     */
    public function __construct()
    {
        $this->buffer = '';
        $this->offset = 0;
    }

    /**
     * Returns completed buffer.
     * @return string
     */
    public function buffer(): string
    {
        return $this->buffer;
    }

    /**
     * Returns buffer size in bytes.
     * @return int
     */
    public function size(): int
    {
        return $this->offset;
    }

    /**
     * Writes 1 byte unsigned integer (uint8).
     * Catbuffer: U8 (1 byte)
     * @param int $v 0..255
     */
    public function writeU8(int $v): void
    {
        if ($v < 0 || $v > 0xFF) {
            throw new \InvalidArgumentException('U8 must be 0..255');
        }
        $this->buffer .= chr($v);
        $this->offset += 1;
    }

    /**
     * Writes 2 bytes unsigned integer LE (uint16).
     * Catbuffer: U16 (2 bytes, little endian)
     * @param int $v 0..65535
     */
    public function writeU16LE(int $v): void
    {
        if ($v < 0 || $v > 0xFFFF) {
            throw new \InvalidArgumentException('U16 must be 0..65535');
        }
        $s = chr($v & 0xFF) . chr(($v >> 8) & 0xFF);
        $this->buffer .= $s;
        $this->offset += 2;
    }

    /**
     * Writes 4 bytes unsigned integer LE (uint32).
     * Catbuffer: U32 (4 bytes, little endian)
     * @param int $v 0..4294967295
     */
    public function writeU32LE(int $v): void
    {
        if ($v < 0 || $v > 0xFFFFFFFF) {
            throw new \InvalidArgumentException('U32 must be 0..4294967295');
        }
        $s = chr($v & 0xFF)
            . chr(($v >> 8) & 0xFF)
            . chr(($v >> 16) & 0xFF)
            . chr(($v >> 24) & 0xFF);
        $this->buffer .= $s;
        $this->offset += 4;
    }

    /**
     * Writes 8 bytes unsigned integer LE, from decimal string.
     * Catbuffer: U64 (8 bytes, little endian)
     * @param string $decimal Decimal string, 0..18446744073709551615
     */
    public function writeU64LEDec(string $decimal): void
    {
        if (!preg_match('/^[0-9]+$/', $decimal)) {
            throw new \InvalidArgumentException('U64 must be a decimal string');
        }
        // min: 0, max: 18446744073709551615 (2^64-1)
        $max = '18446744073709551615';
        if (strlen($decimal) > strlen($max) || (strlen($decimal) === strlen($max) && strcmp($decimal, $max) > 0)) {
            throw new \InvalidArgumentException('U64 out of range');
        }

        $out = [];
        $value = ltrim($decimal, '0');
        if ($value === '') {
            $value = '0';
        }
        // divmod by 256 for 8 times.
        for ($i = 0; $i < 8; ++$i) {
            $out[$i] = (int)bcmod($value, '256');
            $value = bcdiv($value, '256');
        }
        // After 8 times, $value should be zero
        if (bccomp($value, '0') !== 0) {
            throw new \InvalidArgumentException('U64 overflow');
        }
        $bytes = '';
        for ($i = 0; $i < 8; ++$i) {
            $bytes .= chr($out[$i]);
        }
        $this->buffer .= $bytes;
        $this->offset += 8;
    }

    /**
     * Writes raw bytes to buffer.
     * Catbuffer: Octet Bytes (N-byte sequence)
     * @param string $bytes
     */
    public function writeBytes(string $bytes): void
    {
        if ($bytes === '') {
            return;
        }
        $len = strlen($bytes);
        $this->buffer .= $bytes;
        $this->offset += $len;
    }

    /**
     * Writes var length bytes with U32LE length prefix.
     * Catbuffer: byte array with length (U32LE | bytes)
     * @param string $bytes
     */
    public function writeVarBytesWithLenLE(string $bytes): void
    {
        $len = strlen($bytes);
        $this->writeU32LE($len);
        if ($len > 0) {
            $this->buffer .= $bytes;
            $this->offset += $len;
        }
    }

    /**
     * Writes vector of items (catbuffer array).
     * Catbuffer: U32LE count + elements
     * @template T
     * @param iterable<T> $items
     * @param callable(T, self): void $elemWriter function($elem, BinaryWriter $writer): void
     */
    public function writeVector(iterable $items, callable $elemWriter): void
    {
        // For precise count (iterable may not be countable), buffer items then write with length
        $elems = [];
        foreach ($items as $item) {
            $elems[] = $item;
        }
        $num = count($elems);
        $this->writeU32LE($num);
        foreach ($elems as $item) {
            $elemWriter($item, $this);
        }
    }
}
