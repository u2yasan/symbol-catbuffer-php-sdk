<?php
declare(strict_types=1);

namespace SymbolSdk\Builder;

use SymbolSdk\CryptoTypes\KeyPair;

abstract class BaseBuilder
{
    protected int $networkType;  // enum-int (TESTNET=152, MAINNET=104 等)
    protected int $deadline;     // UNIX秒 or SDK内部形式（実装側で統一）
    protected int $maxFee = 0;

    public function networkType(int $v): static { $this->networkType = $v; return $this; }
    public function deadline(int $v): static { $this->deadline = $v; return $this; }
    public function maxFee(int $v): static { $this->maxFee = $v; return $this; }

    /** 子クラスがDTO→catbuffer serializeする */
    abstract protected function serializeForSigning(): string;

    /** 署名対象ペイロード */
    public function bytesToSign(): string {
        return $this->serializeForSigning(); // 後で「署名フィールドを0埋め」等に差し替え可能
    }

    /** 実署名し、署名埋め込み済みペイロードを返す */
    public function signWith(KeyPair $kp): string {
        $sig = \SymbolSdk\Signer\TransactionSigner::signBytes($this->bytesToSign(), $kp);
        return $this->embedSignature($sig);
    }

    /** 子クラスで署名を埋め込む */
    abstract protected function embedSignature(string $signature): string;
}
