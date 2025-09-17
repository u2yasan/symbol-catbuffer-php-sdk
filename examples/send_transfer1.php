<?php

// composerでインストールしたライブラリを読み込む
require_once __DIR__ . '/../vendor/autoload.php';

// .envファイルを読み込む
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use SymbolSdk\CryptoTypes\PrivateKey;
use SymbolSdk\Models\NetworkType;
use SymbolSdk\IdGenerator;
use SymbolSdk\Models\Address;
use SymbolSdk\Models\Deadline;
use SymbolSdk\Models\Mosaic;
use SymbolSdk\Models\NamespaceId;
use SymbolSdk\Models\NetworkCurrency;
use SymbolSdk\Models\PlainMessage;
use SymbolSdk\Models\TransferTransaction;
use SymbolSdk\TransactionHttp;

// 1) RESTから取得した定数（実運用はcurl等で取得→json_decodeして変数に）
$restBase = 'http://sym-test-01.opening-line.jp/:3000';
$generationHashSeed = '49D6E1CE276A85B70EAFE52349AACCA389302E7A9754BCF1221E79494FC665A4'; // 例: '49D6E1CE...'
$epochAdjustment    = '1667250467'; // 例: 1615853185(メインネット例)とは異なるので必ずTestnet値を使用。 [oai_citation:6‡docs.symbol.dev](https://docs.symbol.dev/guides/exchanges/exchange-REST-troubleshooting.html?utm_source=chatgpt.com)
$networkType        = NetworkType::TESTNET; // identifier 0x98=152。SDK内に定義がある前提。

// 2) 送信元秘密鍵（テスト用）
$privateKey = new PrivateKey(getenv('TEST_SENDER_PRIVATE_KEY')); // 環境変数から読むの推奨
$account    = \SymbolSdk\Symbol\Models\Account::createFromPrivateKey($privateKey, $networkType);

// 3) 送信先アドレス
$recipientAddress = Address::createFromRawAddress('TDT5NHDLPIIE3A7QN7VQYSJNNH7UXO74GS6HJ4Y'); // Testnetアドレス

// 4) 送るモザイク: namespace 'symbol.xym' を使用（1 XYM = 1_000_000 microXYM）
$symbolNamespace = new NamespaceId('symbol.xym'); //  [oai_citation:7‡docs.symbol.dev](https://docs.symbol.dev/guides/blockchain/getting-the-mosaic-identifier-behind-a-namespace-with-receipts.html?utm_source=chatgpt.com)
$amount = 1000000; // 1 XYM

// 5) 期限（SDKがepochAdjustmentを使う設計なら内部計算に委譲）
$deadline = Deadline::createUsingEpochAdjustment($epochAdjustment)->extendHours(2);

// 6) トランザクション作成
$tx = new TransferTransaction(
    $networkType,
    $deadline,
    $recipientAddress,
    [ new Mosaic($symbolNamespace, $amount) ],
    PlainMessage::create('Hello Testnet via PHP SDK'),
    200000 // maxFee（microXYM）。必要に応じて上げる
);

// 7) 署名（generationHashSeedが必要）
$signedTx = $account->sign($tx, $generationHashSeed);

// 8) アナウンス
$txHttp = new TransactionHttp($restBase);
$txHttp->announce($signedTx);
echo "announced: {$signedTx->hash}\n";
