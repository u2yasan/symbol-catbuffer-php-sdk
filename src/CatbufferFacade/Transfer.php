<?php
declare(strict_types=1);

namespace SymbolSdk\CatbufferFacade;

use SymbolSdk\Catbuffer\TransferTransactionV3;
use SymbolSdk\Catbuffer\TransferTransactionV1;
use SymbolSdk\CryptoTypes\PublicKey;
use SymbolSdk\Util\Serializer;
final class Transfer {
    /** 生成（V3固定） */
    public static function fromParamsWithMosaics(
        int $networkType, int $deadlineMs, int $maxFee,
        string $recipientRaw24, string $messagePlain, array $mosaics
    ): string {
        if (strlen($recipientRaw24) !== 24) {
            throw new \InvalidArgumentException('recipientRaw24 must be 24 bytes');
        }
        // message: Plain = 0x00 + text
        $messageBytes = "\x00" . $messagePlain;
        $messageSize  = strlen($messageBytes);

        // mosaics: id昇順
        usort($mosaics, fn($a,$b)=> (int)$a['id'] <=> (int)$b['id']);
        $mosaicsCount = count($mosaics);
        if ($mosaicsCount > 255) throw new \InvalidArgumentException('too many mosaics');

        $tx = new TransferTransactionV3(
            TransferTransactionV3::SIZE,
            0,
            str_repeat("\x00", 64),
            str_repeat("\x00", 32),
            3, // version
            $networkType,
            0x4154,
            $maxFee,
            $deadlineMs,
            $recipientRaw24,
            $messageSize,
            $mosaicsCount,
            $messageBytes
        );

        $bin = $tx->serialize();

        // mosaics entries を末尾に連結（count は連結しない）
        foreach ($mosaics as $m) {
            if (!array_key_exists('id', $m))     throw new \InvalidArgumentException('mosaic id missing');
            if (!array_key_exists('amount', $m)) throw new \InvalidArgumentException('mosaic amount missing');

            // ← これで HEX / hi-lo / int どれでもOK
            $bin .= Serializer::u64le_from_any($m['id']);

            // amount も共通経路に
            $bin .= Serializer::u64le_from_amount($m['amount']);
        }

        // 先頭 size 上書き
        $total = strlen($bin);
        $bin = pack('V', $total) . substr($bin, 4);
        return $bin;
    }

    /** 読み取り（V1/V3 自動判別） */
    public static function parse(string $payload): TransferTransactionV1|TransferTransactionV3 {
        $version = ord($payload[104]);
        if (3 === $version) {
            // V3 の固定部だけ読む簡易ラッパ（完全な parse が必要なら別途実装）
            $off = 0;
            $size = unpack('V', substr($payload, $off, 4))[1]; $off+=4;
            $reserved = unpack('V', substr($payload, $off, 4))[1]; $off+=4;
            $sig = substr($payload, $off, 64); $off+=64;
            $signer = substr($payload, $off, 32); $off+=32;
            $ver = ord($payload[$off++]);
            $net = ord($payload[$off++]);
            $type = unpack('v', substr($payload, $off, 2))[1]; $off+=2;
            $feeLoHi = unpack('V2', substr($payload, $off, 8)); $off+=8;
            $fee = ($feeLoHi[2] << 32) | $feeLoHi[1];
            $dlLoHi = unpack('V2', substr($payload, $off, 8)); $off+=8;
            $deadline = ($dlLoHi[2] << 32) | $dlLoHi[1];
            $recipient = substr($payload, $off, 24); $off+=24;
            $msgSize = unpack('v', substr($payload, $off, 2))[1]; $off+=2;
            $mCount = ord($payload[$off++]);
            $message = substr($payload, $off, $msgSize); $off += $msgSize;
            return new TransferTransactionV3($size,$reserved,$sig,$signer,$ver,$net,$type,$fee,$deadline,$recipient,$msgSize,$mCount,$message);
        }
        // それ以外は V1 と見なして読む（serialize は提供しない＝作成禁止）
        return TransferTransactionV1::fromBytes($payload);
    }

    /** 署名対象（verifiable data 以降 + gen hash プレフィックス） */
    public static function bytesToSign(string $unsignedPayload, string $genHashHex): string {
        $gen = hex2bin($genHashHex);
        return $gen . substr($unsignedPayload, 104); // 104=verifiable data start
    }

    /** 署名と signer をオフセットに in-place で埋め戻し */
    public static function embedSignature(string $unsignedPayload, string $signature64, PublicKey $pk): string {
        if (strlen($signature64) !== 64) throw new \InvalidArgumentException('sig must be 64B');
        $pkBytes = $pk->bytes(); if (strlen($pkBytes)!==32) throw new \InvalidArgumentException('pk must be 32B');

        $sigOff = 8;  $signerOff = 72;
        $p = substr($unsignedPayload, 0, $sigOff) . $signature64 . substr($unsignedPayload, $sigOff + 64);
        $p = substr($p, 0, $signerOff) . $pkBytes . substr($p, $signerOff + 32);

        // 簡易検証
        if (substr($p, $sigOff, 64) !== $signature64) throw new \RuntimeException('sig not written');
        if (substr($p, $signerOff, 32) !== $pkBytes) throw new \RuntimeException('pk not written');
        return $p;
    }
}