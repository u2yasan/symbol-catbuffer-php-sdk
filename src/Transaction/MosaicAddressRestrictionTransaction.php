<?php
declare(strict_types=1);
namespace SymbolSdk\Transaction;

final class MosaicAddressRestrictionTransaction extends AbstractTransaction
{
    public readonly string $mosaicIdDec;
    public readonly string $restrictionKeyDec;
    public readonly string $targetAddress;
    public readonly string $previousRestrictionValue;
    public readonly string $newRestrictionValue;

    /**
     * @param string $mosaicIdDec decimal string
     * @param string $restrictionKeyDec decimal string
     * @param string $targetAddress 24-byte address
     * @param string $previousRestrictionValue decimal string
     * @param string $newRestrictionValue decimal string
     * @param string $headerRaw
     * @param int $size
     * @param int $version
     * @param int $network
     * @param int $type
     * @param string $maxFeeDec
     * @param string $deadlineDec
     */
    public function __construct(
        string $mosaicIdDec,
        string $restrictionKeyDec,
        string $targetAddress,
        string $previousRestrictionValue,
        string $newRestrictionValue,
        string $headerRaw,
        int $size,
        int $version,
        int $network,
        int $type,
        string $maxFeeDec,
        string $deadlineDec
    ) {
        if (preg_match('/^[0-9]+$/', $mosaicIdDec) !== 1) {
            throw new \InvalidArgumentException('mosaicIdDec must be decimal string');
        }
        if (preg_match('/^[0-9]+$/', $restrictionKeyDec) !== 1) {
            throw new \InvalidArgumentException('restrictionKeyDec must be decimal string');
        }
        if (strlen($targetAddress) !== 24) {
            throw new \InvalidArgumentException('targetAddress must be 24 bytes');
        }
        if (preg_match('/^[0-9]+$/', $previousRestrictionValue) !== 1) {
            throw new \InvalidArgumentException('previousRestrictionValue must be decimal string');
        }
        if (preg_match('/^[0-9]+$/', $newRestrictionValue) !== 1) {
            throw new \InvalidArgumentException('newRestrictionValue must be decimal string');
        }
        $this->mosaicIdDec = ltrim($mosaicIdDec, '0') === '' ? '0' : ltrim($mosaicIdDec, '0');
        $this->restrictionKeyDec = ltrim($restrictionKeyDec, '0') === '' ? '0' : ltrim($restrictionKeyDec, '0');
        $this->targetAddress = $targetAddress;
        $this->previousRestrictionValue = ltrim($previousRestrictionValue, '0') === '' ? '0' : ltrim($previousRestrictionValue, '0');
        $this->newRestrictionValue = ltrim($newRestrictionValue, '0') === '' ? '0' : ltrim($newRestrictionValue, '0');
        parent::__construct($headerRaw, $size, $version, $network, $type, $maxFeeDec, $deadlineDec);
    }

    /**
     * @param string $binary
     * @param int $offset
     * @return array{
     *   mosaicIdDec:string,
     *   restrictionKeyDec:string,
     *   targetAddress:string,
     *   previousRestrictionValue:string,
     *   newRestrictionValue:string,
     *   offset:int
     * }
     */
    protected static function decodeBody(string $binary, int $offset): array
    {
        $len = strlen($binary);
        $need = 8 + 8 + 24 + 8 + 8;
        $remaining = $len - $offset;
        if ($remaining < $need) {
            throw new \RuntimeException("Unexpected EOF: need $need, have $remaining");
        }
        $mosaicIdDec = self::readU64LEDecAt($binary, $offset);
        $offset += 8;
        $restrictionKeyDec = self::readU64LEDecAt($binary, $offset);
        $offset += 8;
        $targetAddress = substr($binary, $offset, 24);
        if (strlen($targetAddress) !== 24) {
            throw new \RuntimeException('Unexpected EOF (targetAddress)');
        }
        $offset += 24;
        $previousRestrictionValue = self::readU64LEDecAt($binary, $offset);
        $offset += 8;
        $newRestrictionValue = self::readU64LEDecAt($binary, $offset);
        $offset += 8;
        return [
            'mosaicIdDec' => $mosaicIdDec,
            'restrictionKeyDec' => $restrictionKeyDec,
            'targetAddress' => $targetAddress,
            'previousRestrictionValue' => $previousRestrictionValue,
            'newRestrictionValue' => $newRestrictionValue,
            'offset' => $offset,
        ];
    }

    /**
     * @return string
     */
    protected function encodeBody(): string
    {
        $bin = '';
        $bin .= self::u64LE($this->mosaicIdDec);
        $bin .= self::u64LE($this->restrictionKeyDec);
        if (strlen($this->targetAddress) !== 24) {
            throw new \InvalidArgumentException('targetAddress must be 24 bytes');
        }
        $bin .= $this->targetAddress;
        $bin .= self::u64LE($this->previousRestrictionValue);
        $bin .= self::u64LE($this->newRestrictionValue);
        return $bin;
    }

    /**
     * @param string $a
     * @param string $b
     * @return int
     */
    protected static function cmpDec(string $a, string $b): int
    {
        $a = ltrim($a, '0');
        $b = ltrim($b, '0');
        if ($a === '') $a = '0';
        if ($b === '') $b = '0';
        $la = strlen($a);
        $lb = strlen($b);
        if ($la > $lb) return 1;
        if ($la < $lb) return -1;
        return strcmp($a, $b);
    }

    /**
     * @param string $dec
     * @param int $by
     * @return array{0:string,1:int}
     */
    protected static function divmodDecBy(string $dec, int $by): array
    {
        $len = strlen($dec);
        $q = '';
        $carry = 0;
        for ($i = 0; $i < $len; $i++) {
            $carry = $carry * 10 + (ord($dec[$i]) - 48);
            $digit = intdiv($carry, $by);
            $carry = $carry % $by;
            if ($q !== '' || $digit !== 0) {
                $q .= chr($digit + 48);
            }
        }
        if ($q === '') $q = '0';
        return [$q, $carry];
    }

    /**
     * @param string $dec
     * @param int $by
     * @return string
     */
    protected static function mulDecBy(string $dec, int $by): string
    {
        if ($dec === '0') return '0';
        $carry = 0;
        $out = '';
        for ($i = strlen($dec) - 1; $i >= 0; $i--) {
            $t = (ord($dec[$i]) - 48) * $by + $carry;
            $out .= chr(($t % 10) + 48);
            $carry = intdiv($t, 10);
        }
        while ($carry > 0) {
            $out .= chr(($carry % 10) + 48);
            $carry = intdiv($carry, 10);
        }
        return strrev($out);
    }

    /**
     * @param string $dec
     * @param int $small
     * @return string
     */
    protected static function addDecSmall(string $dec, int $small): string
    {
        $i = strlen($dec) - 1;
        $carry = $small;
        $out = '';
        while ($i >= 0 || $carry > 0) {
            $d = $i >= 0 ? (ord($dec[$i]) - 48) : 0;
            $t = $d + $carry;
            $out .= chr(($t % 10) + 48);
            $carry = intdiv($t, 10);
            $i--;
        }
        for (; $i >= 0; $i--) {
            $out .= $dec[$i];
        }
        $res = strrev($out);
        $res = ltrim($res, '0');
        return $res === '' ? '0' : $res;
    }

    /**
     * @param string $bin
     * @param int $off
     * @return string
     */
    protected static function readU64LEDecAt(string $bin, int $off): string
    {
        $len = strlen($bin);
        if ($off < 0 || $off + 8 > $len) {
            throw new \RuntimeException('Unexpected EOF (need 8 bytes for u64)');
        }
        $dec = '0';
        for ($i = 7; $i >= 0; $i--) {
            $dec = self::mulDecBy($dec, 256);
            $dec = self::addDecSmall($dec, ord($bin[$off + $i]));
        }
        return $dec;
    }

    /**
     * @param string $dec
     * @return string
     */
    protected static function u64LE(string $dec): string
    {
        $max = '18446744073709551615';
        if (preg_match('/^[0-9]+$/', $dec) !== 1 || self::cmpDec($dec, $max) > 0) {
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
                for ($j = $i + 1; $j < 8; $j++) {
                    $bytes[] = "\x00";
                }
                return implode('', $bytes);
            }
            $cur = $q;
        }
        if ($cur !== '0') {
            throw new \InvalidArgumentException('u64 overflow');
        }
        return implode('', $bytes);
    }
}
