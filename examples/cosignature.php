<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/bootstrap.php';

use SymbolSdk\CryptoTypes\PrivateKey;
use SymbolSdk\CryptoTypes\KeyPair;
use SymbolSdk\Signer\Cosignature;

$skHex = getenv('SK') ?: ($argv[1] ?? '');
$parentHashHex = getenv('PARENT_HASH') ?: ($argv[2] ?? '');
if (!$skHex || !$parentHashHex) {
    fwrite(STDERR, "Usage: SK=<hex32> PARENT_HASH=<hex64> php examples/cosignature.php\n");
    exit(1);
}
$kp = KeyPair::fromPrivateKey(PrivateKey::fromHex($skHex));
$dto = Cosignature::toDtoHex((string) hex2bin($parentHashHex), $kp);

echo json_encode($dto, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PHP_EOL;
