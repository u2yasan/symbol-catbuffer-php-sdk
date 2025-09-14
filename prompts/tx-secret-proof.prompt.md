id: tx-secret-proof
version: 1.0.0
purpose: "SecretProofTransaction の PHP 実装（共通ヘッダ128B対応／JSON vectors準拠）"
namespace: "SymbolSdk\\Transaction"
class_name: "SecretProofTransaction"
output_path: "src/Transaction/SecretProofTransaction.php"
references:
  catbuffer: |
    https://github.com/symbol/symbol/blob/main/catbuffer/schemas/symbol/lock/secret_proof.cats
dependencies:
  base:   "SymbolSdk\\Transaction\\AbstractTransaction"

---
{{> common-php-guardrails.md }}
{{> common-principles.md }}

# SecretProofTransaction 実装プロンプト

## 目的
- SecretProofTransaction クラスを実装する
- SecretLockTransaction に対応して、証明を提出する

## 実装要件
- プロパティ
  - `hashAlgorithm: int`
  - `secret: string` (binary 32 bytes)
  - `proof: string` (任意長バイナリ)
  - `recipientAddress: string` (24 bytes)
- fromBinary(), encodeBody(), decodeBody() を実装
- proof 長さのチェック
- 親コンストラクタは **7 引数** で必ず呼ぶ：
  `parent::__construct($headerRaw, $size, $version, $network, $type, $maxFeeDec, $deadlineDec);`
- サブコンストラクタの引数順は **Tx固有フィールド → ヘッダ7項目** に固定。
- `hashAlgorithm` の検証は **上限チェックのみ**（`$x > 255`）か **列挙**（`in_array($x, [0,1,2,3], true)`）を使い、`$x < 0` は書かない。
- `fromBinary()` は `parseHeader()` → `$offset = $h['offset']` から読み取り開始 → `new self(..., $h['headerRaw'], ..., $h['deadlineDec'])` の順。
- `encodeBody()` は **bodyのみ** を返し、`serialize()` は親に任せる。
- サブクラスに `readonly class` を付けないこと。
  - `AbstractTransaction` は non-readonly なので、子クラス側も `final class` などに留める。
- 代わりに immutable にしたいプロパティは `public readonly` プロパティとして個別に宣言する。
- クラス宣言行は必ず:
  `final class XxxTransaction extends AbstractTransaction`
  とする。

### booleanNot.exprNotBoolean を出さないための厳守ルール

- **preg_match の判定は必ず厳密比較**  
  ❌ `if (!preg_match($re, $s)) { ... }`  
  ✅ `if (preg_match($re, $s) !== 1) { ... }`

- **unpack の結果は false チェックを厳密に**  
  ```php
  $arr = unpack('Vval', $chunk);
  if ($arr === false) {
      throw new \RuntimeException('unpack failed');
  }
  $v = $arr['val'];
  ```

- **substr / unpack の「否定」禁⽌**  
  ❌ `if (!$chunk) { ... }`  
  ✅ `if (strlen($chunk) !== 4) { ... }`

- **EOF チェックは “残量” で行う**  
  ```php
  $remaining = $len - $offset;
  if ($remaining < $need) {
      throw new \RuntimeException("Unexpected EOF: need $need, have $remaining");
  }
  ```

- **is_string / is_int など “常に true” 判定は書かない**（PHPStan で常真になります）

- **visibility と override**  
  - `encodeBody()` は `protected`  
  - `decodeBody(string $binary, int $offset): array{...}` は `protected static` で共通シグネチャ  
  - 親の `protected` を `private` で覆わない

- **u64 は AbstractTransaction のヘルパを使う**  
  `self::readU64LEDecAt($binary, $offset)` / `self::u64LE(string $dec)` を使用（`unpack('P')` 禁止）。

- **バリデーション**  
  入力チェックは “型+範囲+長さ” を明示的に：  
  ```php
  if ($action !== 0 && $action !== 1) {
      throw new \InvalidArgumentException('action must be 0 or 1');
  }
  if (strlen($address) !== 24) { ... } // 例: 24B raw address
  ```

# Body
- `hashAlgorithm`: u8（0..3, SecretLock と同）
- `secret`: 32B 固定
- `recipientAddress`: 24B 固定
- `proofSize`: u16（2B LE）
- `proof`: `proofSize` バイト
