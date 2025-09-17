<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use SymbolSdk\Net\NodeClient;

$node = getenv('NODE') ?: 'http://sym-test-01.opening-line.jp:3000';
$payloadHex = getenv('PAYLOAD') ?: ($argv[1] ?? '');

if (!$payloadHex) { fwrite(STDERR, "Usage: PAYLOAD=<hex> php examples/announce_payload.php [payloadHex]\n"); exit(1); }

$client = new NodeClient($node);
$res = $client->announcePayloadHex($payloadHex);
echo json_encode($res, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PHP_EOL;
