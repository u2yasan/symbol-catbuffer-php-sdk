id: tx-node-key-link
version: 1.0.2
purpose: "NodeKeyLinkTransaction の PHP 実装（共通ヘッダ128B対応／JSON vectors準拠）"
namespace: "SymbolSdk\\Transactions"
class_name: "NodeKeyLinkTransaction"
output_path: "src/Transactions/NodeKeyLinkTransaction.php"
references:
  catbuffer: |
    https://github.com/symbol/symbol/blob/main/catbuffer/schemas/symbol/account_link/node_key_link.cats
dependencies:
  base:   "SymbolSdk\\Transactions\\AbstractTransaction"

---
{{> common-php-guardrails.md }}
{{> common-principles.md }}
{{> common-prompt-guidelines.md }}

# 実装ルール（AbstractTransaction準拠・必須）
- クラスは **`{{namespace}}\\{{class_name}}`**、`AbstractTransaction` を **extends**。
- **共通ヘッダ128B** は `self::parseHeader($binary)` を使用。戻りキー名は固定：
  `headerRaw:string, size:int, version:int, network:int, type:int, maxFeeDec:string, deadlineDec:string, offset:int`
- コンストラクタは **必ず** 次のシグネチャで実装：
  ```php
  public function __construct(
      string $linkedPublicKey,
      int $linkAction,
      string $headerRaw, int $size, int $version, int $network, int $type, string $maxFeeDec, string $deadlineDec
  )
  ```
  直後に `parent::__construct($headerRaw, $size, $version, $network, $type, $maxFeeDec, $deadlineDec);` を呼ぶ。
- `fromBinary(string $binary): self` は
  1) `$h = self::parseHeader($binary); $offset = $h['offset'];` から開始  
  2) cats の **body 定義順**で読み取り、残量不足は `RuntimeException`、値域違反は `InvalidArgumentException`  
  3) 取得値＋ヘッダ7項目で **`new self(...)`** を返す
- `encodeBody(): string` は **protected** で **body の直列化のみ**行う（ヘッダは基底の `serialize()` が付与）。
- `decodeBody(string $binary, int $offset): array` は **protected static** で宣言（未使用でも必須）。
- **完成した PHP コードのみ**出力（コメント/説明/フェンス禁止）。`declare(strict_types=1)` と namespace を含める。

# Body（読み取り仕様）
- `linkedPublicKey`: 32 bytes 固定長
- `linkAction`: `u8`（enum: 0 = unlink, 1 = link）

# 追加型
- `enum LinkAction: int { case UNLINK = 0; case LINK = 1; }`
  - もし enum を使わない場合は、`final class LinkAction` に `public const UNLINK = 0; public const LINK = 1;` を定義。

# 実装ヒント
- `fromBinary()` では、`$h = self::parseHeader($binary); $offset = $h['offset']; $len = strlen($binary);` から開始。
- `linkedPublicKey` は `substr($binary, $offset, 32)` で取得し、**長さ不足で例外**。
- `linkAction` は `ord($binary[$offset])` で読み取り、`0/1` 以外は例外。
- `encodeBody()` は **同じ順序で直列化**：`linkedPublicKey`（32B）→ `chr($linkAction)`。
- JSON vectors との比較用に **完全一致**（ヘッダ含む）を目指す。

# 出力
- **コードのみ**、保存先: `{{output_path}}`
- 余計な囲み/説明は禁止。

# cats body（原文）
```cats
struct NodeKeyLinkTransactionBody
{
  linked_public_key = PublicKey
	link_action = LinkAction
}
```