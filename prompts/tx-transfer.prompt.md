id: tx-transfer
version: 1.0.0
purpose: "TransferTransaction の PHP 実装（共通ヘッダ128B対応／message / mosaics 拡張互換・JSON vectors準拠）"
namespace: "SymbolSdk\\Transaction"
class_name: "TransferTransaction"
output_path: "src/Transaction/TransferTransaction.php"
references:
  catbuffer: |
    https://github.com/symbol/symbol/blob/main/catbuffer/schemas/symbol/transfer/transfer.cats
dependencies:
  base: "SymbolSdk\\Transaction\\AbstractTransaction"

---
{{> common-php-guardrails.md }}
{{> common-principles.md }}
{{> common-prompt-guidelines.md }}

# 実装ルール（AbstractTransaction準拠・必須）
- クラスは **`{{namespace}}\\{{class_name}}`**、`AbstractTransaction` を **extends**。
- **共通ヘッダ128B** は `self::parseHeader($binary)` を使用。戻りキー名は固定：
  `headerRaw:string, size:int, version:int, network:int, type:int, maxFeeDec:string, deadlineDec:string, offset:int`

- コンストラクタは必ず次のシグネチャ：
  ```php
  /**
   * @param list<array{mosaicIdDec:string, amountDec:string}> $mosaics  // {id, amount} は u64 の10進文字列
   */
  public function __construct(
      string $recipientAddress,             // 24 bytes (UnresolvedAddress)
      array $mosaics,                       // list<{mosaicIdDec, amountDec}>
      string $message,                      // 任意。message_size=length(message)
      string $headerRaw, int $size, int $version, int $network, int $type, string $maxFeeDec, string $deadlineDec
  )
  ```
  直後に `parent::__construct($headerRaw, $size, $version, $network, $type, $maxFeeDec, $deadlineDec);` を呼ぶ。

- `fromBinary(string $binary): self`
  1) `$h = self::parseHeader($binary); $offset = $h['offset'];`
  2) **cats の順序**で body を読み取る：
     - `recipient_address:UnresolvedAddress(24B)`
     - `message_size:uint16 LE`
     - `mosaics_count:uint8`
     - `mosaics: array(UnresolvedMosaic, mosaics_count)`
       - 各 `UnresolvedMosaic` は `{ id:u64, amount:u64 }`（**10進文字列**に復元）
     - `message: array(uint8, message_size)`（そのままバイナリ文字列で保持; 上位層で型/ペイロードを解釈）
  3) 残量不足は `RuntimeException`、値域違反は `InvalidArgumentException`
  4) `new self(...)` を返す

- `encodeBody(): string` は **protected**、**body の直列化のみ**行う：
  - recipient(24B) → `message_size:uint16`（`strlen($message)`）→ `mosaics_count:uint8`
  - mosaics（各要素で `u64LE(idDec) + u64LE(amountDec)`）→ `message`
  - `mosaics` の個数は **0〜255**、`message` 長は **0〜65535** を許容。範囲外は例外。

- `decodeBody(string $binary, int $offset): array` は **protected static**（未使用でも宣言）。

- mosaics は **list<array{mosaicIdDec:string, amountDec:string}>** として受け取ること。
  - 既知キー（mosaicIdDec, amountDec）に対して **`isset()` を使わない**。代わりに `is_string` と `/^\d+$/` で値検証を行う。
  - コード先頭に `/** @var list<array{mosaicIdDec:string, amountDec:string}> $mosaics */` を配置して array-shape を確定する。
  - 重複検出は内部マップ `$ids` に対する `isset($ids[$id])` を用いるのは可。
  
- **互換の注意点（JS/Python SDK 準拠）**
  - `message` は **生バイト列**として保持（先頭1バイトに messageType を含むフォーマットは上位層で扱う）。
  - `mosaics` は順不同・0件可、**同一IDの重複は許容しない**（重複検出時は `InvalidArgumentException`）。

- 完成PHPコードのみを出力（説明/フェンス禁止）。`declare(strict_types=1)` と namespace を含める。
- 配列には `@param list<...>` / `@var list<...>` を必ず付与。戻り型に配列を返す場合は `@return array<string,mixed>`。

# Body（cats準拠の読み取り仕様・要点）
- `recipient_address: UnresolvedAddress`（24 bytes）
- `message_size: uint16`（LE）
- `mosaics_count: uint8`
- `mosaics: array(UnresolvedMosaic, mosaics_count)`（各要素 = `{ id:u64, amount:u64 }`）
- `message: array(uint8, message_size)`
- **u64** は `AbstractTransaction` の `u64DecAt()` / `u64LE()` を使う

# cats body（原文を直貼り）
```cats
inline struct TransferTransactionBody
	# recipient address
	recipient_address = UnresolvedAddress

	# size of attached message
	message_size = uint16

	# number of attached mosaics
	mosaics_count = uint8

	# reserved padding to align mosaics on 8-byte boundary
	transfer_transaction_body_reserved_1 = make_reserved(uint8, 0)

	# reserved padding to align mosaics on 8-byte boundary
	transfer_transaction_body_reserved_2 = make_reserved(uint32, 0)

	# attached mosaics
	@sort_key(mosaic_id)
	mosaics = array(UnresolvedMosaic, mosaics_count)

	# attached message
	message = array(uint8, message_size)

```