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
  ❌ 禁止: `unpack('V', ...)[1]`（array|false の [1] 直アクセスをしない）
- 読み取り前に **残量チェック**：
  ```php
  $remaining = strlen($bin) - $off;
  // @phpstan-ignore-next-line runtime boundary check
  if ($remaining < $need) throw new \RuntimeException("Unexpected EOF: need {$need}, have {$remaining}");
  ```

## Uint64（LE8）— 安全な10進文字列で保持
- 64bit整数は **10進文字列**（`0..18446744073709551615`）で保持。直列化は **LE 8バイト**。
- **禁止事項**：10進文字列に対して **`/` や `%`** 等の数値演算を使わない（PHPStan: *Binary operation between string and int* を回避）。
- **タウトロジー禁止**：`ord()` の結果は常に **0..255**。`0..255` の範囲チェックや、PHPStan が静的に「常に真/偽」と判断する比較（例：`$x < 0 || $x > 4294967295` など）は**書かない**。
- 実装に含める最小ヘルパ（同クラス内 or 共通化）：
  ```php
  private static function cmpDec(string $a, string $b): int { /* 長さ→辞書順 */ }
  /** @return array{0:string,1:int} */
  private static function divmodDecBy(string $dec, int $by): array {
      // long division in base10
      $len = strlen($dec);
      $q = '';
      $carry = 0; // int
      for ($i = 0; $i < $len; $i++) {
          $carry = $carry * 10 + (ord($dec[$i]) - 48); // int
          $digit = intdiv((int)$carry, (int)$by);       // 明示的に (int) キャスト
          $carry = (int)($carry % $by);                 // 明示的に (int)
          if ($q !== '' || $digit !== 0) $q .= chr($digit + 48);
      }
      if ($q === '') $q = '0';
      return [$q, $carry];
  }
  private static function mulDecBy(string $dec, int $by): string {
      if ($dec === '0') return '0';
      $carry = 0; $out = '';
      for ($i = strlen($dec) - 1; $i >= 0; $i--) {
          $t = (ord($dec[$i]) - 48) * $by + $carry; // int
          $out .= chr(($t % 10) + 48);
          $carry = intdiv((int)$t, 10);             // 明示的に (int)
      }
      while ($carry > 0) {
          $out .= chr(($carry % 10) + 48);
          $carry = intdiv((int)$carry, 10);         // 明示的に (int)
      }
      return strrev($out);
  }
  private static function addDecSmall(string $dec, int $small): string {
      $i = strlen($dec) - 1; $carry = $small; $out = '';
      while ($i >= 0 || $carry > 0) {
          $d = $i >= 0 ? (ord($dec[$i]) - 48) : 0;
          $t = $d + $carry;
          $out .= chr(($t % 10) + 48);
          $carry = intdiv((int)$t, 10);            // 明示的に (int)
          $i--;
      }
      for (; $i >= 0; $i--) $out .= $dec[$i];
      $res = strrev($out);
      $res = ltrim($res, '0');
      return $res === '' ? '0' : $res;
  }
  private static function readU64LEDecAt(string $bin, int $off): string {
      $dec = '0';
      for ($i = 7; $i >= 0; $i--) {
          $dec = self::mulDecBy($dec, 256);
          $dec = self::addDecSmall($dec, ord($bin[$off + $i]));
      }
      return $dec;
  }
  private static function u64LE(string $dec): string {
      $max = '18446744073709551615';
      if (!preg_match('/^[0-9]+$/', $dec) || self::cmpDec($dec, $max) > 0) {
          throw new \InvalidArgumentException('u64 decimal out of range');
      }
      $dec = ltrim($dec, '0');
      if ($dec === '') return "\x00\x00\x00\x00\x00\x00\x00\x00";
      $bytes = [];
      $cur = $dec;
      for ($i = 0; $i < 8; $i++) {
          [$q, $r] = self::divmodDecBy($cur, 256); // r: int 0..255
          $bytes[] = chr($r);
          if ($q === '0') {
              for ($j = $i + 1; $j < 8; $j++) $bytes[] = "\x00";
              return implode('', $bytes);
          }
          $cur = $q;
      }
      if ($cur !== '0') throw new \InvalidArgumentException('u64 overflow');
      return implode('', $bytes);
  }
  ```
- **LE8 → 10進** は **base-256 の畳み込み**で行う（*dec = dec*256 + byte* を 8回繰り返す）。
- **他クラスの private を呼ばない**（必要なら同等ヘルパを複製し自己完結）。

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

### Generics風ヘルパ（PHPStanフレンドリー版）
- **テンプレートは使わない**（メソッド内に T を“パラメータ型”として参照できないため警告になる）。
- 代わりに `mixed` を使い、呼び出し側で具体型を担保する。

```php
/**
 * @param int $count
 * @param callable(int):mixed $reader  // 要素 i を読み出して返すクロージャ
 * @return list<mixed>
 */
public function readVector(int $count, callable $reader): array {
    $out = [];
    for ($i = 0; $i < $count; $i++) {
        $out[] = $reader($i);
    }
    return $out; // list<mixed>
}

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
- ❌ `readonly` 再代入 → ✅ ローカルで整形 → **一度だけ**代入
- ❌ 文字列に対する `/`・`%` → ✅ `divmodDecBy()` などの **10進文字列演算**を使う
- ❌ `ord()` の結果に対する 0..255 のレンジチェック → ✅ **書かない**（常に 0..255）
- ❌ 未定義の親メソッド呼び出し → ✅ 親前提があるときのみ（指定が無ければ**単体実装**）
