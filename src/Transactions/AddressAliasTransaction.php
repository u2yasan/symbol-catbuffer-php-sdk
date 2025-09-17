<?php
declare(strict_types=1);
namespace SymbolSdk\Transactions;

final class AddressAliasTransaction extends AbstractTransaction
{
    public readonly string $namespaceIdDec;
    public readonly string $address;
    public readonly int $aliasAction;

    public function __construct(
        string $namespaceIdDec,
        string $address,
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
        if (strlen($address) !== 24) {
            throw new \InvalidArgumentException('address must be 24 bytes');
        }
        if ($aliasAction !== 0 && $aliasAction !== 1) {
            throw new \InvalidArgumentException('aliasAction must be 0 or 1');
        }
        $this->namespaceIdDec = ltrim($namespaceIdDec, '0') === '' ? '0' : ltrim($namespaceIdDec, '0');
        $this->address = $address;
        $this->aliasAction = $aliasAction;
        parent::__construct($headerRaw, $size, $version, $network, $type, $maxFeeDec, $deadlineDec);
    }

    public static function fromBinary(string $binary): self
    {
        $h = self::parseHeader($binary);
        $offset = $h['offset'];
        $len = strlen($binary);
        $need = $offset + 8 + 24 + 1;
        if ($len < $need) {
            throw new \RuntimeException("Unexpected EOF: need {$need}, have {$len}");
        }
        $namespaceIdDec = self::readU64LEDecAt($binary, $offset);
        $offset += 8;
        $address = substr($binary, $offset, 24);
        if (strlen($address) !== 24) {
            throw new \RuntimeException('Unexpected EOF (need 24 bytes for address)');
        }
        $offset += 24;
        $aliasAction = ord($binary[$offset]);
        $offset += 1;
        if ($aliasAction !== 0 && $aliasAction !== 1) {
            throw new \InvalidArgumentException('aliasAction must be 0 or 1');
        }
        return new self(
            $namespaceIdDec,
            $address,
            $aliasAction,
            $h['headerRaw'],
            $h['size'],
            $h['version'],
            $h['network'],
            $h['type'],
            $h['maxFeeDec'],
            $h['deadlineDec']
        );
    }

    protected function encodeBody(): string
    {
        return self::u64LE($this->namespaceIdDec)
            . $this->address
            . chr($this->aliasAction);
    }

    /**
     * @param string $binary
     * @param int $offset
     * @return array{namespaceIdDec:string,address:string,aliasAction:int,offset:int}
     */
    protected static function decodeBody(string $binary, int $offset): array
    {
        $len = strlen($binary);
        $need = $offset + 8 + 24 + 1;
        if ($len < $need) {
            throw new \RuntimeException("Unexpected EOF: need {$need}, have {$len}");
        }
        $namespaceIdDec = self::readU64LEDecAt($binary, $offset);
        $offset += 8;
        $address = substr($binary, $offset, 24);
        if (strlen($address) !== 24) {
            throw new \RuntimeException('Unexpected EOF (need 24 bytes for address)');
        }
        $offset += 24;
        $aliasAction = ord($binary[$offset]);
        $offset += 1;
        if ($aliasAction !== 0 && $aliasAction !== 1) {
            throw new \InvalidArgumentException('aliasAction must be 0 or 1');
        }
        return [
            'namespaceIdDec' => $namespaceIdDec,
            'address' => $address,
            'aliasAction' => $aliasAction,
            'offset' => $offset
        ];
    }
}
