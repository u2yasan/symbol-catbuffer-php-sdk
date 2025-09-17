<?php
declare(strict_types=1);

namespace SymbolSdk\Builder;

final class TransferTransactionBuilder extends BaseBuilder
{
    private string $recipientAddress;   // 形式は後でユーティリティ化
    private int $amount = 0;            // mosaic量（例）
    private string $message = '';       // plaintext (UTF-8) 前提

    public function recipient(string $addr): static { $this->recipientAddress = $addr; return $this; }
    public function amount(int $v): static { $this->amount = $v; return $this; }
    public function message(string $m): static { $this->message = $m; return $this; }

    protected function serializeForSigning(): string
    {
        // TODO: catbuffer 実装に接続する。ここでは仮のワイヤ形式を置く。
        // 例: [header..][networkType(4)][deadline(8)][maxFee(8)][recipient(24)][amount(8)][msgLen(2)][msg...]
        // ※ 正式実装では catbuffer の定義に沿って serialize() を呼ぶだけにする。
        return \SymbolSdk\Catbuffer\Transfer::serializeForSigning(
            networkType: $this->networkType,
            deadline: $this->deadline,
            maxFee: $this->maxFee,
            recipient: $this->recipientAddress,
            amount: $this->amount,
            message: $this->message
        );
    }

    protected function embedSignature(string $signature): string
    {
        return \SymbolSdk\Catbuffer\Transfer::embedSignature($signature);
    }
}
