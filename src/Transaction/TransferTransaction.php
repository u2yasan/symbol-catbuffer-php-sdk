<?php
declare(strict_types=1);

namespace SymbolSdk\Transaction;

use SymbolSdk\Model\MosaicId; // 既に作っているなら使用
use InvalidArgumentException;
use RuntimeException;

/**
 * Minimal standalone TransferTransaction (no base class).
 * catbuffer BODY (example):
 * - recipientAddress: bytes[24]
 * - messageSize: uint16 (LE)
 * - mosaicsCount: uint16 (LE)
 * - message: bytes[messageSize]
 * - mosaics: array of Mosaic { mosaicId: u64 LE (decimal-string), amount: u64 LE (decimal-string) }
 */
final class TransferTransaction
{
    private const RECIPIENT_ADDRESS_SIZE = 24;

    /** @var string 24-byte raw address */
    private readonly string $recipientAddress;
    /** @var string raw message bytes */
    private readonly string $message;
    /** @var list<Mosaic> */
    private readonly array $mosaics;

    /**
     * @param list<Mosaic> $mosaics
     */
    public function __construct(string $recipientAddress, string $message, array $mosaics)
    {
        if (strlen($recipientAddress) !== self::RECIPIENT_ADDRESS_SIZE) {
            throw new InvalidArgumentException('recipientAddress must be 24 bytes.');
        }
        // list<Mosaic> の簡易検証
        foreach ($mosaics as $m) {
            if (!$m instanceof Mosaic) {
                throw new InvalidArgumentException('mosaics must be list<Mosaic>.');
            }
        }
        $this->recipientAddress = $recipientAddress;
        $this->message = $message;
        $this->mosaics = array_values($mosaics);
    }

    /** @return string 24-byte address */
    public function recipientAddress(): string { return $this->recipientAddress; }
    /** @return string message bytes */
    public function message(): string { return $this->message; }
    /** @return list<Mosaic> */
    public function mosaics(): array { return $this->mosaics; }

    /**
     * Deserialize body from catbuffer layout.
     */
    public static function fromBinary(string $binary): self
    {
        $offset = 0;
        $len = strlen($binary);

        // recipientAddress (24)
        self::ensure($len, $offset, self::RECIPIENT_ADDRESS_SIZE, 'recipientAddress');
        $recipient = substr($binary, $offset, self::RECIPIENT_ADDRESS_SIZE);
        $offset += self::RECIPIENT_ADDRESS_SIZE;

        // messageSize (u16 LE)
        $messageSize = self::readU16LEAt($binary, $offset);
        $offset += 2;

        // mosaicsCount (u16 LE)
        $mosaicsCount = self::readU16LEAt($binary, $offset);
        $offset += 2;

        // message (var bytes)
        self::ensure($len, $offset, $messageSize, 'message');
        $message = $messageSize > 0 ? substr($binary, $offset, $messageSize) : '';
        $offset += $messageSize;

        // mosaics (vector)
        $mosaics = [];
        for ($i = 0; $i < $mosaicsCount; $i++) {
            // mosaicId (u64 LE as decimal string)
            $mosaicId = self::readU64LEDecAt($binary, $offset);
            $offset += 8;
            // amount (u64 LE as decimal string)
            $amount = self::readU64LEDecAt($binary, $offset);
            $offset += 8;
            $mosaics[] = new Mosaic($mosaicId, $amount);
        }

        return new self($recipient, $message, $mosaics);
    }

    /**
     * Serialize body to catbuffer layout.
     */
    public function serialize(): string
    {
        $out = '';
        $out .= $this->recipientAddress;

        // messageSize, mosaicsCount
        $out .= self::u16LE(strlen($this->message));
        $out .= self::u16LE(count($this->mosaics));

        // message
        $out .= $this->message;

        // mosaics vector
        foreach ($this->mosaics as $m) {
            $out .= self::u64LE($m->mosaicId());
            $out .= self::u64LE($m->amount());
        }
        return $out;
    }

    // ----------------- helpers -----------------

    private static function ensure(int $len, int $offset, int $need, string $field): void
    {
        $remaining = $len - $offset;
        if ($remaining < $need) {
            throw new RuntimeException("Unexpected EOF while reading {$field}: need {$need}, have {$remaining}");
        }
    }

    private static function readU16LEAt(string $bin, int $offset): int
    {
        self::ensure(strlen($bin), $offset, 2, 'u16');
        $chunk = substr($bin, $offset, 2);
        $u = unpack('vvalue', $chunk);
        if ($u === false) throw new RuntimeException('unpack failed (u16).');
        /** @var array{value:int} $u */
        return $u['value'];
    }

    private static function u16LE(int $v): string
    {
        if ($v < 0 || $v > 0xFFFF) throw new InvalidArgumentException('u16 out of range');
        return chr($v & 0xFF) . chr(($v >> 8) & 0xFF);
    }

    /**
     * Read uint64 LE and return decimal string (0..2^64-1).
     */
    private static function readU64LEDecAt(string $bin, int $offset): string
    {
        self::ensure(strlen($bin), $offset, 8, 'u64');
        $le8 = substr($bin, $offset, 8);
        // Convert LE8 -> decimal string (base256 accumulation)
        $acc = '0';
        for ($i = 7; $i >= 0; $i--) {
            $byte = ord($le8[$i]);
            // acc = acc * 256 + byte
            $acc = self::mulDecBy($acc, 256);
            if ($byte !== 0) $acc = self::addDecSmall($acc, $byte);
        }
        return $acc;
    }

    /**
     * Encode decimal-string uint64 as LE8 bytes.
     */
    private static function u64LE(string $dec): string
    {
        // range check (<= 2^64-1)
        $max = '18446744073709551615';
        if (!preg_match('/^[0-9]+$/', $dec) || self::cmpDec($dec, $max) > 0) {
            throw new InvalidArgumentException('u64 decimal out of range');
        }
        $dec = ltrim($dec, '0');
        if ($dec === '') return "\x00\x00\x00\x00\x00\x00\x00\x00";
        $bytes = [];
        $cur = $dec;
        for ($i = 0; $i < 8; $i++) {
            [$q, $r] = self::divmodDecBy($cur, 256);
            $bytes[] = chr($r);
            if ($q === '0') {
                for ($j = $i + 1; $j < 8; $j++) $bytes[] = "\x00";
                return implode('', $bytes);
            }
            $cur = $q;
        }
        throw new InvalidArgumentException('u64 overflow');
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
            $digit = intdiv($carry, $by);
            $carry = $carry % $by;
            if ($q !== '' || $digit !== 0) $q .= chr($digit + 48);
        }
        if ($q === '') $q = '0';
        return [$q, $carry];
    }

    private static function mulDecBy(string $dec, int $by): string
    {
        if ($dec === '0' || $by === 0) return '0';
        $carry = 0;
        $out = '';
        for ($i = strlen($dec) - 1; $i >= 0; $i--) {
            $prod = (ord($dec[$i]) - 48) * $by + $carry;
            $out .= chr(($prod % 10) + 48);
            $carry = intdiv($prod, 10);
        }
        while ($carry > 0) {
            $out .= chr(($carry % 10) + 48);
            $carry = intdiv($carry, 10);
        }
        return strrev($out);
    }

    private static function addDecSmall(string $dec, int $small): string
    {
        if ($small === 0) return $dec;
        $i = strlen($dec) - 1;
        $carry = $small;
        $chars = str_split($dec);
        while ($i >= 0 && $carry > 0) {
            $sum = (ord($chars[$i]) - 48) + ($carry % 10);
            $carry = intdiv($carry, 10);
            if ($sum >= 10) {
                $sum -= 10;
                $carry += 1;
            }
            $chars[$i] = chr($sum + 48);
            $i--;
        }
        if ($carry > 0) {
            return (string)$carry . implode('', $chars);
        }
        return implode('', $chars);
    }
}

/**
 * Minimal Mosaic value object for Transfer (decimal-string ids/amounts).
 */
final class Mosaic
{
    /** @var string decimal-string */
    private readonly string $mosaicId;
    /** @var string decimal-string */
    private readonly string $amount;

    public function __construct(string $mosaicId, string $amount)
    {
        if (!preg_match('/^[0-9]+$/', $mosaicId)) {
            throw new \InvalidArgumentException('mosaicId must be decimal string');
        }
        if (!preg_match('/^[0-9]+$/', $amount)) {
            throw new \InvalidArgumentException('amount must be decimal string');
        }

        $mid = ltrim($mosaicId, '0');
        if ($mid === '') { $mid = '0'; }

        $amt = ltrim($amount, '0');
        if ($amt === '') { $amt = '0'; }

        // readonly はここで一度だけ代入
        $this->mosaicId = $mid;
        $this->amount   = $amt;
    }

    public function mosaicId(): string { return $this->mosaicId; }
    public function amount(): string { return $this->amount; }

    public function serialize(): string
    {
        return self::u64LE($this->mosaicId) . self::u64LE($this->amount);
    }

    /** Encode decimal-string uint64 as LE8 bytes (local helpers). */
    private static function u64LE(string $dec): string
    {
        $max = '18446744073709551615';
        if (!preg_match('/^[0-9]+$/', $dec) || self::cmpDec($dec, $max) > 0) {
            throw new \InvalidArgumentException('u64 decimal out of range');
        }
        $dec = ltrim($dec, '0');
        if ($dec === '') return "\x00\x00\x00\x00\x00\x00\x00\x00";

        $bytes = [];
        $cur = $dec;
        for ($i = 0; $i < 8; $i++) {
            [$q, $r] = self::divmodDecBy($cur, 256);
            $bytes[] = chr($r);
            if ($q === '0') {
                for ($j = $i + 1; $j < 8; $j++) $bytes[] = "\x00";
                return implode('', $bytes);
            }
            $cur = $q;
        }
        throw new \InvalidArgumentException('u64 overflow');
    }

    /** @return array{0:string,1:int} */
    private static function divmodDecBy(string $dec, int $by): array
    {
        $len = strlen($dec);
        $q = '';
        $carry = 0;
        for ($i = 0; $i < $len; $i++) {
            $carry = $carry * 10 + (ord($dec[$i]) - 48);
            $digit = intdiv($carry, $by);
            $carry = $carry % $by;
            if ($q !== '' || $digit !== 0) $q .= chr($digit + 48);
        }
        if ($q === '') $q = '0';
        return [$q, $carry];
    }

    private static function cmpDec(string $a, string $b): int
    {
        $a = ltrim($a, '0'); if ($a === '') $a = '0';
        $b = ltrim($b, '0'); if ($b === '') $b = '0';
        $la = strlen($a); $lb = strlen($b);
        if ($la !== $lb) return $la <=> $lb;
        return strcmp($a, $b) <=> 0;
    }
}