<?php

declare(strict_types=1);

namespace SymbolSdk\Transactions;

/**
 * MosaicDefinitionTransaction
 * - 共通ヘッダ128B対応
 * - body: nonce(u32) / mosaicId(u64) / flags(u8) / divisibility(u8) / duration(u64?) ※残量あれば読む
 *
 * ルール:
 * - u64 は 10進文字列で保持（*_Dec）。
 * - 親 AbstractTransaction の readU16LEAt/readU32LEAt/readU64LEDecAt/u64LE を使用（再実装しない）。
 */
final class MosaicDefinitionTransaction extends AbstractTransaction
{
    /** @var int u32 nonce */
    private int $nonce;

    /** @var string u64 decimal string */
    private string $mosaicIdDec;

    /** @var int 0..255 */
    private int $flags;

    /** @var int 0..255 */
    private int $divisibility;

    /** @var string|null u64 decimal string（残量があれば読み取る） */
    private ?string $durationDec;

    public function __construct(
        int $nonce,
        string $mosaicIdDec,
        int $flags,
        int $divisibility,
        ?string $durationDec,
        string $headerRaw,
        int $size,
        int $version,
        int $network,
        int $type,
        string $maxFeeDec,
        string $deadlineDec,
    ) {
        if ($nonce < 0) {
            throw new \InvalidArgumentException('nonce must be non-negative u32');
        }

        if ($flags < 0 || $flags > 255) {
            throw new \InvalidArgumentException('flags must be 0..255');
        }

        if ($divisibility < 0 || $divisibility > 255) {
            throw new \InvalidArgumentException('divisibility must be 0..255');
        }

        if (1 !== \preg_match('/^[0-9]+$/', $mosaicIdDec)) {
            throw new \InvalidArgumentException('mosaicIdDec must be decimal string');
        }

        if (null !== $durationDec && 1 !== \preg_match('/^[0-9]+$/', $durationDec)) {
            throw new \InvalidArgumentException('durationDec must be decimal string or null');
        }

        parent::__construct($headerRaw, $size, $version, $network, $type, $maxFeeDec, $deadlineDec);

        $this->nonce = $nonce;
        $this->mosaicIdDec = '' === \ltrim($mosaicIdDec, '0') ? '0' : \ltrim($mosaicIdDec, '0');
        $this->flags = $flags;
        $this->divisibility = $divisibility;
        $this->durationDec = $durationDec;
    }

    /**
     * HEX全体（ヘッダ+ボディ）から復元.
     */
    public static function fromBinary(string $binary): self
    {
        $h = self::parseHeader($binary);
        $offset = $h['offset'];
        $len = \strlen($binary);

        // 固定部: nonce(4) + mosaicId(8) + flags(1) + divisibility(1)
        if ($len < $offset + 4 + 8 + 1 + 1) {
            throw new \RuntimeException('Unexpected EOF while reading MosaicDefinitionTransaction body');
        }

        $nonce = self::readU32LEAt($binary, $offset);
        $offset += 4;

        $mosaicIdDec = self::readU64LEDecAt($binary, $offset);
        $offset += 8;

        $flags = \ord($binary[$offset]);
        ++$offset;

        $divisibility = \ord($binary[$offset]);
        ++$offset;

        // 可変部: duration(u64) は残量が 8 以上なら読む
        $remaining = $len - $offset;
        $durationDec = null;

        if ($remaining >= 8) {
            $durationDec = self::readU64LEDecAt($binary, $offset);
            $offset += 8;
        }

        return new self(
            $nonce,
            $mosaicIdDec,
            $flags,
            $divisibility,
            $durationDec,
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
        // nonce u32
        $out .= \pack('V', $this->nonce);
        // mosaicId u64 LE
        $out .= self::u64LE($this->mosaicIdDec);
        // flags, divisibility
        $out .= \chr($this->flags);
        $out .= \chr($this->divisibility);

        // duration u64（指定があれば）
        if (null !== $this->durationDec) {
            $out .= self::u64LE($this->durationDec);
        }

        return $out;
    }
}
