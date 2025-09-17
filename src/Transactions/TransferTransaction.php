<?php

declare(strict_types=1);

namespace SymbolSdk\Transactions;

/**
 * TransferTransaction
 * - 共通ヘッダ128B対応
 * - body: recipient(24) / messageSize(u16) / mosaicsCount(u8) / reserved(u8) / mosaics[] / message(type+payload)
 * - u64 は 10進文字列 (…Dec) で保持
 *
 * 依存:
 * - 親 AbstractTransaction の parseHeader / readU16LEAt / readU32LEAt / readU64LEDecAt / u64LE / serialize() を使用
 */
final class TransferTransaction extends AbstractTransaction
{
    /** @var string 受信者 UnresolvedAddress（24バイト固定） */
    private string $recipient;

    /** @var list<array{mosaicIdDec:string, amountDec:string}> */
    private array $mosaics;

    /** @var int MessageType (0..255) */
    private int $messageType;

    /** @var string メッセージのペイロード（生バイト列） */
    private string $message;

    /**
     * @param list<array{mosaicIdDec:string, amountDec:string}> $mosaics
     */
    public function __construct(
        string $recipient,
        array $mosaics,
        int $messageType,
        string $message,
        string $headerRaw,
        int $size,
        int $version,
        int $network,
        int $type,
        string $maxFeeDec,
        string $deadlineDec,
    ) {
        if (24 !== \strlen($recipient)) {
            throw new \InvalidArgumentException('recipient must be 24 bytes (UnresolvedAddress)');
        }

        if ($messageType < 0 || $messageType > 255) {
            throw new \InvalidArgumentException('messageType must be 0..255');
        }

        // mosaics の型&値域を正規化（param で list<array{mosaicIdDec:string, amountDec:string}> を受領しているため
        // array_values() や isset() は不要。長さ・形式のみ検証する）
        /** @var list<array{mosaicIdDec:string, amountDec:string}> $normalized */
        $normalized = [];

        foreach ($mosaics as $i => $mosaic) {
            $mid = $mosaic['mosaicIdDec'];
            $amt = $mosaic['amountDec'];

            if (1 !== \preg_match('/^[0-9]+$/', $mid)) {
                throw new \InvalidArgumentException("mosaics[$i].mosaicIdDec must be decimal string");
            }

            if (1 !== \preg_match('/^[0-9]+$/', $amt)) {
                throw new \InvalidArgumentException("mosaics[$i].amountDec must be decimal string");
            }
            $mid = '' === \ltrim($mid, '0') ? '0' : \ltrim($mid, '0');
            $amt = '' === \ltrim($amt, '0') ? '0' : \ltrim($amt, '0');
            $normalized[] = ['mosaicIdDec' => $mid, 'amountDec' => $amt];
        }

        if (\strlen($message) > 0xFFFF - 1) { // messageSize = 1(type) + payload
            throw new \InvalidArgumentException('message payload too large (max 65534 bytes)');
        }

        parent::__construct($headerRaw, $size, $version, $network, $type, $maxFeeDec, $deadlineDec);

        $this->recipient = $recipient;
        $this->mosaics = $normalized;
        $this->messageType = $messageType;
        $this->message = $message;
    }

    /**
     * ヘッダ＋ボディの完全なバイナリから復元.
     */
    public static function fromBinary(string $binary): self
    {
        $h = self::parseHeader($binary);
        $offset = $h['offset'];
        $len = \strlen($binary);

        // 最低限必要: recipient(24) + messageSize(2) + mosaicsCount(1) + reserved(1)
        if ($len < $offset + 24 + 2 + 1 + 1) {
            $need = ($offset + 28) - $len;
            throw new \RuntimeException("Unexpected EOF while reading Transfer body header: need {$need} more bytes");
        }

        // recipient (24)
        $recipient = \substr($binary, $offset, 24);

        if (24 !== \strlen($recipient)) {
            throw new \RuntimeException('Unexpected EOF while reading recipient');
        }
        $offset += 24;

        // messageSize (u16 LE)
        $messageSize = self::readU16LEAt($binary, $offset);
        $offset += 2;

        // mosaicsCount (u8)
        $mosaicsCount = \ord($binary[$offset]);
        ++$offset;

        // reserved (u8) skip
        ++$offset;

        // mosaics
        /** @var list<array{mosaicIdDec:string, amountDec:string}> $mosaics */
        $mosaics = [];

        for ($i = 0; $i < $mosaicsCount; ++$i) {
            // 各要素: UnresolvedMosaicId(u64) + amount(u64)
            if ($len < $offset + 16) {
                $need = ($offset + 16) - $len;
                throw new \RuntimeException("Unexpected EOF while reading mosaic[$i]: need {$need} more bytes");
            }
            $mosaicIdDec = self::readU64LEDecAt($binary, $offset);
            $offset += 8;
            $amountDec = self::readU64LEDecAt($binary, $offset);
            $offset += 8;

            if (1 !== \preg_match('/^[0-9]+$/', $mosaicIdDec)) {
                throw new \RuntimeException("mosaics[$i].mosaicIdDec must be decimal string");
            }

            if (1 !== \preg_match('/^[0-9]+$/', $amountDec)) {
                throw new \RuntimeException("mosaics[$i].amountDec must be decimal string");
            }
            $mid = '' === \ltrim($mosaicIdDec, '0') ? '0' : \ltrim($mosaicIdDec, '0');
            $amt = '' === \ltrim($amountDec, '0') ? '0' : \ltrim($amountDec, '0');
            $mosaics[] = ['mosaicIdDec' => $mid, 'amountDec' => $amt];
        }

        // message: size は type(1) + payload(n)
        if (0 === $messageSize) {
            $messageType = 0;
            $payload = '';
        } else {
            if ($len < $offset + $messageSize) {
                $need = ($offset + $messageSize) - $len;
                throw new \RuntimeException("Unexpected EOF while reading message: need {$need} more bytes");
            }
            $messageType = \ord($binary[$offset]);
            ++$offset;
            $payloadLen = $messageSize - 1;

            if ($payloadLen > 0) {
                $payload = \substr($binary, $offset, $payloadLen);

                if (\strlen($payload) !== $payloadLen) {
                    throw new \RuntimeException('Unexpected EOF while slicing message payload');
                }
                $offset += $payloadLen;
            } else {
                $payload = '';
            }
        }

        return new self(
            $recipient,
            $mosaics,
            $messageType,
            $payload,
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
     * ボディ直列化（ヘッダは親が前置）.
     */
    protected function encodeBody(): string
    {
        $out = '';
        $out .= $this->recipient;

        $payload = $this->message;
        $msgSize = 1 + \strlen($payload);

        if ($msgSize > 0xFFFF) {
            throw new \LogicException('message payload too large');
        }
        // messageSize (u16 LE)
        $out .= \pack('v', $msgSize);
        // mosaicsCount (u8)
        $out .= \chr(\count($this->mosaics));
        // reserved (u8)
        $out .= "\x00";

        // mosaics
        foreach ($this->mosaics as $i => $mosaic) {
            $mid = $mosaic['mosaicIdDec'];
            $amt = $mosaic['amountDec'];

            if (1 !== \preg_match('/^[0-9]+$/', $mid)) {
                throw new \LogicException("mosaics[$i].mosaicIdDec must be decimal string");
            }

            if (1 !== \preg_match('/^[0-9]+$/', $amt)) {
                throw new \LogicException("mosaics[$i].amountDec must be decimal string");
            }
            $out .= self::u64LE($mid);
            $out .= self::u64LE($amt);
        }

        // message (type + payload)
        $out .= \chr($this->messageType);
        $out .= $payload;

        return $out;
    }
}
