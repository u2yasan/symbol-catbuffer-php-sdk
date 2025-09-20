<?php
declare(strict_types=1);

namespace SymbolSdk\Util;

final class Serializer
{
    /** 64bit HEX → Little Endian 8B */
    public static function u64le_from_hex(string $raw): string {
        $hex = trim($raw);
        if ($hex === '') throw new \InvalidArgumentException('hex is empty');
        // 0x, アポストロフィ, 空白等を除去
        $hex = preg_replace('/[^0-9a-fA-F]/', '', $hex);
        if ($hex === '') throw new \InvalidArgumentException('hex cleaned to empty');
        if (strncasecmp($hex, '0x', 2) === 0) $hex = substr($hex, 2);
        $hex = ltrim($hex, '0');
        if ($hex === '') $hex = '0';
        if (strlen($hex) > 16) throw new \InvalidArgumentException('hex too long for u64');
        $hex = str_pad($hex, 16, '0', STR_PAD_LEFT);
        $bin = hex2bin($hex);
        if ($bin === false || strlen($bin) !== 8)
            throw new \RuntimeException('hex2bin failed or wrong length');
        return strrev($bin); // BE→LE
    }

    /** 上位/下位32bit → Little Endian 8B */
    public static function u64le_from_hilo(int $hi, int $lo): string {
        // pack('V2', lo, hi) で LE 8B
        return pack('V2', $lo & 0xFFFFFFFF, $hi & 0xFFFFFFFF);
    }

    /** PHPのint（64bit想定）→ Little Endian 8B（※2^63-1 を超えない前提） */
    public static function u64le_from_int(int $value): string {
        // 負値や 2^63-1 超は未サポート。HEXで渡すのが安全。
        if ($value < 0) throw new \InvalidArgumentException('negative int not allowed for u64');
        // 64bit 環境前提の簡易版：上位/下位32bitに分解
        $lo = $value & 0xFFFFFFFF;
        $hi = ($value >> 32) & 0xFFFFFFFF;
        return pack('V2', $lo, $hi);
    }

    /**
     * 任意型のIDを受けて Little Endian 8B へ正規化
     * 受理: HEX文字列 / ['hex'=>...] / ['hi'=>..,'lo'=>..] / int(<=2^63-1)
     */
    public static function u64le_from_any(mixed $id): string {
        if (is_string($id)) {
            return self::u64le_from_hex($id);
        }
        if (is_array($id)) {
            if (isset($id['hex'])) {
                return self::u64le_from_hex((string)$id['hex']);
            }
            if (isset($id['hi'], $id['lo'])) {
                return self::u64le_from_hilo((int)$id['hi'], (int)$id['lo']);
            }
            throw new \InvalidArgumentException('mosaic id array must have hex or hi/lo');
        }
        if (is_int($id)) {
            return self::u64le_from_int($id); // 注: 2^63-1 超は非対応。HEXで渡してください。
        }
        throw new \InvalidArgumentException('unsupported mosaic id type');
    }

    /** amount の u64 LE 化（通常はintで十分） */
    public static function u64le_from_amount(int|string $amount): string {
        if (is_string($amount)) {
            // 10進文字列→gmp/bcmathが無くても小さければOK。大きい時はHEXにして別途対応。
            if (!ctype_digit($amount)) throw new \InvalidArgumentException('amount string must be decimal digits');
            $val = (int)$amount; // 通常の送金なら収まる
            return self::u64le_from_int($val);
        }
        return self::u64le_from_int($amount);
    }
}