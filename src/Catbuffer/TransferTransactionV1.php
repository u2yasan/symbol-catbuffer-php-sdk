<?php
declare(strict_types=1);

namespace SymbolSdk\Catbuffer;

final class TransferTransactionV1 {
    public const SIZE = 151;             // 固定部 (148 + messageSize(2) + mosaicsCount(1))
    public const SIGNATURE_OFFSET = 8;
    public const SIGNATURE_SIZE   = 64;
    public const SIGNER_OFFSET    = 72;
    public const SIGNER_SIZE      = 32;

    public function __construct(
        public int $size,
        public int $reserved1,
        public string $signature,        // 64B
        public string $signerPublicKey,  // 32B
        public int $version,             // u8
        public int $network,             // u8
        public int $type,                // u16le
        public int $maxFee,              // u64le
        public int $deadline,            // u64le
        public string $recipientRaw24,   // 24B
        public int $messageSize,         // u16le
        public int $mosaicsCount,        // u8  ★ 追加
        public string $message           // bytes(messageSize)
    ) {}

    public function serialize(): string {
        $out  = pack('V', $this->size);
        $out .= pack('V', $this->reserved1);
        $out .= $this->signature;
        $out .= $this->signerPublicKey;
        $out .= pack('C', $this->version);
        $out .= pack('C', $this->network);
        $out .= pack('v', $this->type);
        // u64 little-endian（環境非依存）
        $out .= pack('V2', $this->maxFee & 0xFFFFFFFF, ($this->maxFee >> 32) & 0xFFFFFFFF);
        $out .= pack('V2', $this->deadline & 0xFFFFFFFF, ($this->deadline >> 32) & 0xFFFFFFFF);
        $out .= $this->recipientRaw24;
        $out .= pack('v', $this->messageSize);
        $out .= pack('C', $this->mosaicsCount);         // ★ ここで mosaicsCount
        $out .= $this->message;                         // ★ その後に message 本体
        return $out;
    }
}