<?php

declare(strict_types=1);

namespace SymbolSdk\Transaction;

/**
 * NamespaceRegistrationTransaction
 * - 共通ヘッダ128B対応
 * - body の順序（cats 準拠）:
 *   registration_type が ROOT(=0) のとき: duration(u64) を先頭に配置
 *   registration_type が CHILD(=1) のとき: parent_id(u64) を先頭に配置
 *   その後: id(u64), registration_type(u8), name_size(u8), name(bytes).
 *
 * ルール:
 * - u64 値は 10進文字列 (…Dec) で保持
 * - 読み取りは AbstractTransaction の readU64LEDecAt() を使用（u64DecAt は使わない）
 * - 残量チェックは「必要長 < 残バイト」で明示比較（!$x の否定は使わない）
 */
final class NamespaceRegistrationTransaction extends AbstractTransaction
{
    /** @var int 0=root, 1=child */
    private int $registrationType;

    /** @var string|null u64 decimal string（root のとき必須、それ以外 null） */
    private ?string $durationDec;

    /** @var string|null u64 decimal string（child のとき必須、それ以外 null） */
    private ?string $parentIdDec;

    /** @var string u64 decimal string */
    private string $namespaceIdDec;

    /** @var string 生の名前（バイト列） */
    private string $name;

    public function __construct(
        int $registrationType,
        ?string $durationDec,
        ?string $parentIdDec,
        string $namespaceIdDec,
        string $name,
        string $headerRaw,
        int $size,
        int $version,
        int $network,
        int $type,
        string $maxFeeDec,
        string $deadlineDec,
    ) {
        if (0 !== $registrationType && 1 !== $registrationType) {
            throw new \InvalidArgumentException('registrationType must be 0 (root) or 1 (child)');
        }

        if (0 === $registrationType) { // root
            if (null === $durationDec) {
                throw new \InvalidArgumentException('durationDec is required for root registration');
            }

            if (1 !== \preg_match('/^[0-9]+$/', $durationDec)) {
                throw new \InvalidArgumentException('durationDec must be decimal string');
            }

            if (null !== $parentIdDec) {
                throw new \InvalidArgumentException('parentIdDec must be null for root registration');
            }
        } else { // child
            if (null === $parentIdDec) {
                throw new \InvalidArgumentException('parentIdDec is required for child registration');
            }

            if (1 !== \preg_match('/^[0-9]+$/', $parentIdDec)) {
                throw new \InvalidArgumentException('parentIdDec must be decimal string');
            }

            if (null !== $durationDec) {
                throw new \InvalidArgumentException('durationDec must be null for child registration');
            }
        }

        if (1 !== \preg_match('/^[0-9]+$/', $namespaceIdDec)) {
            throw new \InvalidArgumentException('namespaceIdDec must be decimal string');
        }

        if ('' === $name) {
            throw new \InvalidArgumentException('name must not be empty');
        }

        if (\strlen($name) > 255) {
            throw new \InvalidArgumentException('name_size must be <= 255');
        }

        parent::__construct($headerRaw, $size, $version, $network, $type, $maxFeeDec, $deadlineDec);

        $this->registrationType = $registrationType;
        $this->durationDec = $durationDec;
        $this->parentIdDec = $parentIdDec;
        $this->namespaceIdDec = '' === \ltrim($namespaceIdDec, '0') ? '0' : \ltrim($namespaceIdDec, '0');
        $this->name = $name;
    }

    /**
     * ヘッダ＋ボディの完全なバイナリから復元.
     */
    public static function fromBinary(string $binary): self
    {
        $h = self::parseHeader($binary);
        $offset = $h['offset'];
        $len = \strlen($binary);

        // 最低限必要: 先頭の可変 u64(8) + id(8) + registration_type(1) + name_size(1)
        if ($len < $offset + 8 + 8 + 1 + 1) {
            $need = ($offset + 18) - $len;
            throw new \RuntimeException("Unexpected EOF while reading NamespaceRegistrationTransaction body: need {$need} more bytes");
        }

        // 先頭 8 バイトは registrationType に応じて duration か parentId
        // ただし registration_type(u8) は id(u64) の後に現れるため、いったん両方の可能性に対応して進める
        // cats 仕様に従い、最初に「duration or parentId」を 8B 読む
        $firstU64Dec = self::readU64LEDecAt($binary, $offset);
        $offset += 8;

        // 次に id(u64)
        $namespaceIdDec = self::readU64LEDecAt($binary, $offset);
        $offset += 8;

        // 次に registration_type(u8)
        $registrationType = \ord($binary[$offset]);
        ++$offset;

        if (0 !== $registrationType && 1 !== $registrationType) {
            throw new \InvalidArgumentException('registrationType must be 0 (root) or 1 (child)');
        }

        // name_size(u8)
        $nameSize = \ord($binary[$offset]);
        ++$offset;

        // name(bytes)
        $remaining = $len - $offset;

        if ($remaining < $nameSize) {
            $need = $nameSize - $remaining;
            throw new \RuntimeException("Unexpected EOF while reading name: need {$need} more bytes");
        }
        $name = \substr($binary, $offset, $nameSize);

        if (\strlen($name) !== $nameSize) {
            throw new \RuntimeException('Unexpected EOF while slicing name');
        }
        $offset += $nameSize;

        // registration_type に応じて duration/parentId を確定
        $durationDec = null;
        $parentIdDec = null;

        if (0 === $registrationType) {          // root
            $durationDec = $firstU64Dec;
        } else {                                 // child
            $parentIdDec = $firstU64Dec;
        }

        return new self(
            $registrationType,
            $durationDec,
            $parentIdDec,
            $namespaceIdDec,
            $name,
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
     *
     * cats の定義順:
     *   registration_type が ROOT の場合: duration, id, registration_type, name_size, name
     *   registration_type が CHILD の場合: parent_id, id, registration_type, name_size, name
     */
    protected function encodeBody(): string
    {
        $out = '';

        if (0 === $this->registrationType) { // root: duration first
            if (null === $this->durationDec) {
                throw new \LogicException('durationDec must not be null for root');
            }
            $out .= self::u64LE($this->durationDec);
        } else { // child: parentId first
            if (null === $this->parentIdDec) {
                throw new \LogicException('parentIdDec must not be null for child');
            }
            $out .= self::u64LE($this->parentIdDec);
        }

        // id
        $out .= self::u64LE($this->namespaceIdDec);
        // registration_type
        $out .= \chr($this->registrationType);
        // name_size
        $nameSize = \strlen($this->name);

        if ($nameSize > 255) {
            throw new \LogicException('name_size must be <= 255');
        }
        $out .= \chr($nameSize);
        // name
        $out .= $this->name;

        return $out;
    }
}
