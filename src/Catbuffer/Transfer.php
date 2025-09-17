<?php
declare(strict_types=1);

namespace SymbolSdk\Catbuffer;

final class Transfer {
    public static function serializeForSigning(
        int $networkType,
        int $deadline,
        int $maxFee,
        string $recipient,
        int $amount,
        string $message
    ): string {
        // ★ここは本来catbuffer生成。暫定で固定長バイナリを返す。
        return pack("N", $networkType) .
               pack("J", $deadline) .
               pack("J", $maxFee) .
               $recipient .
               pack("J", $amount) .
               $message;
    }

    public static function embedSignature(string $signature): string {
        // 署名を単純に後ろに付けるだけ（ダミー）
        return "TX" . $signature;
    }
}
