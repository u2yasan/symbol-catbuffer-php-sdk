id: tx-namespace-metadata
version: 1.0.0
purpose: "NamespaceMetadataTransaction の PHP 実装（共通ヘッダ128B対応／JSON vectors準拠）"
namespace: "SymbolSdk\\Transaction"
class_name: "NamespaceMetadataTransaction"
output_path: "src/Transaction/NamespaceMetadataTransaction.php"
references:
  catbuffer: |
    https://github.com/symbol/symbol/blob/main/catbuffer/schemas/symbol/metadata/namespace_metadata.cats
dependencies:
  base: "SymbolSdk\\Transaction\\AbstractTransaction"

---
{{> common-php-guardrails.md }}
{{> common-principles.md }}

# ねらい
- `{{namespace}}\{{class_name}}` を **非 readonly** クラスで実装し、`AbstractTransaction` を **extends**。
- **共通ヘッダ128B** を `AbstractTransaction::parseHeader()` / `serializeHeader()` で往復保持。
- Body は catbuffer の順序に厳密に従って decode / encode。  
  - `targetAddress` (UnresolvedAddress 24B)
  - `scopedMetadataKey` (u64, 10進文字列として保持)
  - `targetNamespaceId` (u64, 10進文字列として保持)
  - `valueSizeDelta` (i16)
  - `valueSize` (u16)
  - `value` (byte[valueSize])

# 重要ルール（PHPStan/静的解析で落ちないための縛り）
- **クラスは readonly にしない**（親が非 readonly）。
- サブクラスの **コンストラクタ引数順** は「Tx固有フィールド → ヘッダ7項目」。引数名は固定：
  ```php
  public function __construct(
      string $targetAddress,          // 24 bytes
      string $scopedMetadataKeyDec,   // decimal string
      string $targetNamespaceIdDec,   // decimal string
      int $valueSizeDelta,            // int16
      string $value,                  // raw bytes
      string $headerRaw, int $size, int $version, int $network, int $type, string $maxFeeDec, string $deadlineDec
  )
  ```
- `encodeBody()` は **protected**、`decodeBody()` は **protected static**。private で override しない。
- `decodeBody(string $binary, int $offset): array{targetAddress:string, scopedMetadataKeyDec:string, targetNamespaceIdDec:string, valueSizeDelta:int, value:string, offset:int}`
- **u64 は 10進文字列で保持**。読込は `self::readU64LEDecAt()`、書込は `self::u64LE()`（いずれも `AbstractTransaction` の protected ヘルパ）。
- **符号付き/符号なし 16bit の境界チェック**は「不必要な常真/常偽判定」を避ける。  
  - 具体的に：`$valueSizeDelta` は i16 の前提だが、**常に false と判定される範囲比較**は書かない。  
  - 必要最小限の検証のみ（オーバーフロー回避・負値長を禁止など）。
- **is_string/is_int の常真チェック禁止**。型宣言で担保し、不要な `is_*` は書かない。
- **無益なキャスト禁止**（「既に int を int にキャスト」「既に string を string にキャスト」などは書かない）。
- **否定の対象が bool でない**パターン（`if (!$x)` で $x が int など）を作らない。明示的な比較を使う。
- `parseHeader()` の戻りキー名を**厳守**：  
  `headerRaw, size, version, network, type, maxFeeDec, deadlineDec, offset`
- 例外：
  - 残量不足やサイズ不整合は `\RuntimeException`（メッセージに必要バイト数/現バイト数を含める）
  - 値域・形式違反は `\InvalidArgumentException`

# Body の読み書き仕様
- 読み順（decode）：`targetAddress(24)` → `scopedMetadataKey(u64)` → `targetNamespaceId(u64)` → `valueSizeDelta(i16)` → `valueSize(u16)` → `value(valueSize)`
- 書き順（encode）：同じ順序で直列化。
- `targetAddress` は 24B 固定。`strlen($targetAddress) === 24` を満たさない場合は例外。
- `value` は任意バイト列（空 OK）。エンコード時は `valueSize = strlen($value)` を u16 で出力。
- `valueSizeDelta` は int。**delta と valueSize の整合性はここでは強制しない**（vectors 往復一致を優先）。

# 実装ヒント
- `fromBinary(string $binary): self`
  ```php
  $h = self::parseHeader($binary);
  $body = self::decodeBody($binary, $h['offset']);
  return new self(
      $body['targetAddress'],
      $body['scopedMetadataKeyDec'],
      $body['targetNamespaceIdDec'],
      $body['valueSizeDelta'],
      $body['value'],
      $h['headerRaw'], $h['size'], $h['version'], $h['network'], $h['type'], $h['maxFeeDec'], $h['deadlineDec']
  );
  ```
- `serialize(): string` は `return $this->serializeHeader() . $this->encodeBody();`

# 修正方針
- `int|false` を返す関数の結果に対して `!` を使わないこと。  
- 代わりに `=== false` または `!== false` を明示的に書く。  
- これにより PHPStan の booleanNot.exprNotBoolean 警告を解消する。

# 書き換えルール
- NG: `if (!$value)` （$value が int|false 型のとき）
- OK: `if ($value === false)`
- NG: `if (!preg_match(...))`
- OK: `if (preg_match(...) === 0)`

# PHPStan / 互換ガード（NamespaceMetadata 専用追記）

## 型チェックの無駄を禁止（function.alreadyNarrowedType 回避）
- ドメイン検証のみを書くこと。**型の再確認（is_string / is_int / is_array など）は禁止**。
  - NG: if (!is_string($scopedMetadataKeyDec)) { ... }
  - OK: if ($scopedMetadataKeyDec === '' || !preg_match('/^[0-9]+$/', $scopedMetadataKeyDec)) { ... }  // 空や表記違反などのドメイン検証のみ

## serialize の扱い（final override エラー回避）
- `AbstractTransaction::serialize()` は **final**。**サブクラスでは絶対に override しない**。
- 直列化は **親クラスに任せる**。サブクラスは **protected function encodeBody(): string** のみ実装する。
- `serializeHeader()` のようなメソッドは **呼び出さない／定義しない**。必要なときは **`return parent::serialize();` を使わず、encodeBody() が正しければ親が自動的にヘッダ＋ボディを連結する**（親の実装に委任）。

## decodeBody の契約
- `protected static function decodeBody(string $binary, int $offset): array{... , offset:int}` を実装し、**親の fromBinary() 互換**を満たす。
- 返却配列は body フィールドと `offset` を必ず含める。

## 可視性
- `encodeBody()` は **protected**、`decodeBody()` は **protected static**。private にはしない。

## 例外運用
- 残量不足: `RuntimeException`（期待バイト数を含める）
- 値域違反 / フォーマット違反: `InvalidArgumentException`

## u64
- 10進文字列で保持し、直列化/復元は **AbstractTransaction の u64 ヘルパ**（`u64LE()` / `readU64LEDecAt()`）のみを使う。

# 出力要件
- **完成した PHP コードのみ**を出力。説明/コメント/フェンス禁止。
- ファイル先頭：`<?php` と `declare(strict_types=1);`、`namespace {{namespace}};`
- `use` は不要なものを入れない。
- 型宣言・戻り型・PHPDoc（list や配列の値型）を適切に付与。

# cats body（参照）
```cats
# https://github.com/symbol/symbol/blob/main/catbuffer/schemas/symbol/metadata/namespace_metadata.cats

inline struct NamespaceMetadataTransactionBody
    target_address = UnresolvedAddress
    scoped_metadata_key = uint64
    target_namespace_id = NamespaceId
    value_size_delta = int16
    value_size = uint16
    value = array(uint8, value_size)

struct NamespaceMetadataTransactionV1
    TRANSACTION_VERSION = make_const(uint8, 1)
    TRANSACTION_TYPE = make_const(TransactionType, NAMESPACE_METADATA)

    inline Transaction
    inline NamespaceMetadataTransactionBody
```
