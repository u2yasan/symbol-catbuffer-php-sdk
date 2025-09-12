<?php

declare(strict_types=1);

namespace SymbolSdk\Io;

/**
 * final
 * バッファのバイナリストリームをリトルエンディアンで読み出すクラス.
 */
final class BinaryReader
{
    /** @var string 読み込みバッファ（不変・バイナリ） */
    private readonly string $buffer;
    /** @var int 現在のバッファオフセット（読み位置） */
    private int $offset;

    /**
     * コンストラクタ
     *
     * @param string $buffer 読み出しバッファ
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(string $buffer)
    {
        if ('' === $buffer) {
            throw new \InvalidArgumentException('BinaryReader requires non-empty input');
        }
        $this->buffer = $buffer;
        $this->offset = 0;
    }

    /**
     * 現在のオフセットを返す
     * catbuffer: ストリーム位置.
     *
     * @return int
     */
    public function offset(): int
    {
        return $this->offset;
    }

    /**
     * 残り未読バイト数を返す
     * catbuffer: ストリーム残バイト数.
     *
     * @return int
     */
    public function remaining(): int
    {
        return \strlen($this->buffer) - $this->offset;
    }

    /**
     * 次の1バイトを読み、符号なし8ビット整数として返す
     * catbuffer: u8.
     *
     * @return int 0〜255
     *
     * @throws \RuntimeException
     */
    public function readU8(): int
    {
        $this->requireBytes(1);
        $value = \ord($this->buffer[$this->offset]);
        ++$this->offset;

        return $value;
    }

    /**
     * 次の2バイトをリトルエンディアンとして読み、符号なし16ビット整数として返す
     * catbuffer: u16 (LE).
     *
     * @return int 0〜65535
     *
     * @throws \RuntimeException
     */
    public function readU16LE(): int
    {
        $this->requireBytes(2);
        $b0 = \ord($this->buffer[$this->offset]);
        $b1 = \ord($this->buffer[$this->offset + 1]);
        $val = ($b1 << 8) | $b0;
        $this->offset += 2;

        return $val;
    }

    /**
     * 次の4バイトをリトルエンディアンとして読み、符号なし32ビット整数として返す
     * catbuffer: u32 (LE).
     *
     * @return int 0〜4294967295
     *
     * @throws \RuntimeException
     */
    public function readU32LE(): int
    {
        $this->requireBytes(4);
        $b0 = \ord($this->buffer[$this->offset]);
        $b1 = \ord($this->buffer[$this->offset + 1]);
        $b2 = \ord($this->buffer[$this->offset + 2]);
        $b3 = \ord($this->buffer[$this->offset + 3]);
        $val = ($b3 << 24) | ($b2 << 16) | ($b1 << 8) | $b0;
        // UINT32 は PHP int で十分表現できる
        $this->offset += 4;

        return $val;
    }

    /**
     * 次の8バイトをリトルエンディアンとして読み、符号なし64ビット整数を10進数文字列で返す
     * catbuffer: u64 (LE, 10進数）.
     *
     * @return string 例: '12345678901234567890'
     *
     * @throws \RuntimeException
     */
    public function readU64LE(): string
    {
        $this->requireBytes(8);
        $start = $this->offset;
        // $bytes[0]...$bytes[7]
        $low = 0;
        $high = 0;

        for ($i = 0; $i < 4; ++$i) {
            $low |= (\ord($this->buffer[$start + $i]) << ($i * 8));
        }

        for ($i = 0; $i < 4; ++$i) {
            $high |= (\ord($this->buffer[$start + 4 + $i]) << ($i * 8));
        }
        // PHP では UInt64 全域を整数で安全に扱えないため、10進文字列で返す
        // $value = $high << 32 | $low も溢れるので、文字列で合成
        // $value = $high * (2 ** 32) + $low
        $result = \bcadd(
            \bcmul((string) $high, '4294967296'), // 2^32
            (string) $low
        );
        $this->offset += 8;

        return $result;
    }

    /**
     * 次の8バイト（LE u64）を生（バイナリ）で返す
     * catbuffer: u64 bytes (LE).
     *
     * @return string 8バイト
     *
     * @throws \RuntimeException
     */
    public function readU64LEBytes(): string
    {
        return $this->readBytes(8);
    }

    /**
     * 指定バイト数を生で返す
     * catbuffer: bytes[N].
     *
     * @param int $length
     *
     * @return string
     *
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public function readBytes(int $length): string
    {
        if ($length < 0) {
            throw new \InvalidArgumentException('Negative length');
        }
        $this->requireBytes($length);
        $result = \substr($this->buffer, $this->offset, $length);
        $this->offset += $length;

        return $result;
    }

    /**
     * 先頭4バイト（U32LE長）付きのバイト列を読む
     * catbuffer: bytes (len: U32LE).
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    public function readVarBytesWithLenLE(): string
    {
        $length = $this->readU32LE();

        return $this->readBytes($length);
    }

    /**
     * @param int $count
     * @param callable(int):mixed $reader
     *
     * @return list<mixed>
     */
    public function readVector(int $count, callable $reader): array
    {
        $out = [];

        for ($i = 0; $i < $count; ++$i) {
            $out[] = $reader($i);
        }

        return $out;
    }

    /**
     * 残りバイト数が必要量を満たすか確認し、不足時は例外.
     *
     * @param int $length
     *
     * @return void
     *
     * @throws \RuntimeException
     */
    private function requireBytes(int $length): void
    {
        if ($this->remaining() < $length) {
            throw new \RuntimeException("BinaryReader: buffer underrun (need $length, remaining {$this->remaining()})");
        }
    }
}
