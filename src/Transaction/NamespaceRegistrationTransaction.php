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
        $len = strlen($binary);

        $need = function (int $n) use ($len, $offset): void {
            if ($len - $offset < $n) {
                throw new \RuntimeException("Unexpected EOF while reading NamespaceRegistration body: need {$n}, have " . ($len - $offset));
            }
        };

        // 1) first u64 = duration (root) or parent_id (child)
        $need(8);
        $firstU64 = self::u64DecAt($binary, $offset);
        $offset += 8;

        // 2) id:u64 (namespaceId)
        $need(8);
        $namespaceIdDec = self::u64DecAt($binary, $offset);
        $offset += 8;

        // 3) registration_type:u8 (0=root, 1=child)
        $need(1);
        $registrationType = ord($binary[$offset]);
        $offset += 1;
        if ($registrationType !== 0 && $registrationType !== 1) {
            throw new \InvalidArgumentException('registrationType must be 0 (root) or 1 (child)');
        }

        // 4) name_size:u8
        $need(1);
        $nameSize = ord($binary[$offset]);
        $offset += 1;

        // 5) name[name_size]
        $need($nameSize);
        $name = substr($binary, $offset, $nameSize);
        $offset += $nameSize;

        // map firstU64
        $durationDec = null;
        $parentIdDec = null;
        if ($registrationType === 0) {
            $durationDec = $firstU64;
        } else {
            $parentIdDec = $firstU64;
        }

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

        // 1) duration or parent_id
        if ($this->registrationType === 0) {
            // root
            if ($this->durationDec === null) {
                throw new \RuntimeException('durationDec is required for root namespace');
            }
            $out .= self::u64LE($this->durationDec);
        } else {
            // child
            if ($this->parentIdDec === null) {
                throw new \RuntimeException('parentIdDec is required for child namespace');
            }
            $out .= self::u64LE($this->parentIdDec);
        }

        // 2) id (namespaceId)
        $out .= self::u64LE($this->namespaceIdDec);

        // 3) registration_type
        $out .= chr($this->registrationType);

        // 4) name_size + name
        $nameSize = strlen($this->name);
        if ($nameSize > 255) {
            throw new \InvalidArgumentException('name too long (max 255)');
        }
        $out .= chr($nameSize);
        $out .= $this->name;

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
