id: tx-address-alias
version: 1.0.1
purpose: "AddressAliasTransaction の PHP 実装（共通ヘッダ128B対応／JSON vectors準拠／phpstan clean）"
namespace: "SymbolSdk\\Transactions"
class_name: "AddressAliasTransaction"
output_path: "src/Transactions/AddressAliasTransaction.php"
references:
  catbuffer: |
    https://github.com/symbol/symbol/blob/main/catbuffer/schemas/symbol/namespace/address_alias.cats
dependencies:
  base:   "SymbolSdk\\Transactions\\AbstractTransaction"

---
{{> common-php-guardrails.md }}
{{> common-principles.md }}

# 目的
- **`{{namespace}}\\{{class_name}}`** を実装。**`AbstractTransaction` を extends**（親の u32/u64/ヘッダ補助は**再定義しない**）。
- **JSON vectors とラウンドトリップ一致**（ヘッダ128B+ボディ）。

# トランザクション仕様（cats 対応）
- Body フィールド順・型:
  1. `namespaceIdDec`: **u64 Little Endian**（10進文字列で保持）
  2. `address`: **24 bytes 固定**（Symbol encoded address のバイナリ、文字列として保持）
  3. `aliasAction`: **u8**（0=unlink, 1=link）

# コンストラクタ（順番・型を固定）
```php
public function __construct(
    string $namespaceIdDec,  // decimal string (u64)
    string $address,         // 24 bytes
    int $aliasAction,        // 0 or 1
    string $headerRaw,
    int $size,
    int $version,
    int $network,
    int $type,
    string $maxFeeDec,
    string $deadlineDec
)
```
# インターフェースと可視性（ここ重要）
- `encodeBody(): string` は **protected**。
- `decodeBody(string $binary, int $offset): array{namespaceIdDec:string,address:string,aliasAction:int,offset:int}` は **protected static**。
- 親の `parseHeader()/u32LE()/u64LE()/readU32LEAt()/readU64LEDecAt()` などは **上書きしない**。**private で再定義もしない**。
- 例外型:
  - 残量不足や unpack 失敗 → `\\RuntimeException`
  - 値域や形式不正（長さ・enum 値）→ `\\InvalidArgumentException`

# バリデーション規約（phpstan の “常に真/偽” を出さない書き方）
- 文字列形式チェックは **`preg_match(...) === 1`** を使う。
- `address` の長さは `strlen($address) !== 24` で判定。**否定に false を混ぜない**（例: `!unpack(...)` のような “int|false” を直接否定しない）。
- `aliasAction` は `if ($aliasAction !== 0 && $aliasAction !== 1) throw ...;` とする。
- **無意味な `is_string($x)` やキャスト**（`(string)$alreadyString` や `(int)$alreadyInt`）は**禁止**。
- `isset($arr['hex'])` のように **常在キーへの isset** は**禁止**（phpstan の “always exists” 回避）。

# 実装詳細
- `fromBinary(string $binary): self`
  - `$h = self::parseHeader($binary); $offset = $h['offset']; $len = strlen($binary);`
  - `namespaceIdDec = self::readU64LEDecAt($binary, $offset); $offset += 8;`
  - `address` は `substr($binary, $offset, 24)`、不足なら `RuntimeException`、`strlen(...)===24` を必須チェック、`$offset += 24;`
  - `aliasAction = ord($binary[$offset]); $offset += 1;` 値は 0/1 以外なら `InvalidArgumentException`
  - `new self(..., $h['headerRaw'], $h['size'], $h['version'], $h['network'], $h['type'], $h['maxFeeDec'], $h['deadlineDec'])` を返す
- `encodeBody(): string`
  - `return self::u64LE($this->namespaceIdDec) . $this->address . chr($this->aliasAction);`
- `decodeBody(string $binary, int $offset)`
  - 上と同順で読み、配列 `['namespaceIdDec'=>..., 'address'=>..., 'aliasAction'=>..., 'offset'=>$offset]` を返す

# 追加型
- **enum は不要**。定数で良いなら必要に応じて:
  final class AliasAction { public const UNLINK = 0; public const LINK = 1; }
  ただし **重複定義しない**。存在確認して重複を避けること。

# 出力形式
- **完成した PHP コードのみ**（説明/フェンス/コメント禁止）。
- ファイル先頭に `<?php` と `declare(strict_types=1);`、正しい **namespace**、`use` は最小限。
- 保存先: `{{output_path}}`