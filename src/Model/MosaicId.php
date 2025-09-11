<?php
declare(strict_types=1);

namespace SymbolSdk\Model;

final readonly class MosaicId
{
    /**
     * 8-byte little-endian binary representation (exactly 8 bytes).
     */
    private function __construct(private string $le8)
    {
        if (strlen($this->le8) !== 8) {
            throw new \InvalidArgumentException('MosaicId must be exactly 8 bytes (little-endian).');
        }
    }

    /**
     * Create from decimal unsigned 64-bit string (0 .. 18446744073709551615).
     */
    public static function fromUint64String(string $decimal): self
    {
        $decimal = trim($decimal);
        if ($decimal === '' || !preg_match('/^[0-9]+$/', $decimal)) {
            throw new \InvalidArgumentException('Decimal string required.');
        }
        // Max: 2^64 - 1
        $max = '18446744073709551615';
        if (self::cmpDec($decimal, $max) > 0) {
            throw new \InvalidArgumentException('Out of range for uint64.');
        }
        $le8 = self::decToLe8($decimal);
        return new self($le8);
    }

    /**
     * Create from 8-byte little-endian binary.
     */
    public static function fromBinary(string $binary): self
    {
        if (strlen($binary) !== 8) {
            throw new \InvalidArgumentException('Binary length must be 8.');
        }
        return new self($binary);
    }

    /**
     * Serialize to 8-byte little-endian binary.
     */
    public function serialize(): string
    {
        return $this->le8;
    }

    /**
     * Return decimal string representation.
     */
    public function __toString(): string
    {
        return self::le8ToDec($this->le8);
    }

    // -----------------------
    // Helpers (pure functions)
    // -----------------------

    /**
     * Compare two non-negative decimal strings.
     * @return int -1 if a<b, 0 if a==b, 1 if a>b
     */
    private static function cmpDec(string $a, string $b): int
    {
        $a = ltrim($a, '0'); if ($a === '') $a = '0';
        $b = ltrim($b, '0'); if ($b === '') $b = '0';
        $la = strlen($a); $lb = strlen($b);
        if ($la !== $lb) return $la <=> $lb;
        return strcmp($a, $b) <=> 0;
    }

    /**
     * Convert decimal string to 8-byte little-endian binary (uint64).
     * Algorithm: repeated divmod by 256, collect remainders.
     */
    private static function decToLe8(string $dec): string
    {
        $dec = ltrim($dec, '0');
        if ($dec === '') {
            return "\x00\x00\x00\x00\x00\x00\x00\x00";
        }

        $bytes = [];
        $current = $dec;
        // produce up to 8 bytes
        for ($i = 0; $i < 8; $i++) {
            [$q, $r] = self::divmodDecBy($current, 256);
            $bytes[] = chr($r);
            if ($q === '0') {
                // fill remaining with zeros
                for ($j = $i + 1; $j < 8; $j++) {
                    $bytes[] = "\x00";
                }
                return implode('', $bytes);
            }
            $current = $q;
        }
        // ここまで来る＝8バイト消費しても 0 になっていない → オーバーフロー
        throw new \RuntimeException('Overflow converting to uint64.');
    }

    /**
     * Convert 8-byte little-endian binary to decimal string.
     * Algorithm: accumulate big integer in base-10 via multiply-by-256 and add.
     */
    private static function le8ToDec(string $le8): string
    {
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
     * Decimal string division by small int (<= 256).
     * @return array{0:string,1:int} [quotient, remainder]
     */
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

    /**
     * Decimal string multiply by small int (<=256).
     */
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

    /**
     * Decimal string add small int (0..255).
     */
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
