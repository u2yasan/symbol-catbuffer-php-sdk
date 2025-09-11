<?php
declare(strict_types=1);
namespace SymbolSdk\Model;

final readonly class MosaicId
{
    private const SIZE = 8;
    private const UINT64_MAX = '18446744073709551615';
    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromUint64String(string $decimal): self
    {
        if (!preg_match('/^[0-9]+$/', $decimal)) {
            throw new \InvalidArgumentException('Invalid uint64 decimal.');
        }
        if (bccomp($decimal, '0') < 0 || bccomp($decimal, self::UINT64_MAX) > 0) {
            throw new \InvalidArgumentException('Value out of uint64 range.');
        }
        return new self(ltrim($decimal, '0') === '' ? '0' : ltrim($decimal, '0'));
    }

    public static function fromBinary(string $binary): self
    {
        if (strlen($binary) !== self::SIZE) {
            throw new \InvalidArgumentException('Binary must be exactly 8 bytes.');
        }
        $unpacked = unpack('V2', $binary);
        if ($unpacked === false) {
            throw new \RuntimeException('Unpack failed.');
        }
        [$lo, $hi] = [$unpacked[1], $unpacked[2]];
        $decimal = bcadd((string)$lo, bcmul((string)$hi, '4294967296'));
        return self::fromUint64String($decimal);
    }

    public function serialize(): string
    {
        $num = $this->value;
        $hi = '0';
        $lo = $num;
        if (bccomp($num, '4294967295') > 0) {
            $hi = bcdiv($num, '4294967296', 0);
            $lo = bcmod($num, '4294967296');
        }
        $lo32 = (int)$lo;
        $hi32 = (int)$hi;
        $packed = pack('V2', $lo32, $hi32);
        if (strlen($packed) !== self::SIZE) {
            throw new \RuntimeException('Serialization failed.');
        }
        return $packed;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
