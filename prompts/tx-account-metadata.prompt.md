id: tx-account-metadata
version: 1.0.1
purpose: "AccountMetadataTransaction の PHP 実装（共通ヘッダ128B対応／JSON vectors 準拠／PHPStan 無警告方針）"
namespace: "SymbolSdk\Transactions"
class_name: "AccountMetadataTransaction"
output_path: "src/Transactions/AccountMetadataTransaction.php"
references:
  catbuffer: |
    https://github.com/symbol/symbol/blob/main/catbuffer/schemas/symbol/metadata/account_metadata.cats
dependencies:
  base:   "SymbolSdk\Transactions\AbstractTransaction"

---
{{> common-php-guardrails.md }}
{{> common-principles.md }}

# 目的
- `{{namespace}}\{{class_name}}` を **final class** として実装（readonly は **禁止**。親 `AbstractTransaction` は non-readonly）。
- 共通 Tx ヘッダ（128B）は **`AbstractTransaction::parseHeader()`** を使って解析・保持。  
  返却キー: `headerRaw, size, version, network, type, maxFeeDec, deadlineDec, offset`（**キー名固定**）。
- `serialize()` は **親クラスに任せる**（保持ヘッダ + `encodeBody()` を接続）。サブクラスは **`encodeBody()` と `decodeBody()` のみ**実装。

# シグネチャ（固定）
- コンストラクタは **Tx 固有 → 共通ヘッダ 7 項目**の順・名前固定:
  ```php
  public function __construct(
      string $targetAddress,      // 24B
      string $scopedMetadataKeyDec, // u64 decimal string
      int $valueSizeDelta,        // int16 範囲チェック
      string $value,              // 任意長
      string $headerRaw, int $size, int $version, int $network, int $type, string $maxFeeDec, string $deadlineDec
  )
  ```
  - `parent::__construct($headerRaw, $size, $version, $network, $type, $maxFeeDec, $deadlineDec)` の **7 引数**をこの順で呼ぶ。

- 本体のパース/直列化:
  - `public static function fromBinary(string $binary): self`
    1. `$h = self::parseHeader($binary); $offset = $h['offset']; $len = strlen($binary);`
    2. Body を **cats の順**で読み取る（下記「Body 仕様」）。
    3. 値域／残量不足は `InvalidArgumentException` / `RuntimeException` を投げる。
  - `protected function encodeBody(): string`
  - `protected static function decodeBody(string $binary, int $offset): array`
    - 返却は `['targetAddress'=>string,'scopedMetadataKeyDec'=>string,'valueSizeDelta'=>int,'value'=>string,'offset'=>int]`。

# PHPStan ガード（この通りに書く）
- **boolean 判定**で `int|false` を否定しない。`preg_match()` は `=== 1` で比較。`strpos()` は `!== false` で比較。
- **常に true/false** と見なされる式を避ける。必要なら条件を分割:
  ```php
  if (!is_int($valueSizeDelta)) { throw new \InvalidArgumentException(...); }
  if ($valueSizeDelta < -0x8000 || $valueSizeDelta > 0x7FFF) { throw new \InvalidArgumentException(...); }
  ```
- **useless cast** を入れない（既に int/string のものに `(int)/(string)` を付けない）。
- **visibility** は `AbstractTransaction` に合わせて **protected** で override。private で再定義しない。
- `decodeBody()` の引数は **(string $binary, int $offset)** のみ。`$header` を引数に入れない（親のシグネチャに合わせる）。
- `readonly class` としない。プロパティは `public readonly` を使って immutability を担保するのは可。

# Body 仕様（cats 対応）
- `targetAddress`: 24 bytes 固定。`$len - $offset < 24` なら `RuntimeException('Unexpected EOF: need 24 bytes for targetAddress')`。
- `scopedMetadataKeyDec`: u64 LE（8B）→ **10 進文字列**で保持。`$dec = self::readU64LEDecAt($binary, $offset)`。`$offset += 8;`
- `valueSizeDelta`: i16 LE（2B）。`$delta = self::readI16LEAt($binary, $offset)`。`$offset += 2;`  
  - 範囲: `-0x8000..0x7FFF` をチェック。
- `valueSize`: u16 LE（2B）。続いて `value` を `valueSize` バイト取得。
  - `valueSize` 取得後に `$len - $offset < $valueSize` なら EOF 例外。

# バリデーション
- `targetAddress` は `strlen($targetAddress) === 24` のみ厳密チェック。
- `scopedMetadataKeyDec` は `/^[0-9]+$/` に **`preg_match(... ) === 1`** で一致必須。
- `valueSizeDelta` は `is_int($valueSizeDelta)` を先に判定→その後で範囲を別 if で判定（恒真/恒偽回避）。
- `value` は **バイナリ許容**（型は string）。正規表現は使わない。

# 実装ヒント（具体）
- `fromBinary()` の終端で new self(..., **$h['headerRaw'], $h['size'], $h['version'], $h['network'], $h['type'], $h['maxFeeDec'], $h['deadlineDec']**) を渡す。
- `encodeBody()` は **この順で**: `targetAddress (24B)` → `u64LE(scopedMetadataKeyDec)` → `i16LE(valueSizeDelta)` → `u16LE(strlen(value))` → `value`。
- 親のヘルパ群を使う（**再定義しない**）:
  - `readU64LEDecAt() / u64LE($dec)`（10進文字列↔LE 8B）
  - `readI16LEAt()` / `i16LE()`、`readU16LEAt()` / `u16LE()`

# 期待される返り値型
- `decodeBody()` は `array{targetAddress:string, scopedMetadataKeyDec:string, valueSizeDelta:int, value:string, offset:int}` を返す。
- `fromBinary()` は `self`。

# 追加ガード（AccountMetadata 専用）

- **is_int などの型チェックを入れない**  
  すでに型宣言 `int` が付いている引数・プロパティに対して `is_int()` を呼ばないこと。PHPStan が「常に true」と判定するため。

- **int16 / uint16 のLEヘルパ**  
  `AbstractTransaction` に 16bit ヘルパがない前提で、本クラス内に **protected static** で実装すること（private禁止・オーバーライド整合のため）。  
  - `protected static function u16LE(int $v): string`  
    - 範囲: `0..65535` を検証し、`pack('v', $v)` で返す。  
  - `protected static function i16LE(int $v): string`  
    - 範囲: `-32768..32767` を検証。負値は `$v + 0x10000` にして `pack('v', $u)`。  
  - `protected static function readU16LEAt(string $bin, int $offset): int`  
    - `substr($bin, $offset, 2)` の長さ不足を検知して `RuntimeException`。`unpack('vval', $chunk)['val']` を `int` で返す。  
  - `protected static function readI16LEAt(string $bin, int $offset): int`  
    - `readU16LEAt` で `0..65535` を取得し、`$u >= 0x8000 ? $u - 0x10000 : $u` に変換して返す。

- **本文デコード/エンコードの要件（cats順）**  
  AccountMetadata の body は catbuffer の定義順に処理する：  
  1) `targetAddress`（UnresolvedAddress, 24 bytes 固定。残量不足は `RuntimeException`）  
  2) `scopedMetadataKey`（u64, 10進文字列で保持。`readU64LEDecAt()` / `u64LE()` は `AbstractTransaction` のヘルパ使用）  
  3) `valueSizeDelta`（**int16LE**。`readI16LEAt()` / `i16LE()` を使う）  
  4) `valueSize`（**uint16LE**。`readU16LEAt()` / `u16LE()` を使う）  
  5) `value`（`valueSize` バイト。残量検査を行う）

- **可視性とシグネチャ**  
  - `encodeBody(): string` は **protected**。  
  - `decodeBody(string $binary, int $offset): array{targetAddress:string, scopedMetadataKeyDec:string, valueSizeDelta:int, value:string, offset:int}` は **protected static**。  
  - `__construct(...)` は Tx 固有 → ヘッダ7項目（`headerRaw, size, version, network, type, maxFeeDec, deadlineDec`）の順・引数名固定。  
  - 親クラスの `__construct` は **7引数** で必ず呼ぶ。

- **その他**  
  - すべての `unpack()` は `unpack('Vval', ...)` のように **キーを指定**し、`$a === false` の検査は不要（`unpack` は失敗時に false を返さないため）。  
  - 不要なキャスト（既に `int` の値に `(int)`）や、常に真偽が決まる比較は書かない。  

- **unpack の戻りチェック必須**
  - unpack('vval', $chunk) / unpack('Vval', $chunk) 等は、いったん $tmp に受けて
  - is_array($tmp) と isset($tmp['val']) をチェック → 失敗時は RuntimeException。
  - その後に (int)$tmp['val'] を取り出す。配列アクセスの前に必ず検査すること。
- **キー付きフォーマットを使う（vval, Vval など）**
  - キー未指定だと array{1:int} になり扱いにくい。常に val キーを指定する。
- **短絡・冗長回避**
  - unpack() 直後に ['val'] でアクセスする書き方（ワンライナー）は禁止。
  - PHPStan が array|false とみなすので NG。

# 出力
- **PHP コードのみ**を `{{output_path}}` に出力。余計なコメント/説明/コードフェンス禁止。
- ファイル先頭は `<?php` と `declare(strict_types=1);`、`namespace {{namespace}};` を必須。
