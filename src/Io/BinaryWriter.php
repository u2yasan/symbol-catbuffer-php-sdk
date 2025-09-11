<?php
declare(strict_types=1);
namespace SymbolSdk\Io;

/**
 * Catbuffer準拠のバイナリ書き込み器（Little Endian、length-prefixed vector/bytes対応、イミュータブルAPI）。
 * @final
 */
final class BinaryWriter
{
    /**
     * @var string
     */
    private string $buffer = '';

    /**
     * @var int
     */
    private int $offset = 0;

    /**
     * Catbuffer：バッファ全体を返す
     * @return string
     */
    public function buffer(): string
    {
        return $this->buffer;
    }

    /**
     * Catbuffer：バッファの現在サイズ（バイト数）
     * @return int
     */
    public function size(): int
    {
        return $this->offset;
    }

    /**
     * Catbuffer (U8)：1バイトを書き込む
     * @param int $v 0〜255
     */
    public function writeU8(int $v): void
    {
        if ($v < 0 || $v > 0xFF) {
            throw new \InvalidArgumentException('U8 value out of range: ' . $v);
        }
        $this->buffer .= chr($v);
        $this->offset += 1;
    }

    /**
     * Catbuffer (U16LE)：2バイトを書き込む
     * @param int $v 0〜65535
     */
    public function writeU16LE(int $v): void
    {
        if ($v < 0 || $v > 0xFFFF) {
            throw new \InvalidArgumentException('U16 value out of range: ' . $v);
        }
        $this->buffer .= chr($v & 0xFF) . chr(($v >> 8) & 0xFF);
        $this->offset += 2;
    }

    /**
     * Catbuffer (U32LE)：4バイトを書き込む
     * @param int $v 0〜4294967295
     */
    public function writeU32LE(int $v): void
    {
        if ($v < 0 || $v > 0xFFFFFFFF) {
            throw new \InvalidArgumentException('U32 value out of range: ' . $v);
        }
        $this->buffer .= chr($v & 0xFF)
            . chr(($v >> 8) & 0xFF)
            . chr(($v >> 16) & 0xFF)
            . chr(($v >> 24) & 0xFF);
        $this->offset += 4;
    }

    /**
     * Catbuffer (U64LE)：10進文字列を8バイトのリトルエンディアンに変換し書き込む
     * @param string $decimal U64範囲(0〜18446744073709551615)の10進文字列
     */
    public function writeU64LEDec(string $decimal): void
    {
        // Check decimal string validity with regex (no sign, only digit, no spaces)
        if (!preg_match('/^(0|[1-9][0-9]*)$/', $decimal)) {
            throw new \InvalidArgumentException('U64: invalid decimal string');
        }

        // Max uint64: 18446744073709551615
        if (strlen($decimal) > 20 ||
            (strlen($decimal) === 20 && strcmp($decimal, "18446744073709551615") > 0)
        ) {
            throw new \InvalidArgumentException('U64 value out of range');
        }

        // Convert decimal string to 8-byte LE binary by divmod 256
        $num = $decimal;
        $result = [];
        for ($i = 0; $i < 8; $i++) {
            $q = '';
            $carry = 0;
            $started = false;
            for ($j = 0, $n = strlen($num); $j < $n; $j++) {
                $digit = (int) $num[$j];
                $acc = $carry * 10 + $digit;
                $div = intdiv($acc, 256);
                $rem = $acc % 256;
                if ($started || $div > 0) {
                    $q .= (string)$div;
                    $started = true;
                }
                $carry = $rem;
            }
            $result[] = chr($carry);
            $num = $q === '' ? '0' : $q;
        }
        if ($num !== '0') {
            throw new \InvalidArgumentException('U64 value out of range');
        }

        $bytes = implode('', $result);
        $this->buffer .= $bytes;
        $this->offset += 8;
    }

    /**
     * Catbuffer：任意のバイト列を書き込む
     * @param string $bytes
     */
    public function writeBytes(string $bytes): void
    {
        $len = strlen($bytes);
        if ($len === 0) {
            return;
        }
        $this->buffer .= $bytes;
        $this->offset += $len;
    }

    /**
     * Catbuffer：先頭にU32LE長を付与した bytes を書き込む
     * @param string $bytes
     */
    public function writeVarBytesWithLenLE(string $bytes): void
    {
        $len = strlen($bytes);
        if ($len > 0xFFFFFFFF) {
            throw new \InvalidArgumentException('VarBytes length exceeds U32 max');
        }
        $this->writeU32LE($len);
        $this->writeBytes($bytes);
    }

    /**
     * Catbuffer：可変長vector。各要素への書き込み関数を受ける
     * @template TValue
     * @param iterable<TValue> $items
     * @param callable(TValue, BinaryWriter):void $elemWriter
     */
    public function writeVector(iterable $items, callable $elemWriter): void
    {
        // Collect into array to count and ensure same order as JS/Python SDK
        $arr = [];
        foreach ($items as $item) {
            $arr[] = $item;
        }
        $count = count($arr);
        if ($count > 0xFFFFFFFF) {
            throw new \InvalidArgumentException('Vector length exceeds U32 max');
        }
        $this->writeU32LE($count);
        foreach ($arr as $item) {
            $elemWriter($item, $this);
        }
    }
}
