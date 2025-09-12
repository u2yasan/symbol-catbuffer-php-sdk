---
id: model-mosaic-id
version: 1.0.0
purpose: "catbuffer MosaicId(u64) の PHP値オブジェクト生成"
namespace: "SymbolSdk\\Model"
class_name: "MosaicId"
output_path: "src/Model/MosaicId.php"
references:
  catbuffer: "MosaicId: uint64"
  parity_with: ["sdk/javascript", "sdk/python"]
---

{{> common-principles.md }}
{{> common-php-guardrails.md }}
{{> common-prompt-guidelines.md }}

# Role
あなたは Symbol/catapult の catbuffer と JS/Python SDKに精通した PHP 8.3 エキスパートです。

# Task
以下の要件に従い {{namespace}}\\{{class_name}} を実装してください。
- 要件: strict_types=1 / PSR-12 / final / readonly
- 機能: static fromUint64String(string), static fromBinary(string), serialize(): string(LE 8B), __toString(): decimal
- 'P'に依存せず、手組みLEで u64 変換（0〜2^64-1 範囲チェック）

# Input
catbuffer 定義: {{ references.catbuffer }}

# Output
- 完成した **PHPコードのみ** を出力してください。以下を厳守：
  - 先頭は必ず `<?php` から開始すること（前置きの説明文・空行・BOM・Markdown記法の禁止）。
  - コードブロックフェンス（```, ```php）や `===FILE ...===` / `===END===` は出力しない。
  - 後置きの説明や例も一切出力しない（コードのみ）。
  - ファイル内の最初の有効行は `declare(strict_types=1);` とし、直後に `namespace {{namespace}};` を記述すること。
  - 先頭や末尾に不要な空白・改行を付けない。
  - 出力対象は **単一ファイル**、保存先: `{{output_path}}`