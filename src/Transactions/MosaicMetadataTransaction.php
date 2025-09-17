<?php
declare(strict_types=1);
namespace SymbolSdk\Transactions;

final class MosaicMetadataTransaction extends AbstractTransaction
{
    public readonly string $targetAddress;
    public readonly string $scopedMetadataKeyDec;
    public readonly string $targetMosaicIdDec;
    public readonly int $valueSizeDelta;
    public readonly string $value;

    /**
     * @param string $targetAddress 32 bytes
     * @param string $scopedMetadataKeyDec u64 decimal string
     * @param string $targetMosaicIdDec u64 decimal string
     * @param int $valueSizeDelta -32768..32767
     * @param string $value binary string (0..65535 bytes)
     * @param string $headerRaw
     * @param int $size
     * @param int $version
     * @param int $network
     * @param int $type
     * @param string $maxFeeDec
     * @param string $deadlineDec
     */
    public function __construct(
        string $targetAddress,
        string $scopedMetadataKeyDec,
        string $targetMosaicIdDec,
        int $valueSizeDelta,
        string $value,
        string $headerRaw, int $size, int $version, int $network, int $type, string $maxFeeDec, string $deadlineDec
    ) {
        if (strlen($targetAddress) !== 32) {
            throw new \InvalidArgumentException('targetAddress must be 32 bytes');
        }
        if (preg_match('/^[0-9]+$/', $scopedMetadataKeyDec) !== 1) {
            throw new \InvalidArgumentException('scopedMetadataKeyDec must be decimal string');
        }
        if (preg_match('/^[0-9]+$/', $targetMosaicIdDec) !== 1) {
            throw new \InvalidArgumentException('targetMosaicIdDec must be decimal string');
        }
        if ($valueSizeDelta < -32768 || $valueSizeDelta > 32767) {
            throw new \InvalidArgumentException('valueSizeDelta out of int16 range');
        }
        $valueLen = strlen($value);
        if ($valueLen > 65535) {
            throw new \InvalidArgumentException('value length exceeds 65535');
        }
        $this->targetAddress = $targetAddress;
        $this->scopedMetadataKeyDec = ltrim($scopedMetadataKeyDec, '0') === '' ? '0' : ltrim($scopedMetadataKeyDec, '0');
        $this->targetMosaicIdDec = ltrim($targetMosaicIdDec, '0') === '' ? '0' : ltrim($targetMosaicIdDec, '0');
        $this->valueSizeDelta = $valueSizeDelta;
        $this->value = $value;
        parent::__construct($headerRaw, $size, $version, $network, $type, $maxFeeDec, $deadlineDec);
    }

    /**
     * @param string $binary
     * @return self
     */
    public static function fromBinary(string $binary): self
    {
        $h = self::parseHeader($binary);
        $offset = $h['offset'];
        $len = strlen($binary);

        // targetAddress (32B)
        $need = 32;
        // @phpstan-ignore-next-line runtime boundary check
        if ($len - $offset < $need) {
            throw new \RuntimeException("Unexpected EOF: need 32 bytes for targetAddress, have " . ($len - $offset));
        }
        $targetAddress = substr($binary, $offset, 32);
        $offset += 32;

        // scopedMetadataKey (u64 LE)
        $need = 8;
        // @phpstan-ignore-next-line runtime boundary check
        if ($len - $offset < $need) {
            throw new \RuntimeException("Unexpected EOF: need 8 bytes for scopedMetadataKey, have " . ($len - $offset));
        }
        $scopedMetadataKeyDec = parent::readU64LEDecAt($binary, $offset);
        $offset += 8;

        // targetMosaicId (u64 LE)
        $need = 8;
        // @phpstan-ignore-next-line runtime boundary check
        if ($len - $offset < $need) {
            throw new \RuntimeException("Unexpected EOF: need 8 bytes for targetMosaicId, have " . ($len - $offset));
        }
        $targetMosaicIdDec = parent::readU64LEDecAt($binary, $offset);
        $offset += 8;

        // valueSizeDelta (int16 LE)
        $need = 2;
        // @phpstan-ignore-next-line runtime boundary check
        if ($len - $offset < $need) {
            throw new \RuntimeException("Unexpected EOF: need 2 bytes for valueSizeDelta, have " . ($len - $offset));
        }
        $valueSizeDelta = self::readI16LEAt($binary, $offset);
        $offset += 2;
        if ($valueSizeDelta < -32768 || $valueSizeDelta > 32767) {
            throw new \InvalidArgumentException('valueSizeDelta out of int16 range');
        }

        // valueSize (uint16 LE)
        $need = 2;
        // @phpstan-ignore-next-line runtime boundary check
        if ($len - $offset < $need) {
            throw new \RuntimeException("Unexpected EOF: need 2 bytes for valueSize, have " . ($len - $offset));
        }
        $valueSize = self::readU16LEAt($binary, $offset);
        $offset += 2;
        if ($valueSize < 0 || $valueSize > 65535) {
            throw new \InvalidArgumentException('valueSize out of uint16 range');
        }

        // value (valueSize bytes)
        $need = $valueSize;
        // @phpstan-ignore-next-line runtime boundary check
        if ($len - $offset < $need) {
            throw new \RuntimeException("Unexpected EOF: need {$valueSize} bytes for value, have " . ($len - $offset));
        }
        $value = substr($binary, $offset, $valueSize);
        $offset += $valueSize;

        return new self(
            $targetAddress,
            $scopedMetadataKeyDec,
            $targetMosaicIdDec,
            $valueSizeDelta,
            $value,
            $h['headerRaw'], $h['size'], $h['version'], $h['network'], $h['type'], $h['maxFeeDec'], $h['deadlineDec']
        );
    }

    /**
     * @return string
     */
    protected function encodeBody(): string
    {
        $out = '';
        $out .= $this->targetAddress;
        $out .= parent::u64LE($this->scopedMetadataKeyDec);
        $out .= parent::u64LE($this->targetMosaicIdDec);
        $out .= self::i16LE($this->valueSizeDelta);
        $valueLen = strlen($this->value);
        $out .= self::u16LE($valueLen);
        $out .= $this->value;
        return $out;
    }

    /**
     * @param string $binary
     * @param int $offset
     * @return array{
     *   targetAddress:string,
     *   scopedMetadataKeyDec:string,
     *   targetMosaicIdDec:string,
     *   valueSizeDelta:int,
     *   value:string,
     *   offset:int
     * }
     */
    protected static function decodeBody(string $binary, int $offset): array
    {
        $len = strlen($binary);

        // targetAddress (32B)
        $need = 32;
        // @phpstan-ignore-next-line runtime boundary check
        if ($len - $offset < $need) {
            throw new \RuntimeException("Unexpected EOF: need 32 bytes for targetAddress, have " . ($len - $offset));
        }
        $targetAddress = substr($binary, $offset, 32);
        $offset += 32;

        // scopedMetadataKey (u64 LE)
        $need = 8;
        // @phpstan-ignore-next-line runtime boundary check
        if ($len - $offset < $need) {
            throw new \RuntimeException("Unexpected EOF: need 8 bytes for scopedMetadataKey, have " . ($len - $offset));
        }
        $scopedMetadataKeyDec = parent::readU64LEDecAt($binary, $offset);
        $offset += 8;

        // targetMosaicId (u64 LE)
        $need = 8;
        // @phpstan-ignore-next-line runtime boundary check
        if ($len - $offset < $need) {
            throw new \RuntimeException("Unexpected EOF: need 8 bytes for targetMosaicId, have " . ($len - $offset));
        }
        $targetMosaicIdDec = parent::readU64LEDecAt($binary, $offset);
        $offset += 8;

        // valueSizeDelta (int16 LE)
        $need = 2;
        // @phpstan-ignore-next-line runtime boundary check
        if ($len - $offset < $need) {
            throw new \RuntimeException("Unexpected EOF: need 2 bytes for valueSizeDelta, have " . ($len - $offset));
        }
        $valueSizeDelta = self::readI16LEAt($binary, $offset);
        $offset += 2;
        if ($valueSizeDelta < -32768 || $valueSizeDelta > 32767) {
            throw new \InvalidArgumentException('valueSizeDelta out of int16 range');
        }

        // valueSize (uint16 LE)
        $need = 2;
        // @phpstan-ignore-next-line runtime boundary check
        if ($len - $offset < $need) {
            throw new \RuntimeException("Unexpected EOF: need 2 bytes for valueSize, have " . ($len - $offset));
        }
        $valueSize = self::readU16LEAt($binary, $offset);
        $offset += 2;
        if ($valueSize < 0 || $valueSize > 65535) {
            throw new \InvalidArgumentException('valueSize out of uint16 range');
        }

        // value (valueSize bytes)
        $need = $valueSize;
        // @phpstan-ignore-next-line runtime boundary check
        if ($len - $offset < $need) {
            throw new \RuntimeException("Unexpected EOF: need {$valueSize} bytes for value, have " . ($len - $offset));
        }
        $value = substr($binary, $offset, $valueSize);
        $offset += $valueSize;

        return [
            'targetAddress' => $targetAddress,
            'scopedMetadataKeyDec' => $scopedMetadataKeyDec,
            'targetMosaicIdDec' => $targetMosaicIdDec,
            'valueSizeDelta' => $valueSizeDelta,
            'value' => $value,
            'offset' => $offset,
        ];
    }

    /**
     * @param string $bin
     * @param int $offset
     * @return int
     */
    protected static function readU16LEAt(string $bin, int $offset): int
    {
        $chunk = substr($bin, $offset, 2);
        if (strlen($chunk) !== 2) {
            throw new \RuntimeException('Unexpected EOF (need 2 bytes for u16)');
        }
        $a = unpack('vval', $chunk);
        if ($a === false) {
            throw new \RuntimeException('unpack failed');
        }
        return (int)$a['val'];
    }

    /**
     * @param string $bin
     * @param int $offset
     * @return int
     */
    protected static function readI16LEAt(string $bin, int $offset): int
    {
        $chunk = substr($bin, $offset, 2);
        if (strlen($chunk) !== 2) {
            throw new \RuntimeException('Unexpected EOF (need 2 bytes for i16)');
        }
        $a = unpack('vval', $chunk);
        if ($a === false) {
            throw new \RuntimeException('unpack failed');
        }
        $v = (int)$a['val'];
        if ($v >= 0x8000) {
            $v -= 0x10000;
        }
        return $v;
    }

    /**
     * @param int $v
     * @return string
     */
    protected static function u16LE(int $v): string
    {
        if ($v < 0 || $v > 0xFFFF) {
            throw new \InvalidArgumentException('u16 out of range');
        }
        return pack('v', $v);
    }

    /**
     * @param int $v
     * @return string
     */
    protected static function i16LE(int $v): string
    {
        if ($v < -32768 || $v > 32767) {
            throw new \InvalidArgumentException('i16 out of range');
        }
        if ($v < 0) {
            $v += 0x10000;
        }
        return pack('v', $v);
    }
}
