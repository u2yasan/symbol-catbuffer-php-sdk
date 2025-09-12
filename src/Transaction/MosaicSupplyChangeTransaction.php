<?php
declare(strict_types=1);
namespace SymbolSdk\Transaction;

final class MosaicSupplyChangeTransaction extends AbstractTransaction
{
    public readonly string $mosaicIdDec;
    public readonly int $action;
    public readonly string $deltaDec;

    public function __construct(
        string $mosaicIdDec,
        int $action,
        string $deltaDec,
        string $headerRaw,
        int $size,
        int $version,
        int $network,
        int $type,
        string $maxFeeDec,
        string $deadlineDec
    ) {
        if (!preg_match('/^[0-9]+$/', $mosaicIdDec)) {
            throw new \InvalidArgumentException('mosaicIdDec must be a decimal string');
        }
        if ($action !== 0 && $action !== 1) {
            throw new \InvalidArgumentException('action must be 0 or 1');
        }
        if (!preg_match('/^[0-9]+$/', $deltaDec)) {
            throw new \InvalidArgumentException('deltaDec must be a decimal string');
        }
        $this->mosaicIdDec = $mosaicIdDec;
        $this->action = $action;
        $this->deltaDec = $deltaDec;
        parent::__construct($headerRaw, $size, $version, $network, $type, $maxFeeDec, $deadlineDec);
    }

    public static function fromBinary(string $binary): self
    {
        $h = self::parseHeader($binary);
        $offset = $h['offset'];
        $len = strlen($binary);

        // mosaic_id:u64
        if ($len - $offset < 8) {
            throw new \RuntimeException('Unexpected EOF: need 8 bytes for mosaic_id');
        }
        $mosaicIdDec = self::u64DecAt($binary, $offset);
        $offset += 8;

        // action:u8
        if ($len - $offset < 1) {
            throw new \RuntimeException('Unexpected EOF: need 1 byte for action');
        }
        $action = ord($binary[$offset]);
        $offset += 1;
        if ($action !== 0 && $action !== 1) {
            throw new \InvalidArgumentException('action must be 0 or 1');
        }

        // delta:u64
        if ($len - $offset < 8) {
            throw new \RuntimeException('Unexpected EOF: need 8 bytes for delta');
        }
        $deltaDec = self::u64DecAt($binary, $offset);
        $offset += 8;

        return new self(
            $mosaicIdDec,
            $action,
            $deltaDec,
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
     * @return string
     */
    protected function encodeBody(): string
    {
        $body = '';
        $body .= self::u64LE($this->mosaicIdDec);
        $body .= chr($this->action);
        $body .= self::u64LE($this->deltaDec);
        return $body;
    }

    /**
     * @param string $binary
     * @param int $offset
     * @return array<string, mixed>
     */
    protected static function decodeBody(string $binary, int $offset): array
    {
        // Not used in this implementation, but required by base class.
        return [];
    }
}
