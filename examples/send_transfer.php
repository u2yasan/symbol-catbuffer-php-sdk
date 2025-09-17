<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/bootstrap.php';

use SymbolSdk\CryptoTypes\PrivateKey;
use SymbolSdk\CryptoTypes\KeyPair;
use SymbolSdk\Builder\TransferTransactionBuilder;
use SymbolSdk\Signer\TxHash;

// 例: 引数処理（省略可）
$skHex     = getenv('TEST_SENDER_PRIVATE_KEY') ?: '0ABF4B7CA4250A5B741C78058717BA872A4A29297048F3DA55E54A42A28FE07F'; // 32B hex
$recipient = getenv('RECIPIENT') ?: 'TDT5NHDLPIIE3A7QN7VQYSJNNH7UXO74GS6HJ4Y'; // アドレス文字列
$amount    = (int) (getenv('AMOUNT') ?: 1);
$deadline  = time() + 2*60*60;

$kp = KeyPair::fromPrivateKey(PrivateKey::fromHex($skHex));

$builder = (new TransferTransactionBuilder())
    ->networkType(152) // TESTNET
    ->deadline($deadline)
    ->maxFee(0)
    ->recipient($recipient)
    ->amount($amount)
    ->message('hello symbol');

$signedPayload = $builder->signWith($kp);
$txHash = TxHash::sha3_256_of_payload($signedPayload);

echo "payload=", bin2hex($signedPayload), PHP_EOL;
echo "hash   =", bin2hex($txHash), PHP_EOL;

// announce は HTTP クライアント層が整ったら追加
