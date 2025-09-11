---
id: io-binary-reader
version: 1.0.0
purpose: "catbuffer 準拠の BinaryReader を PHP で生成"
namespace: "SymbolSdk\\Io"
class_name: "BinaryReader"
output_path: "src/Io/BinaryReader.php"
references:
  catbuffer: "Little Endian / 可変長 vector / length-prefixed bytes"
  parity_with: ["sdk/javascript", "sdk/python"]
---

{{> common-principles.md }}

# Role
あなたは Symbol/catapult の catbuffer と JS/Python SDK に精通した PHP 8.3 エキスパートです。

# Task
以下の要件に従い {{namespace}}\\{{class_name}} を実装してください。

## 要件
- final class。内部に**オフセット前進方式**のストリーム読み出し。
- バイト列は **string**（バイナリ）で保持し、mbstringは使わない。
- Little Endian 固定。
- 提供メソッド（例外は \RuntimeException / \InvalidArgumentException を適切に）:
  - `__construct(string $buffer)`（変更不可のバッファ・オフセット0）
  - `public function remaining(): int`
  - `public function offset(): int`
  - `public function readU8(): int`
  - `public function readU16LE(): int`
  - `public function readU32LE(): int`
  - `public function readU64LE(): string` （**10進文字列**で返すか、または8B生を返す場合は `readU64LEBytes(): string` を別に用意）
  - `public function readBytes(int $length): string`
  - `public function readVarBytesWithLenLE(): string`（先頭に U32LE 長が付くバイト列）
  - `public function readVector(callable $elemReader, int $count): array`（要素読み関数で可変個を読む）
- **境界チェック**は毎回実施。範囲外アクセスは \RuntimeException。
- 速度面：`substr` 連鎖による O(n^2) 化を避け、**単一バッファ＋整数オフセット**で前進。
- U64 の扱い：`readU64LE()` は 0〜2^64-1 を **10進文字列**で返す（PHPの64bit安全のため）。内部実装は8Bを取り、256基数で加算。
- phpdoc で **catbuffer との対応**と**戻り値の厳密型**を記述。

# Input
- 規約: {{ references.catbuffer }}

# Output
- **PHPコードのみ**を出力。説明文・Markdownフェンス・`===FILE`は一切禁止。
- 先頭は `<?php`、続いて `declare(strict_types=1);`、`namespace {{namespace}};` の順。
- 保存先: `{{output_path}}`
