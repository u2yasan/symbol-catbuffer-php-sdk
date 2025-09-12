id: tx-aggregate
version: 1.0.0
purpose: "AggregateCompleteTransaction の PHP 実装（共通ヘッダ128B対応／cosignatures あり／JSON vectors準拠）"
namespace: "SymbolSdk\\Transaction"
# class_name と output_path はコマンド引数で与える
references:
  catbuffer: |
    https://github.com/symbol/symbol/blob/main/catbuffer/schemas/symbol/aggregate/aggregate.cats
dependencies:
  base: "SymbolSdk\\Transaction\\AbstractTransaction"

---
{{> common-php-guardrails.md }}
{{> common-principles.md }}

# 実装ルール（AbstractTransaction準拠・必須）
- クラスは **`{{namespace}}\\{{class_name}}`**、`AbstractTransaction` を **extends**。
- **共通ヘッダ128B** は `self::parseHeader($binary)` を使用。戻りキー名は固定：
  `headerRaw:string, size:int, version:int, network:int, type:int, maxFeeDec:string, deadlineDec:string, offset:int`

- フィールド（cats body に準拠）
  - `transactionsHash: Hash256`（32B）
  - `payloadSize: uint32`（LE）
  - `aggregateTransactions: array(byte, payloadSize)`（= EmbeddedTransaction 群の**生バイト列**）
  - `cosignatures: array(Cosignature, cosignaturesCount)`（終端まで繰り返し）

- **Cosignature 構造（cats 準拠）**
  - `version:uint32`（LE, 一般に値=0）
  - `signerPublicKey: PublicKey`（32B）
  - `signature: Signature`（64B）
  - （DetachedCosignature の `parentHash` は **AggregateTx 本体には含まれない**点に注意）

- コンストラクタは次のシグネチャ：
  ```php
  /**
   * @param list<string> $innerPayloads  // EmbeddedTransaction を連結した raw チャンク（SizePrefixedEntity 境界で分割済）
   * @param list<array{version:int, signerPublicKey:string, signature:string}> $cosignatures // 各バイト列はそのまま保持
   */
  public function __construct(
      string $transactionsHash,
      array $innerPayloads,
      array $cosignatures,
      string $headerRaw, int $size, int $version, int $network, int $type, string $maxFeeDec, string $deadlineDec
  )
  ```
  - `transactionsHash` は **32B** の生バイト（長さ厳格）
  - `innerPayloads` は **各要素が SizePrefixedEntity 1件ぶんの生バイト**（境界を保つ）。連結総和が `payloadSize` に一致すること。
  - `cosignatures[*].version` は `int`、`signerPublicKey(32B)` / `signature(64B)` は生バイト。

- `fromBinary(string $binary): self`
  1) `$h = self::parseHeader($binary); $offset = $h['offset']; $len = strlen($binary);`
  2) 32B の `transactionsHash` を読む
  3) `payloadSize:uint32(LE)` を読む（`$payloadSize`）
  4) **埋込Tx領域**を `$payload = substr($binary, $offset, $payloadSize)` で切り出し、`$offset += $payloadSize`
     - `$payload` を SizePrefixedEntity の反復でスキャン：  
       `while ($pos < $payloadSize) { $need(4); $size = unpack('V', substr($payload,$pos,4))[1]; $chunk = substr($payload,$pos,$size); $pos += $size; $innerPayloads[] = $chunk; }`  
       余剰・不足があれば `RuntimeException`
  5) 残余は **Cosignature** の繰り返し：  
     `while ($offset + 4 + 32 + 64 <= $len) { version:u32, signer:32B, signature:64B }`
  6) `new self($transactionsHash, $innerPayloads, $cosignatures, ...header...)`

- `encodeBody(): string` は **protected**：
  1) `$out = $transactionsHash (32B)`  
  2) `$payload = implode('', $this->innerPayloads);`  
     - 先頭に `pack('V', strlen($payload))` を書き出し  
     - 続けて `$payload` 本体  
  3) 各 cosignature を `pack('V',version) . signer(32B) . signature(64B)` で連結

- 妥当性
  - `transactionsHash` 長は **必ず 32B**
  - `innerPayloads` の各チャンク先頭 `uint32` size と実長一致を検査（0 やオーバーは例外）
  - `payloadSize == sum(chunks)` を保証
  - `cosignatures` は残余バイトが **100B 単位**で割り切れること（`4+32+64`）。端数があれば例外。

- 完成 PHP のみ出力（説明/フェンス禁止）。`declare(strict_types=1)` と `namespace` を含める。
- 配列には `@param list<...>` / `@var list<...>` を必ず付与。戻り値配列は `@return array<string,mixed>` 等で値型を指定。

# cats body（原文を直貼り）
```cats
# Hash of the aggregate's transaction.
	transactions_hash = Hash256

	# Transaction payload size in bytes.
	#
	# This is the total number of bytes occupied by all embedded transactions,
	# including any padding present.
	payload_size = uint32

	# Reserved padding to align end of AggregateTransactionHeader to an 8-byte boundary.
	aggregate_transaction_header_reserved_1 = make_reserved(uint32, 0)

	# Embedded transaction data.
	#
	# Transactions are variable-sized and the total payload size is in bytes.
	#
	# Embedded transactions cannot be aggregates.
	@is_byte_constrained
	@alignment(8)
	transactions = array(EmbeddedTransaction, payload_size)

	# Cosignatures data.
	#
	# Fills up remaining body space after transactions.
	cosignatures = array(Cosignature, __FILL__)
```
