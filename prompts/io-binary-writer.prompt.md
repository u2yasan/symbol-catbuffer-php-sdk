---
id: io-binary-writer
version: 1.0.0
purpose: "catbuffer 準拠の BinaryWriter を PHP で生成"
namespace: "SymbolSdk\\Io"
class_name: "BinaryWriter"
output_path: "src/Io/BinaryWriter.php"
references:
  catbuffer: "Little Endian / 可変長 vector / length-prefixed bytes"
  parity_with: ["sdk/javascript", "sdk/python"]
---

{{> common-principles.md }}
{{> common-php-guardrails.md }}
{{> common-prompt-guidelines.md }}

# Role
あなたは Symbol/catapult の catbuffer と JS/Python SDK に精通した PHP 8.3 エキスパートです。

# Task
以下の要件に従い {{namespace}}\\{{class_name}} を実装してください。

## 要件
- final class。内部に **string バッファ** と **append オフセット**。
- Little Endian 固定。
- 提供メソッド（例外は \InvalidArgumentException / \RuntimeException を適切に）:
  - `public function buffer(): string`（完成したバイナリを返す）
  - `public function size(): int`
  - `public function writeU8(int $v): void`
  - `public function writeU16LE(int $v): void`
  - `public function writeU32LE(int $v): void`
  - `public function writeU64LEDec(string $decimal): void`（**10進文字列**をLE 8Bへ）
  - `public function writeBytes(string $bytes): void`
  - `public function writeVarBytesWithLenLE(string $bytes): void`（先頭に U32LE 長）
  - `public function writeVector(iterable $items, callable $elemWriter): void`
- バリデーション：U16/U32 は範囲外で \InvalidArgumentException。U64 は 0〜2^64-1 の **10進文字列**のみ受け付け、範囲外は例外。
- 速度面：`$buf .= ...` で追記しつつ、不要コピーを最小化（小さなチャンクでの連結を避ける）。
- U64 の出力：`writeU64LEDec()` は **10進→LE8B** を 256基数の divmod で実装（`pack('P')` 等に依存しない）。
- phpdoc で **catbuffer との対応**と**引数の厳密型**を記述。

# Input
- 規約: {{ references.catbuffer }}

# Output
- **PHPコードのみ**を出力。説明文・Markdownフェンス・`===FILE`は一切禁止。
- 先頭は `<?php`、続いて `declare(strict_types=1);`、`namespace {{namespace}};` の順。
- 保存先: `{{output_path}}`
