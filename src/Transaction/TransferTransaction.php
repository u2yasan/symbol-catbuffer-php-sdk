<?php
declare(strict_types=1);

namespace SymbolSdk\Transaction;

final class TransferTransaction extends AbstractTransaction
{
    /** @var list<Mosaic> */
    private array $mosaics;
    /**
     * @param list<Mosaic> $mosaics
     */
    public function __construct(
        public readonly string $recipient24,
        array $mosaics,
        public readonly string $message,
        // header fields:
        string $headerRaw, int $size, int $version, int $network, int $type, string $maxFeeDec, string $deadlineDec
    ) {
        parent::__construct($headerRaw, $size, $version, $network, $type, $maxFeeDec, $deadlineDec);
        /** @var list<Mosaic> $mosaics */
        $this->mosaics = $mosaics;
    }

    public static function fromBinary(string $binary): self
    {
        $h = self::parseHeader($binary);
        $offset = $h['offset']; $len = strlen($binary);

        if ($len < $offset + 24) throw new \RuntimeException('EOF recipient');
        $recipient = substr($binary, $offset, 24);
        $offset += 24;

        if ($len < $offset + 2) throw new \RuntimeException('EOF messageSize');
        $messageSize = self::u16At($binary, $offset); $offset += 2;

        if ($len < $offset + 1) throw new \RuntimeException('EOF mosaicsCount');
        $mosaicsCount = ord($binary[$offset]); $offset += 1;

        if ($len < $offset + 1) throw new \RuntimeException('EOF reserved1');
        $offset += 1;

        if ($len < $offset + 4) throw new \RuntimeException('EOF reserved2');
        $offset += 4;

        $needForMosaics = $mosaicsCount * 16;
        if ($len < $offset + $needForMosaics) {
            throw new \RuntimeException('EOF mosaics array');
        }
        /** @var list<Mosaic> $mosaics */
        $mosaics = [];
        for ($i = 0; $i < $mosaicsCount; $i++) {
            $mid = self::u64DecAt($binary, $offset); $offset += 8;
            $amt = self::u64DecAt($binary, $offset); $offset += 8;
            $mosaics[] = new Mosaic($mid, $amt);
        }

        if ($len < $offset + $messageSize) {
            throw new \RuntimeException("Unexpected EOF while reading message: need {$messageSize}, have " . ($len - $offset));
        }
        $message = $messageSize > 0 ? substr($binary, $offset, $messageSize) : '';
        $offset += $messageSize;

        return new self(
            $recipient, $mosaics, $message,
            $h['headerRaw'], $h['size'], $h['version'], $h['network'], $h['type'], $h['maxFeeDec'], $h['deadlineDec']
        );
    }

    protected static function decodeBody(string $binary, int $offset): array
    {
        // 未使用（上で実装済）— 他 Tx とインタフェースを合わせるために残すだけ
        return [];
    }

    protected function encodeBody(): string
    {
        $body  = $this->recipient24;
        $body .= pack('v', strlen($this->message));     // u16LE
        $body .= chr(count($this->mosaics));            // u8
        $body .= chr(0);                                // reserved1 u8
        $body .= pack('V', 0);                          // reserved2 u32
        foreach ($this->mosaics as $m) {
            $body .= self::u64LE($m->mosaicIdDec);
            $body .= self::u64LE($m->amountDec);
        }
        $body .= $this->message;
        return $body;
    }
}

/** 値オブジェクト */
final class Mosaic
{
    public function __construct(
        public readonly string $mosaicIdDec,
        public readonly string $amountDec
    ) {}
}