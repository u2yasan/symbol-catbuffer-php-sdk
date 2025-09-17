<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/bootstrap.php';

use SymbolSdk\CryptoTypes\PrivateKey;
use SymbolSdk\CryptoTypes\KeyPair;
use SymbolSdk\Builder\AggregateCompleteBuilder;
use SymbolSdk\Signer\TxHash;

$kp = KeyPair::fromPrivateKey(PrivateKey::fromHex(getenv('SK')));

$inner1 = "..."; // Transfer等の serialize（未署名インナー）
$inner2 = "...";

$agg = (new AggregateCompleteBuilder())
    ->networkType(152)
    ->deadline(time()+7200)
    ->maxFee(0)
    ->addInner($inner1)
    ->addInner($inner2);

$payload = $agg->signWith($kp);
$hash = TxHash::sha3_256_of_payload($payload);

echo "agg_hash=", bin2hex($hash), PHP_EOL;
