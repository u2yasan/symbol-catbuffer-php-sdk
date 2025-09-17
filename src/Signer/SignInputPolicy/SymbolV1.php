<?php
declare(strict_types=1);

namespace SymbolSdk\Signer\SignInputPolicy;

final class SymbolV1
{
    /**
     * 後で「generationHash(32B) + 署名フィールド0埋めpayload」を返す実装に差し替える。
     * いまは“そのまま返す”だけのダミー。
     */
    public static function buildBytesToSign(string $unsignedPayload, string $generationHash): string
    {
        return $unsignedPayload;
    }
}
