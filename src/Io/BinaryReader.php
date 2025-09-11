<?php
declare(strict_types=1);

namespace SymbolSdk\Io;

/**
 * BinaryReader: catbuffer互換のリトルエンディアン・バイナリリーダ。
 * @psalm-immutable
 */
final class BinaryReader
{
    /** @readonly */
    private string $buffer;

    /** @readonly */
    private int $length;

    /** @var int 現在の読み出し位置 */
    private int $offset = 0;

    /**
     * @param string $buffer - 不変バッファ（catbufferの生バイト列）
     * @throws \InvalidArgumentException
     */
    public function __construct(string $buffer)
    {
        $this->buffer = $buffer;
        $this->length = strlen($buffer);
    }

    /**
     * 残りバイト数
     * @return int
     */
    public function remaining(): int
    {
        return $this->length - $this->offset;
    }

    /**
     * 現在のオフセット（先頭0）
     * @return int
     */
    public function offset(): int
    {
        return $this->offset;
    }

    /**
     * catbuffer: u8
     * @return int 0〜255
     * @throws \RuntimeException
     */
    public function readU8(): int
    {
        if ($this->remaining() < 1) {
            throw new \RuntimeException('readU8: buffer underrun');
        }
        $value = ord($this->buffer[$this->offset]);
        $this->offset += 1;
        return $value;
    }

    /**
     * catbuffer: u16 (Little Endian)
     * @return int 0〜65535
     * @throws \RuntimeException
     */
    public function readU16LE(): int
    {
        if ($this->remaining() < 2) {
            throw new \RuntimeException('readU16LE: buffer underrun');
        }
        $v = unpack('v', substr($this->buffer, $this->offset, 2));
        if (!isset($v[1])) {
            throw new \RuntimeException('readU16LE: unpack failed');
        }
        $this->offset += 2;
        return $v[1];
    }

    /**
     * catbuffer: u32 (Little Endian)
     * @return int 0〜4294967295
     * @throws \RuntimeException
     */
    public function readU32LE(): int
    {
        if ($this->remaining() < 4) {
            throw new \RuntimeException('readU32LE: buffer underrun');
        }
        $v = unpack('V', substr($this->buffer, $this->offset, 4));
        if (!isset($v[1])) {
            throw new \RuntimeException('readU32LE: unpack failed');
        }
        // PHPは符号付きintなので上位bitの値は負値となる可能性あり
        $result = $v[1];
        if ($result < 0) {
            $result += 4294967296;
        }
        $this->offset += 4;
        return $result;
    }

    /**
     * catbuffer: u64 (Little Endian)
     * @return string 10進文字列
     * @throws \RuntimeException
     */
    public function readU64LE(): string
    {
        if ($this->remaining() < 8) {
            throw new \RuntimeException('readU64LE: buffer underrun');
        }
        $bytes = substr($this->buffer, $this->offset, 8);
        $this->offset += 8;
        // 8バイト LSB優先→10進に
        $value = '0';
        for ($i = 7; $i >= 0; --$i) {
            $byteValue = strval(ord($bytes[$i]));
            if ($byteValue !== '0') {
                $value = bcmul($value, '256');
                $value = bcadd($value, $byteValue);
            } else {
                $value = bcmul($value, '256');
            }
        }
        // 0埋め対応
        if ($value[0] === '0' && strlen($value) > 1) {
            $value = ltrim($value, '0');
        }
        return $value;
    }

    /**
     * catbuffer: u64 (Little Endian, raw bytes)
     * @return string 8バイト生
     * @throws \RuntimeException
     */
    public function readU64LEBytes(): string
    {
        if ($this->remaining() < 8) {
            throw new \RuntimeException('readU64LEBytes: buffer underrun');
        }
        $result = substr($this->buffer, $this->offset, 8);
        $this->offset += 8;
        return $result;
    }

    /**
     * catbuffer: [byte] 固定長バイト列
     * @param int $length
     * @return string $lengthバイト生
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function readBytes(int $length): string
    {
        if ($length < 0) {
            throw new \InvalidArgumentException('readBytes: negative length');
        }
        if ($this->remaining() < $length) {
            throw new \RuntimeException('readBytes: buffer underrun');
        }
        $result = substr($this->buffer, $this->offset, $length);
        $this->offset += $length;
        return $result;
    }

    /**
     * catbuffer: プレフィックス付の可変バイト列 (u32 length + bytes)
     * @return string 長さ自己一致
     * @throws \RuntimeException
     */
    public function readVarBytesWithLenLE(): string
    {
        $length = $this->readU32LE();
        return $this->readBytes($length);
    }

    /**
     * 可変長ベクタ（要素読み取りコールバック）。
     *
     * @template T
     * @param callable(self):T $elemReader コールバックはこのリーダーから要素1つを読み取り T を返す
     * @return list<T>
     */
    public function readVector(callable $elemReader, int $count): array
    {
        if ($count < 0) {
            throw new \InvalidArgumentException('count must be >= 0');
        }
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = $elemReader($this);
        }
        return $out;
    }
}
