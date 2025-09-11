<?php
declare(strict_types=1);
namespace SymbolSdk\Transaction;

final class AccountKeyLinkTransaction extends AbstractTransaction
{
    public readonly string $linkedPublicKey;
    public readonly int $linkAction;

    public function __construct(
        string $linkedPublicKey,
        int $linkAction,
        string $headerRaw, int $size, int $version, int $network, int $type, string $maxFeeDec, string $deadlineDec
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

        $need = 32;
        if ($len - $offset < $need) {
            throw new \RuntimeException("Unexpected EOF: need {$need} bytes for linkedPublicKey, have " . ($len - $offset));
        }
        $linkedPublicKey = substr($binary, $offset, 32);
        $offset += 32;

        $need = 1;
        if ($len - $offset < $need) {
            throw new \RuntimeException("Unexpected EOF: need {$need} byte for linkAction, have " . ($len - $offset));
        }
        $linkAction = ord($binary[$offset]);
        $offset += 1;
        if ($linkAction !== 0 && $linkAction !== 1) {
            throw new \InvalidArgumentException('linkAction must be 0 or 1');
        }

        return new self(
            $linkedPublicKey,
            $linkAction,
            $h['headerRaw'], $h['size'], $h['version'], $h['network'], $h['type'], $h['maxFeeDec'], $h['deadlineDec']
        );
    }

    protected function encodeBody(): string
    {
        return $this->linkedPublicKey . chr($this->linkAction);
    }

    /**
     * @param string $binary
     * @param int $offset
     * @return array{linkedPublicKey:string, linkAction:int, offset:int}
     */
    protected static function decodeBody(string $binary, int $offset): array
    {
        $len = strlen($binary);

        $need = 32;
        if ($len - $offset < $need) {
            throw new \RuntimeException("Unexpected EOF: need {$need} bytes for linkedPublicKey, have " . ($len - $offset));
        }
        $linkedPublicKey = substr($binary, $offset, 32);
        $offset += 32;

        $need = 1;
        if ($len - $offset < $need) {
            throw new \RuntimeException("Unexpected EOF: need {$need} byte for linkAction, have " . ($len - $offset));
        }
        $linkAction = ord($binary[$offset]);
        $offset += 1;
        if ($linkAction !== 0 && $linkAction !== 1) {
            throw new \InvalidArgumentException('linkAction must be 0 or 1');
        }

        return [
            'linkedPublicKey' => $linkedPublicKey,
            'linkAction' => $linkAction,
            'offset' => $offset,
        ];
    }
}

final class LinkAction
{
    public const UNLINK = 0;
    public const LINK = 1;
}
