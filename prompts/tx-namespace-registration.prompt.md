id: tx-namespace-registration
version: 1.0.0
purpose: "NamespaceRegistrationTransaction の PHP 実装（共通ヘッダ128B対応／JSON vectors準拠）"
namespace: "SymbolSdk\\Transactions"
class_name: "NamespaceRegistrationTransaction"
output_path: "src/Transactions/NamespaceRegistrationTransaction.php"
references:
  catbuffer: |
    https://github.com/symbol/symbol/blob/main/catbuffer/schemas/symbol/namespace/namespace_registration.cats
dependencies:
  base: "SymbolSdk\\Transactions\\AbstractTransaction"

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
  public function __construct(
      int $registrationType,              // 0=root, 1=child
      ?string $durationDec,               // root のとき u64 (10進文字列) / child のとき null
      ?string $parentIdDec,               // child のとき u64 (10進文字列) / root のとき null
      string $name,                       // nameSize + name(bytes)
      string $namespaceIdDec,             // u64 (10進文字列)
      string $headerRaw, int $size, int $version, int $network, int $type, string $maxFeeDec, string $deadlineDec
  )
  ```
  直後に `parent::__construct($headerRaw, $size, $version, $network, $type, $maxFeeDec, $deadlineDec);` を呼ぶ。
- `fromBinary(string $binary): self`
  1) `$h = self::parseHeader($binary); $offset = $h['offset'];`
  2) cats の **body 定義順**で読み取り（`registrationType` 分岐に注意）
  3) u64 は **10進文字列**で保持（基底クラスのヘルパ使用）
  4) 残量不足は `RuntimeException`、値域違反は `InvalidArgumentException`
  5) `new self(...)` を返す
- `encodeBody(): string` は **protected**、**body の直列化のみ**行う（ヘッダ付与は基底に任せる）。
- `decodeBody(string $binary, int $offset): array` は **protected static**（未使用でも宣言）。
- **完成した PHP コードのみ**出力（説明/フェンス禁止）。`declare(strict_types=1)` と namespace を含める。
- 可変長 `name` は `nameSize:u8` + `name[bytes]`。残量チェックを必ず行う。

# Body（cats準拠の読み取り仕様・要点）
- `registrationType:u8`（0=root, 1=child）
- **root**: `duration:u64` を読み、`parentId` は無し（null）
- **child**: `parentId:u64` を読み、`duration` は無し（null）
- `nameSize:u8`、続けて `name[nameSize]` bytes
- `namespaceId:u64`
- 予約領域があれば cats に従ってスキップ
- **u64** は `AbstractTransaction` の `u64DecAt()` / `u64LE()` を使う

# cats body（原文を直貼り）
```cats
inline struct NamespaceRegistrationTransactionBody
	# Number of confirmed blocks you would like to rent the namespace for. Required for root namespaces.
	duration = BlockDuration if ROOT equals registration_type

	# Parent namespace identifier. Required for sub-namespaces.
	parent_id = NamespaceId if CHILD equals registration_type

	# Namespace identifier.
	id = NamespaceId

	# Namespace registration type.
	registration_type = NamespaceRegistrationType

	# Namespace name size in bytes.
	name_size = uint8

	# Namespace name.
	name = array(uint8, name_size)
```