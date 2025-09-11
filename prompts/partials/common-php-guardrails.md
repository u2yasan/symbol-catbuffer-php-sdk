<!-- prompts/partials/common-php-guardrails.md -->
# Output Rules (STRICT)
- **PHP コードのみを出力**。説明・Markdown・コードフェンスは禁止。先頭は `<?php`、直後に `declare(strict_types=1);`、次に正しい `namespace`。
- **1 ファイル 1 クラス**（必要なら同ファイル内に小さな値オブジェクトを最小限で同梱可）。重複定義禁止。
- **ネームスペースは固定**: `namespace {{namespace}};`（例: `SymbolSdk\\Transaction`）。
- 例外は**グローバル**: `\InvalidArgumentException`, `\RuntimeException`。独自例外は禁止。
- PHP 8.3+ 準拠。`readonly`/型宣言を必須。プロパティはコンストラクタで**一度だけ代入**。

# Binary IO Rules
- `substr()` の **false 判定はしない**（PHP8+は false を返さない）。**長さ検証は `strlen($chunk) !== N`** で行う。
- `unpack()` は **必ず名前付きキー**で受け取り、**失敗チェック**を行う。
  - ✅ 例:
    ```php
    $chunk = substr($bin, $off, 4);
    if (strlen($chunk) !== 4) throw new \RuntimeException('EOF u32');
    $u = unpack('Vvalue', $chunk);
    if ($u === false) throw new \RuntimeException('unpack failed');
    /** @var array{value:int} $u */
    $v = $u['value'];
    ```
  - ❌ NG: `unpack('V', ...)[1]`
- 位置ポインタ `$offset` を使うときは**先に残量チェック**：
  ```php
  $remaining = strlen($bin) - $offset;
  if ($remaining < $need) throw new \RuntimeException("EOF need $need");
