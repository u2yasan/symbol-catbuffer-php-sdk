id: tx-mosaic-alias
version: 1.0.0
purpose: "MosaicAliasTransaction の PHP 実装（共通ヘッダ128B対応／JSON vectors準拠）"
namespace: "SymbolSdk\\Transaction"
class_name: "MosaicAliasTransaction"
output_path: "src/Transaction/MosaicAliasTransaction.php"
references:
  catbuffer: |
    https://github.com/symbol/symbol/blob/main/catbuffer/schemas/symbol/namespace/mosaic_alias.cats
dependencies:
  base:   "SymbolSdk\\Transaction\\AbstractTransaction"

---
{{> common-php-guardrails.md }}
{{> common-principles.md }}

# 要件
- クラスは **`{{namespace}}\\{{class_name}}`**、`AbstractTransaction` を extends。
- ヘッダ128Bは `AbstractTransaction::parseHeader()` の結果をそのまま保持（headerRaw 等）。
- u64 は 10進文字列で保持し、`u64DecAt()/u64LE()` を利用。
- **完成した PHP コードのみ**を出力（フェンス/説明禁止、declare(strict_types=1) 必須）。

## 目的
- MosaicAliasTransaction クラスを実装する
- AbstractTransaction を継承
- mosaicIdDec と namespaceIdDec を結びつける
- action (link/unlink) を持つ

## 実装要件
- プロパティ
  - `mosaicIdDec: string` (uint64 decimal string)
  - `namespaceIdDec: string` (uint64 decimal string)
  - `aliasAction: int` (0 or 1)
- コンストラクタでバリデーション
- fromBinary() でデコード
- encodeBody() でエンコード
- decodeBody() でパース
- AbstractTransaction の `readU64LEDecAt` / `u64LE` ユーティリティを活用
- PHPStan, PHPUnit のテストで通る設計

# Body（読み取り仕様）
- `namespaceIdDec`: u64（10進文字列, 8B LE）
- `mosaicIdDec`: u64（10進文字列, 8B LE）
- `aliasAction`: u8（0=unlink, 1=link）

# 実装ヒント
- `fromBinary()` は parseHeader → offset から body を順に読む。
- 直列化は `encodeBody()` で同順に書き出し。
- 値域・長さチェック、EOF検出は例外。

# 追加型
- `final class AliasAction { public const UNLINK = 0; public const LINK = 1; }`

# 出力
- 保存先: `{{output_path}}`
- コード以外は出力しない。
