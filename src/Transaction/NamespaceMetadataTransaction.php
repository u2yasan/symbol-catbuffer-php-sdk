<?php
declare(strict_types=1);
namespace SymbolSdk\Transaction;

final class NamespaceMetadataTransaction extends AbstractTransaction
{
    public string $targetAddress;
    public string $scopedMetadataKeyDec;
    public string $targetNamespaceIdDec;
    public int $valueSizeDelta;
    public string $value;

    public function __construct(
        string $targetAddress,
        string $scopedMetadataKeyDec,
        string $targetNamespaceIdDec,
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
        if ($scopedMetadataKeyDec === '' || preg_match('/^[0-9]+$/', $scopedMetadataKeyDec) === 0) {
            throw new \InvalidArgumentException('scopedMetadataKeyDec must be a non-empty decimal string');
        }
        if ($targetNamespaceIdDec === '' || preg_match('/^[0-9]+$/', $targetNamespaceIdDec) === 0) {
            throw new \InvalidArgumentException('targetNamespaceIdDec must be a non-empty decimal string');
        }
        if ($valueSizeDelta < -32768 || $valueSizeDelta > 32767) {
            throw new \InvalidArgumentException('valueSizeDelta out of int16 range');
        }
        if (strlen($value) > 65535) {
            throw new \InvalidArgumentException('value too long (max 65535 bytes)');
        }
        parent::__construct($headerRaw, $size, $version, $network, $type, $maxFeeDec, $deadlineDec);
        $this->targetAddress = $targetAddress;
        $this->scopedMetadataKeyDec = $scopedMetadataKeyDec;
        $this->targetNamespaceIdDec = $targetNamespaceIdDec;
        $this->valueSizeDelta = $valueSizeDelta;
        $this->value = $value;
    }

    /**
     * @param string $binary
     * @return self
     */
    public static function fromBinary(string $binary): self
    {
        $h = self::parseHeader($binary);
        $body = self::decodeBody($binary, $h['offset']);
        return new self(
            $body['targetAddress'],
            $body['scopedMetadataKeyDec'],
            $body['targetNamespaceIdDec'],
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
     * @param string $binary
     * @param int $offset
     * @return array{
     *   targetAddress:string,
     *   scopedMetadataKeyDec:string,
     *   targetNamespaceIdDec:string,
     *   valueSizeDelta:int,
     *   value:string,
     *   offset:int
     * }
     */
    protected static function decodeBody(string $binary, int $offset): array
    {
        $remaining = strlen($binary) - $offset;
        if ($remaining < 24) {
            throw new \RuntimeException("Unexpected EOF: need 24 bytes for targetAddress, have {$remaining}");
        }
        $targetAddress = substr($binary, $offset, 24);
        $offset += 24;

        $remaining = strlen($binary) - $offset;
        if ($remaining < 8) {
            throw new \RuntimeException("Unexpected EOF: need 8 bytes for scopedMetadataKey, have {$remaining}");
        }
        $scopedMetadataKeyDec = self::readU64LEDecAt($binary, $offset);
        $offset += 8;

        $remaining = strlen($binary) - $offset;
        if ($remaining < 8) {
            throw new \RuntimeException("Unexpected EOF: need 8 bytes for targetNamespaceId, have {$remaining}");
        }
        $targetNamespaceIdDec = self::readU64LEDecAt($binary, $offset);
        $offset += 8;

        $remaining = strlen($binary) - $offset;
        if ($remaining < 2) {
            throw new \RuntimeException("Unexpected EOF: need 2 bytes for valueSizeDelta, have {$remaining}");
        }
        $chunk = substr($binary, $offset, 2);
        if (strlen($chunk) !== 2) {
            throw new \RuntimeException('Unexpected EOF (need 2 bytes for valueSizeDelta)');
        }
        $u = unpack('vs', $chunk);
        if ($u === false) {
            throw new \RuntimeException('unpack failed for valueSizeDelta');
        }
        /** @var array{s:int} $u */
        $valueSizeDelta = $u['s'];
        $offset += 2;

        $remaining = strlen($binary) - $offset;
        if ($remaining < 2) {
            throw new \RuntimeException("Unexpected EOF: need 2 bytes for valueSize, have {$remaining}");
        }
        $chunk = substr($binary, $offset, 2);
        if (strlen($chunk) !== 2) {
            throw new \RuntimeException('Unexpected EOF (need 2 bytes for valueSize)');
        }
        $u = unpack('vsize', $chunk);
        if ($u === false) {
            throw new \RuntimeException('unpack failed for valueSize');
        }
        /** @var array{size:int} $u */
        $valueSize = $u['size'];
        $offset += 2;

        $remaining = strlen($binary) - $offset;
        if ($remaining < $valueSize) {
            throw new \RuntimeException("Unexpected EOF: need {$valueSize} bytes for value, have {$remaining}");
        }
        $value = substr($binary, $offset, $valueSize);
        $offset += $valueSize;

        return [
            'targetAddress' => $targetAddress,
            'scopedMetadataKeyDec' => $scopedMetadataKeyDec,
            'targetNamespaceIdDec' => $targetNamespaceIdDec,
            'valueSizeDelta' => $valueSizeDelta,
            'value' => $value,
            'offset' => $offset
        ];
    }

    /**
     * @return string
     */
    protected function encodeBody(): string
    {
        $out = $this->targetAddress;
        $out .= self::u64LE($this->scopedMetadataKeyDec);
        $out .= self::u64LE($this->targetNamespaceIdDec);
        $out .= pack('v', $this->valueSizeDelta & 0xFFFF);
        $out .= pack('v', strlen($this->value));
        $out .= $this->value;
        return $out;
    }
}
