<?php
declare(strict_types=1);

namespace SymbolSdk\Tests\TestUtil;

final class Vectors
{
    /**
     * transactions.json から {schema_name?, test_name?, type?, hex} の配列を作る。
     * @return array<int, array{schema_name?:string,test_name?:string,type?:string,hex:string,meta?:array}>
     */
    public static function loadTransactions(string $path): array
    {
        if (!is_file($path)) throw new \RuntimeException("not found: {$path}");
        $data = json_decode((string)file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $out = [];
        self::walk($data, $out);
        return $out;
    }

    /** @param mixed $node */
    private static function walk($node, array &$out): void
    {
        if (is_array($node)) {
            if (self::looksLikeTx($node)) {
                $out[] = [
                    'schema_name' => isset($node['schema_name']) && is_string($node['schema_name']) ? $node['schema_name'] : null,
                    'test_name'   => isset($node['test_name']) && is_string($node['test_name']) ? $node['test_name'] : null,
                    'type'        => isset($node['type']) && is_string($node['type']) ? $node['type'] : null,
                    'hex'         => self::pickHex($node),
                    'meta'        => $node,
                ];
            }
            foreach ($node as $v) self::walk($v, $out);
        }
    }

    /** @param array<mixed> $n */
    private static function looksLikeTx(array $n): bool
    {
        // どのフィールドにHEXが入っているかは実装によって異なるので、候補を広めに
        return isset($n['payload']) || isset($n['bytes']) || isset($n['hex']) || isset($n['serialized']) || isset($n['body']);
    }

    /** @param array<mixed> $n */
    private static function pickHex(array $n): string
    {
        foreach (['payload','bytes','hex','serialized','body'] as $k) {
            if (!empty($n[$k]) && is_string($n[$k])) {
                return $n[$k];
            }
        }
        throw new \RuntimeException('hex field not found in record');
    }

    /** @param array<int, array{schema_name?:string,test_name?:string,hex:string}> $recs */
    public static function filterBySchemaContains(array $recs, string $needle): array
    {
        $needle = strtolower($needle);
        return array_values(array_filter($recs, function ($r) use ($needle) {
            $schema = strtolower((string)($r['schema_name'] ?? ''));
            return $schema !== '' && str_contains($schema, $needle);
        }));
    }

    /** @param array<int, array{schema_name?:string,test_name?:string,hex:string}> $recs */
    public static function filterBySchemaEquals(array $recs, string $exact): array
    {
        return array_values(array_filter($recs, fn($r) =>
            isset($r['schema_name']) && $r['schema_name'] === $exact
        ));
    }

    /** @param array<int, array{schema_name?:string,test_name?:string,hex:string}> $recs */
    public static function filterByTestNameContains(array $recs, string $needle): array
    {
        $needle = strtolower($needle);
        return array_values(array_filter($recs, function ($r) use ($needle) {
            $name = strtolower((string)($r['test_name'] ?? ''));
            return $name !== '' && str_contains($name, $needle);
        }));
    }
}