<?php

declare(strict_types=1);

namespace SymbolSdk\Transactions;

/**
 * MosaicSupplyChangeTransaction
 * - 共通ヘッダ128B対応
 * - body: mosaicId(u64) / action(u8) / delta(u64)
 * - u64 は 10進文字列 (…Dec) で保持
 *
 * 依存:
 * - 親 AbstractTransaction の readU16LEAt / readU32LEAt / readU64LEDecAt / u64LE を使用
 */
final class MosaicSupplyChangeTransaction extends AbstractTransaction
{
    /** @var string u64 decimal string */
    private string $mosaicIdDec;

    /** @var int 0(increase?) / 1(decrease?) などの u8 値 */
    private int $action;

    /** @var string u64 decimal string */
    private string $deltaDec;

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
        string $deadlineDec,
    ) {
        if (1 !== \preg_match('/^[0-9]+$/', $mosaicIdDec)) {
            throw new \InvalidArgumentException('mosaicIdDec must be decimal string');
        }

        if ($action < 0 || $action > 255) {
            throw new \InvalidArgumentException('action must be 0..255');
        }

        if (1 !== \preg_match('/^[0-9]+$/', $deltaDec)) {
            throw new \InvalidArgumentException('deltaDec must be decimal string');
        }

        parent::__construct($headerRaw, $size, $version, $network, $type, $maxFeeDec, $deadlineDec);

        // 先頭ゼロの正規化（空なら "0"）
        $this->mosaicIdDec = '' === \ltrim($mosaicIdDec, '0') ? '0' : \ltrim($mosaicIdDec, '0');
        $this->action = $action;
        $this->deltaDec = '' === \ltrim($deltaDec, '0') ? '0' : \ltrim($deltaDec, '0');
    }

    /**
     * ヘッダ＋ボディの完全なバイナリから復元.
     */
    public static function fromBinary(string $binary): self
    {
        $h = self::parseHeader($binary);
        $offset = $h['offset'];
        $len = \strlen($binary);

        // 必要長: mosaicId(8) + action(1) + delta(8) = 17
        if ($len < $offset + 8 + 1 + 8) {
            $need = ($offset + 17) - $len;
            throw new \RuntimeException("Unexpected EOF while reading MosaicSupplyChangeTransaction body: need {$need} more bytes");
        }

        // mosaicId u64 LE → decimal string
        $mosaicIdDec = self::readU64LEDecAt($binary, $offset);
        $offset += 8;

        // action u8
        $action = \ord($binary[$offset]);
        ++$offset;

        // delta u64 LE → decimal string
        $deltaDec = self::readU64LEDecAt($binary, $offset);
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
     * ボディ直列化（ヘッダ前置は親が行う）.
     */
    protected function encodeBody(): string
    {
        $out = '';
        $out .= self::u64LE($this->mosaicIdDec); // u64 LE
        $out .= \chr($this->action);              // u8
        $out .= self::u64LE($this->deltaDec);    // u64 LE

        return $out;
    }
}
