<?php
declare(strict_types=1);

namespace SymbolSdk\Signer;

use SymbolSdk\CryptoTypes\KeyPair;

final class Cosignature
{
    /** @param string $aggregateHashRaw 32B（親Aggregateのハッシュ raw） */
    public static function signAggregateHash(string $aggregateHashRaw, KeyPair $kp): string
    {
        if (strlen($aggregateHashRaw) !== 32) {
            throw new \InvalidArgumentException('aggregateHash must be 32 bytes');
        }
        return TransactionSigner::signBytes($aggregateHashRaw, $kp);
    }

    /** 送信用の簡易DTO（hex） */
    public static function toDtoHex(string $aggregateHashRaw, KeyPair $kp): array
    {
        $sig = self::signAggregateHash($aggregateHashRaw, $kp);
        return [
            'parentHash'      => bin2hex($aggregateHashRaw),
            'signature'       => bin2hex($sig),
            'signerPublicKey' => $kp->publicKey()->toHex(),
        ];
    }
}
