<?php
declare(strict_types=1);
namespace SymbolSdk\Io;

/**
 * Catbuffer-compatible binary writer (Little Endian, exact size tracking, immutable buffer).
 * @psalm-immutable
 */
final class BinaryWriter
{
    /**
     * @var string
     */
    private string $buf;

    /**
     * @var int
     */
    private int $offset;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->buf = '';
        $this->offset = 0;
    }

    /**
     * Returns the accumulated binary buffer.
     * catbuffer: bytes[]
     *
     * @return string
     */
    public function buffer(): string
    {
        return $this->buf;
    }

    /**
     * Returns the current buffer size (bytes).
     * catbuffer: size
     *
     * @return int
     */
    public function size(): int
    {
        return $this->offset;
    }

    /**
     * Writes a uint8 (catbuffer: u8).
     *
     * @param int $v 0-255 unsigned integer
     * @return void
     * @throws \InvalidArgumentException
     */
    public function writeU8(int $v): void
    {
        if ($v < 0 || $v > 0xFF) {
            throw new \InvalidArgumentException('U8 out of range: ' . $v);
        }
        $this->buf .= chr($v);
        $this->offset += 1;
    }

    /**
     * Writes a uint16 (Little Endian) (catbuffer: u16).
     *
     * @param int $v 0-65535 unsigned integer
     * @return void
     * @throws \InvalidArgumentException
     */
    public function writeU16LE(int $v): void
    {
        if ($v < 0 || $v > 0xFFFF) {
            throw new \InvalidArgumentException('U16 out of range: ' . $v);
        }
        $this->buf .= chr($v & 0xFF) . chr(($v >> 8) & 0xFF);
        $this->offset += 2;
    }

    /**
     * Writes a uint32 (Little Endian) (catbuffer: u32).
     *
     * @param int $v 0-4294967295 unsigned integer
     * @return void
     * @throws \InvalidArgumentException
     */
    public function writeU32LE(int $v): void
    {
        if ($v < 0 || $v > 0xFFFFFFFF) {
            throw new \InvalidArgumentException('U32 out of range: ' . $v);
        }
        $this->buf .= chr($v & 0xFF)
            . chr(($v >> 8) & 0xFF)
            . chr(($v >> 16) & 0xFF)
            . chr(($v >> 24) & 0xFF);
        $this->offset += 4;
    }

    /**
     * Writes a uint64 (Little Endian) as 8 bytes. Decimal string required.
     * catbuffer: u64 (from base-10 string, 0 <= n < 2^64)
     *
     * @param string $decimal 10-based unsigned string, 0 <= n < 2^64
     * @return void
     * @throws \InvalidArgumentException
     */
    public function writeU64LEDec(string $decimal): void
    {
        // Validate decimal (not negative, only digits)
        if ('' === $decimal || !ctype_digit($decimal)) {
            throw new \InvalidArgumentException('Invalid U64 decimal string');
        }
        // 2^64-1 = 18446744073709551615
        if (strlen($decimal) > 20 ||
            (strlen($decimal) === 20 && strcmp($decimal, '18446744073709551615') > 0)) {
            throw new \InvalidArgumentException('U64 out of range: ' . $decimal);
        }
        $n = $decimal;
        $out = [];
        for ($i = 0; $i < 8; ++$i) {
            if ($n === '0') {
                $out[] = 0;
                continue;
            }
            // Manual divmod 256
            $quot = '';
            $rem = 0;
            $len = strlen($n);
            for ($j = 0; $j < $len; ++$j) {
                $digit = (int)$n[$j];
                $tmp = $rem * 10 + $digit;
                $q = intdiv($tmp, 256);
                $rem = $tmp % 256;
                if ('' !== $quot || $q !== 0) {
                    $quot .= (string)$q;
                }
            }
            if ('' === $quot) {
                $quot = '0';
            }
            $out[] = $rem;
            $n = $quot;
        }
        if ($n !== '0') {
            throw new \InvalidArgumentException('U64 out of range: ' . $decimal);
        }
        $this->buf .= chr($out[0]) . chr($out[1]) . chr($out[2]) . chr($out[3])
            . chr($out[4]) . chr($out[5]) . chr($out[6]) . chr($out[7]);
        $this->offset += 8;
    }

    /**
     * Appends bytes (catbuffer: bytes[N] or bytes[] without prefix).
     *
     * @param string $bytes
     * @return void
     * @throws \InvalidArgumentException
     */
    public function writeBytes(string $bytes): void
    {
        $len = strlen($bytes);
        if ($len === 0) {
            return;
        }
        if (!is_string($bytes)) {
            throw new \InvalidArgumentException('Input is not string');
        }
        $this->buf .= $bytes;
        $this->offset += $len;
    }

    /**
     * Appends length-prefixed bytes, with 4-byte u32le length (catbuffer: bytes).
     *
     * @param string $bytes
     * @return void
     */
    public function writeVarBytesWithLenLE(string $bytes): void
    {
        $len = strlen($bytes);
        $this->writeU32LE($len);
        if ($len > 0) {
            $this->buf .= $bytes;
            $this->offset += $len;
        }
    }

    /**
     * Writes a variable-length vector: [count|elem_1|elem_2|...] as catbuffer.
     * The count is u32le of item count.
     *
     * @template T
     * @param iterable<T> $items
     * @param callable $elemWriter function(T $v, self $writer): void
     * @return void
     * @throws \RuntimeException
     */
    public function writeVector(iterable $items, callable $elemWriter): void
    {
        // Optimize: convert countable or iterate once for count
        if (is_array($items) || $items instanceof \Countable) {
            /** @var int $count */
            $count = is_array($items) ? count($items) : count($items);
            $this->writeU32LE($count);
            foreach ($items as $v) {
                $elemWriter($v, $this);
            }
            return;
        }
        // Not countable: must materialize to count
        $tmp = [];
        foreach ($items as $v) {
            $tmp[] = $v;
        }
        $count = count($tmp);
        $this->writeU32LE($count);
        foreach ($tmp as $v) {
            $elemWriter($v, $this);
        }
    }
}
