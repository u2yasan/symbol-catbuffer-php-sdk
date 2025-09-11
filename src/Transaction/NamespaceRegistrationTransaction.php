<?php
declare(strict_types=1);
namespace MyApp\Transaction;

use InvalidArgumentException;
use RuntimeException;

final class NamespaceRegistrationTransaction
{
    public readonly int $registrationType;
    public readonly int $duration;
    public readonly int $parentId;
    public readonly string $name;

    public function __construct(int $registrationType, ?int $duration, ?int $parentId, string $name)
    {
        $this->registrationType = $registrationType;

        if ($registrationType === 0) { // root
            if (!isset($duration) || isset($parentId)) {
                throw new InvalidArgumentException('Root namespace must have duration and no parentId.');
            }
            $this->duration = $duration;
            $this->parentId = 0;
        } elseif ($registrationType === 1) { // child
            if (!isset($parentId) || isset($duration)) {
                throw new InvalidArgumentException('Child namespace must have parentId and no duration.');
            }
            $this->parentId = $parentId;
            $this->duration = 0;
        } else {
            throw new InvalidArgumentException('Invalid registrationType; expected 0(root) or 1(child).');
        }

        $this->name = $name;
    }

    public static function fromBinary(string $binary): self
    {
        $offset = 0;
        $len = strlen($binary);

        if ($len < 9) {
            throw new InvalidArgumentException('Binary too short');
        }

        $registrationType = \ord($binary[$offset++]);

        if ($registrationType === 0) { // root
            $remaining = $len - $offset;
            // @phpstan-ignore-next-line runtime boundary check
            if ($remaining < 8) {
                throw new \InvalidArgumentException('Binary too short for root: need 8 bytes for duration.');
            }
            $duration = self::getUint64($binary, $offset);
            $offset += 8;
            $parentId = null;
        } elseif ($registrationType === 1) { // child
            $remaining = $len - $offset;
            // @phpstan-ignore-next-line runtime boundary check
            if ($remaining < 8) {
                throw new \InvalidArgumentException('Binary too short for child: need 8 bytes for parentId.');
            }
            $parentId = self::getUint64($binary, $offset);
            $offset += 8;
            $duration = null;
        } else {
            throw new \InvalidArgumentException('Invalid registrationType (expected 0=root or 1=child).');
        }

        if ($offset >= $len) {
            throw new InvalidArgumentException('Binary end reached while reading nameSize');
        }
        $nameSize = \ord($binary[$offset++]);
        if ($nameSize < 1 || $offset + $nameSize > $len) {
            throw new InvalidArgumentException('Invalid or overflow nameSize');
        }
        $name = substr($binary, $offset, $nameSize);

        return new self($registrationType, $duration, $parentId, $name);
    }

    public function serialize(): string
    {
        $bin = \chr($this->registrationType);
        if ($this->registrationType === 0) {
            $bin .= self::putUint64($this->duration);
        } elseif ($this->registrationType === 1) {
            $bin .= self::putUint64($this->parentId);
        } else {
            throw new RuntimeException('Invalid registrationType during serialize');
        }
        $nameBytes = $this->name;
        $nameLen = strlen($nameBytes);
        if ($nameLen > 255) {
            throw new InvalidArgumentException('Name too long; max 255 bytes.');
        }
        $bin .= \chr($nameLen) . $nameBytes;
        return $bin;
    }

    private static function getUint64(string $s, int $offset): int
    {
        $value = 0;
        for ($i=0; $i<8; ++$i) {
            $value |= (ord($s[$offset+$i]) << (8*$i));
        }
        // warning: PHP int is signed, this is unsigned interpretation
        if (PHP_INT_SIZE < 8 && ($value & 0x8000000000000000)) {
            throw new RuntimeException('uint64 value does not fit in PHP int');
        }
        return $value;
    }

    private static function putUint64(int $n): string
    {
        $bytes = '';
        for ($i=0; $i<8; ++$i) {
            $bytes .= chr(($n >> (8*$i)) & 0xff);
        }
        return $bytes;
    }
}
