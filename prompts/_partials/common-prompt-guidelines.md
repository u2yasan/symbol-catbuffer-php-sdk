# 共通プロンプト指針（増補）

- **入力形式**
  - `.cats` の body 定義は **直接貼り付けない**。必要な場合は「要件化」して記述する。
  - 出力は **PHP コードのみ**。Markdown の ```php フェンスや説明文は一切不要。

- **クラス設計**
  - 名前空間・クラス名・出力パスは **必ず front-matter の値を使う**（メタ情報に従う）。
  - `declare(strict_types=1);` と `namespace` はファイル冒頭に必須。

- **継承/ヘッダ**
  - 全トランザクションは `AbstractTransaction` を **extends**。
  - ヘッダ128Bは `parseHeader()` / `serializeHeader()` に完全依存。
  - サブクラスでは `encodeBody()` / `decodeBody()` のみ実装。

- **例外処理**
  - バッファ不足 → `RuntimeException`
  - 値域違反 → `InvalidArgumentException`
  - メッセージには「期待サイズ・不足バイト数」を含める。

- **PHPDoc / 型**
  - 配列は `@var list<Foo>` / `@param list<Foo>` を必ず明示。
  - 戻り値が配列の場合は `@return array<string,mixed>` など具体的に指定。
  - `u64` は必ず **10進文字列**で扱い、変換は `AbstractTransaction` のヘルパーを使用。

- **禁止事項**
  - `unpack('P')` のような **環境依存のu64処理は禁止**。
  - private で `encodeBody` や `decodeBody` を再定義しない（protected以上必須）。
  - `short ternary (?:)` は禁止。`??` または通常の三項演算子を使う。

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

