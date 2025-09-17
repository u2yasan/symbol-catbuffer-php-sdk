id: model-network-type
version: 1.0.0
purpose: "Symbol NetworkType enum の PHP値オブジェクト生成"
namespace: "SymbolSdk\\Model"
class_name: "NetworkType"
output_path: "src/Model/NetworkType.php"
references:
  spec: "Symbol Catapult / SDK の NetworkType 定義"
  parity_with: ["sdk/javascript", "sdk/java", "sdk/python"]

---

{{> common-principles.md }}
{{> common-php-guardrails.md }}
{{> common-prompt-guidelines.md }}

# Role
あなたは Symbol/catapult に精通した PHP 8.3 エキスパートです。

# Task
以下の要件に従い {{namespace}}\\{{class_name}} を実装してください。

- 要件:
  - PHP 8.3+
  - strict_types=1
  - PSR-12
  - `enum {{class_name}}: int`
- 定義する値:
  - MAINNET = 104
  - TESTNET = 152
  - PRIVATE = 96
  - PRIVATE_TEST = 144
- 機能:
  - `public static function fromInt(int $value): self`
    - 値に応じて対応する enum を返す
    - 不正値なら `InvalidArgumentException` を投げる
  - `public function value(): int` を定義（`$this->value` の薄いラッパー）
  - **注意:** `__toString()` は実装しない（enum での magic method を避ける）

# Input
Symbol ネットワークタイプ仕様

# Output
- 完成した **PHPコードのみ** を出力してください。以下を厳守：
  - 先頭は必ず `<?php` から開始すること（前置きの説明文・空行・BOM・Markdown記法の禁止）。
  - コードブロックフェンス（```, ```php）や `===FILE ...===` / `===END===` は出力しない。
  - ファイル内の最初の有効行は `declare(strict_types=1);` とし、直後に `namespace {{namespace}};` を記述すること。
  - 出力対象は **単一ファイル**、保存先: `{{output_path}}`