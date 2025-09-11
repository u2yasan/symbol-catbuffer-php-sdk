<?php
declare(strict_types=1);
namespace SymbolSdk\Model;

use InvalidArgumentException;
use RuntimeException;

final readonly class MosaicId
{
    private const SIZE = 8;
    private int $hi;
    private int $lo;

    private function __construct(int $hi, int $lo)
    {
        if ($hi < 0 || $hi > 0xFFFFFFFF) {
            throw new InvalidArgumentException('hi is out of range');
        }
        if ($lo < 0 || $lo > 0xFFFFFFFF) {
            throw new InvalidArgumentException('lo is out of range');
        }
        $this->hi = $hi;
        $this->lo = $lo;
    }

    public static function fromUint64String(string $value): self
    {
        if (!preg_match('/^[0-9]{1,20}$/', $value)) {
            throw new InvalidArgumentException('Invalid uint64 string');
        }
        if (strlen($value) > 1 && $value[0] === '0') {
            throw new InvalidArgumentException('Leading zeros are not allowed');
        }

        // Convert string to two 32bit words (hi, lo) using only PHP int ops
        $val = $value;
        $hi = 0;
        $lo = 0;
        $maxUint64 = '18446744073709551615';
        if (strlen($val) > strlen($maxUint64) || (strlen($val) === strlen($maxUint64) && $val > $maxUint64)) {
            throw new InvalidArgumentException('Value out of uint64 range');
        }
        // Using math, repeatedly divide by 2^32 (=4294967296)
        $NUM_BASE = 4294967296;

        $hi = intdiv((int)($val / $NUM_BASE), 1);
        $lo = (int)($val % $NUM_BASE);
        if (bccomp($val, (string)$NUM_BASE) >= 0) {
            // PHP 8.3: intdiv string disables. Use bcdiv/bcmod for 53b+.
            $hi = (int)bcdiv($val, (string)$NUM_BASE, 0);
            $lo = (int)bcmod($val, (string)$NUM_BASE);
        }

        return new self($hi, $lo);
    }

    public static function fromBinary(string $payload): self
    {
        if (strlen($payload) !== self::SIZE) {
            throw new InvalidArgumentException('Binary length mismatch');
        }
        $lo = unpack('V', substr($payload, 0, 4))[1];
        $hi = unpack('V', substr($payload, 4, 4))[1];
        return new self($hi, $lo);
    }

    public function serialize(): string
    {
        return pack('V', $this->lo) . pack('V', $this->hi);
    }

    public function __toString(): string
    {
        // Reconstruct from hi/lo
        $NUM_BASE = '4294967296';
        $hi = (string)$this->hi;
        $lo = (string)$this->lo;
        if ($hi === '0') {
            return $lo;
        }
        return bcadd(bcmul($hi, $NUM_BASE), $lo, 0);
    }
}
