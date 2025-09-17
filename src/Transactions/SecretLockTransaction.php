<?php
declare(strict_types=1);
namespace SymbolSdk\Transactions;

final class SecretLockTransaction extends AbstractTransaction
{
    public string $mosaicIdDec;
    public string $amountDec;
    public string $durationDec;
    public int $hashAlgorithm;
    public string $secret;
    public string $recipientAddress;

    /**
     * @param string $mosaicIdDec
     * @param string $amountDec
     * @param string $durationDec
     * @param int $hashAlgorithm
     * @param string $secret 32 bytes
     * @param string $recipientAddress 24 bytes
     * @param string $headerRaw
     * @param int $size
     * @param int $version
     * @param int $network
     * @param int $type
     * @param string $maxFeeDec
     * @param string $deadlineDec
     */
    public function __construct(
        string $mosaicIdDec,
        string $amountDec,
        string $durationDec,
        int $hashAlgorithm,
        string $secret,
        string $recipientAddress,
        string $headerRaw,
        int $size,
        int $version,
        int $network,
        int $type,
        string $maxFeeDec,
        string $deadlineDec
    ) {
        if (preg_match('/^[0-9]+$/', $mosaicIdDec) !== 1) {
            throw new \InvalidArgumentException('mosaicIdDec must be decimal string');
        }
        if (preg_match('/^[0-9]+$/', $amountDec) !== 1) {
            throw new \InvalidArgumentException('amountDec must be decimal string');
        }
        if (preg_match('/^[0-9]+$/', $durationDec) !== 1) {
            throw new \InvalidArgumentException('durationDec must be decimal string');
        }
        if ($hashAlgorithm < 0 || $hashAlgorithm > 3) {
            throw new \InvalidArgumentException('hashAlgorithm must be 0..3');
        }
        if (strlen($secret) !== 32) {
            throw new \InvalidArgumentException('secret must be 32 bytes');
        }
        if (strlen($recipientAddress) !== 24) {
            throw new \InvalidArgumentException('recipientAddress must be 24 bytes');
        }
        $this->mosaicIdDec = ltrim($mosaicIdDec, '0') === '' ? '0' : ltrim($mosaicIdDec, '0');
        $this->amountDec = ltrim($amountDec, '0') === '' ? '0' : ltrim($amountDec, '0');
        $this->durationDec = ltrim($durationDec, '0') === '' ? '0' : ltrim($durationDec, '0');
        $this->hashAlgorithm = $hashAlgorithm;
        $this->secret = $secret;
        $this->recipientAddress = $recipientAddress;
        parent::__construct($headerRaw, $size, $version, $network, $type, $maxFeeDec, $deadlineDec);
    }

    /**
     * @param string $binary
     * @return self
     */
    public static function fromBinary(string $binary): self
    {
        $header = self::parseHeader($binary);
        $body = self::decodeBody($binary, $header['offset']);
        return new self(
            $body['mosaicIdDec'],
            $body['amountDec'],
            $body['durationDec'],
            $body['hashAlgorithm'],
            $body['secret'],
            $body['recipientAddress'],
            $header['headerRaw'],
            $header['size'],
            $header['version'],
            $header['network'],
            $header['type'],
            $header['maxFeeDec'],
            $header['deadlineDec']
        );
    }

    /**
     * @param string $binary
     * @param int $offset
     * @return array{
     *   mosaicIdDec:string,
     *   amountDec:string,
     *   durationDec:string,
     *   hashAlgorithm:int,
     *   secret:string,
     *   recipientAddress:string,
     *   nextOffset:int
     * }
     */
    protected static function decodeBody(string $binary, int $offset): array
    {
        $len = strlen($binary);
        $need = 8 + 8 + 8 + 1 + 32 + 24;
        $remaining = $len - $offset;
        if ($remaining < $need) {
            throw new \RuntimeException("Unexpected EOF: need $need, have $remaining");
        }
        $mosaicIdDec = self::readU64LEDecAt($binary, $offset);
        $offset += 8;
        $amountDec = self::readU64LEDecAt($binary, $offset);
        $offset += 8;
        $durationDec = self::readU64LEDecAt($binary, $offset);
        $offset += 8;
        $hashAlgorithm = ord($binary[$offset]);
        $offset += 1;
        $secret = substr($binary, $offset, 32);
        if (strlen($secret) !== 32) {
            throw new \RuntimeException('Unexpected EOF: secret');
        }
        $offset += 32;
        $recipientAddress = substr($binary, $offset, 24);
        if (strlen($recipientAddress) !== 24) {
            throw new \RuntimeException('Unexpected EOF: recipientAddress');
        }
        $offset += 24;
        return [
            'mosaicIdDec' => $mosaicIdDec,
            'amountDec' => $amountDec,
            'durationDec' => $durationDec,
            'hashAlgorithm' => $hashAlgorithm,
            'secret' => $secret,
            'recipientAddress' => $recipientAddress,
            'nextOffset' => $offset
        ];
    }

    /**
     * @return string
     */
    protected function encodeBody(): string
    {
        $out = '';
        $out .= self::u64LE($this->mosaicIdDec);
        $out .= self::u64LE($this->amountDec);
        $out .= self::u64LE($this->durationDec);
        $out .= chr($this->hashAlgorithm);
        $out .= $this->secret;
        $out .= $this->recipientAddress;
        return $out;
    }
}
