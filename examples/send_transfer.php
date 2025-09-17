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

// === Builder ===
$b = (new TransferTransactionBuilder())
    ->networkType($net)
    ->deadline($deadlineMs)              // ★ ここが重要
    ->maxFee(200000)
    ->recipient((string)hex2bin($raw24))
    ->message("hello symbol")
    ->mosaics([['id' => 0x6BED913FA20223F8, 'amount' => 1000000]])
    ->generationHash($genHash)
    ->keyPair($kp);

// === 署名と出力 ===
$signed = $b->signWith($kp);

echo 'payloadHex=', bin2hex($signed), PHP_EOL;
echo 'hash=', bin2hex(TxHash::sha3_256_of_payload($signed)), PHP_EOL;