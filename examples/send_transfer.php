<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/bootstrap.php';

use SymbolSdk\CryptoTypes\PrivateKey;
use SymbolSdk\CryptoTypes\KeyPair;
use SymbolSdk\Builder\TransferTransactionBuilder;
use SymbolSdk\Signer\TxHash;

// === 環境変数から読み込み ===
$skHex   = getenv('SK') ?: die("export SK\n");
$net     = (int)(getenv('NETWORK') ?: 152);
$genHash = getenv('GEN_HASH') ?: die("export GEN_HASH\n");
$raw24   = getenv('RECIPIENT_RAW24') ?: die("export RECIPIENT_RAW24\n");

// epochAdjustment 基準の deadline（ms）
$epoch   = (int)(getenv('EPOCH') ?: 0);
if ($epoch === 0) {
    fwrite(STDERR, "export EPOCH first (from network/properties)\n");
    exit(1);
}
$deadlineMs = ((time() - $epoch) + 2 * 60 * 60) * 1000;

// === KeyPair 準備 ===
$kp = KeyPair::fromPrivateKey(PrivateKey::fromHex($skHex));

$currencyHex = getenv('CURRENCY_HEX'); // 0x72C0212E67A08BCE を数値に
$amount     = 1000000; // 1 XYM 相当（divisibility=6 前提、最低送金量は任意）
// === Builder ===
$b = (new TransferTransactionBuilder())
    ->networkType($net)
    ->deadline($deadlineMs)              // ★ ここが重要
    ->maxFee(500000)
    ->recipient((string)hex2bin($raw24))
    ->message("hello symbol")
    ->mosaics([['id' => $currencyHex, 'amount' => $amount]])
    ->generationHash($genHash)
    ->keyPair($kp);

// === 署名と出力 ===
$signed = $b->signWith($kp);

echo 'payloadHex=', bin2hex($signed), PHP_EOL;
echo 'hash=', TxHash::sha3_256_hex($signed, $genHash), PHP_EOL;