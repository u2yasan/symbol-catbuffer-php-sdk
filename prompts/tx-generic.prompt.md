---
id: tx-generic
version: 1.0.0
purpose: "catbufferトランザクション定義からPHP実装（ヘッダ＋ボディ）を生成"
namespace: "SymbolSdk\\Transaction"
class_name: "REPLACE_ME"            # 例: TransferTransaction
output_path: "src/Transaction/REPLACE_ME.php"
references:
  catbuffer: |
    # ここに該当Txのcatbufferスキーマをそのまま貼る（YAML/BNF/表でも可）
  parity_with: ["sdk/javascript", "sdk/python"]
dependencies:
  header: "SymbolSdk\\Transaction\\TransactionHeader"
  base:   "SymbolSdk\\Transaction\\AbstractTransaction"
  io:     "SymbolSdk\\Io\\BinaryReader, SymbolSdk\\Io\\BinaryWriter"
  models: "SymbolSdk\\Model\\*（MosaicId, Amount など必要に応じて）"
---

{{> common-principles.md }}
{{> partials/common-php-guardrails.md }}

# Role
あなたは catbuffer と JS/Python SDKに精通した PHP 8.3 エンジニアです。対象Txの**ボディ**をcatbuffer順序で実装し、ヘッダと連結します。

# Task
- namespace: {{namespace}}
- クラス名: {{class_name}}（final）
- 親クラス: {{dependencies.base}}
- 依存: {{dependencies.header}}, {{dependencies.io}}, {{dependencies.models}}
- 仕様: {{references.catbuffer | indent(2)}}

## 実装要件
- コンストラクタは **不変/readonly**。フィールドは catbuffer の順序・型に厳密一致。
- `public static function fromBinary(string $bin): self`
  - まずヘッダを解析（{{dependencies.header}}）、続いて**ボディ**を順に読む。
  - ヘッダ `size` と実バイト長の **自己一致チェック**。不一致は `\RuntimeException`。
- `public function serialize(): string`
  - ボディをLE順に書き、最後にヘッダの `size` と一致すること。
- `public function size(): int` はヘッダ＋ボディ合計。
- U64 は 10進文字列または 8B-LE値オブジェクトで扱う（JS/Pythonとパリティ）。
- 固定長bytes/配列は **長さ検証**、可変長vectorは **長さフィールド**に従う。
- 例外: 想定外値/境界越えは `\InvalidArgumentException` or `\RuntimeException` を使い分け。
- **public API は値取得用の getter のみ**（副作用なし）。

# Output
- **PHPコードのみ**を出力（説明・Markdownフェンス・`===FILE`禁止）。
- 先頭 `<?php` → `declare(strict_types=1);` → `namespace {{namespace}};`。
- 保存先: `{{output_path}}`
