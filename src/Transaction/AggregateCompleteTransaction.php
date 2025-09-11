<?php
declare(strict_types=1);

namespace Transaction;

use InvalidArgumentException;
use RuntimeException;

final readonly class AggregateBondedTransaction
{
    public const TRANSACTIONS_HASH_SIZE = 32;

    public function __construct(
        public string $transactionsHash,
        /** @var Cosignature[] */
        public array $cosignatures,
        public string $payload,
    ) {
        if (strlen($transactionsHash) !== self::TRANSACTIONS_HASH_SIZE) {
            throw new InvalidArgumentException('Invalid transactionsHash length');
        }
        foreach ($cosignatures as $cosignature) {
            if (!$cosignature instanceof Cosignature) {
                throw new InvalidArgumentException('cosignatures must be array of Cosignature');
            }
        }
        if (!is_string($payload)) {
            throw new InvalidArgumentException('payload must be string');
        }
    }

    public static function fromBinary(string $binary): self
    {
        $offset = 0;
        $len = strlen($binary);

        if ($len < 4 + self::TRANSACTIONS_HASH_SIZE + 4) {
            throw new InvalidArgumentException('Insufficient data for AggregateBondedTransaction');
        }

        // payload size (little endian)
        $chunk = substr($binary, $offset, 4);
        if (strlen($chunk) !== 4) {
            throw new \RuntimeException('Unexpected EOF while reading payloadSize (need 4 bytes).');
        }
        $u = unpack('Vvalue', $chunk);
        if ($u === false) {
            throw new \RuntimeException('unpack failed for payloadSize.');
        }
        /** @var array{value:int} $u */
        $payloadSize = $u['value'];
        $offset += 4;

        // transactionsHash (32 bytes)
        $transactionsHash = substr($binary, $offset, self::TRANSACTIONS_HASH_SIZE);
        $offset += self::TRANSACTIONS_HASH_SIZE;

        // cosignature count (little endian)
        $chunk = substr($binary, $offset, 4);
        if (strlen($chunk) !== 4) {
            throw new \RuntimeException('Unexpected EOF while reading payloadSize (need 4 bytes).');
        }
        $u = unpack('Vcount', $chunk);
        if ($u === false) {
            throw new \RuntimeException('unpack failed for cosignatureCount.');
        }
        /** @var array{count:int} $u */
        $cosignatureCount = $u['count'];
        $offset += 4;

        // cosignatures
        $cosignatures = [];
        for ($i = 0; $i < $cosignatureCount; ++$i) {
            if ($offset + Cosignature::SIZE > $len) {
                throw new InvalidArgumentException('Insufficient data for Cosignature');
            }
            $cosignature = Cosignature::fromBinary(substr($binary, $offset, Cosignature::SIZE));
            $cosignatures[] = $cosignature;
            $offset += Cosignature::SIZE;
        }

        // payload
        if ($offset + $payloadSize > $len) {
            throw new InvalidArgumentException('Insufficient data for payload');
        }
        $payload = substr($binary, $offset, $payloadSize);
        $offset += $payloadSize;

        // end check: require offset == $len
        if ($offset !== $len) {
            throw new InvalidArgumentException('Unexpected extra bytes');
        }

        return new self($transactionsHash, $cosignatures, $payload);
    }

    public function toBinary(): string
    {
        $payloadSize = strlen($this->payload);
        $cosignatureCount = count($this->cosignatures);

        $binary = pack('V', $payloadSize);
        $binary .= $this->transactionsHash;
        $binary .= pack('V', $cosignatureCount);
        foreach ($this->cosignatures as $cosignature) {
            $binary .= $cosignature->toBinary();
        }
        $binary .= $this->payload;

        return $binary;
    }
}

final readonly class Cosignature
{
    public const SIGNER_PUBLIC_KEY_SIZE = 32;
    public const SIGNATURE_SIZE = 64;
    public const SIZE = self::SIGNER_PUBLIC_KEY_SIZE + self::SIGNATURE_SIZE;

    public function __construct(
        public string $signerPublicKey,
        public string $signature
    ) {
        if (strlen($signerPublicKey) !== self::SIGNER_PUBLIC_KEY_SIZE) {
            throw new InvalidArgumentException('Invalid signerPublicKey length');
        }
        if (strlen($signature) !== self::SIGNATURE_SIZE) {
            throw new InvalidArgumentException('Invalid signature length');
        }
    }

    public static function fromBinary(string $binary): self
    {
        if (strlen($binary) !== self::SIZE) {
            throw new InvalidArgumentException('Cosignature binary size mismatch');
        }
        $signerPublicKey = substr($binary, 0, self::SIGNER_PUBLIC_KEY_SIZE);
        $signature = substr($binary, self::SIGNER_PUBLIC_KEY_SIZE, self::SIGNATURE_SIZE);
        return new self($signerPublicKey, $signature);
    }

    public function toBinary(): string
    {
        return $this->signerPublicKey . $this->signature;
    }
}
