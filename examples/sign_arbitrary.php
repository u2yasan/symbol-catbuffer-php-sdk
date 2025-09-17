<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/bootstrap.php';

use SymbolSdk\CryptoTypes\PrivateKey;
use SymbolSdk\CryptoTypes\KeyPair;
use SymbolSdk\Signer\TransactionSigner;
use SymbolSdk\Signer\TxHash;

// 引数/環境変数
$skHex = getenv('SK') ?: ($argv[1] ?? '');
if (!$skHex) { fwrite(STDERR, "Usage: SK=<hex32> php examples/sign_arbitrary.php [unsignedHex]\n"); exit(1); }
$unsignedHex = $argv[2] ?? ($argv[1] ?? '');
if (strlen($unsignedHex) === 64 || strlen($unsignedHex) === 0) {
    // 2引数形式 or 未指定ならダミー生成
    $unsignedBin = random_bytes(180);
} else {
    $unsignedBin = (string) hex2bin($unsignedHex);
}

$kp = KeyPair::fromPrivateKey(PrivateKey::fromHex($skHex));

// 署名対象: 今はそのまま（catbuffer導入後に差し替え）
$bytesToSignBuilder = fn (string $unsigned) => $unsigned;
// 署名埋め込み: 今は末尾に付与（後でフォーマットに合わせて変更）
$embedSignature     = fn (string $unsigned, string $sig64) => $unsigned . $sig64;

$signedPayload = TransactionSigner::signAndEmbed($unsignedBin, $kp, $bytesToSignBuilder, $embedSignature);
$hash = TxHash::sha3_256_of_payload($signedPayload);

echo "signedPayloadHex=", bin2hex($signedPayload), PHP_EOL;
echo "txHash=", bin2hex($hash), PHP_EOL;
