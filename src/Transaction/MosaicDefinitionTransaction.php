<?php
declare(strict_types=1);

namespace SymbolSdk\Transaction;

/**
 * MosaicDefinitionTransaction (ヘッダ+ボディ形式のHEX対応)
 * - 入力HEXは 120B ヘッダ + ボディ。ヘッダは保持し serialize() で前置してラウンドトリップ一致。
 * - u64 は10進文字列で保持し、LE8 として直列化/復元。
 */
final class MosaicDefinitionTransaction
{
    private string $header;

    public function __construct(
        public readonly int $nonce,           // u32
        public readonly string $mosaicIdDec,  // u64 as decimal-string
        public readonly int $flags,           // u8
        public readonly int $divisibility,    // u8
        public readonly ?string $durationDec, // u64 as decimal-string or null
        string $header = ''
    ) {
        $this->header = $header;
    }

    public static function fromBinary(string $binary): self
    {
        $len = strlen($binary);
        if ($len < 120) {
            throw new \InvalidArgumentException('Invalid binary size: full transaction expected');
        }
        $header = substr($binary, 0, 120);
        $offset = 120;
        $remaining = $len - $offset;

        // nonce u32 (LE)
        if ($remaining < 4) throw new \RuntimeException('EOF nonce');
        $u = unpack('Vvalue', substr($binary, $offset, 4));
        if ($u === false) throw new \RuntimeException('unpack nonce failed');
        /** @var array{value:int} $u */
        $nonce = $u['value'];
        $offset += 4; $remaining -= 4;

        // mosaicId u64 (LE) -> decimal
        if ($remaining < 8) throw new \RuntimeException('EOF mosaicId');
        $mosaicIdDec = self::readU64LEDecAt($binary, $offset);
        $offset += 8; $remaining -= 8;

        // flags u8
        if ($remaining < 1) throw new \RuntimeException('EOF flags');
        $flags = ord($binary[$offset]); $offset += 1; $remaining -= 1;

        // divisibility u8
        if ($remaining < 1) throw new \RuntimeException('EOF divisibility');
        $div = ord($binary[$offset]); $offset += 1; $remaining -= 1;

        // duration u64 (optional)
        $durationDec = null;
        if ($remaining >= 8) {
            $durationDec = self::readU64LEDecAt($binary, $offset);
            $offset += 8; $remaining -= 8;
        }

        return new self($nonce, $mosaicIdDec, $flags, $div, $durationDec, $header);
    }

    public function serialize(): string
    {
        $body = '';
        // nonce u32
        $body .= pack('V', $this->nonce);
        // mosaicId u64
        $body .= self::u64LE($this->mosaicIdDec);
        // flags u8
        $body .= chr($this->flags);
        // divisibility u8
        $body .= chr($this->divisibility);
        // duration u64 (optional)
        if ($this->durationDec !== null) {
            $body .= self::u64LE($this->durationDec);
        }
        return $this->header . $body;
    }

    // ---------- u64 helpers (decimal-string safe) ----------
    private static function readU64LEDecAt(string $bin, int $off): string
    {
        $dec = '0';
        for ($i = 7; $i >= 0; $i--) {
            $dec = self::mulDecBy($dec, 256);
            $dec = self::addDecSmall($dec, ord($bin[$off + $i]));
        }
        return $dec;
    }

    private static function u64LE(string $dec): string
    {
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

    private static function cmpDec(string $a, string $b): int
    {
        $a = ltrim($a, '0'); if ($a === '') $a = '0';
        $b = ltrim($b, '0'); if ($b === '') $b = '0';
        $la = strlen($a); $lb = strlen($b);
        if ($la !== $lb) return $la <=> $lb;
        return strcmp($a, $b) <=> 0;
    }

    /** @return array{0:string,1:int} */
    private static function divmodDecBy(string $dec, int $by): array
    {
        $len = strlen($dec);
        $q = '';
        $carry = 0;
        for ($i = 0; $i < $len; $i++) {
            $carry = $carry * 10 + (ord($dec[$i]) - 48);
            $digit = intdiv((int)$carry, (int)$by);
            $carry = (int)($carry % $by);
            if ($q !== '' || $digit !== 0) {
                $q .= chr($digit + 48);
            }
        }
        if ($q === '') {
            $q = '0';
        }
        return [$q, $carry];
    }

    private static function mulDecBy(string $dec, int $by): string
    {
        if ($dec === '0') {
            return '0';
        }
        $carry = 0;
        $out = '';
        for ($i = strlen($dec) - 1; $i >= 0; $i--) {
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

    private static function addDecSmall(string $dec, int $small): string
    {
        $i = strlen($dec) - 1;
        $carry = $small;
        $out = '';
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
