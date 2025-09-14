id: tx-mosaic-address-restriction
version: 1.0.0
purpose: "MosaicAddressRestrictionTransaction の PHP 実装（共通ヘッダ128B対応／JSON vectors準拠）"
namespace: "SymbolSdk\\Transaction"
class_name: "MosaicAddressRestrictionTransaction"
output_path: "src/Transaction/MosaicAddressRestrictionTransaction.php"
references:
  catbuffer: |
    https://github.com/symbol/symbol/blob/main/catbuffer/schemas/symbol/restriction/mosaic_address_restriction.cats
dependencies:
  base:   "SymbolSdk\\Transaction\\AbstractTransaction"

---
{{> common-php-guardrails.md }}
{{> common-principles.md }}

# Prompt: Generate MosaicAddressRestrictionTransaction Implementation

You are implementing a PHP class `MosaicAddressRestrictionTransaction` for the Symbol SDK (PHP 8.3+).  
It must follow the **catbuffer transaction schema** and be consistent with existing transaction classes like `TransferTransaction`, `MosaicGlobalRestrictionTransaction`, and `AddressAliasTransaction`.  

## Goals
- Implement the transaction as a **non-readonly class** extending `AbstractTransaction` (avoid `readonly class` since `AbstractTransaction` is non-readonly).
- Ensure **PHPStan level max** compatibility (no static analysis errors).
- Avoid **useless casts** (no `(int)$x` if `$x` is already int).
- Ensure correct method overrides (`decodeBody`, `cmpDec`, `divmodDecBy`, etc. must be `protected` not `private`).
- Avoid invalid negated boolean expressions (`if (!$intVar)` should be rewritten properly).
- Ensure constructor matches **AbstractTransaction::__construct()`** signature (7 parameters).
- Type-safe handling of binary parsing and encoding.
- Include validation for `restrictionKey`, `targetAddress`, `previousRestrictionValue`, and `newRestrictionValue`.

## Class Specification

### Namespace
```php
namespace SymbolSdk\\Transaction;
```

### Class Declaration
```php
final class MosaicAddressRestrictionTransaction extends AbstractTransaction
```

### Properties
```php
public readonly string $mosaicIdDec;              // decimal string
public readonly string $restrictionKeyDec;        // decimal string (uint64)
public readonly string $targetAddress;            // 24-byte address
public readonly string $previousRestrictionValue; // decimal string
public readonly string $newRestrictionValue;      // decimal string
```

### Constructor
```php
public function __construct(
    string $mosaicIdDec,
    string $restrictionKeyDec,
    string $targetAddress,
    string $previousRestrictionValue,
    string $newRestrictionValue,
    string $headerRaw,
    int $size,
    int $version,
    int $network,
    int $type,
    string $maxFeeDec,
    string $deadlineDec
)
```

### fromBinary()
- Parse transaction body from binary.
- Enforce length checks (throw `RuntimeException` on insufficient bytes).
- Decode fields using helper methods from `AbstractTransaction`.

### encodeBody()
- Concatenate encoded fields into binary representation.
- Use `self::u64LE()` for uint64 values, ensure validation.

### decodeBody()
```php
protected static function decodeBody(string $binary, int $offset): array
```
- Must return an **array with parsed values** and updated `offset`.
- Compatible with parent signature.

### Helper Methods
Override inherited methods as **protected** (not private):
- `cmpDec`
- `divmodDecBy`
- `mulDecBy`
- `addDecSmall`
- `readU64LEDecAt`
- `u64LE`

All helper methods must maintain compatibility with `AbstractTransaction`.

## Constraints
- Do not use `readonly class` (use `final class` with `public readonly` props).
- Do not cast to int/string if already typed.
- Do not write `if (!$intVar)` — instead check `if ($intVar === 0)` or explicit comparison.
- Ensure PHPStan passes with no `booleanNot.exprNotBoolean` or `cast.useless` warnings.

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


# Output
Provide the **full implementation** of `src/Transaction/MosaicAddressRestrictionTransaction.php`.
