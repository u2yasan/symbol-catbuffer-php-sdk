<?php
declare(strict_types=1);

namespace SymbolSdk\Transaction;

/**
 * 128B 共通ヘッダの読み書きと保持を行う基底クラス。
 * 各 Tx は body の encode/decode のみ実装する。
 */
abstract class AbstractTransaction
{
    public const HEADER_SIZE = 128;

    protected string $headerRaw;   // serialize 時にそのまま前置してラウンドトリップ一致
    protected int $size;           // u32 全体長（ヘッダの size）
    protected int $version;        // u8
    protected int $network;        // u8
    protected int $type;           // u16
    protected string $maxFeeDec;   // u64 decimal-string
    protected string $deadlineDec; // u64 decimal-string

    protected function __construct(
        string $headerRaw,
        int $size,
        int $version,
        int $network,
        int $type,
        string $maxFeeDec,
        string $deadlineDec
    ) {
        $this->headerRaw   = $headerRaw;
        $this->size        = $size;
        $this->version     = $version;
        $this->network     = $network;
        $this->type        = $type;
        $this->maxFeeDec   = $maxFeeDec;
        $this->deadlineDec = $deadlineDec;
    }

    /**
     * ヘッダを解析し、ボディ開始オフセット等を返す。
     *
     * @return array{
     *   headerRaw: string,
     *   size: int,
     *   version: int,
     *   network: int,
     *   type: int,
     *   maxFeeDec: string,
     *   deadlineDec: string,
     *   offset: int
     * }
     */
    protected static function parseHeader(string $binary): array
    {
        if (strlen($binary) < self::HEADER_SIZE) {
            throw new \InvalidArgumentException('Binary too short for common header.');
        }
        $hdr = substr($binary, 0, self::HEADER_SIZE);

        $size = self::u32At($hdr, 0);
        // version/network/type は後半固定オフセット
        $version = ord($hdr[104]);
        $network = ord($hdr[105]);
        $type    = self::u16At($hdr, 106);
        $maxFee  = self::u64DecAt($hdr, 108);
        $deadline= self::u64DecAt($hdr, 116); // 108+8=116

        return [
            'headerRaw'   => $hdr,
            'size'        => $size,
            'version'     => $version,
            'network'     => $network,
            'type'        => $type,
            'maxFeeDec'   => $maxFee,
            'deadlineDec' => $deadline,
            'offset'      => self::HEADER_SIZE,
        ];
    }

    /** ヘッダは入力をそのまま返す（署名/署名者維持による完全一致のため） */
    protected function serializeHeader(): string
    {
        return $this->headerRaw;
    }

    // ---- サブクラスが実装する：ボディの decode/encode ----

    /**
     * サブクラス用：ボディのデコード結果を返す。
     * 返す内容はサブクラス依存だが、最低でも次のキーを含めるのが望ましい。
     *
     * @return array<string, mixed>
     */
    abstract protected static function decodeBody(string $binary, int $offset): array;

    /** ボディを直列化して返す。 */
    abstract protected function encodeBody(): string;

    // ---- 公開：全体シリアライズ ----
    public function serialize(): string
    {
        $body = $this->encodeBody();
        return $this->serializeHeader() . $body;
    }

    // ---- ヘルパ（LE 整数）----
    protected static function u32At(string $bin, int $off): int {
        $u = unpack('Vv', substr($bin, $off, 4));
        if ($u === false) throw new \RuntimeException('u32At failed');
        return (int)$u['v'];
    }
    protected static function u16At(string $bin, int $off): int {
        $u = unpack('vv', substr($bin, $off, 2));
        if ($u === false) throw new \RuntimeException('u16At failed');
        return (int)$u['v'];
    }
    protected static function u64DecAt(string $bin, int $off): string {
        $dec = '0';
        for ($i = 7; $i >= 0; $i--) {
            $dec = self::mulDecBy($dec, 256);
            $dec = self::addDecSmall($dec, ord($bin[$off + $i]));
        }
        return $dec;
    }
    protected static function u64LE(string $dec): string {
        $max = '18446744073709551615';
        if (!preg_match('/^[0-9]+$/', $dec) || self::cmpDec($dec, $max) > 0) {
            throw new \InvalidArgumentException('u64 decimal out of range');
        }
        $cur = ltrim($dec, '0');
        if ($cur === '') return str_repeat("\x00", 8);
        $bytes = [];
        for ($i = 0; $i < 8; $i++) {
            [$q, $r] = self::divmodDecBy($cur, 256);
            $bytes[] = chr($r);
            if ($q === '0') {
                for ($j = $i + 1; $j < 8; $j++) $bytes[] = "\x00";
                return implode('', $bytes);
            }
            $cur = $q;
        }
        if ($cur !== '0') throw new \InvalidArgumentException('u64 overflow');
        return implode('', $bytes);
    }
    protected static function cmpDec(string $a, string $b): int {
        $a = ltrim($a, '0'); if ($a === '') $a = '0';
        $b = ltrim($b, '0'); if ($b === '') $b = '0';
        $la = strlen($a); $lb = strlen($b);
        if ($la !== $lb) return $la <=> $lb;
        return strcmp($a, $b) <=> 0;
    }
    /** @return array{0:string,1:int} */
    protected static function divmodDecBy(string $dec, int $by): array {
        $len = strlen($dec); $q = ''; $carry = 0;
        for ($i = 0; $i < $len; $i++) {
            $carry = $carry * 10 + (ord($dec[$i]) - 48);
            $digit = intdiv((int)$carry, (int)$by);
            $carry = (int)($carry % $by);
            if ($q !== '' || $digit !== 0) $q .= chr($digit + 48);
        }
        if ($q === '') $q = '0';
        return [$q, $carry];
    }
    protected static function mulDecBy(string $dec, int $by): string {
        if ($dec === '0') return '0';
        $carry = 0; $out = '';
        for ($i = strlen($dec)-1; $i >= 0; $i--) {
            $t = (ord($dec[$i]) - 48) * $by + $carry;
            $out .= chr(($t % 10) + 48);
            $carry = intdiv((int)$t, 10);
        }
        while ($carry > 0) {
            $out .= chr(($carry % 10) + 48);
            $carry = intdiv((int)$carry, 10);
        }
        return strrev($out);
    }
    protected static function addDecSmall(string $dec, int $small): string {
        $i = strlen($dec) - 1; $carry = $small; $out = '';
        while ($i >= 0 || $carry > 0) {
            $d = $i >= 0 ? (ord($dec[$i]) - 48) : 0;
            $t = $d + $carry;
            $out .= chr(($t % 10) + 48);
            $carry = intdiv((int)$t, 10);
            $i--;
        }
        $res = ltrim(strrev($out), '0');
        return $res === '' ? '0' : $res;
    }
}
