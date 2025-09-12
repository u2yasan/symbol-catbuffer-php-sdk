<?php
declare(strict_types=1);
namespace SymbolSdk\Transaction;

final class TransferTransaction extends AbstractTransaction
{
    public readonly string $recipientAddress;
    /** @var list<array{mosaicIdDec:string, amountDec:string}> */
    public readonly array $mosaics;
    public readonly string $message;

    /**
     * @param list<array{mosaicIdDec:string, amountDec:string}> $mosaics
     */
    public function __construct(
        string $recipientAddress,
        array $mosaics,
        string $message,
        string $headerRaw,
        int $size,
        int $version,
        int $network,
        int $type,
        string $maxFeeDec,
        string $deadlineDec
    ) {
        if (strlen($recipientAddress) !== 24) {
            throw new \InvalidArgumentException('recipientAddress must be 24 bytes');
        }
        if (strlen($message) > 65535) {
            throw new \InvalidArgumentException('message too long (max 65535)');
        }
        if (count($mosaics) > 255) {
            throw new \InvalidArgumentException('mosaics count exceeds 255');
        }
        /** @var list<array{mosaicIdDec:string, amountDec:string}> $mosaics */
        $ids = [];
        foreach ($mosaics as $i => $mosaic) {
            // 値が文字列かつ 10進数のみ（u64 の decimal-string）を検証
            $id  = $mosaic['mosaicIdDec'];
            $amt = $mosaic['amountDec'];

            if (!is_string($id) || $id === '' || !preg_match('/^\d+$/', $id)) {
                throw new \InvalidArgumentException("mosaics[$i].mosaicIdDec must be a non-empty decimal string");
            }
            if (!is_string($amt) || $amt === '' || !preg_match('/^\d+$/', $amt)) {
                throw new \InvalidArgumentException("mosaics[$i].amountDec must be a non-empty decimal string");
            }

            // 重複ID検出（こちらの isset は OK：$ids はこの関数内で作るマップなので可）
            if (isset($ids[$id])) {
                throw new \InvalidArgumentException("Duplicate mosaicIdDec in mosaics: {$id}");
            }
            $ids[$id] = true;
        }
        $this->recipientAddress = $recipientAddress;
        /** @var list<array{mosaicIdDec:string, amountDec:string}> $mosaics */
        $this->mosaics = $mosaics;
        $this->message = $message;
        parent::__construct($headerRaw, $size, $version, $network, $type, $maxFeeDec, $deadlineDec);
    }

    public static function fromBinary(string $binary): self
    {
        $h = self::parseHeader($binary);
        $off = $h['offset'];
        $len = strlen($binary);

        // recipient_address: 24 bytes
        if ($len - $off < 24) {
            throw new \RuntimeException('Unexpected EOF: need 24 bytes for recipientAddress');
        }
        $recipientAddress = substr($binary, $off, 24);
        $off += 24;

        // message_size: uint16 LE
        if ($len - $off < 2) {
            throw new \RuntimeException('Unexpected EOF: need 2 bytes for message_size');
        }
        $chunk = substr($binary, $off, 2);
        $u = unpack('vsize', $chunk);
        if ($u === false) {
            throw new \RuntimeException('unpack failed for message_size');
        }
        /** @var array{size:int} $u */
        $messageSize = $u['size'];
        $off += 2;

        // mosaics_count: uint8
        if ($len - $off < 1) {
            throw new \RuntimeException('Unexpected EOF: need 1 byte for mosaics_count');
        }
        $mosaicsCount = ord($binary[$off]);
        $off += 1;

        // reserved1: uint8
        if ($len - $off < 1) {
            throw new \RuntimeException('Unexpected EOF: need 1 byte for reserved1');
        }
        $off += 1;

        // reserved2: uint32
        if ($len - $off < 4) {
            throw new \RuntimeException('Unexpected EOF: need 4 bytes for reserved2');
        }
        $off += 4;

        // mosaics: array of {id:u64, amount:u64}
        /** @var list<array{mosaicIdDec:string, amountDec:string}> $mosaics */
        $mosaics = [];
        $ids = [];
        for ($i = 0; $i < $mosaicsCount; $i++) {
            if ($len - $off < 16) {
                throw new \RuntimeException("Unexpected EOF: need 16 bytes for mosaic[$i]");
            }
            $mosaicIdDec = self::u64DecAt($binary, $off);
            $amountDec = self::u64DecAt($binary, $off + 8);
            if (isset($ids[$mosaicIdDec])) {
                throw new \InvalidArgumentException("Duplicate mosaicIdDec in mosaics: {$mosaicIdDec}");
            }
            $ids[$mosaicIdDec] = true;
            $mosaics[] = [
                'mosaicIdDec' => $mosaicIdDec,
                'amountDec' => $amountDec,
            ];
            $off += 16;
        }

        // message: messageSize bytes
        if ($len - $off < $messageSize) {
            throw new \RuntimeException("Unexpected EOF: need {$messageSize} bytes for message");
        }
        $message = substr($binary, $off, $messageSize);
        $off += $messageSize;

        return new self(
            $recipientAddress,
            $mosaics,
            $message,
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
        $out = $this->recipientAddress;
        $messageLen = strlen($this->message);
        if ($messageLen > 65535) {
            throw new \InvalidArgumentException('message too long (max 65535)');
        }
        $out .= pack('v', $messageLen);

        $mosaicsCount = count($this->mosaics);
        if ($mosaicsCount > 255) {
            throw new \InvalidArgumentException('mosaics count exceeds 255');
        }
        $out .= chr($mosaicsCount);

        // reserved1: uint8 (0)
        $out .= "\x00";
        // reserved2: uint32 (0)
        $out .= "\x00\x00\x00\x00";

        $ids = [];
        foreach ($this->mosaics as $i => $mosaic) {
            $mosaicIdDec = $mosaic['mosaicIdDec'];
            $amountDec = $mosaic['amountDec'];
            if (isset($ids[$mosaicIdDec])) {
                throw new \InvalidArgumentException("Duplicate mosaicIdDec in mosaics: {$mosaicIdDec}");
            }
            $ids[$mosaicIdDec] = true;
            $out .= self::u64LE($mosaicIdDec);
            $out .= self::u64LE($amountDec);
        }
        $out .= $this->message;
        return $out;
    }

    /**
     * @param string $binary
     * @param int $offset
     * @return array<string,mixed>
     */
    protected static function decodeBody(string $binary, int $offset): array
    {
        // Not used in this implementation.
        return [];
    }
}
