<?php
use SymbolSdk\CryptoTypes\PrivateKey;
use SymbolSdk\Symbol\NetworkType;
use SymbolSdk\Symbol\IdGenerator;
use SymbolSdk\Symbol\Models\Address;
use SymbolSdk\Symbol\Models\Deadline;
use SymbolSdk\Symbol\Models\Mosaic;
use SymbolSdk\Symbol\Models\NamespaceId;
use SymbolSdk\Symbol\Models\NetworkCurrency;
use SymbolSdk\Symbol\Models\PlainMessage;
use SymbolSdk\Symbol\Models\TransferTransaction;
use SymbolSdk\Symbol\TransactionHttp;

// 1) RESTから取得した定数（実運用はcurl等で取得→json_decodeして変数に）
$restBase = 'http://<your-testnet-node>:3000';
$generationHashSeed = '<from /network/properties>'; // 例: '49D6E1CE...'
$epochAdjustment    = 123456789; // 例: 1615853185(メインネット例)とは異なるので必ずTestnet値を使用。 [oai_citation:6‡docs.symbol.dev](https://docs.symbol.dev/guides/exchanges/exchange-REST-troubleshooting.html?utm_source=chatgpt.com)
$networkType        = NetworkType::TESTNET(); // identifier 0x98=152。SDK内に定義がある前提。

// 2) 送信元秘密鍵（テスト用）
$privateKey = new PrivateKey(getenv('TEST_SENDER_PRIVATE_KEY')); // 環境変数から読むの推奨
$account    = \SymbolSdk\Symbol\Models\Account::createFromPrivateKey($privateKey, $networkType);

// 3) 送信先アドレス
$recipientAddress = Address::createFromRawAddress('TA...'); // Testnetアドレス

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
