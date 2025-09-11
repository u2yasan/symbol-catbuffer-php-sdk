<?php
declare(strict_types=1);

namespace SymbolSdk\Tests\Model;

use PHPUnit\Framework\TestCase;
use SymbolSdk\Model\MosaicId;

final class MosaicIdTest extends TestCase
{
    /**
     * @return array<string, array{dec:string}>
     */
    public static function provideRoundTrip(): array
    {
        return [
            'zero'     => ['dec' => '0'],
            'one'      => ['dec' => '1'],
            'mid'      => ['dec' => '1844674407370955'],       // 任意の中間値
            'max_u64'  => ['dec' => '18446744073709551615'],   // 2^64 - 1
        ];
    }

    /**
     * @dataProvider provideRoundTrip
     */
    public function testRoundTrip(string $dec): void
    {
        $m = MosaicId::fromUint64String($dec);

        // __toString は元の10進と一致
        $this->assertSame($dec, (string)$m);

        // 期待LE 8Bをテスト側で算出（本体とは独立実装）
        $expectedHex = bin2hex(self::decToLe8($dec));
        $this->assertSame($expectedHex, bin2hex($m->serialize()), 'serialize should be correct LE 8B');

        // fromBinary → __toString の往復
        $m2 = MosaicId::fromBinary(hex2bin($expectedHex));
        $this->assertSame((string)$m, (string)$m2);
    }

    public function testFromBinaryRejectsWrongLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MosaicId::fromBinary("\x00\x00\x00"); // 8B以外はNG
    }

    public function testFromUint64StringRejectsNonDecimal(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MosaicId::fromUint64String('abc');
    }

    public function testFromUint64StringRejectsTooLarge(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MosaicId::fromUint64String('18446744073709551616'); // 2^64 超え
    }

    // ---- test-side helpers (本体から独立) ----

    private static function decToLe8(string $dec): string
    {
        $dec = ltrim($dec, '0');
        if ($dec === '') {
            return "\x00\x00\x00\x00\x00\x00\x00\x00";
        }
        $bytes = [];
        $current = $dec;
        for ($i = 0; $i < 8; $i++) {
            [$q, $r] = self::divmodDecBy($current, 256);
            $bytes[] = chr($r);
            if ($q === '0') {
                for ($j = $i + 1; $j < 8; $j++) $bytes[] = "\x00";
                return implode('', $bytes);
            }
            $current = $q;
        }
        throw new \RuntimeException('Overflow converting to uint64 (test helper).');
    }

    /**
     * @return array{0:string,1:int} [quotient, remainder]
     */
    private static function divmodDecBy(string $dec, int $by): array
    {
        $len = strlen($dec);
        $q = '';
        $carry = 0;
        for ($i = 0; $i < $len; $i++) {
            $carry = $carry * 10 + (ord($dec[$i]) - 48);
            $digit = intdiv($carry, $by);
            $carry = $carry % $by;
            if ($q !== '' || $digit !== 0) $q .= chr($digit + 48);
        }
        if ($q === '') $q = '0';
        return [$q, $carry];
    }
}
