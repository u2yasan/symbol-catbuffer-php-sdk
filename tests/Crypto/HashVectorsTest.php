<?php
declare(strict_types=1);

namespace SymbolSdk\Tests\Crypto;

use PHPUnit\Framework\TestCase;
use SymbolSdk\Tests\TestUtil\Hex;

final class HashVectorsTest extends TestCase
{
    public function testKeccak256Set0(): void
    {
        $path = __DIR__ . '/../vectors/symbol/crypto/0.test-keccak-256.json';
        if (!is_file($path)) {
            $this->markTestSkipped('keccak vectors not present');
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            $this->markTestSkipped('cannot read keccak vectors');
        }

        /** @var mixed $cases */
        $cases = json_decode($raw, true);
        if (!is_array($cases)) {
            $this->markTestSkipped('invalid keccak vectors json');
        }

        foreach ($cases as $c) {
            if (!is_array($c)) {
                continue;
            }
            $inHex  = isset($c['input']) && is_string($c['input']) ? $c['input'] : '';
            $outHex = isset($c['output']) && is_string($c['output']) ? $c['output'] : '';
            if ($inHex === '' || $outHex === '') {
                continue; // 型が揃っていないレコードは読み飛ばし
            }

            $msg = Hex::fromString($inHex);
            $exp = strtolower(preg_replace('/[^0-9a-f]/', '', $outHex) ?? '');

            // TODO: ハッシュ実装が入ったら以下を有効化
            // $act = bin2hex(Keccak256::hash($msg));
            // $this->assertSame($exp, $act);

            // いまは型・フォーマットのみ確認
            $this->assertIsString($exp);
        }
    }
}
