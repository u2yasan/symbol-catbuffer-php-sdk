id: tx-mosaic-global-restriction
version: 1.1.0
purpose: "MosaicGlobalRestrictionTransaction の PHP 実装（共通ヘッダ128B準拠／strict rulesでの静的解析通過）"
namespace: "SymbolSdk\\Transactions"
class_name: "MosaicGlobalRestrictionTransaction"
output_path: "src/Transactions/MosaicGlobalRestrictionTransaction.php"
references:
  catbuffer: |
    https://github.com/symbol/symbol/blob/main/catbuffer/schemas/symbol/restriction/mosaic_global_restriction.cats
dependencies:
  base:   "SymbolSdk\\Transactions\\AbstractTransaction"

---
{{> common-php-guardrails.md }}
{{> common-principles.md }}

# 生成方針（PHP 8.3+ / 静的解析パス）
- クラスは **`{{namespace}}\{{class_name}}`**、`AbstractTransaction` を **extends**。
- **共通ヘッダ128B** は `self::parseHeader($binary)` を使用。戻り値キー：
  `headerRaw:string, size:int, version:int, network:int, type:int, maxFeeDec:string, deadlineDec:string, offset:int`。
- コンストラクタは **Tx固有フィールド → ヘッダ7項目** の順で受け取り、親 `__construct()` を最後に呼ぶ。
- **戻り/引数の型宣言は厳格に**。配列は必ず値型まで記載（`list<array{...}>`）。
- **u64は10進文字列**で保持し、直列化/復元は `AbstractTransaction` の `u64LE()` / `readU64LEDecAt()` を使う。`unpack('P')`は禁止。
- クラス宣言は「final class {{class_name}} extends AbstractTransaction」。
- class レベルの readonly は禁止。必要ならプロパティに readonly を付与。

## booleanNot.exprNotBoolean（今回の主因）を出さないための明確ルール
- `preg_match()` は **int (0/1)** を返すため、否定形 `if (!preg_match(...))` は**禁止**。  
  ✅ `if (preg_match($re, $s) !== 1) { throw ... }`
- `unpack()` は **array|false** を返すため、`if (!$a)` などの否定は**禁止**。  
  ✅ `if ($a === false) { throw ... }`
- `strpos()` 等 **int|false** の戻りは、`=== false` / `!== false` を使う。
- これらの**禁止・推奨**は本クラス内で徹底すること。

## cats body（読み取り仕様）
```cats
# Shared content between MosaicGlobalRestrictionTransaction and EmbeddedMosaicGlobalRestrictionTransaction.
inline struct MosaicGlobalRestrictionTransactionBody
    mosaic_id = UnresolvedMosaicId
    reference_mosaic_id = UnresolvedMosaicId
    restriction_key = MosaicRestrictionKey
    previous_restriction_value = uint64
    new_restriction_value = uint64
    previous_restriction_type = MosaicRestrictionType
    new_restriction_type = MosaicRestrictionType

# Add or update restrictions for a mosaic by key relative to the value of another mosaic (V1, latest).
struct MosaicGlobalRestrictionTransactionV1
    TRANSACTION_VERSION = make_const(uint8, 1)
    TRANSACTION_TYPE = make_const(TransactionType, MOSAIC_GLOBAL_RESTRICTION)

    inline Transaction
    inline MosaicGlobalRestrictionTransactionBody
```

## フィールド定義（本クラスのプロパティ）
- `public readonly string $mosaicIdDec;`            // u64 decimal string
- `public readonly string $referenceMosaicIdDec;`   // u64 decimal string
- `public readonly string $restrictionKeyDec;`      // u64 decimal string
- `public readonly string $previousRestrictionValueDec;` // u64 decimal string
- `public readonly string $newRestrictionValueDec;`      // u64 decimal string
- `public readonly int $previousRestrictionType;`   // u8
- `public readonly int $newRestrictionType;`        // u8

## API（実装必須）
- `public function __construct(
    string $mosaicIdDec,
    string $referenceMosaicIdDec,
    string $restrictionKeyDec,
    string $previousRestrictionValueDec,
    string $newRestrictionValueDec,
    int $previousRestrictionType,
    int $newRestrictionType,
    string $headerRaw, int $size, int $version, int $network, int $type, string $maxFeeDec, string $deadlineDec
  )`
  - すべての十進文字列は `/^[0-9]+$/` で検証（`preg_match(...) !== 1` で判定）。
  - タイプは `in_array($x, [0,1,2,3,4], true)` のように **厳密比較**で域チェック。
- `public static function fromBinary(string $binary): self`
  - `$h = self::parseHeader($binary); $offset = $h['offset']; $len = strlen($binary);`
  - 順番に **u64LE×4（mosaicId, referenceMosaicId, restrictionKey, prevValue）**、**u64LE×1（newValue）**、**u8×2（prevType, newType）** を読む。
  - 読み取りは `self::readU64LEDecAt($binary, $offset)` を使い、毎回 `if ($len - $offset < N) throw` を行う。
  - `unpack()` など array|false を返す関数は、**必ず `=== false` 判定**で失敗時例外。
- `protected static function decodeBody(string $binary, int $offset): array{...,'offset':int}`
  - `fromBinary()` と同じ順序で読み、配列で返す。**戻り配列の型を完全に記載**。
- `protected function encodeBody(): string`
  - `self::u64LE(...)` を使い **同順**に直列化。最後に `chr(...)` でタイプ 2 つ。

## 直列化との整合
- `serialize()` は `AbstractTransaction` に任せる（本クラスは **encodeBody() だけ**を実装）。
- 例外は **RuntimeException**（EOF/長さ不足）と **InvalidArgumentException**（値域・形式違反）を使い分け。

## 重要：禁止事項（静的解析用）
- `if (!preg_match(...))`、`if (!$a)`、`if (!$idx)` のような**否定の真偽値判定は禁止**。
- `private` で `AbstractTransaction` の `protected` メソッドを **オーバーライドしない**（可視性を下げない）。
- `unpack('P')` や **プラットフォーム依存のフォーマット**は禁止。u64は必ず `u64LE()/readU64LEDecAt()` を使用。

## 出力
- 完成した **PHPコードのみ**（フェンス・説明文なし）。
- 先頭に `<?php` と `declare(strict_types=1);`、名前空間・use宣言を正しく付与。
- 保存先: `{{output_path}}`。
