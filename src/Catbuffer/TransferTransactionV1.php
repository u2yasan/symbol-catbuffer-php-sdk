<?php
declare(strict_types=1);

namespace SymbolSdk\Catbuffer;

final class TransferTransactionV1 {
    public const SIZE = 148;
    public const SIGNATURE_OFFSET = 8;
    public const SIGNATURE_SIZE   = 64;
    public const SIGNER_OFFSET    = 72;
    public const SIGNER_SIZE      = 32;

    // 解析用の最低限フィールド
    public function __construct(
        public int $size,
        public int $reserved1,
        public string $signature,        // 64
        public string $signerPublicKey,  // 32
        public int $version,             // u8 (1想定)
        public int $network,             // u8
        public int $type,                // u16le
        public int $maxFee,              // u64le
        public int $deadline,            // u64le
        public string $recipientRaw24,   // 24
        public int $messageSize,         // u16le
        public string $message           // bytes(messageSize)
        // mosaicsCount は V1 には無い（配列直列が後続）
    ) {}

    /** バイト列から V1 をパース（読み取り専用） */
    public static function fromBytes(string $bytes): self {
        $off = 0;
        $size = unpack('V', substr($bytes, $off, 4))[1]; $off += 4;
        $reserved1 = unpack('V', substr($bytes, $off, 4))[1]; $off += 4;
        $signature = substr($bytes, $off, 64); $off += 64;
        $signer = substr($bytes, $off, 32); $off += 32;
        $version = ord($bytes[$off++]);
        $network = ord($bytes[$off++]);
        $type    = unpack('v', substr($bytes, $off, 2))[1]; $off += 2;
        $maxFeeLoHi = unpack('V2', substr($bytes, $off, 8)); $off += 8;
        $maxFee = ($maxFeeLoHi[2] << 32) | $maxFeeLoHi[1];
        $dlLoHi = unpack('V2', substr($bytes, $off, 8)); $off += 8;
        $deadline = ($dlLoHi[2] << 32) | $dlLoHi[1];
        $recipient = substr($bytes, $off, 24); $off += 24;
        $msgSize = unpack('v', substr($bytes, $off, 2))[1]; $off += 2;
        $message = substr($bytes, $off, $msgSize); $off += $msgSize;
        return new self($size,$reserved1,$signature,$signer,$version,$network,$type,$maxFee,$deadline,$recipient,$msgSize,$message);
    }

    // 送信用途の serialize は用意しない（＝作成禁止）
}
