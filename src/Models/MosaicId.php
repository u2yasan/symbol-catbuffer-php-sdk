<?php

declare(strict_types=1);

namespace SymbolSdk\Models;

final class MosaicId
{
    /** @var string 10進文字列 (0..18446744073709551615) */
    public readonly string $idDec;

    /**
     * @param string $idDec 10進文字列 (numeric-string)
     */
    public function __construct(string $idDec)
    {
        if (1 !== \preg_match('/^[0-9]+$/', $idDec)) {
            throw new \InvalidArgumentException('MosaicId must be a decimal numeric string');
        }
        $max = '18446744073709551615';
        $idDec = \ltrim($idDec, '0');

        if ('' === $idDec) {
            $idDec = '0';
        }

        if (self::cmpDec($idDec, $max) > 0) {
            throw new \InvalidArgumentException('MosaicId out of u64 range');
        }
        $this->idDec = $idDec;
    }

    public static function fromBinary(string $binary): self
    {
        if (8 !== \strlen($binary)) {
            throw new \InvalidArgumentException('Invalid binary size for MosaicId; expected 8 bytes');
        }
        $dec = self::readU64LEDecAt($binary, 0);

        return new self($dec);
    }

    public function serialize(): string
    {
        return self::u64LE($this->idDec);
    }

    /** @return string 10進文字列 */
    public function toDecimal(): string
    {
        return $this->idDec;
    }

    public function equals(self $other): bool
    {
        return $this->idDec === $other->idDec;
    }

    // ------------------------------------------------------------
    // Decimal helpers (no BCMath). All math is done on numeric-string.
    // ------------------------------------------------------------

    /** 比較: a<b:-1, a=b:0, a>b:1 */
    private static function cmpDec(string $a, string $b): int
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

    /**
     * 10進文字列 ÷ 小さな数 (2..256) の商と余り.
     *
     * @return array{0:string,1:int}
     */
    private static function divmodDecBy(string $dec, int $by): array
    {
        if ($by < 2) {
            throw new \InvalidArgumentException('divisor must be >= 2');
        }
        $len = \strlen($dec);
        $q = '';
        $carry = 0; // int

        for ($i = 0; $i < $len; ++$i) {
            $carry = $carry * 10 + (\ord($dec[$i]) - 48); // int
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

    private static function mulDecBy(string $dec, int $by): string
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
            $t = (\ord($dec[$i]) - 48) * $by + $carry; // int
            $out .= \chr(($t % 10) + 48);
            $carry = \intdiv($t, 10);
        }

        while ($carry > 0) {
            $out .= \chr(($carry % 10) + 48);
            $carry = \intdiv($carry, 10);
        }

        return \strrev($out);
    }

    private static function addDecSmall(string $dec, int $small): string
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

    /** LE8 → 10進 */
    private static function readU64LEDecAt(string $bin, int $off): string
    {
        $dec = '0';

        for ($i = 7; $i >= 0; --$i) {
            $dec = self::mulDecBy($dec, 256);
            $dec = self::addDecSmall($dec, \ord($bin[$off + $i]));
        }

        return $dec;
    }

    /** 10進 → LE8 */
    private static function u64LE(string $dec): string
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
}
