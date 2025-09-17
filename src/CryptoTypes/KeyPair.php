<?php
declare(strict_types=1);

namespace SymbolSdk\CryptoTypes;

final class KeyPair {
    public function __construct(
        private PrivateKey $privateKey,
        private PublicKey $publicKey
    ) {}

    public static function fromPrivateKey(PrivateKey $sk): self {
        // Ed25519: seed → keypair（libsodium）
        [$pk, $skExpanded] = self::seedToKeypair($sk->bytes());
        // $skExpandedはsodium形式64Bだが、SDKではseed32Bを保持
        if (function_exists('sodium_memzero')) {
            \sodium_memzero($skExpanded);
        }
        return new self($sk, new PublicKey($pk));
    }

    public static function generate(): self {
        $sk = PrivateKey::fromRandom();
        return self::fromPrivateKey($sk);
    }

    public function privateKey(): PrivateKey { return $this->privateKey; }
    public function publicKey(): PublicKey { return $this->publicKey; }

    /** @return array{0:string,1:string} [pk32, sk64] */
    private static function seedToKeypair(string $seed): array {
        $kp = \sodium_crypto_sign_seed_keypair($seed);
        $pk = \sodium_crypto_sign_publickey($kp);   // 32B
        $sk = \sodium_crypto_sign_secretkey($kp);   // 64B (seed+pk)
        return [$pk, $sk];
    }
}
