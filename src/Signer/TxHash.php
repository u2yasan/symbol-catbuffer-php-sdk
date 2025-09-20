<?php
declare(strict_types=1);

namespace SymbolSdk\Signer;

final class TxHash {
    /** preimage: sig[0..31] + signer + genHash + body */
    private static function preimage(string $signedPayload, string $genHashHex): string {
        $g = hex2bin($genHashHex);
        return substr($signedPayload, 8, 32)   // signature first 32 bytes
             . substr($signedPayload, 72, 32)  // signer public key
             . $g
             . substr($signedPayload, 104);    // verifiable data
    }

    /** 32B（64hex）: SHA3-256 */
    public static function sha3_256_hex(string $signedPayload, string $genHashHex): string {
        return bin2hex(hash('sha3-256', self::preimage($signedPayload, $genHashHex), true));
    }
}