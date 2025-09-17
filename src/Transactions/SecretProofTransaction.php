<?php
declare(strict_types=1);
namespace SymbolSdk\Transactions;

final class SecretProofTransaction extends AbstractTransaction
{
    public int $hashAlgorithm;
    public string $secret;
    public string $recipientAddress;
    public string $proof;

    /**
     * @param int $hashAlgorithm
     * @param string $secret 32 bytes
     * @param string $recipientAddress 24 bytes
     * @param string $proof 任意長
     * @param string $headerRaw
     * @param int $size
     * @param int $version
     * @param int $network
     * @param int $type
     * @param string $maxFeeDec
     * @param string $deadlineDec
     */
    public function __construct(
        int $hashAlgorithm,
        string $secret,
        string $recipientAddress,
        string $proof,
        string $headerRaw,
        int $size,
        int $version,
        int $network,
        int $type,
        string $maxFeeDec,
        string $deadlineDec
    ) {
        if (!in_array($hashAlgorithm, [0, 1, 2, 3], true)) {
            throw new \InvalidArgumentException('hashAlgorithm must be 0,1,2,3');
        }
        if (strlen($secret) !== 32) {
            throw new \InvalidArgumentException('secret must be 32 bytes');
        }
        if (strlen($recipientAddress) !== 24) {
            throw new \InvalidArgumentException('recipientAddress must be 24 bytes');
        }
        if (strlen($proof) > 65535) {
            throw new \InvalidArgumentException('proof too long');
        }
        $this->hashAlgorithm = $hashAlgorithm;
        $this->secret = $secret;
        $this->recipientAddress = $recipientAddress;
        $this->proof = $proof;
        parent::__construct($headerRaw, $size, $version, $network, $type, $maxFeeDec, $deadlineDec);
    }

    /**
     * @param string $binary
     * @return self
     */
    public static function fromBinary(string $binary): self
    {
        $h = self::parseHeader($binary);
        $offset = $h['offset'];
        $body = self::decodeBody($binary, $offset);
        return new self(
            $body['hashAlgorithm'],
            $body['secret'],
            $body['recipientAddress'],
            $body['proof'],
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
     * @param string $binary
     * @param int $offset
     * @return array{hashAlgorithm:int,secret:string,recipientAddress:string,proof:string}
     */
    protected static function decodeBody(string $binary, int $offset): array
    {
        $len = strlen($binary);
        $remaining = $len - $offset;
        if ($remaining < 1 + 32 + 24 + 2) {
            throw new \RuntimeException('Unexpected EOF: need at least 59 bytes for SecretProofTransaction body');
        }
        $hashAlgorithm = ord($binary[$offset]);
        if (!in_array($hashAlgorithm, [0, 1, 2, 3], true)) {
            throw new \InvalidArgumentException('hashAlgorithm must be 0,1,2,3');
        }
        $secret = substr($binary, $offset + 1, 32);
        if (strlen($secret) !== 32) {
            throw new \RuntimeException('Unexpected EOF: need 32 bytes for secret');
        }
        $recipientAddress = substr($binary, $offset + 33, 24);
        if (strlen($recipientAddress) !== 24) {
            throw new \RuntimeException('Unexpected EOF: need 24 bytes for recipientAddress');
        }
        $proofSizeChunk = substr($binary, $offset + 57, 2);
        if (strlen($proofSizeChunk) !== 2) {
            throw new \RuntimeException('Unexpected EOF: need 2 bytes for proofSize');
        }
        $u = unpack('vval', $proofSizeChunk);
        if ($u === false) {
            throw new \RuntimeException('unpack failed');
        }
        $proofSize = $u['val'];
        $proofOffset = $offset + 59;
        $remainingProof = $len - $proofOffset;
        if ($remainingProof < $proofSize) {
            throw new \RuntimeException("Unexpected EOF: need {$proofSize} bytes for proof, have {$remainingProof}");
        }
        $proof = substr($binary, $proofOffset, $proofSize);
        if (strlen($proof) !== $proofSize) {
            throw new \RuntimeException('Unexpected EOF: proof truncated');
        }
        return [
            'hashAlgorithm' => $hashAlgorithm,
            'secret' => $secret,
            'recipientAddress' => $recipientAddress,
            'proof' => $proof
        ];
    }

    /**
     * @return string
     */
    protected function encodeBody(): string
    {
        $out = '';
        $out .= chr($this->hashAlgorithm);
        $out .= $this->secret;
        $out .= $this->recipientAddress;
        $proofLen = strlen($this->proof);
        if ($proofLen > 65535) {
            throw new \InvalidArgumentException('proof too long');
        }
        $out .= pack('v', $proofLen);
        $out .= $this->proof;
        return $out;
    }
}
