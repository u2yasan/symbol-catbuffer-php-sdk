<?php
declare(strict_types=1);
namespace SymbolSdk\Transactions;

final class AccountMetadataTransaction extends AbstractTransaction
{
    public readonly string $targetAddress;
    public readonly string $scopedMetadataKeyDec;
    public readonly int $valueSizeDelta;
    public readonly string $value;

    public function __construct(
        string $targetAddress,
        string $scopedMetadataKeyDec,
        int $valueSizeDelta,
        string $value,
        string $headerRaw,
        int $size,
        int $version,
        int $network,
        int $type,
        string $maxFeeDec,
        string $deadlineDec
    ) {
        if (strlen($targetAddress) !== 24) {
            throw new \InvalidArgumentException('targetAddress must be 24 bytes');
        }
        if (preg_match('/^[0-9]+$/', $scopedMetadataKeyDec) !== 1) {
            throw new \InvalidArgumentException('scopedMetadataKeyDec must be decimal string');
        }
        if ($valueSizeDelta < -0x8000 || $valueSizeDelta > 0x7FFF) {
            throw new \InvalidArgumentException('valueSizeDelta out of int16 range');
        }
        $this->targetAddress = $targetAddress;
        $this->scopedMetadataKeyDec = $scopedMetadataKeyDec;
        $this->valueSizeDelta = $valueSizeDelta;
        $this->value = $value;
        parent::__construct($headerRaw, $size, $version, $network, $type, $maxFeeDec, $deadlineDec);
    }

    public static function fromBinary(string $binary): self
    {
        $h = self::parseHeader($binary);
        $body = self::decodeBody($binary, $h['offset']);
        return new self(
            $body['targetAddress'],
            $body['scopedMetadataKeyDec'],
            $body['valueSizeDelta'],
            $body['value'],
            $h['headerRaw'],
            $h['size'],
            $h['version'],
            $h['network'],
            $h['type'],
            $h['maxFeeDec'],
            $h['deadlineDec']
        );
    }

    /**
     * @return array{targetAddress:string, scopedMetadataKeyDec:string, valueSizeDelta:int, value:string, offset:int}
     */
    protected static function decodeBody(string $binary, int $offset): array
    {
        $len = strlen($binary);
        if ($len - $offset < 24) {
            throw new \RuntimeException('Unexpected EOF: need 24 bytes for targetAddress');
        }
        $targetAddress = substr($binary, $offset, 24);
        $offset += 24;

        $scopedMetadataKeyDec = self::readU64LEDecAt($binary, $offset);
        $offset += 8;

        $valueSizeDelta = self::readI16LEAt($binary, $offset);
        $offset += 2;

        $valueSize = self::readU16LEAt($binary, $offset);
        $offset += 2;

        if ($len - $offset < $valueSize) {
            throw new \RuntimeException('Unexpected EOF: need '.$valueSize.' bytes for value');
        }
        $value = substr($binary, $offset, $valueSize);
        $offset += $valueSize;

        return [
            'targetAddress' => $targetAddress,
            'scopedMetadataKeyDec' => $scopedMetadataKeyDec,
            'valueSizeDelta' => $valueSizeDelta,
            'value' => $value,
            'offset' => $offset
        ];
    }

    protected function encodeBody(): string
    {
        $out = $this->targetAddress;
        $out .= self::u64LE($this->scopedMetadataKeyDec);
        $out .= self::i16LE($this->valueSizeDelta);
        $out .= self::u16LE(strlen($this->value));
        $out .= $this->value;
        return $out;
    }

    /**
     * @param int $v
     * @return string
     */
    protected static function u16LE(int $v): string
    {
        if ($v < 0 || $v > 0xFFFF) {
            throw new \InvalidArgumentException('u16LE: value out of range');
        }
        return pack('v', $v);
    }

    /**
     * @param int $v
     * @return string
     */
    protected static function i16LE(int $v): string
    {
        if ($v < -0x8000 || $v > 0x7FFF) {
            throw new \InvalidArgumentException('i16LE: value out of range');
        }
        $u = $v < 0 ? $v + 0x10000 : $v;
        return pack('v', $u);
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
            throw new \RuntimeException('Unexpected EOF: need 2 bytes for u16');
        }
        $tmp = unpack('vval', $chunk);
        if (!is_array($tmp) || !isset($tmp['val'])) {
            throw new \RuntimeException('unpack failed for u16');
        }
        return $tmp['val'];
    }

    /**
     * @param string $bin
     * @param int $offset
     * @return int
     */
    protected static function readI16LEAt(string $bin, int $offset): int
    {
        $u = self::readU16LEAt($bin, $offset);
        return $u >= 0x8000 ? $u - 0x10000 : $u;
    }
}
