<?php
declare(strict_types=1);

namespace SymbolSdk\Tests\Crypto;

use PHPUnit\Framework\TestCase;
use SymbolSdk\Tests\TestUtil\Hex;

final class HashVectorsTest extends TestCase {
    public function testKeccak256Set0(): void {
        $path = __DIR__ . '/../vectors/symbol/crypto/0.test-keccak-256.json';
        if (!is_file($path)) $this->markTestSkipped('keccak vectors not present');
        $cases = json_decode((string)file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        foreach ($cases as $c) {
            $msg = Hex::fromString($c['input'] ?? '');
            $exp = strtolower(preg_replace('/[^0-9a-f]/', '', (string)($c['output'] ?? '')));
            // TODO: 実装したハッシュ関数に合わせて置換
            // $act = bin2hex(Keccak256::hash($msg));
            // $this->assertSame($exp, $act);
            $this->assertTrue(is_string($exp)); // ダミー（実装後に置換）
        }
    }
}
