id: tx-secret-lock
version: 1.0.0
purpose: "SecretLockTransaction の PHP 実装（共通ヘッダ128B対応／JSON vectors準拠）"
namespace: "SymbolSdk\\Transactions"
class_name: "SecretLockTransaction"
output_path: "src/Transactions/SecretLockTransaction.php"
references:
  catbuffer: |
    https://github.com/symbol/symbol/blob/main/catbuffer/schemas/symbol/lock/secret_lock.cats
dependencies:
  base:   "SymbolSdk\\Transactions\\AbstractTransaction"

---
{{> common-php-guardrails.md }}
{{> common-principles.md }}

# SecretLockTransaction 実装プロンプト

## 目的
- SecretLockTransaction クラスを実装する
- 秘密値のハッシュをロックして、後に解放可能にする

## 実装要件
- プロパティ
  - `mosaicIdDec: string`
  - `amountDec: string`
  - `durationDec: string`
  - `hashAlgorithm: int`
  - `secret: string` (binary 32 bytes)
  - `recipientAddress: string` (24 bytes)
- fromBinary(), encodeBody(), decodeBody() を実装
- バリデーション: secret は 32 バイト必須

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
- `mosaicIdDec`: u64（8B LE）
- `amountDec`: u64（8B LE）
- `durationDec`: u64（8B LE）
- `hashAlgorithm`: u8（0=Op_Sha3_256,1=Op_Keccak_256,2=Op_Hash_160,3=Op_Hash_256）
- `secret`: 32B 固定
- `recipientAddress`: 24B 固定
