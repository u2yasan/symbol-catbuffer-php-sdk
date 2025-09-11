<?php
declare(strict_types=1);

namespace SymbolSdk\Tests\TestUtil;

final class Vectors
{
    /**
     * transactions.json を読み、各レコード（HEX保持）を平坦化して返す。
     *
     * @return list<array{
     *   schema_name?: string,
     *   test_name?: string,
     *   type?: string,
     *   hex: string,
     *   meta?: array<mixed>
     * }>
     */
    public static function loadTransactions(string $path): array
    {
        if (!is_file($path)) {
            throw new \RuntimeException("transactions.json not found: {$path}");
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException("cannot read: {$path}");
        }

        /** @var mixed $data */
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \RuntimeException('invalid transactions.json (not array)');
        }

        /** @var list<array{
         *   schema_name?: string,
         *   test_name?: string,
         *   type?: string,
         *   hex: string,
         *   meta?: array<mixed>
         * }> $items
         */
        $items = [];
        self::collectRecords($data, $items);
        return $items;
    }

    /**
     * 再帰的に走査して「トランザクション1件」っぽいオブジェクトを収集する。
     *
     * @param array<mixed> $node
     * @param list<array{
     *   schema_name?: string,
     *   test_name?: string,
     *   type?: string,
     *   hex: string,
     *   meta?: array<mixed>
     * }> $out
     * @return void
     */
    private static function collectRecords(array $node, array &$out): void
    {
        if (self::looksLikeTxRecord($node)) {
            $out[] = [
                'schema_name' => isset($node['schema_name']) && is_string($node['schema_name']) ? $node['schema_name'] : null,
                'test_name'   => isset($node['test_name']) && is_string($node['test_name']) ? $node['test_name'] : null,
                'type'        => isset($node['type']) && is_string($node['type']) ? $node['type'] : null,
                'hex'         => self::extractHex($node),
                'meta'        => isset($node['meta']) && is_array($node['meta']) ? $node['meta'] : [],
            ];
            return;
        }

        foreach ($node as $v) {
            if (is_array($v)) {
                self::collectRecords($v, $out);
            }
        }
    }

    /** @param array<mixed> $rec */
    private static function looksLikeTxRecord(array $rec): bool
    {
        return is_array($rec)
            && (
                array_key_exists('hex', $rec)
                || array_key_exists('payload', $rec)
                || array_key_exists('serialized', $rec)
            );
    }

    /**
     * @param array<mixed> $rec
     * @return string
     */
    private static function extractHex(array $rec): string
    {
        foreach (['hex', 'payload', 'serialized'] as $k) {
            if (isset($rec[$k]) && is_string($rec[$k])) {
                return strtolower(preg_replace('/[^0-9a-f]/i', '', $rec[$k]) ?? '');
            }
        }
        throw new \RuntimeException('No hex-like field found in record');
    }

    /**
     * schema_name に部分一致するレコードを抽出。
     *
     * @param list<array{schema_name?:string,test_name?:string,type?:string,hex:string,meta?:array<mixed>}> $recs
     * @return list<array{schema_name?:string,test_name?:string,type?:string,hex:string,meta?:array<mixed>}>
     */
    public static function filterBySchemaContains(array $recs, string $needle): array
    {
        $needle = strtolower($needle);
        return array_values(array_filter($recs, static function ($r) use ($needle) {
            $schema = strtolower((string)($r['schema_name'] ?? ''));
            return $schema !== '' && str_contains($schema, $needle);
        }));
    }

    /**
     * schema_name が完全一致するレコードを抽出。
     *
     * @param list<array{schema_name?:string,test_name?:string,type?:string,hex:string,meta?:array<mixed>}> $recs
     * @return list<array{schema_name?:string,test_name?:string,type?:string,hex:string,meta?:array<mixed>}>
     */
    public static function filterBySchemaEquals(array $recs, string $exact): array
    {
        return array_values(array_filter($recs, static fn($r) =>
            isset($r['schema_name']) && $r['schema_name'] === $exact
        ));
    }

    /**
     * test_name に部分一致するレコードを抽出。
     *
     * @param list<array{schema_name?:string,test_name?:string,type?:string,hex:string,meta?:array<mixed>}> $recs
     * @return list<array{schema_name?:string,test_name?:string,type?:string,hex:string,meta?:array<mixed>}>
     */
    public static function filterByTestNameContains(array $recs, string $needle): array
    {
        $needle = strtolower($needle);
        return array_values(array_filter($recs, static function ($r) use ($needle) {
            $name = strtolower((string)($r['test_name'] ?? ''));
            return $name !== '' && str_contains($name, $needle);
        }));
    }
}
