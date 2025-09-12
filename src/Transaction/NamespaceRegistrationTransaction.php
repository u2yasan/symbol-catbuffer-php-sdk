<?php
declare(strict_types=1);
namespace SymbolSdk\Transaction;

final class NamespaceRegistrationTransaction extends AbstractTransaction
{
    public readonly int $registrationType;
    public readonly ?string $durationDec;
    public readonly ?string $parentIdDec;
    public readonly string $name;
    public readonly string $namespaceIdDec;

    /**
     * @param int $registrationType 0=root, 1=child
     * @param ?string $durationDec 10進文字列, root時のみ
     * @param ?string $parentIdDec 10進文字列, child時のみ
     * @param string $name
     * @param string $namespaceIdDec 10進文字列
     * @param string $headerRaw
     * @param int $size
     * @param int $version
     * @param int $network
     * @param int $type
     * @param string $maxFeeDec
     * @param string $deadlineDec
     */
    public function __construct(
        int $registrationType,
        ?string $durationDec,
        ?string $parentIdDec,
        string $name,
        string $namespaceIdDec,
        string $headerRaw,
        int $size,
        int $version,
        int $network,
        int $type,
        string $maxFeeDec,
        string $deadlineDec
    ) {
        parent::__construct($headerRaw, $size, $version, $network, $type, $maxFeeDec, $deadlineDec);
        if ($registrationType !== 0 && $registrationType !== 1) {
            throw new \InvalidArgumentException('registrationType must be 0 (root) or 1 (child)');
        }
        if ($registrationType === 0 && $durationDec === null) {
            throw new \InvalidArgumentException('durationDec required for root namespace');
        }
        if ($registrationType === 1 && $parentIdDec === null) {
            throw new \InvalidArgumentException('parentIdDec required for child namespace');
        }
        if ($registrationType === 0 && $parentIdDec !== null) {
            throw new \InvalidArgumentException('parentIdDec must be null for root namespace');
        }
        if ($registrationType === 1 && $durationDec !== null) {
            throw new \InvalidArgumentException('durationDec must be null for child namespace');
        }
        if (!preg_match('/^[\x20-\x7E]{1,64}$/', $name)) {
            throw new \InvalidArgumentException('name must be 1-64 printable ASCII bytes');
        }
        if (!preg_match('/^[0-9]+$/', $namespaceIdDec)) {
            throw new \InvalidArgumentException('namespaceIdDec must be decimal string');
        }
        if ($registrationType === 0 && !preg_match('/^[0-9]+$/', $durationDec)) {
            throw new \InvalidArgumentException('durationDec must be decimal string');
        }
        if ($registrationType === 1 && !preg_match('/^[0-9]+$/', $parentIdDec)) {
            throw new \InvalidArgumentException('parentIdDec must be decimal string');
        }
        $this->registrationType = $registrationType;
        $this->durationDec = $durationDec;
        $this->parentIdDec = $parentIdDec;
        $this->name = $name;
        $this->namespaceIdDec = $namespaceIdDec;
    }

    public static function fromBinary(string $binary): self
    {
        $h = self::parseHeader($binary);
        $offset = $h['offset'];
        $remaining = strlen($binary) - $offset;
        // registrationType:u8
        if ($remaining < 1) throw new \RuntimeException('Unexpected EOF: need 1 byte for registrationType');
        $registrationType = ord($binary[$offset]);
        $offset += 1;
        $remaining = strlen($binary) - $offset;
        $durationDec = null;
        $parentIdDec = null;
        if ($registrationType === 0) {
            // root: duration:u64
            if ($remaining < 8) throw new \RuntimeException('Unexpected EOF: need 8 bytes for duration');
            $durationDec = self::u64DecAt($binary, $offset);
            $offset += 8;
        } elseif ($registrationType === 1) {
            // child: parentId:u64
            if ($remaining < 8) throw new \RuntimeException('Unexpected EOF: need 8 bytes for parentId');
            $parentIdDec = self::u64DecAt($binary, $offset);
            $offset += 8;
        } else {
            throw new \InvalidArgumentException('registrationType must be 0 (root) or 1 (child)');
        }
        $remaining = strlen($binary) - $offset;
        // nameSize:u8
        if ($remaining < 1) throw new \RuntimeException('Unexpected EOF: need 1 byte for nameSize');
        $nameSize = ord($binary[$offset]);
        $offset += 1;
        $remaining = strlen($binary) - $offset;
        if ($nameSize < 1 || $nameSize > 64) {
            throw new \InvalidArgumentException('nameSize must be 1-64');
        }
        if ($remaining < $nameSize) throw new \RuntimeException("Unexpected EOF: need {$nameSize} bytes for name");
        $name = substr($binary, $offset, $nameSize);
        $offset += $nameSize;
        $remaining = strlen($binary) - $offset;
        // namespaceId:u64
        if ($remaining < 8) throw new \RuntimeException('Unexpected EOF: need 8 bytes for namespaceId');
        $namespaceIdDec = self::u64DecAt($binary, $offset);
        $offset += 8;
        return new self(
            $registrationType,
            $durationDec,
            $parentIdDec,
            $name,
            $namespaceIdDec,
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
        $out = '';
        $out .= chr($this->registrationType);
        if ($this->registrationType === 0) {
            // root: duration
            $out .= self::u64LE($this->durationDec ?? '0');
        } elseif ($this->registrationType === 1) {
            // child: parentId
            $out .= self::u64LE($this->parentIdDec ?? '0');
        } else {
            throw new \InvalidArgumentException('registrationType must be 0 or 1');
        }
        $nameLen = strlen($this->name);
        if ($nameLen < 1 || $nameLen > 64) {
            throw new \InvalidArgumentException('name must be 1-64 bytes');
        }
        $out .= chr($nameLen);
        $out .= $this->name;
        $out .= self::u64LE($this->namespaceIdDec);
        return $out;
    }

    /**
     * @param string $binary
     * @param int $offset
     * @return array<string, mixed>
     */
    protected static function decodeBody(string $binary, int $offset): array
    {
        // 未使用（テスト用ダミー）
        return [];
    }
}
