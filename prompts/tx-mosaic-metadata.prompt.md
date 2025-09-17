id: tx-mosaic-metadata
version: 1.0.0
purpose: "MosaicMetadataTransaction の PHP 実装（共通ヘッダ128B対応／cats準拠／vectors往復一致）"
namespace: "SymbolSdk\\Transactions"
class_name: "MosaicMetadataTransaction"
output_path: "src/Transactions/MosaicMetadataTransaction.php"
references:
  catbuffer: |
    https://github.com/symbol/symbol/blob/main/catbuffer/schemas/symbol/metadata/mosaic_metadata.cats
dependencies:
  base: "SymbolSdk\\Transactions\\AbstractTransaction"

---
{{> common-php-guardrails.md }}
{{> common-principles.md }}

# 実装ルール（必読）
- クラスは **non-readonly**、`{{namespace}}\{{class_name}}` とし、`AbstractTransaction` を **extends**。
- **共通ヘッダ128B** は `self::parseHeader($binary)` を使用。戻り配列キーは固定：
  `headerRaw:string, size:int, version:int, network:int, type:int, maxFeeDec:string, deadlineDec:string, offset:int`。
- コンストラクタのシグネチャ（順序固定・引数名固定）：
  ```php
  public function __construct(
      string $targetAddress,
      string $scopedMetadataKeyDec,
      string $targetMosaicIdDec,
      int $valueSizeDelta,
      string $value,
      string $headerRaw, int $size, int $version, int $network, int $type, string $maxFeeDec, string $deadlineDec
  )
  ```
  - `scopedMetadataKeyDec` / `targetMosaicIdDec` は **10進文字列**（u64）。
  - `value` は **バイナリ**（長さは 0〜65535）。
- **可視性**：`encodeBody()` は **protected**、`decodeBody(string $binary, int $offset): array` は **protected static**。
- **u64 ヘルパ**は親の `readU64LEDecAt()` / `u64LE()` を **再実装せず**に使用する。
- `preg_match()` は `=== 1` で評価。否定の多段は使わず、**陽に if (...) { throw ... }** で書く。
- `unpack()` は **false ガード**＋**名前付きキー**で取り出す（例：`unpack('vval', $chunk)` → `['val']`）。

# Body（cats 準拠の読み取り順）
- `targetAddress` : 32 bytes
- `scopedMetadataKey` : u64 (LE) → **10進文字列として保持**
- `targetMosaicId`   : u64 (LE) → **10進文字列として保持**
- `valueSizeDelta`   : int16 (LE)
- `valueSize`        : uint16 (LE)
- `value`            : `valueSize` バイト（可変長）

# 実装詳細
- `fromBinary(string $binary): self`
  - `$h = self::parseHeader($binary); $offset = $h['offset']; $len = strlen($binary);`
  - 以降、Body を上記順に読み取る。残量不足は `RuntimeException`。
  - `valueSizeDelta` は **-32768〜32767** チェック。`valueSize` は **0〜65535**。
  - `value` は `substr($binary, $offset, $valueSize)`。
- `encodeBody(): string`
  - 同順序で直列化：`targetAddress (32B)` → `u64LE(scopedMetadataKeyDec)` → `u64LE(targetMosaicIdDec)` → `i16LE(valueSizeDelta)` → `u16LE(strlen($value))` → `value`。
- 追加ヘルパ（必要最小限）を **protected** で実装可：
  ```php
  // readU16LEAt / readI16LEAt / u16LE / i16LE （いずれも LE ）
  ```

# 例外・バリデーション
- `targetAddress` は **32B 必須**。長さ不正は `InvalidArgumentException`。
- `scopedMetadataKeyDec`, `targetMosaicIdDec` は `/^[0-9]+$/` にマッチ必須。
- `value` の長さが 65535 を超える場合は `InvalidArgumentException`。

# 出力形式
- **完成した PHP コードのみ**を出力（解説や囲み、コードフェンス禁止）。
- ファイル先頭は `<?php` と `declare(strict_types=1);`、`namespace {{namespace}};` 必須。

# cats Body（参考原文）
```cats
inline struct MosaicMetadataTransactionBody
    target_address = UnresolvedAddress
    scoped_metadata_key = uint64
    target_mosaic_id = UnresolvedMosaicId
    value_size_delta = int16
    value_size = uint16
    value = array(uint8, value_size)
```
