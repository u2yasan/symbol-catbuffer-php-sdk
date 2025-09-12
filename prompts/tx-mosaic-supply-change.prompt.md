id: tx-mosaic-supply-change
version: 1.0.0
purpose: "MosaicSupplyChangeTransaction の PHP 実装（共通ヘッダ128B対応／JSON vectors準拠）"
namespace: "SymbolSdk\\Transaction"
class_name: "MosaicSupplyChangeTransaction"
output_path: "src/Transaction/MosaicSupplyChangeTransaction.php"
references:
  catbuffer: |
    https://github.com/symbol/symbol/blob/main/catbuffer/schemas/symbol/mosaic/mosaic_supply_change.cats
dependencies:
  base: "SymbolSdk\\Transaction\\AbstractTransaction"

---
{{> common-php-guardrails.md }}
{{> common-principles.md }}
{{> common-prompt-guidelines.md }}

# 実装ルール（AbstractTransaction準拠・必須）
- クラスは **`{{namespace}}\\{{class_name}}`**、`AbstractTransaction` を **extends**。
- **共通ヘッダ128B** は `self::parseHeader($binary)` を使用。戻りキー名は固定：
  `headerRaw:string, size:int, version:int, network:int, type:int, maxFeeDec:string, deadlineDec:string, offset:int`
- コンストラクタは必ず次のシグネチャ：
  ```php
  public function __construct(
      string $mosaicIdDec,                // u64 (10進文字列)
      int $action,                        // 0=decrease, 1=increase
      string $deltaDec,                   // u64 (10進文字列)
      string $headerRaw, int $size, int $version, int $network, int $type, string $maxFeeDec, string $deadlineDec
  )
  ```
  直後に `parent::__construct($headerRaw, $size, $version, $network, $type, $maxFeeDec, $deadlineDec);` を呼ぶ。
- `fromBinary(string $binary): self`
  1) `$h = self::parseHeader($binary); $offset = $h['offset'];`
  2) cats の **body 定義順**で読み取り：`mosaic_id:u64` → `action:u8` → `delta:u64`
  3) u64 は **10進文字列**で保持（基底クラスヘルパ使用）
  4) `action` は 0/1 のみ許容（それ以外は `InvalidArgumentException`）
  5) 残量不足は `RuntimeException`
  6) `new self(...)` を返す
- `encodeBody(): string` は **protected**、**body の直列化のみ**行う（ヘッダ付与は基底に任せる）。
- `decodeBody(string $binary, int $offset): array` は **protected static**（未使用でも宣言）。
- **完成した PHP コードのみ**出力（説明/フェンス禁止）。`declare(strict_types=1)` と namespace を含める。

# Body（cats準拠の読み取り仕様・要点）
- `mosaic_id:u64` （MosaicId）
- `action:u8` （MosaicSupplyChangeAction, 0=decrease, 1=increase）
- `delta:u64` （Amount）
- **u64** は `AbstractTransaction` の `u64DecAt()` / `u64LE()` を使う

# cats body（原文を直貼り）
```cats
inline struct MosaicSupplyChangeTransactionBody
	mosaic_id = MosaicId
	action = MosaicSupplyChangeAction
	delta = Amount
```
