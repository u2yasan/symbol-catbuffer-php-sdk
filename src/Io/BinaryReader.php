<?php
declare(strict_types=1);
namespace SymbolSdk\Io;

/**
 * Little Endian, forward-only binary reader for catbuffer compatible streams.
 * Buffer is immutable, offset progresses on each read.
 * All boundary checks throw RuntimeException on overrun.
 * U64 is returned as decimal string ("0"〜"18446744073709551615").
 */
final class BinaryReader
{
    /** @var string */
    private readonly string $buffer;
    /** @var int */
    private int $offset = 0;

    /**
     * @param string $buffer バイナリ列。catbuffer準拠
     * @throws \InvalidArgumentException
     */
    public function __construct(string $buffer)
    {
        $this->buffer = $buffer;
        $this->offset = 0;
    }

    /**
     * 現オフセット（バッファ先頭を0とする、読み取り開始位置）
     * @return int 0以上バッファ長未満
     */
    public function offset(): int
    {
        return $this->offset;
    }

    /**
     * 残りバイト数
     * @return int 0〜
     */
    public function remaining(): int
    {
        return \strlen($this->buffer) - $this->offset;
    }

    /**
     * 1バイト（U8, uint8_t, Little Endian）読み出し
     * @catbuffer type: uint8_t
     * @return int 0〜255
     * @throws \RuntimeException
     */
    public function readU8(): int
    {
        if ($this->remaining() < 1) {
            throw new \RuntimeException('Insufficient bytes for U8');
        }
        $v = \ord($this->buffer[$this->offset]);
        $this->offset += 1;
        return $v;
    }

    /**
     * 2バイト（U16, uint16_t, Little Endian）読み出し
     * @catbuffer type: uint16_t
     * @return int 0〜65535
     * @throws \RuntimeException
     */
    public function readU16LE(): int
    {
        if ($this->remaining() < 2) {
            throw new \RuntimeException('Insufficient bytes for U16');
        }
        $b0 = \ord($this->buffer[$this->offset]);
        $b1 = \ord($this->buffer[$this->offset + 1]);
        $this->offset += 2;
        return ($b1 << 8) | $b0;
    }

    /**
     * 4バイト（U32, uint32_t, Little Endian）読み出し
     * @catbuffer type: uint32_t
     * @return int 0〜4294967295
     * @throws \RuntimeException
     */
    public function readU32LE(): int
    {
        if ($this->remaining() < 4) {
            throw new \RuntimeException('Insufficient bytes for U32');
        }
        $b0 = \ord($this->buffer[$this->offset]);
        $b1 = \ord($this->buffer[$this->offset + 1]);
        $b2 = \ord($this->buffer[$this->offset + 2]);
        $b3 = \ord($this->buffer[$this->offset + 3]);
        $this->offset += 4;
        return ($b3 << 24) | ($b2 << 16) | ($b1 << 8) | $b0;
    }

    /**
     * 8バイト（U64, uint64_t, Little Endian）読み出し
     * @catbuffer type: uint64_t
     * @return string "0"〜"18446744073709551615" （10進数で可逆文字列）
     * @throws \RuntimeException
     */
    public function readU64LE(): string
    {
        if ($this->remaining() < 8) {
            throw new \RuntimeException('Insufficient bytes for U64');
        }
        $result = '0';
        $mul = '1';
        for ($i = 0; $i < 8; ++$i) {
            $byte = \ord($this->buffer[$this->offset + $i]);
            if ($byte !== 0) {
                $add = bcmul((string)$byte, $mul, 0);
                $result = bcadd($result, $add, 0);
            }
            $mul = bcmul($mul, '256', 0);
        }
        $this->offset += 8;
        return $result;
    }

    /**
     * 8バイト（U64, uint64_t, Little Endian）生bytesで取得
     * @catbuffer type: uint64_t (bytes)
     * @return string 8バイトのバイナリ
     * @throws \RuntimeException
     */
    public function readU64LEBytes(): string
    {
        return $this->readBytes(8);
    }

    /**
     * 固定長バイト列を読み出し
     * @catbuffer type: bytes[固定長]
     * @param int $length 長さ
     * @return string 長さ$lengthのバイナリ
     * @throws \RuntimeException
     */
    public function readBytes(int $length): string
    {
        if ($length < 0) {
            throw new \InvalidArgumentException('Negative length');
        }
        if ($this->remaining() < $length) {
            throw new \RuntimeException('Insufficient bytes for readBytes');
        }
        $result = \substr($this->buffer, $this->offset, $length);
        $this->offset += $length;
        return $result;
    }

    /**
     * 可変長（prefix: U32LE長）バイト列の読み出し
     * @catbuffer type: bytes[U32-prefixed]
     * @return string 長さprefixのバイナリ
     * @throws \RuntimeException
     */
    public function readVarBytesWithLenLE(): string
    {
        $length = $this->readU32LE();
        return $this->readBytes($length);
    }

    /**
     * ベクタ（個数: 固定長）の要素をリーダ関数で可変個読み出し
     * @catbuffer type: T[] (数は指定count個)
     * @param callable $elemReader 1要素を読む: fn(BinaryReader $r): T
     * @param int $count 個数
     * @return array<int, mixed> 各要素
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function readVector(callable $elemReader, int $count): array
    {
        if ($count < 0) {
            throw new \InvalidArgumentException('Negative vector count');
        }
        $result = [];
        for ($i = 0; $i < $count; ++$i) {
            /** @var mixed $v */
            $v = $elemReader($this);
            $result[] = $v;
        }
        return $result;
    }
}
