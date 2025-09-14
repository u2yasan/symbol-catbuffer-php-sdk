<?php
declare(strict_types=1);
namespace SymbolSdk\Transaction;

final class AliasAction
{
    public const UNLINK = 0;
    public const LINK = 1;
}

final class MosaicAliasTransaction extends AbstractTransaction
{
    public readonly string $namespaceIdDec;
    public readonly string $mosaicIdDec;
    public readonly int $aliasAction;

    /**
     * @param string $namespaceIdDec 10進文字列u64
     * @param string $mosaicIdDec 10進文字列u64
     * @param int $aliasAction 0=unlink, 1=link
     * @param string $headerRaw
     * @param int $size
     * @param int $version
     * @param int $network
     * @param int $type
     * @param string $maxFeeDec
     * @param string $deadlineDec
     */
    public function __construct(
        string $namespaceIdDec,
        string $mosaicIdDec,
        int $aliasAction,
        string $headerRaw,
        int $size,
        int $version,
        int $network,
        int $type,
        string $maxFeeDec,
        string $deadlineDec
    ) {
        if (preg_match('/^[0-9]+$/', $namespaceIdDec) !== 1) {
            throw new \InvalidArgumentException('namespaceIdDec must be decimal string');
        }
        if (preg_match('/^[0-9]+$/', $mosaicIdDec) !== 1) {
            throw new \InvalidArgumentException('mosaicIdDec must be decimal string');
        }
        if ($aliasAction !== AliasAction::UNLINK && $aliasAction !== AliasAction::LINK) {
            throw new \InvalidArgumentException('aliasAction must be 0 or 1');
        }
        $this->namespaceIdDec = ltrim($namespaceIdDec, '0') === '' ? '0' : ltrim($namespaceIdDec, '0');
        $this->mosaicIdDec = ltrim($mosaicIdDec, '0') === '' ? '0' : ltrim($mosaicIdDec, '0');
        $this->aliasAction = $aliasAction;
        parent::__construct($headerRaw, $size, $version, $network, $type, $maxFeeDec, $deadlineDec);
    }

    /**
     * @param string $bin
     * @return self
     */
    public static function fromBinary(string $bin): self
    {
        $h = self::parseHeader($bin);
        $body = self::decodeBody($bin, $h['offset']);
        return new self(
            $body['namespaceIdDec'],
            $body['mosaicIdDec'],
            $body['aliasAction'],
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
     * @param string $bin
     * @param int $offset
     * @return array{namespaceIdDec:string, mosaicIdDec:string, aliasAction:int}
     */
    protected static function decodeBody(string $bin, int $offset): array
    {
        $remaining = strlen($bin) - $offset;
        if ($remaining < 17) {
            throw new \RuntimeException("Unexpected EOF: need 17, have {$remaining}");
        }
        $namespaceIdDec = self::readU64LEDecAt($bin, $offset);
        $mosaicIdDec = self::readU64LEDecAt($bin, $offset + 8);
        $aliasActionByte = substr($bin, $offset + 16, 1);
        if (strlen($aliasActionByte) !== 1) {
            throw new \RuntimeException('Unexpected EOF (need 1 byte for aliasAction)');
        }
        $aliasAction = ord($aliasActionByte);
        if ($aliasAction !== AliasAction::UNLINK && $aliasAction !== AliasAction::LINK) {
            throw new \InvalidArgumentException('aliasAction must be 0 or 1');
        }
        return [
            'namespaceIdDec' => $namespaceIdDec,
            'mosaicIdDec' => $mosaicIdDec,
            'aliasAction' => $aliasAction,
        ];
    }

    /**
     * @return string
     */
    protected function encodeBody(): string
    {
        $out = '';
        $out .= self::u64LE($this->namespaceIdDec);
        $out .= self::u64LE($this->mosaicIdDec);
        $out .= chr($this->aliasAction);
        return $out;
    }
}
