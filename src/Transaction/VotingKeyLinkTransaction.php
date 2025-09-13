<?php
declare(strict_types=1);
namespace SymbolSdk\Transaction;

final class VotingKeyLinkTransaction extends AbstractTransaction
{
    public readonly string $linkedPublicKey;
    public readonly int $startEpoch;
    public readonly int $endEpoch;
    public readonly int $linkAction;

    /**
     * @param string $linkedPublicKey 32 bytes
     * @param int $startEpoch uint32
     * @param int $endEpoch uint32
     * @param int $linkAction 0 or 1
     * @param string $headerRaw
     * @param int $size
     * @param int $version
     * @param int $network
     * @param int $type
     * @param string $maxFeeDec
     * @param string $deadlineDec
     */
    public function __construct(
        string $linkedPublicKey,
        int $startEpoch,
        int $endEpoch,
        int $linkAction,
        string $headerRaw,
        int $size,
        int $version,
        int $network,
        int $type,
        string $maxFeeDec,
        string $deadlineDec
    ) {
        if (strlen($linkedPublicKey) !== 32) {
            throw new \InvalidArgumentException('linkedPublicKey must be 32 bytes');
        }
        if ($startEpoch < 0 || $startEpoch > 0xFFFFFFFF) {
            throw new \InvalidArgumentException('startEpoch out of range');
        }
        if ($endEpoch < 0 || $endEpoch > 0xFFFFFFFF) {
            throw new \InvalidArgumentException('endEpoch out of range');
        }
        if ($linkAction !== 0 && $linkAction !== 1) {
            throw new \InvalidArgumentException('linkAction must be 0 or 1');
        }
        $this->linkedPublicKey = $linkedPublicKey;
        $this->startEpoch = $startEpoch;
        $this->endEpoch = $endEpoch;
        $this->linkAction = $linkAction;
        parent::__construct($headerRaw, $size, $version, $network, $type, $maxFeeDec, $deadlineDec);
    }

    public static function fromBinary(string $binary): self
    {
        $h = self::parseHeader($binary);
        $offset = $h['offset'];
        $len = strlen($binary);

        // linkedPublicKey: 32 bytes
        if ($len - $offset < 32) {
            throw new \RuntimeException('Unexpected EOF: need 32 bytes for linkedPublicKey');
        }
        $linkedPublicKey = substr($binary, $offset, 32);
        $offset += 32;

        // startEpoch: uint32 LE
        if ($len - $offset < 4) {
            throw new \RuntimeException('Unexpected EOF: need 4 bytes for startEpoch');
        }
        $startEpoch = self::readU32LEAt($binary, $offset);
        $offset += 4;

        // endEpoch: uint32 LE
        if ($len - $offset < 4) {
            throw new \RuntimeException('Unexpected EOF: need 4 bytes for endEpoch');
        }
        $endEpoch = self::readU32LEAt($binary, $offset);
        $offset += 4;

        // linkAction: u8
        if ($len - $offset < 1) {
            throw new \RuntimeException('Unexpected EOF: need 1 byte for linkAction');
        }
        $linkAction = ord($binary[$offset]);
        $offset += 1;
        if ($linkAction !== 0 && $linkAction !== 1) {
            throw new \InvalidArgumentException('linkAction must be 0 or 1');
        }

        return new self(
            $linkedPublicKey,
            $startEpoch,
            $endEpoch,
            $linkAction,
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
     * @internal
     */
    protected function encodeBody(): string
    {
        return
            $this->linkedPublicKey .
            self::u32LE($this->startEpoch) .
            self::u32LE($this->endEpoch) .
            chr($this->linkAction);
    }

    /**
     * @internal
     * @param string $binary
     * @param int $offset
     * @return array{linkedPublicKey:string, startEpoch:int, endEpoch:int, linkAction:int, offset:int}
     */
    protected static function decodeBody(string $binary, int $offset): array
    {
        $len = strlen($binary);

        if ($len - $offset < 32) {
            throw new \RuntimeException('Unexpected EOF: need 32 bytes for linkedPublicKey');
        }
        $linkedPublicKey = substr($binary, $offset, 32);
        $offset += 32;

        if ($len - $offset < 4) {
            throw new \RuntimeException('Unexpected EOF: need 4 bytes for startEpoch');
        }
        $startEpoch = self::readU32LEAt($binary, $offset);
        $offset += 4;

        if ($len - $offset < 4) {
            throw new \RuntimeException('Unexpected EOF: need 4 bytes for endEpoch');
        }
        $endEpoch = self::readU32LEAt($binary, $offset);
        $offset += 4;

        if ($len - $offset < 1) {
            throw new \RuntimeException('Unexpected EOF: need 1 byte for linkAction');
        }
        $linkAction = ord($binary[$offset]);
        $offset += 1;

        return [
            'linkedPublicKey' => $linkedPublicKey,
            'startEpoch' => $startEpoch,
            'endEpoch' => $endEpoch,
            'linkAction' => $linkAction,
            'offset' => $offset
        ];
    }

    /**
     * @internal
     */
    protected static function readU32LEAt(string $bin, int $offset): int
    {
        $chunk = substr($bin, $offset, 4);
        if (strlen($chunk) !== 4) {
            throw new \RuntimeException('EOF u32');
        }
        $a = unpack('Vval', $chunk);
        if ($a === false) {
            throw new \RuntimeException('unpack failed');
        }
        return (int)$a['val'];
    }

    /**
     * @internal
     */
    private static function u32LE(int $v): string
    {
        if ($v < 0 || $v > 0xFFFFFFFF) {
            throw new \InvalidArgumentException('u32 out of range');
        }
        return pack('V', $v);
    }
}
