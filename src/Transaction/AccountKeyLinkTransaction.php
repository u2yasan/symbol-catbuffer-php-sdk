<?php
declare(strict_types=1);

namespace SymbolSdk\Transaction;

use InvalidArgumentException;

enum LinkAction: int
{
    case Unlink = 0;
    case Link = 1;
}

final readonly class AccountKeyLinkTransaction
{
    public const LINKED_PUBLIC_KEY_SIZE = 32;

    public function __construct(
        public string $linkedPublicKey,
        public LinkAction $linkAction,
    ) {
        if (strlen($this->linkedPublicKey) !== self::LINKED_PUBLIC_KEY_SIZE) {
            throw new InvalidArgumentException('linkedPublicKey must be exactly 32 bytes.');
        }
    }
}
