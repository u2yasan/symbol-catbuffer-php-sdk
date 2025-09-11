<?php
declare(strict_types=1);

namespace SymbolSdk\Transaction;

final class MosaicDefinitionTransaction extends AbstractTransaction
{
    public function __construct(
        public readonly int $nonce,
        public readonly string $mosaicIdDec,
        public readonly int $flags,
        public readonly int $divisibility,
        public readonly ?string $durationDec,
        // header fields:
        string $headerRaw, int $size, int $version, int $network, int $type, string $maxFeeDec, string $deadlineDec
    ) {
        parent::__construct($headerRaw, $size, $version, $network, $type, $maxFeeDec, $deadlineDec);
    }

    public static function fromBinary(string $binary): self
    {
        $h = self::parseHeader($binary);
        $offset = $h['offset']; $len = strlen($binary);
        $remaining = $len - $offset;

        if ($remaining < 4) throw new \RuntimeException('EOF nonce');
        $nonce = self::u32At($binary, $offset); $offset += 4; $remaining -= 4;

        if ($remaining < 8) throw new \RuntimeException('EOF mosaicId');
        $mosaicIdDec = self::u64DecAt($binary, $offset); $offset += 8; $remaining -= 8;

        if ($remaining < 1) throw new \RuntimeException('EOF flags');
        $flags = ord($binary[$offset]); $offset += 1; $remaining -= 1;

        if ($remaining < 1) throw new \RuntimeException('EOF divisibility');
        $div = ord($binary[$offset]); $offset += 1; $remaining -= 1;

        $durationDec = null;
        if ($remaining >= 8) {
            $durationDec = self::u64DecAt($binary, $offset); $offset += 8; $remaining -= 8;
        }

        return new self(
            $nonce, $mosaicIdDec, $flags, $div, $durationDec,
            $h['headerRaw'], $h['size'], $h['version'], $h['network'], $h['type'], $h['maxFeeDec'], $h['deadlineDec']
        );
    }

    protected static function decodeBody(string $binary, int $offset): array { return []; }

    protected function encodeBody(): string
    {
        $body = pack('V', $this->nonce);
        $body .= self::u64LE($this->mosaicIdDec);
        $body .= chr($this->flags);
        $body .= chr($this->divisibility);
        if ($this->durationDec !== null) {
            $body .= self::u64LE($this->durationDec);
        }
        return $body;
    }
}