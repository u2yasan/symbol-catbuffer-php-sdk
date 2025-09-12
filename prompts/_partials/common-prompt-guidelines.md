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
