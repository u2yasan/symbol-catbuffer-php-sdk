<?php

declare(strict_types=1);

namespace SymbolSdk\Tests\TestUtil;

/**
 * @phpstan-type TxRecord array{
 *   schema_name?: string,
 *   test_name?: string,
 *   type?: string,
 *   hex: string,
 *   meta?: array<mixed>
 * }
 */
final class Vectors
{
    /**
     * @return list<TxRecord>
     */
    public static function loadTransactions(string $jsonPath): array
    {
        if (!\file_exists($jsonPath)) {
            throw new \RuntimeException("Vector not found: {$jsonPath}");
        }
        $json = \file_get_contents($jsonPath);

        if (false === $json) {
            throw new \RuntimeException("Failed to read: {$jsonPath}");
        }

        /** @var mixed $decoded */
        $decoded = \json_decode($json, true);

        if (null === $decoded && \JSON_ERROR_NONE !== \json_last_error()) {
            throw new \RuntimeException("Invalid JSON: {$jsonPath}");
        }

        /** @var list<TxRecord> $out */
        $out = [];
        self::collectRecords($out, $decoded);

        return $out;
    }

    /**
     * 深さ優先で TxRecord を抽出して $out に追記する。
     *
     * @param list<TxRecord> $out ここにレコードを push していく（参照渡し）
     * @param mixed $node
     */
    private static function collectRecords(array &$out, mixed $node): void
    {
        /** @var list<TxRecord> $out */ // list であることを固定
        // レコード形（最低限 hex を持つ）
        if (\is_array($node) && \array_key_exists('hex', $node) && \is_string($node['hex'])) {
            /** @var TxRecord $record */
            $record = ['hex' => $node['hex']];

            if (isset($node['schema_name']) && \is_string($node['schema_name'])) {
                $record['schema_name'] = $node['schema_name'];
            }

            if (isset($node['test_name']) && \is_string($node['test_name'])) {
                $record['test_name'] = $node['test_name'];
            }

            if (isset($node['type']) && \is_string($node['type'])) {
                $record['type'] = $node['type'];
            }

            if (isset($node['meta']) && \is_array($node['meta'])) {
                /** @var array<mixed> $meta */
                $meta = $node['meta'];
                $record['meta'] = $meta;
            }

            $out[] = $record; // list<TxRecord> に push

            return;
        }

        // 配列 or 連想配列なら子要素を辿る
        if (\is_array($node)) {
            foreach ($node as $child) {
                self::collectRecords($out, $child);
            }
        }
    }

    /**
     * @param list<TxRecord> $records
     *
     * @return list<TxRecord>
     */
    public static function filterBySchemaContains(array $records, string $needle): array
    {
        $needleLower = \strtolower($needle);
        $out = [];

        foreach ($records as $r) {
            $schema = $r['schema_name'] ?? null;

            if (null !== $schema && \str_contains(\strtolower($schema), $needleLower)) {
                $out[] = $r;
            }
        }

        /** @var list<TxRecord> $out */
        return $out;
    }

    /**
     * @param list<TxRecord> $records
     *
     * @return list<TxRecord>
     */
    public static function filterBySchemaEquals(array $records, string $schemaName): array
    {
        $out = [];

        foreach ($records as $r) {
            if (($r['schema_name'] ?? null) === $schemaName) {
                $out[] = $r;
            }
        }

        /** @var list<TxRecord> $out */
        return $out;
    }

    /**
     * @param list<TxRecord> $records
     *
     * @return list<TxRecord>
     */
    public static function filterByTestNameContains(array $records, string $needle): array
    {
        $needleLower = \strtolower($needle);
        $out = [];

        foreach ($records as $r) {
            $name = $r['test_name'] ?? null;

            if (null !== $name && \str_contains(\strtolower($name), $needleLower)) {
                $out[] = $r;
            }
        }

        /** @var list<TxRecord> $out */
        return $out;
    }

    /**
     * @template T
     *
     * @param list<TxRecord> $records
     * @param callable(TxRecord): T $fn
     *
     * @return list<T>
     */
    public static function walk(array $records, callable $fn): array
    {
        $out = [];

        foreach ($records as $r) {
            $out[] = $fn($r);
        }

        /** @var list<T> $out */
        return $out;
    }
}
