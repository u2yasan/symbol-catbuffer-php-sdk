<?php
declare(strict_types=1);

namespace SymbolSdk\Builder;

use SymbolSdk\CatbufferFacade\Transfer as TransferFacade;
use SymbolSdk\CryptoTypes\KeyPair;

final class TransferTransactionBuilder extends BaseBuilder
{
    private string $recipientAddress;   // raw24 bytes
    private string $message = '';
    /** @var array<int, array{id:int|string, amount:int|string}> */
    private array $mosaics = [];

    private string $generationHashHex = '';
    private ?KeyPair $kp = null;

    /** 署名で使った未署名ペイロードをキャッシュ（同一バッファへ埋め戻すため） */
    private ?string $unsignedCache = null;

    public function recipient(string $addrRaw24): static { $this->recipientAddress = $addrRaw24; return $this; }
    public function message(string $m): static { $this->message = $m; return $this; }
    public function mosaics(array $list): static { $this->mosaics = $list; return $this; }
    public function generationHash(string $hex32): static { $this->generationHashHex = $hex32; return $this; }
    public function keyPair(KeyPair $kp): static { $this->kp = $kp; return $this; }

    /** bytesToSign を作る（未署名ペイロードをキャッシュ） */
    protected function serializeForSigning(): string
    {
        $this->unsignedCache = TransferFacade::fromParamsWithMosaics(
            networkType:    $this->networkType,
            deadline:       $this->deadline,
            maxFee:         $this->maxFee,
            recipientRaw24: $this->recipientAddress,
            messagePlain:   $this->message,
            mosaics:        $this->mosaics
        );
        return TransferFacade::bytesToSign($this->unsignedCache, $this->generationHashHex);
    }

    /** 署名をキャッシュ済み未署名ペイロードへ in-place 埋め戻す */
    protected function embedSignature(string $signature): string
    {
        if (null === $this->unsignedCache) {
            throw new \LogicException('serializeForSigning() was not called before embedSignature()');
        }
        if (!$this->kp) {
            throw new \LogicException('keyPair() must be set before signWith()');
        }
        return TransferFacade::embedSignature(
            $this->unsignedCache,
            $signature,
            $this->kp->publicKey()
        );
    }
}