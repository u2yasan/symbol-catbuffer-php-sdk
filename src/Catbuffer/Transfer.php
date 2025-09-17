<?php
declare(strict_types=1);

namespace SymbolSdk\CatbufferFacade;

use SymbolSdk\Catbuffer\TransferTransactionV1;
use SymbolSdk\CryptoTypes\PublicKey;

final class Transfer
{
    /** u64 → LE(8B) */
    private static function packU64LE(int|string $v): string {
        if (is_string($v) && ctype_xdigit($v)) {
            $v = intval($v, 16);
        } elseif (is_string($v)) {
            $v = (int)$v;
        }
        $lo = $v & 0xFFFFFFFF;
        $hi = ($v >> 32) & 0xFFFFFFFF;
        return pack('V2', $lo, $hi);
    }

    /** DTO→未署名ペイロード（messageのみ／mosaicsなし） */
    public static function fromParams(
        int $networkType,
        int $deadline,
        int $maxFee,
        string $recipientRaw24,
        string $messagePlain
    ): string {
        if (strlen($recipientRaw24) !== 24) {
            throw new \InvalidArgumentException('recipientRaw24 must be 24 bytes');
        }

        // Plain: 先頭1Bに 0x00 を付与
        $msgBytes   = "\x00" . (string)$messagePlain;
        $messageSize = strlen($msgBytes); // u16

        $tx = new TransferTransactionV1(
            TransferTransactionV1::SIZE,
            0,
            str_repeat("\x00", 64),
            str_repeat("\x00", 32),
            1,              // version
            $networkType,
            0x4154,         // Transfer (u16leでserialize)
            $maxFee,
            $deadline,
            $recipientRaw24,
            $messageSize,
            $msgBytes
        );
        $bin = $tx->serialize();

        // size を実長で書き戻し
        $total = strlen($bin);
        $bin   = pack('V', $total) . substr($bin, 4);

        return $bin;
    }

    /**
     * DTO→未署名ペイロード（message + mosaics[]）
     * @param array<int, array{id:int|string, amount:int|string}> $mosaics
     */
    public static function fromParamsWithMosaics(
        int $networkType,
        int $deadline,
        int $maxFee,
        string $recipientRaw24,
        string $messagePlain,
        array $mosaics
    ): string {
        // まず message までを既存 fromParams で組む
        $bin = self::fromParams($networkType, $deadline, $maxFee, $recipientRaw24, $messagePlain);

        // ---- ここから mosaics を追加直列化 ----
        // 多くのSymbol系実装では count=u8 を使う想定
        $count = count($mosaics);
        if ($count > 255) {
            throw new \InvalidArgumentException('too many mosaics (max 255)');
        }
        $tail = pack('C', $count); // mosaicCount(u8)

        foreach ($mosaics as $i => $mo) {
            if (!isset($mo['id'], $mo['amount'])) {
                throw new \InvalidArgumentException("mosaic[$i] must have id, amount");
            }
            $tail .= self::packU64LE($mo['id']);     // id u64le
            $tail .= self::packU64LE($mo['amount']);  // amount u64le
        }

        $bin .= $tail;

        // size を実長で書き戻し
        $total = strlen($bin);
        $bin   = pack('V', $total) . substr($bin, 4);

        return $bin;
    }

    /** 署名対象：signature/signaturePublicKey をゼロ化 + generationHash(32B) 前置 */
    public static function bytesToSign(string $unsignedPayload, string $generationHashHex): string
    {
        $p = $unsignedPayload;

        $sigOff = TransferTransactionV1::SIGNATURE_OFFSET;
        $p = substr($p, 0, $sigOff)
           . str_repeat("\x00", TransferTransactionV1::SIGNATURE_SIZE)
           . substr($p, $sigOff + TransferTransactionV1::SIGNATURE_SIZE);

        $signerOff = TransferTransactionV1::SIGNER_OFFSET;
        $p = substr($p, 0, $signerOff)
           . str_repeat("\x00", TransferTransactionV1::SIGNER_SIZE)
           . substr($p, $signerOff + TransferTransactionV1::SIGNER_SIZE);

        return (string) hex2bin($generationHashHex) . $p;
    }

    /** 署名64Bと公開鍵32Bを所定位置に埋め戻す */
    public static function embedSignature(string $unsignedPayload, string $signature64, PublicKey $pk): string
    {
        $p = $unsignedPayload;

        $sigOff = TransferTransactionV1::SIGNATURE_OFFSET;
        $p = substr($p, 0, $sigOff) . $signature64 . substr($p, $sigOff + 64);

        $signerOff = TransferTransactionV1::SIGNER_OFFSET;
        $p = substr($p, 0, $signerOff) . $pk->bytes() . substr($p, $signerOff + 32);

        return $p;
    }
}