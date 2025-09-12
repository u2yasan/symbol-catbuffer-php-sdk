<?php
declare(strict_types=1);
namespace SymbolSdk\Transaction;

final class NodeKeyLinkTransaction extends AbstractTransaction
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
            throw new \InvalidArgumentException('linkAction must be 0 (UNLINK) or 1 (LINK)');
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

        // linkedPublicKey: 32 bytes
        if ($len - $offset < 32) {
            throw new \RuntimeException('Unexpected EOF: need 32 bytes for linkedPublicKey');
        }
        $linkedPublicKey = substr($binary, $offset, 32);
        $offset += 32;

        // linkAction: 1 byte
        if ($len - $offset < 1) {
            throw new \RuntimeException('Unexpected EOF: need 1 byte for linkAction');
        }
        $linkAction = ord($binary[$offset]);
        $offset += 1;
        if ($linkAction !== 0 && $linkAction !== 1) {
            throw new \InvalidArgumentException('linkAction must be 0 (UNLINK) or 1 (LINK)');
        }

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
     * @return string
     */
    protected function encodeBody(): string
    {
        return $this->linkedPublicKey . chr($this->linkAction);
    }

    /**
     * @param string $binary
     * @param int $offset
     * @return array<string, mixed>
     */
    protected static function decodeBody(string $binary, int $offset): array
    {
        $len = strlen($binary);
        if ($len - $offset < 32) {
            throw new \RuntimeException('Unexpected EOF: need 32 bytes for linkedPublicKey');
        }
        $linkedPublicKey = substr($binary, $offset, 32);
        $offset += 32;

        if ($len - $offset < 1) {
            throw new \RuntimeException('Unexpected EOF: need 1 byte for linkAction');
        }
        $linkAction = ord($binary[$offset]);
        $offset += 1;
        if ($linkAction !== 0 && $linkAction !== 1) {
            throw new \InvalidArgumentException('linkAction must be 0 (UNLINK) or 1 (LINK)');
        }

        return [
            'linkedPublicKey' => $linkedPublicKey,
            'linkAction' => $linkAction,
            'offset' => $offset,
        ];
    }
}

enum LinkAction: int
{
    case UNLINK = 0;
    case LINK = 1;
}
