<?php
declare(strict_types=1);
namespace SymbolSdk\Transaction;

final class MosaicGlobalRestrictionTransaction extends AbstractTransaction
{
    public const TRANSACTION_VERSION = 1;
    public const TRANSACTION_TYPE = 0x4151;

    public const MOSAIC_RESTRICTION_TYPE_NONE = 0;
    public const MOSAIC_RESTRICTION_TYPE_EQ = 1;
    public const MOSAIC_RESTRICTION_TYPE_NE = 2;
    public const MOSAIC_RESTRICTION_TYPE_LT = 3;
    public const MOSAIC_RESTRICTION_TYPE_LE = 4;
    public const MOSAIC_RESTRICTION_TYPE_GT = 5;
    public const MOSAIC_RESTRICTION_TYPE_GE = 6;

    public readonly string $mosaicIdDec;
    public readonly string $referenceMosaicIdDec;
    public readonly string $restrictionKeyDec;
    public readonly string $previousRestrictionValueDec;
    public readonly string $newRestrictionValueDec;
    public readonly int $previousRestrictionType;
    public readonly int $newRestrictionType;

    /**
     * @param string $mosaicIdDec
     * @param string $referenceMosaicIdDec
     * @param string $restrictionKeyDec
     * @param string $previousRestrictionValueDec
     * @param string $newRestrictionValueDec
     * @param int $previousRestrictionType
     * @param int $newRestrictionType
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
        string $referenceMosaicIdDec,
        string $restrictionKeyDec,
        string $previousRestrictionValueDec,
        string $newRestrictionValueDec,
        int $previousRestrictionType,
        int $newRestrictionType,
        string $headerRaw,
        int $size,
        int $version,
        int $network,
        int $type,
        string $maxFeeDec,
        string $deadlineDec
    ) {
        if (preg_match('/^[0-9]+$/', $mosaicIdDec) !== 1) {
            throw new \InvalidArgumentException('mosaicIdDec must be a decimal string');
        }
        if (preg_match('/^[0-9]+$/', $referenceMosaicIdDec) !== 1) {
            throw new \InvalidArgumentException('referenceMosaicIdDec must be a decimal string');
        }
        if (preg_match('/^[0-9]+$/', $restrictionKeyDec) !== 1) {
            throw new \InvalidArgumentException('restrictionKeyDec must be a decimal string');
        }
        if (preg_match('/^[0-9]+$/', $previousRestrictionValueDec) !== 1) {
            throw new \InvalidArgumentException('previousRestrictionValueDec must be a decimal string');
        }
        if (preg_match('/^[0-9]+$/', $newRestrictionValueDec) !== 1) {
            throw new \InvalidArgumentException('newRestrictionValueDec must be a decimal string');
        }
        if (!in_array($previousRestrictionType, [
            self::MOSAIC_RESTRICTION_TYPE_NONE,
            self::MOSAIC_RESTRICTION_TYPE_EQ,
            self::MOSAIC_RESTRICTION_TYPE_NE,
            self::MOSAIC_RESTRICTION_TYPE_LT,
            self::MOSAIC_RESTRICTION_TYPE_LE,
            self::MOSAIC_RESTRICTION_TYPE_GT,
            self::MOSAIC_RESTRICTION_TYPE_GE
        ], true)) {
            throw new \InvalidArgumentException('previousRestrictionType out of range');
        }
        if (!in_array($newRestrictionType, [
            self::MOSAIC_RESTRICTION_TYPE_NONE,
            self::MOSAIC_RESTRICTION_TYPE_EQ,
            self::MOSAIC_RESTRICTION_TYPE_NE,
            self::MOSAIC_RESTRICTION_TYPE_LT,
            self::MOSAIC_RESTRICTION_TYPE_LE,
            self::MOSAIC_RESTRICTION_TYPE_GT,
            self::MOSAIC_RESTRICTION_TYPE_GE
        ], true)) {
            throw new \InvalidArgumentException('newRestrictionType out of range');
        }
        $this->mosaicIdDec = $mosaicIdDec;
        $this->referenceMosaicIdDec = $referenceMosaicIdDec;
        $this->restrictionKeyDec = $restrictionKeyDec;
        $this->previousRestrictionValueDec = $previousRestrictionValueDec;
        $this->newRestrictionValueDec = $newRestrictionValueDec;
        $this->previousRestrictionType = $previousRestrictionType;
        $this->newRestrictionType = $newRestrictionType;
        parent::__construct($headerRaw, $size, $version, $network, $type, $maxFeeDec, $deadlineDec);
    }

    public static function fromBinary(string $binary): self
    {
        $h = self::parseHeader($binary);
        $offset = $h['offset'];
        $len = strlen($binary);

        // 1. mosaicId (u64)
        if ($len - $offset < 8) {
            throw new \RuntimeException('Unexpected EOF: need 8 bytes for mosaicId');
        }
        $mosaicIdDec = self::readU64LEDecAt($binary, $offset);
        $offset += 8;

        // 2. referenceMosaicId (u64)
        if ($len - $offset < 8) {
            throw new \RuntimeException('Unexpected EOF: need 8 bytes for referenceMosaicId');
        }
        $referenceMosaicIdDec = self::readU64LEDecAt($binary, $offset);
        $offset += 8;

        // 3. restrictionKey (u64)
        if ($len - $offset < 8) {
            throw new \RuntimeException('Unexpected EOF: need 8 bytes for restrictionKey');
        }
        $restrictionKeyDec = self::readU64LEDecAt($binary, $offset);
        $offset += 8;

        // 4. previousRestrictionValue (u64)
        if ($len - $offset < 8) {
            throw new \RuntimeException('Unexpected EOF: need 8 bytes for previousRestrictionValue');
        }
        $previousRestrictionValueDec = self::readU64LEDecAt($binary, $offset);
        $offset += 8;

        // 5. newRestrictionValue (u64)
        if ($len - $offset < 8) {
            throw new \RuntimeException('Unexpected EOF: need 8 bytes for newRestrictionValue');
        }
        $newRestrictionValueDec = self::readU64LEDecAt($binary, $offset);
        $offset += 8;

        // 6. previousRestrictionType (u8)
        if ($len - $offset < 1) {
            throw new \RuntimeException('Unexpected EOF: need 1 byte for previousRestrictionType');
        }
        $chunk = substr($binary, $offset, 1);
        if (strlen($chunk) !== 1) {
            throw new \RuntimeException('Unexpected EOF: need 1 byte for previousRestrictionType');
        }
        $previousRestrictionType = ord($chunk);
        $offset += 1;

        // 7. newRestrictionType (u8)
        if ($len - $offset < 1) {
            throw new \RuntimeException('Unexpected EOF: need 1 byte for newRestrictionType');
        }
        $chunk = substr($binary, $offset, 1);
        if (strlen($chunk) !== 1) {
            throw new \RuntimeException('Unexpected EOF: need 1 byte for newRestrictionType');
        }
        $newRestrictionType = ord($chunk);
        $offset += 1;

        return new self(
            $mosaicIdDec,
            $referenceMosaicIdDec,
            $restrictionKeyDec,
            $previousRestrictionValueDec,
            $newRestrictionValueDec,
            $previousRestrictionType,
            $newRestrictionType,
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
     *   mosaicIdDec:string,
     *   referenceMosaicIdDec:string,
     *   restrictionKeyDec:string,
     *   previousRestrictionValueDec:string,
     *   newRestrictionValueDec:string,
     *   previousRestrictionType:int,
     *   newRestrictionType:int,
     *   offset:int
     * }
     */
    protected static function decodeBody(string $binary, int $offset): array
    {
        $len = strlen($binary);

        if ($len - $offset < 8) {
            throw new \RuntimeException('Unexpected EOF: need 8 bytes for mosaicId');
        }
        $mosaicIdDec = self::readU64LEDecAt($binary, $offset);
        $offset += 8;

        if ($len - $offset < 8) {
            throw new \RuntimeException('Unexpected EOF: need 8 bytes for referenceMosaicId');
        }
        $referenceMosaicIdDec = self::readU64LEDecAt($binary, $offset);
        $offset += 8;

        if ($len - $offset < 8) {
            throw new \RuntimeException('Unexpected EOF: need 8 bytes for restrictionKey');
        }
        $restrictionKeyDec = self::readU64LEDecAt($binary, $offset);
        $offset += 8;

        if ($len - $offset < 8) {
            throw new \RuntimeException('Unexpected EOF: need 8 bytes for previousRestrictionValue');
        }
        $previousRestrictionValueDec = self::readU64LEDecAt($binary, $offset);
        $offset += 8;

        if ($len - $offset < 8) {
            throw new \RuntimeException('Unexpected EOF: need 8 bytes for newRestrictionValue');
        }
        $newRestrictionValueDec = self::readU64LEDecAt($binary, $offset);
        $offset += 8;

        if ($len - $offset < 1) {
            throw new \RuntimeException('Unexpected EOF: need 1 byte for previousRestrictionType');
        }
        $chunk = substr($binary, $offset, 1);
        if (strlen($chunk) !== 1) {
            throw new \RuntimeException('Unexpected EOF: need 1 byte for previousRestrictionType');
        }
        $previousRestrictionType = ord($chunk);
        $offset += 1;

        if ($len - $offset < 1) {
            throw new \RuntimeException('Unexpected EOF: need 1 byte for newRestrictionType');
        }
        $chunk = substr($binary, $offset, 1);
        if (strlen($chunk) !== 1) {
            throw new \RuntimeException('Unexpected EOF: need 1 byte for newRestrictionType');
        }
        $newRestrictionType = ord($chunk);
        $offset += 1;

        return [
            'mosaicIdDec' => $mosaicIdDec,
            'referenceMosaicIdDec' => $referenceMosaicIdDec,
            'restrictionKeyDec' => $restrictionKeyDec,
            'previousRestrictionValueDec' => $previousRestrictionValueDec,
            'newRestrictionValueDec' => $newRestrictionValueDec,
            'previousRestrictionType' => $previousRestrictionType,
            'newRestrictionType' => $newRestrictionType,
            'offset' => $offset
        ];
    }

    protected function encodeBody(): string
    {
        $out = '';
        $out .= self::u64LE($this->mosaicIdDec);
        $out .= self::u64LE($this->referenceMosaicIdDec);
        $out .= self::u64LE($this->restrictionKeyDec);
        $out .= self::u64LE($this->previousRestrictionValueDec);
        $out .= self::u64LE($this->newRestrictionValueDec);
        $out .= chr($this->previousRestrictionType);
        $out .= chr($this->newRestrictionType);
        return $out;
    }
}
