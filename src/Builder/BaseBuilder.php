<?php
declare(strict_types=1);

namespace SymbolSdk\Builder;

use SymbolSdk\CryptoTypes\KeyPair;
use SymbolSdk\Signer\Ed25519;

abstract class BaseBuilder
{
    protected int $networkType = 0x98; // 例: 152
    protected int $deadline    = 0;
    protected int $maxFee      = 0;

    public function networkType(int $v): static { $this->networkType = $v; return $this; }
    public function deadline(int $v): static { $this->deadline = $v; return $this; }
    public function maxFee(int $v): static { $this->maxFee = $v; return $this; }

    /** bytesToSign を返す */
    abstract protected function serializeForSigning(): string;

    /** 署名を埋め戻したペイロードを返す */
    abstract protected function embedSignature(string $signature): string;

    /** E2E 署名 */
    public function signWith(KeyPair $kp): string {
        $bytesToSign = $this->serializeForSigning();
        $signature   = Ed25519::sign($bytesToSign, $kp);
        return $this->embedSignature($signature);
    }
}