<?php
declare(strict_types=1);

namespace SymbolSdk\Tests\Transaction;

use PHPUnit\Framework\TestCase;
use SymbolSdk\Tests\TestUtil\Hex;
use SymbolSdk\Transaction\TransferTransaction;

final class TransferVectorsTest extends TestCase
{
   /** @return array<string, array{file:string}> */
    public static function provideValidVectors(): array
    {
        $candidates = [
            'valid_min' => __DIR__ . '/../vectors/transfer/valid_min.hex',
            'valid_std' => __DIR__ . '/../vectors/transfer/valid_std.hex',
            'valid_msg' => __DIR__ . '/../vectors/transfer/valid_msg.hex',
        ];
        $out = [];
        foreach ($candidates as $name => $path) {
            if (is_file($path)) {
                $out[$name] = ['file' => $path];
            }
        }
        // ← ここがポイント：空ならダミー1件返す
        return $out ?: ['__none__' => ['file' => '']];
    }

    /** @dataProvider provideValidVectors */
    public function testDecodeReencodeEquals(string $file): void
    {
        if ($file === '' || !is_file($file)) {
            $this->markTestSkipped('No local .hex vectors under tests/vectors/transfer/.');
        }
        $bin = \SymbolSdk\Tests\TestUtil\Hex::fromFile($file);

        $tx = \SymbolSdk\Transaction\TransferTransaction::fromBinary($bin);
        $re = $tx->serialize();

        $this->assertSame(bin2hex($bin), bin2hex($re), 're-encoded bytes must equal input');
    }

    public function testVectorsPresence(): void
    {
        $any =
            is_file(__DIR__ . '/../vectors/transfer/valid_min.hex') ||
            is_file(__DIR__ . '/../vectors/transfer/valid_std.hex') ||
            is_file(__DIR__ . '/../vectors/transfer/valid_msg.hex');

        if (!$any) {
            $this->markTestSkipped('No local .hex transfer vectors under tests/vectors/transfer/. Using JSON-based tests instead.');
        }
        $this->assertTrue(true);
    }
}