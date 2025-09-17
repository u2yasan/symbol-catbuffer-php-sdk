<?php

declare(strict_types=1);

namespace SymbolSdk\Transactions;

/**
 * AggregateCompleteTransaction
 * - 共通ヘッダ128B対応
 * - cosignatures 付き.
 *
 * 注意:
 *  - 親 AbstractTransaction の readU16LEAt / readU32LEAt / readU64LEDecAt / u64LE を再定義しない
 *  - 型宣言済み引数に対する is_string / is_int チェックは行わない（PHPStan 用）
 */
final class AggregateCompleteTransaction extends AbstractTransaction
{
    /** @var string 署名者公開鍵（32B） */
    private string $signerPublicKey;

    /** @var int バージョン（0..255想定） */
    private int $ver;

    /** @var string ペイロードハッシュ（32B） */
    private string $payloadHash;

    /** @var string マークルハッシュ（32B） */
    private string $merkleHash;

    /** @var list<array{signerPublicKey:string, signature:string}> */
    private array $cosignatures;

    /**
     * @param list<array{signerPublicKey:string, signature:string}> $cosignatures
     */
    public function __construct(
        string $signerPublicKey,
        int $ver,
        string $payloadHash,
        string $merkleHash,
        array $cosignatures,
        string $headerRaw,
        int $size,
        int $version,
        int $network,
        int $type,
        string $maxFeeDec,
        string $deadlineDec,
    ) {
        // 値域チェックのみ（型は宣言で保証）
        if (32 !== \strlen($signerPublicKey)) {
            throw new \InvalidArgumentException('signerPublicKey must be 32 bytes');
        }

        if ($ver < 0) {
            throw new \InvalidArgumentException('version must be non-negative');
        }

        if (32 !== \strlen($payloadHash)) {
            throw new \InvalidArgumentException('payloadHash must be 32 bytes');
        }

        if (32 !== \strlen($merkleHash)) {
            throw new \InvalidArgumentException('merkleHash must be 32 bytes');
        }

        // cosignatures は list<shape> 前提。実行時は長さのみ検証。
        /** @var list<array{signerPublicKey:string, signature:string}> $normalized */
        $normalized = [];

        foreach ($cosignatures as $i => $item) {
            $pub = $item['signerPublicKey'];
            $sig = $item['signature'];

            if (32 !== \strlen($pub)) {
                throw new \InvalidArgumentException("cosignatures[$i].signerPublicKey must be 32 bytes");
            }

            if (64 !== \strlen($sig)) {
                throw new \InvalidArgumentException("cosignatures[$i].signature must be 64 bytes");
            }
            $normalized[] = ['signerPublicKey' => $pub, 'signature' => $sig];
        }

        parent::__construct($headerRaw, $size, $version, $network, $type, $maxFeeDec, $deadlineDec);

        $this->signerPublicKey = $signerPublicKey;
        $this->ver = $ver;
        $this->payloadHash = $payloadHash;
        $this->merkleHash = $merkleHash;
        $this->cosignatures = $normalized;
    }

    /**
     * バイナリ→オブジェクト.
     */
    public static function fromBinary(string $binary): self
    {
        $h = self::parseHeader($binary);
        $offset = $h['offset'];

        $len = \strlen($binary);

        if ($len < $offset + 32 + 1 + 32 + 32 + 4) {
            throw new \RuntimeException('Unexpected EOF while reading AggregateCompleteTransaction');
        }

        $signerPublicKey = \substr($binary, $offset, 32);

        if (32 !== \strlen($signerPublicKey)) {
            throw new \RuntimeException('Unexpected EOF while reading signerPublicKey');
        }
        $offset += 32;

        $ver = \ord($binary[$offset]);
        ++$offset;

        $payloadHash = \substr($binary, $offset, 32);

        if (32 !== \strlen($payloadHash)) {
            throw new \RuntimeException('Unexpected EOF while reading payloadHash');
        }
        $offset += 32;

        $merkleHash = \substr($binary, $offset, 32);

        if (32 !== \strlen($merkleHash)) {
            throw new \RuntimeException('Unexpected EOF while reading merkleHash');
        }
        $offset += 32;

        // cosignature count (u32)
        $cosigCount = self::readU32LEAt($binary, $offset);
        $offset += 4;

        /** @var list<array{signerPublicKey:string, signature:string}> $cosignatures */
        $cosignatures = [];

        for ($i = 0; $i < $cosigCount; ++$i) {
            if ($len < $offset + 32 + 64) {
                throw new \RuntimeException("Unexpected EOF in cosignature[$i]");
            }
            $pub = \substr($binary, $offset, 32);

            if (32 !== \strlen($pub)) {
                throw new \RuntimeException("Unexpected EOF in cosignature[$i] pubkey");
            }
            $offset += 32;

            $sig = \substr($binary, $offset, 64);

            if (64 !== \strlen($sig)) {
                throw new \RuntimeException("Unexpected EOF in cosignature[$i] signature");
            }
            $offset += 64;

            $cosignatures[] = ['signerPublicKey' => $pub, 'signature' => $sig];
        }

        return new self(
            $signerPublicKey,
            $ver,
            $payloadHash,
            $merkleHash,
            $cosignatures,
            $h['headerRaw'],
            $h['size'],
            $h['version'],
            $h['network'],
            $h['type'],
            $h['maxFeeDec'],
            $h['deadlineDec']
        );
    }

    /**
     * ボディ直列化.
     */
    protected function encodeBody(): string
    {
        $out = '';
        $out .= $this->signerPublicKey;
        $out .= \chr($this->ver);
        $out .= $this->payloadHash;
        $out .= $this->merkleHash;

        $out .= \pack('V', \count($this->cosignatures));

        foreach ($this->cosignatures as $cosig) {
            $out .= $cosig['signerPublicKey'];
            $out .= $cosig['signature'];
        }

        return $out;
    }

    // 親の readU16LEAt / readU32LEAt / readU64LEDecAt / u64LE を使用する。
    // このクラスでの再定義は行わない（可視性違反や mixed 返り値の原因になる）。
}
