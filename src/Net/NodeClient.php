<?php
declare(strict_types=1);

namespace SymbolSdk\Net;

final class NodeClient
{
    public function __construct(private string $baseUrl) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /** @return array{message?:string,hash?:string}|array */
    public function announcePayloadHex(string $payloadHex): array
    {
        $url = $this->baseUrl . '/transactions';
        $body = json_encode(['payload' => $payloadHex], JSON_UNESCAPED_SLASHES);
        return $this->putJson($url, $body);
    }

    /** @return array */
    public function transactionStatus(string $hashHex): array
    {
        $url = $this->baseUrl . '/transactionStatus/' . $hashHex;
        return $this->getJson($url);
    }

    /** @return array */
    public function confirmed(string $hashHex): array
    {
        $url = $this->baseUrl . '/transactions/confirmed/' . $hashHex;
        return $this->getJson($url);
    }

    /** @return array */
    public function unconfirmed(string $hashHex): array
    {
        $url = $this->baseUrl . '/transactions/unconfirmed/' . $hashHex;
        return $this->getJson($url);
    }

    /** @return array */
    private function putJson(string $url, string $json): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => 'PUT',
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $json,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);
            $resp = curl_exec($ch);
            $err  = curl_error($ch);
            curl_close($ch);
            if ($resp === false) { throw new \RuntimeException("HTTP error: $err"); }
        } else {
            $context = stream_context_create([
                'http' => [
                    'method'  => 'PUT',
                    'header'  => "Content-Type: application/json\r\n",
                    'content' => $json,
                    'timeout' => 10,
                ],
            ]);
            $resp = @file_get_contents($url, false, $context);
            if ($resp === false) { throw new \RuntimeException("HTTP error (streams)"); }
        }
        $decoded = json_decode($resp, true);
        return is_array($decoded) ? $decoded : ['raw' => $resp];
    }

    /** @return array */
    private function getJson(string $url): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);
            $resp = curl_exec($ch);
            $err  = curl_error($ch);
            curl_close($ch);
            if ($resp === false) { throw new \RuntimeException("HTTP error: $err"); }
        } else {
            $resp = @file_get_contents($url);
            if ($resp === false) { throw new \RuntimeException("HTTP error (streams)"); }
        }
        $decoded = json_decode($resp, true);
        return is_array($decoded) ? $decoded : ['raw' => $resp];
    }
}
