<?php
declare(strict_types=1);

namespace SymbolSdk\CatbufferFacade;

use SymbolSdk\Catbuffer\TransferTransactionV1;
use SymbolSdk\CryptoTypes\PublicKey;

/**
 * Transfer トランザクションの直列化ヘルパ（MVP）
 * - message: 先頭0x00 + UTF-8本文
 * - mosaics: count(u8) + [id(u64le) amount(u64le)] * count
 * - size: serialize 後に実長を先頭4Bへ書き戻し
 */
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

    /** message の直列化（Plain） */
    private static function buildMessage(string $messagePlain): array {
        $msg = "\x00" . $messagePlain;     // 0x00: Plain
        $len = strlen($msg);               // u16le
        return [$len, $msg];
    }

    /** mosaics の直列化 */
    private static function buildMosaics(array $mosaics): string {
        $count = count($mosaics);
        if ($count > 255) {
            throw new \InvalidArgumentException('too many mosaics (max 255)');
        }
        $out = pack('C', $count);
        foreach ($mosaics as $i => $m) {
            if (!isset($m['id'], $m['amount'])) {
                throw new \InvalidArgumentException("mosaic[$i] must have id, amount");
            }
            $out .= self::packU64LE($m['id']);
            $out .= self::packU64LE($m['amount']);
        }
        return $out;
    }

    // 直前までの内容はそのまま。fromParamsWithMosaics だけ差し替え
    public static function fromParamsWithMosaics(
        int $networkType,
        int $deadline,
        int $maxFee,
        string $recipientRaw24,
        string $messagePlain,
        array $mosaics
    ): string {
        if (strlen($recipientRaw24) !== 24) {
            throw new \InvalidArgumentException('recipientRaw24 must be 24 bytes');
        }

        // message（Plain: 先頭 0x00）
        $messageBytes = "\x00" . $messagePlain;
        $messageSize  = strlen($messageBytes);

        // mosaics 構築（id昇順に並べる）
        usort($mosaics, fn($a,$b) => (int)$a['id'] <=> (int)$b['id']);
        $mosaicsCount = count($mosaics);
        if ($mosaicsCount > 255) {
            throw new \InvalidArgumentException('too many mosaics (max 255)');
        }
        $mosaicsBytes = pack('C', $mosaicsCount);
        foreach ($mosaics as $i => $m) {
            if (!isset($m['id'], $m['amount'])) {
                throw new \InvalidArgumentException("mosaic[$i] must have id, amount");
            }
            $id = (int)$m['id'];
            $amt= (int)$m['amount'];
            $mosaicsBytes .= pack('V2', $id & 0xFFFFFFFF, ($id >> 32) & 0xFFFFFFFF);
            $mosaicsBytes .= pack('V2', $amt & 0xFFFFFFFF, ($amt >> 32) & 0xFFFFFFFF);
        }

        // ★ ここがポイント：messageSize → mosaicsCount → message の順でシリアライズ
        $tx = new TransferTransactionV1(
            TransferTransactionV1::SIZE,    // 仮サイズ（後で上書き）
            0,
            str_repeat("\x00", 64),
            str_repeat("\x00", 32),
            1,
            $networkType,
            0x4154,
            $maxFee,
            $deadline,
            $recipientRaw24,
            $messageSize,
            $mosaicsCount,                  // ★ 追加
            $messageBytes
        );

        // まず固定部＋message まで
        $bin = $tx->serialize();

        // 末尾に mosaics 配列を追加（count＋entries）
        $bin .= substr($mosaicsBytes, 0);   // すでに count 先頭につけた

        // size を実長へ
        $total = strlen($bin);
        $bin   = pack('V', $total) . substr($bin, 4);
        return $bin;
    }

    /** 署名対象：signature / signer を 0 埋め & generationHash(32B) を前置 */
    public static function bytesToSign(string $unsignedPayload, string $generationHashHex): string
    {
        $p = $unsignedPayload;

        $sigOff = TransferTransactionV1::SIGNATURE_OFFSET; // 8
        $p = substr($p, 0, $sigOff)
           . str_repeat("\x00", TransferTransactionV1::SIGNATURE_SIZE)
           . substr($p, $sigOff + TransferTransactionV1::SIGNATURE_SIZE);

        $signerOff = TransferTransactionV1::SIGNER_OFFSET; // 72
        $p = substr($p, 0, $signerOff)
           . str_repeat("\x00", TransferTransactionV1::SIGNER_SIZE)
           . substr($p, $signerOff + TransferTransactionV1::SIGNER_SIZE);

        return (string) hex2bin($generationHashHex) . $p;
    }

    public static function embedSignature(string $unsignedPayload, string $signature64, PublicKey $pk): string
    {
        // --- 1) 事前バリデーション ---
        if (strlen($signature64) !== 64) {
            throw new \InvalidArgumentException('signature must be 64 bytes, got '.strlen($signature64));
        }
        $pkBytes = $pk->bytes();
        if (strlen($pkBytes) !== 32) {
            throw new \InvalidArgumentException('public key must be 32 bytes, got '.strlen($pkBytes));
        }
        $len = strlen($unsignedPayload);
        $sigOff = \SymbolSdk\Catbuffer\TransferTransactionV1::SIGNATURE_OFFSET; // 8
        $signerOff = \SymbolSdk\Catbuffer\TransferTransactionV1::SIGNER_OFFSET; // 72
        if ($len < $signerOff + 32) {
            throw new \InvalidArgumentException("payload too short: $len bytes");
        }

        // --- 2) in-place 置換（長さ固定） ---
        // helper: 部分上書き（長さ厳密）
        $overwrite = static function (string $buf, int $off, string $data): string {
            return substr($buf, 0, $off) . $data . substr($buf, $off + strlen($data));
        };

        $p = $unsignedPayload;
        // 先に signature（64B）を所定位置へ
        $p = $overwrite($p, $sigOff, $signature64);
        // 次に signerPublicKey（32B）を所定位置へ
        $p = $overwrite($p, $signerOff, $pkBytes);

        // --- 3) 直後に自己検証（ゼロでないこと／一致すること）---
        $postSig   = substr($p, $sigOff, 64);
        $postPk    = substr($p, $signerOff, 32);
        if ($postSig !== $signature64) {
            throw new \RuntimeException('embedSignature failed: signature not written correctly');
        }
        if ($postPk !== $pkBytes) {
            throw new \RuntimeException('embedSignature failed: public key not written correctly');
        }
        return $p;
    }
}