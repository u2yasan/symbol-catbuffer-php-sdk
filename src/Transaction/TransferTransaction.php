<?php
declare(strict_types=1);

namespace SymbolSdk\Transaction;

/**
 * TransferTransaction (ヘッダ+ボディ形式のHEX対応)
 * - 入力HEXは 120B ヘッダ + ボディ。ヘッダは保持し serialize() で前置。
 * - body: recipient(24B) / messageSize(u16LE) / mosaicsCount(u16LE) / reserved(u32) / message / mosaics[]
 * - mosaic は (mosaicId u64LE, amount u64LE) を 1要素 16B
 */
final class TransferTransaction
{
    private string $header;

    /** @var list<Mosaic> */
    private array $mosaics;

    /**
     * @param list<Mosaic> $mosaics
     */
    public function __construct(
        public readonly string $recipient24,   // 24 bytes raw
        array $mosaics,
        public readonly string $message,       // raw bytes
        string $header = ''
    ) {
        $this->mosaics = $mosaics;
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

        // recipient (24B)
        if ($remaining < 24) throw new \RuntimeException('EOF recipient');
        $recipient = substr($binary, $offset, 24);
        $offset += 24; $remaining -= 24;

        // messageSize u16 LE
        if ($remaining < 2) throw new \RuntimeException('EOF messageSize');
        $u = unpack('vvalue', substr($binary, $offset, 2));
        if ($u === false) throw new \RuntimeException('unpack messageSize failed');
        /** @var array{value:int} $u */
        $messageSize = $u['value'];
        $offset += 2; $remaining -= 2;

        // mosaicsCount u16 LE
        if ($remaining < 2) throw new \RuntimeException('EOF mosaicsCount');
        $u = unpack('vvalue', substr($binary, $offset, 2));
        if ($u === false) throw new \RuntimeException('unpack mosaicsCount failed');
        /** @var array{value:int} $u */
        $mosaicsCount = $u['value'];
        $offset += 2; $remaining -= 2;

        // reserved u32（スキップ）
        if ($remaining < 4) throw new \RuntimeException('EOF reserved');
        $offset += 4; $remaining -= 4;

        // message
        if ($remaining < $messageSize) {
            throw new \RuntimeException("Unexpected EOF while reading message: need {$messageSize}, have {$remaining}");
        }
        $message = substr($binary, $offset, $messageSize);
        $offset += $messageSize; $remaining -= $messageSize;

        // mosaics
        $need = $mosaicsCount * 16;
        if ($remaining < $need) {
            throw new \RuntimeException("Unexpected EOF while reading mosaics: need {$need}, have {$remaining}");
        }
        /** @var list<Mosaic> $mosaics */
        $mosaics = [];
        for ($i = 0; $i < $mosaicsCount; $i++) {
            $mid = self::readU64LEDecAt($binary, $offset); $offset += 8;
            $amt = self::readU64LEDecAt($binary, $offset); $offset += 8;
            $mosaics[] = new Mosaic($mid, $amt);
        }

        return new self($recipient, $mosaics, $message, $header);
    }

    public function serialize(): string
    {
        $body  = $this->recipient24;
        $body .= pack('v', strlen($this->message));     // messageSize u16LE
        $body .= pack('v', count($this->mosaics));      // mosaicsCount u16LE
        $body .= pack('V', 0);                          // reserved u32
        $body .= $this->message;

        foreach ($this->mosaics as $m) {
            $body .= self::u64LE($m->mosaicIdDec);
            $body .= self::u64LE($m->amountDec);
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

/**
 * Mosaic 値オブジェクト（u64 は 10進文字列）
 */
final class Mosaic
{
    public function __construct(
        public readonly string $mosaicIdDec, // u64 decimal
        public readonly string $amountDec    // u64 decimal
    ) {}
}
