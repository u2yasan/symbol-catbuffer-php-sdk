<?php
declare(strict_types=1);
namespace SymbolSdk\Transaction;

final class VrfKeyLinkTransaction extends AbstractTransaction
{
    public readonly string $linkedPublicKey;
    public readonly int $linkAction;

    public function __construct(
        string $linkedPublicKey,
        int $linkAction,
        string $headerRaw,
        int $size,
        int $version,
        int $network,
        int $type,
        string $maxFeeDec,
        string $deadlineDec
    ) {
        if (strlen($linkedPublicKey) !== 32) {
            throw new \InvalidArgumentException('linkedPublicKey must be 32 bytes');
        }
        if ($linkAction !== 0 && $linkAction !== 1) {
            throw new \InvalidArgumentException('linkAction must be 0 or 1');
        }
        $this->linkedPublicKey = $linkedPublicKey;
        $this->linkAction = $linkAction;
        parent::__construct($headerRaw, $size, $version, $network, $type, $maxFeeDec, $deadlineDec);
    }

    public static function fromBinary(string $binary): self
    {
        $h = self::parseHeader($binary);
        $offset = $h['offset'];
        $len = strlen($binary);

        $need = 32 + 1;
        $remaining = $len - $offset;
        if ($remaining < $need) {
            throw new \RuntimeException("Unexpected EOF: need {$need}, have {$remaining}");
        }

        $linkedPublicKey = substr($binary, $offset, 32);
        if (strlen($linkedPublicKey) !== 32) {
            throw new \RuntimeException('Unexpected EOF (linkedPublicKey)');
        }
        $offset += 32;

        $linkActionByte = $binary[$offset] ?? null;
        if ($linkActionByte === null) {
            throw new \RuntimeException('Unexpected EOF (linkAction)');
        }
        $linkAction = ord($linkActionByte);
        if ($linkAction !== 0 && $linkAction !== 1) {
            throw new \InvalidArgumentException('linkAction must be 0 or 1');
        }
        $offset += 1;

        return new self(
            $linkedPublicKey,
            $linkAction,
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
     * @return array<string, mixed>
     */
    protected static function decodeBody(string $binary, int $offset): array
    {
        $len = strlen($binary);
        $need = 32 + 1;
        $remaining = $len - $offset;
        if ($remaining < $need) {
            throw new \RuntimeException("Unexpected EOF: need {$need}, have {$remaining}");
        }
        $linkedPublicKey = substr($binary, $offset, 32);
        if (strlen($linkedPublicKey) !== 32) {
            throw new \RuntimeException('Unexpected EOF (linkedPublicKey)');
        }
        $offset += 32;

        $linkActionByte = $binary[$offset] ?? null;
        if ($linkActionByte === null) {
            throw new \RuntimeException('Unexpected EOF (linkAction)');
        }
        $linkAction = ord($linkActionByte);
        if ($linkAction !== 0 && $linkAction !== 1) {
            throw new \InvalidArgumentException('linkAction must be 0 or 1');
        }
        $offset += 1;

        return [
            'linkedPublicKey' => $linkedPublicKey,
            'linkAction' => $linkAction,
            'offset' => $offset
        ];
    }

    protected function encodeBody(): string
    {
        return $this->linkedPublicKey . chr($this->linkAction);
    }
}
