<!-- prompts/partials/common-php-guardrails.md -->
> 目的：PHP 8.3+ / PHPStan クリーン / テスト互換のコードだけを生成する。
> 出力は **PHPコードのみ**（説明やMarkdownは出力しない）。

## Output Rules（STRICT）
- **PHPコードのみを出力**。説明文・Markdown・コードフェンスは禁止。
  先頭は必ずこの3行から開始：
  1) `<?php`
  2) `declare(strict_types=1);`
  3) `namespace {{namespace}};`
- **1ファイル1クラス**（必要なら同ファイルに小さな値オブジェクト最小実装を同梱可）。重複定義禁止。
- 例外は **\InvalidArgumentException / \RuntimeException**（グローバル名前空間）だけを使用。
- PHP 8.3+ 前提。**strict types / 型宣言 / readonly** を必須。readonly は **コンストラクタで一度だけ代入**。
- 戻り値は宣言通り（例：`serialize(): string` で **false を返さない**）。

## Binary IO Rules
- `substr()` は PHP8+ で **false を返さない**。**長さチェックでEOF判定**：
  ```php
  $chunk = substr($bin, $off, 4);
  if (strlen($chunk) !== 4) throw new \RuntimeException('Unexpected EOF (need 4 bytes).');
  ```
- `unpack()` は **必ず名前付きキー**＋**失敗チェック**：
  ```php
  $u = unpack('Vvalue', $chunk);
  if ($u === false) throw new \RuntimeException('unpack failed');
  /** @var array{value:int} $u */
  $v = $u['value'];
  ```
  ❌ 禁止: `unpack('V', ...)[1]`
- 読み取り前に **残量チェック**：
  ```php
  $remaining = strlen($bin) - $off;
  // @phpstan-ignore-next-line runtime boundary check
  if ($remaining < $need) throw new \RuntimeException("Unexpected EOF: need {$need}, have {$remaining}");
  ```

## Uint64（LE8）— 安全な10進文字列で保持
- 64bit整数は **10進文字列**（`0..18446744073709551615`）で保持。直列化は **LE 8バイト**。
- 実装に含める最小ヘルパ（同クラス内 or 共通化）：
  ```php
  private static function cmpDec(string $a, string $b): int { /* 長さ→辞書順 */ }
  /** @return array{0:string,1:int} */
  private static function divmodDecBy(string $dec, int $by): array { /* 手動割り算 */ }
  private static function mulDecBy(string $dec, int $by): string { /* 手動乗算 */ }
  private static function addDecSmall(string $dec, int $small): string { /* 桁上がり */ }
  private static function readU64LEDecAt(string $bin, int $off): string { /* base256畳み込み */ }
  private static function u64LE(string $dec): string {
      $max = '18446744073709551615';
      if (!preg_match('/^[0-9]+$/', $dec) || self::cmpDec($dec, $max) > 0) {
          throw new \InvalidArgumentException('u64 decimal out of range');
      }
      // divmod で8バイト生成
  }
  ```
- **同ファイルの値オブジェクト（例：Mosaic）**が u64 を直列化する場合、**他クラスの private を呼ばない**（必要なら同等ヘルパを複製し自己完結）。

## Arrays & Generics（PHPDocで厳密化）
- 可変配列は **値型を明示**：
  - `/** @var list<Mosaic> */ private readonly array $mosaics;`
  - `/** @param list<Mosaic> $mosaics */`
- ジェネリック風ヘルパにはテンプレート注釈：
  ```php
  /**
   * @template T
   * @param int $count
   * @param callable(int):T $reader
   * @return list<T>
   */
  public function readVector(int $count, callable $reader): array {
      $out = [];
      for ($i = 0; $i < $count; $i++) $out[] = $reader($i);
      return $out;
  }
  ```
- 文字列には `count()` を使わない。**`strlen()` を使う**。

## Class Header & Namespace
- 先頭3行の順序は固定：
  ```php
  <?php
  declare(strict_types=1);
  namespace {{namespace}};
  ```
- 既定 `namespace` はプロンプト変数 `{{namespace}}`（例：`SymbolSdk\\Transaction`）。**`App\...` は使わない**。

## Defensive Checks（PHPStan 友好）
- 実行時ガードは **必要**。PHPStan が「常に false」と誤推論する場合のみ、その直前に：
  ```php
  // @phpstan-ignore-next-line runtime boundary check
  ```
- 早期 `markTestSkipped()` 相当の直後には **`return;` を置く**（到達不能を作らない）。

## Test ベクタ連携（transactions.json / .hex）
- **ボディのみ**実装時、**ヘッダ+ボディ**のベクタは **テスト側でスキップの可能性**あり。
- 未定義の親クラス／メソッド（`parent::...` など）は **呼ばない**。親を導入する指示がある時だけ使う。

## NG → OK 対応表（抜粋）
- ❌ `unpack('V', ...)[1]` → ✅ `unpack('Vvalue', ...); $u['value']`
- ❌ `substr(...) === false` → ✅ `strlen($chunk) !== N`
- ❌ `count($string)` → ✅ `strlen($string)`
- ❌ readonly 再代入 → ✅ ローカルで整形 → **一度だけ**代入
- ❌ 未定義の親メソッド呼び出し → ✅ 親前提があるときのみ（指定が無ければ**単体実装**）
