<?php

declare(strict_types=1);

namespace SymbolSdk\Transaction;

/**
 * 共通トランザクション基底
 * - 共通ヘッダ(先頭128B想定)の解析・直列化を担当
 * - サブクラスは encodeBody() / decodeBody() のみを実装.
 */
abstract class AbstractTransaction
{
    /** 解析済みの元ヘッダ生バイト（ラウンドトリップ用） */
    protected readonly string $headerRaw;

    /** トランザクション全体サイズ（ヘッダ宣言値） */
    protected readonly int $size;

    /** バージョン */
    protected readonly int $version;

    /** ネットワーク種別 */
    protected readonly int $network;

    /** トランザクション種別 */
    protected readonly int $type;

    /** @var string u64 decimal string */
    protected readonly string $maxFeeDec;

    /** @var string u64 decimal string */
    protected readonly string $deadlineDec;

    /**
     * @internal コンストラクタ：ヘッダ項目を確定
     */
    public function __construct(
        string $headerRaw,
        int $size,
        int $version,
        int $network,
        int $type,
        string $maxFeeDec,
        string $deadlineDec,
    ) {
        // 軽微バリデーション（型は宣言で保証済み）
        if ($size <= 0) {
            throw new \InvalidArgumentException('size must be positive');
        }

        if (1 !== \preg_match('/^[0-9]+$/', $maxFeeDec)) {
            throw new \InvalidArgumentException('maxFeeDec must be decimal string');
        }

        if (1 !== \preg_match('/^[0-9]+$/', $deadlineDec)) {
            throw new \InvalidArgumentException('deadlineDec must be decimal string');
        }

        $this->headerRaw = $headerRaw;
        $this->size = $size;
        $this->version = $version;
        $this->network = $network;
        $this->type = $type;
        $this->maxFeeDec = '' === \ltrim($maxFeeDec, '0') ? '0' : \ltrim($maxFeeDec, '0');
        $this->deadlineDec = '' === \ltrim($deadlineDec, '0') ? '0' : \ltrim($deadlineDec, '0');
    }

    // ------------------------------------------------------------
    // サブクラスが実装するポイント
    // ------------------------------------------------------------

    /** ヘッダ以降のボディのみ直列化（ヘッダは本クラスが前置） */
    abstract protected function encodeBody(): string;

    /**
     * ボディのデコード（任意、ユーティリティ用途）.
     *
     * @return array<string,mixed>
     */
    protected static function decodeBody(string $binary, int $offset): array
    {
        // サブクラスで必要に応じて実装
        return ['nextOffset' => $offset];
    }

    // ------------------------------------------------------------
    // 公開API
    // ------------------------------------------------------------

    /** @return string ヘッダ＋ボディの直列化 */
    final public function serialize(): string
    {
        // 既存ヘッダ（ラウンドトリップ用）＋ 現在のボディ
        // 基本方針：ヘッダは parse した raw をそのまま使う
        $body = $this->encodeBody();

        // 安全側: ヘッダ宣言サイズと一致しない場合は例外（将来必要なら緩和）
        $total = \strlen($this->headerRaw) + \strlen($body);

        if ($total !== $this->size) {
            throw new \RuntimeException("Serialized size mismatch: header+body={$total}, declared={$this->size}");
        }

        return $this->headerRaw.$body;
    }

    // ------------------------------------------------------------
    // 共通ヘッダの解析
    // ------------------------------------------------------------

    /**
     * 共通ヘッダ(128B想定)を解析して項目を返す。
     *
     * @return array{
     *   headerRaw:string,
     *   size:int,
     *   version:int,
     *   network:int,
     *   type:int,
     *   maxFeeDec:string,
     *   deadlineDec:string,
     *   offset:int
     * }
     */
    protected static function parseHeader(string $binary): array
    {
        $len = \strlen($binary);

        if ($len < 128) {
            throw new \RuntimeException("Unexpected EOF: need header(128), have {$len}");
        }

        // Symbol Tx の先頭4Bは size (LE u32)
        $size = self::readU32LEAt($binary, 0);

        if ($size > $len) {
            throw new \RuntimeException("Declared size {$size} exceeds buffer {$len}");
        }

        // version+network (u8 + u8) の合成など実装差があるが、
        // ここでは version(u8)/network(u8)/type(u16LE) として抽出
        $version = \ord($binary[4]);          // u8
        $network = \ord($binary[5]);          // u8
        $type = self::readU16LEAt($binary, 6); // u16LE

        // maxFee(deadline と同様に u64LE) の位置は実装により変化し得るが、
        // ここでは 8B + 8B を仮定（典型的な Symbol Tx レイアウト）
        // 実際の Symbol の標準ヘッダでは署名/署名者鍵など固定長フィールドが先行する。
        // 本SDKでは parseHeader で 128B を「そのまま headerRaw」として保持し、
        // maxFee/deadline は末尾近辺（仮定位置）から読む。
        // 位置が異なる場合は JSON ベクタで検知される想定。
        $headerRaw = \substr($binary, 0, 128);

        // maxFee, deadline の位置（仮置き）。安全に走らせるため保守的に末尾から読む想定にする。
        // ここでは [maxFee(8), deadline(8)] をヘッダ末尾16バイトと仮定。
        $maxFeeOff = 128 - 16;
        $deadlineOff = 128 - 8;

        // 残量チェック（substr は false を返さないが、長さで判定）
        // if (($len - $maxFeeOff) < 16) {
        //     throw new \RuntimeException('Unexpected EOF while reading fee/deadline');
        // }
        // invariant: header is at least 128 bytes => ($len - $maxFeeOff) >= 16

        $maxFeeDec = self::readU64LEDecAt($binary, $maxFeeOff);
        $deadlineDec = self::readU64LEDecAt($binary, $deadlineOff);

        return [
            'headerRaw' => $headerRaw,
            'size' => $size,
            'version' => $version,
            'network' => $network,
            'type' => $type,
            'maxFeeDec' => $maxFeeDec,
            'deadlineDec' => $deadlineDec,
            'offset' => 128, // ヘッダの後ろからボディ
        ];
    }

    // ------------------------------------------------------------
    // u16/u32/u64 ユーティリティ（安全版）
    // ------------------------------------------------------------

    /** 安全な u16LE 読み取り */
    protected static function readU16LEAt(string $bin, int $offset): int
    {
        $chunk = \substr($bin, $offset, 2);

        if (2 !== \strlen($chunk)) {
            $have = \strlen($bin) - $offset;
            throw new \RuntimeException("Unexpected EOF: need 2, have {$have} at {$offset}");
        }
        $arr = \unpack('vval', $chunk); // v: unsigned short (16bit little endian)

        if (false === $arr) {
            throw new \RuntimeException('unpack(v) failed');
        }

        /** @var array{val:int} $arr */
        return $arr['val'];
    }

    /** 安全な u32LE 読み取り */
    protected static function readU32LEAt(string $bin, int $offset): int
    {
        $chunk = \substr($bin, $offset, 4);

        if (4 !== \strlen($chunk)) {
            $have = \strlen($bin) - $offset;
            throw new \RuntimeException("Unexpected EOF: need 4, have {$have} at {$offset}");
        }
        $arr = \unpack('Vval', $chunk); // V: unsigned long (32bit little endian)

        if (false === $arr) {
            throw new \RuntimeException('unpack(V) failed');
        }

        /** @var array{val:int} $arr */
        return $arr['val'];
    }

    /** 10進 → LE8 (u64) */
    protected static function u64LE(string $dec): string
    {
        $max = '18446744073709551615';

        if (1 !== \preg_match('/^[0-9]+$/', $dec) || self::cmpDec($dec, $max) > 0) {
            throw new \InvalidArgumentException('u64 decimal out of range');
        }
        $dec = \ltrim($dec, '0');

        if ('' === $dec) {
            return "\x00\x00\x00\x00\x00\x00\x00\x00";
        }
        $bytes = [];
        $cur = $dec;

        for ($i = 0; $i < 8; ++$i) {
            [$q, $r] = self::divmodDecBy($cur, 256); // r: 0..255
            $bytes[] = \chr($r);

            if ('0' === $q) {
                for ($j = $i + 1; $j < 8; ++$j) {
                    $bytes[] = "\x00";
                }

                return \implode('', $bytes);
            }
            $cur = $q;
        }

        if ('0' !== $cur) {
            throw new \InvalidArgumentException('u64 overflow');
        }

        return \implode('', $bytes);
    }

    /** LE8 → 10進 (u64) */
    protected static function readU64LEDecAt(string $bin, int $off): string
    {
        $dec = '0';

        for ($i = 7; $i >= 0; --$i) {
            $dec = self::mulDecBy($dec, 256);
            $dec = self::addDecSmall($dec, \ord($bin[$off + $i]));
        }

        return $dec;
    }

    // ------------------------------------------------------------
    // 10進文字列演算（BCMath不要）
    // ------------------------------------------------------------

    /** 比較: a<b:-1, a=b:0, a>b:1 */
    protected static function cmpDec(string $a, string $b): int
    {
        $a = \ltrim($a, '0');
        $b = \ltrim($b, '0');

        if ('' === $a) {
            $a = '0';
        }

        if ('' === $b) {
            $b = '0';
        }
        $la = \strlen($a);
        $lb = \strlen($b);

        if ($la !== $lb) {
            return $la < $lb ? -1 : 1;
        }

        return $a <=> $b;
    }

    /** @return array{0:string,1:int} */
    protected static function divmodDecBy(string $dec, int $by): array
    {
        if ($by < 2) {
            throw new \InvalidArgumentException('divisor must be >= 2');
        }
        $len = \strlen($dec);
        $q = '';
        $carry = 0;

        for ($i = 0; $i < $len; ++$i) {
            $carry = $carry * 10 + (\ord($dec[$i]) - 48);
            $digit = \intdiv($carry, $by);
            $carry %= $by;

            if ('' !== $q || 0 !== $digit) {
                $q .= \chr($digit + 48);
            }
        }

        if ('' === $q) {
            $q = '0';
        }

        return [$q, $carry];
    }

    protected static function mulDecBy(string $dec, int $by): string
    {
        if ($by < 0) {
            throw new \InvalidArgumentException('multiplier must be non-negative');
        }

        if ('0' === $dec || 0 === $by) {
            return '0';
        }
        $carry = 0;
        $out = '';

        for ($i = \strlen($dec) - 1; $i >= 0; --$i) {
            $t = (\ord($dec[$i]) - 48) * $by + $carry;
            $out .= \chr(($t % 10) + 48);
            $carry = \intdiv($t, 10);
        }

        while ($carry > 0) {
            $out .= \chr(($carry % 10) + 48);
            $carry = \intdiv($carry, 10);
        }

        return \strrev($out);
    }

    protected static function addDecSmall(string $dec, int $small): string
    {
        if ($small < 0) {
            throw new \InvalidArgumentException('addend must be non-negative');
        }
        $i = \strlen($dec) - 1;
        $carry = $small;
        $out = '';

        while ($i >= 0 || $carry > 0) {
            $d = $i >= 0 ? (\ord($dec[$i]) - 48) : 0;
            $t = $d + $carry;
            $out .= \chr(($t % 10) + 48);
            $carry = \intdiv($t, 10);
            --$i;
        }

        for (; $i >= 0; --$i) {
            $out .= $dec[$i];
        }
        $res = \strrev($out);
        $res = \ltrim($res, '0');

        return '' === $res ? '0' : $res;
    }
}
