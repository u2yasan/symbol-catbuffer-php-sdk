---
id: tx-account-key-link
version: 1.0.0
purpose: "AccountKeyLinkTransaction のPHP実装"
namespace: "SymbolSdk\\Transaction"
class_name: "AccountKeyLinkTransaction"
output_path: "src/Transaction/AccountKeyLinkTransaction.php"
references:
  catbuffer: |
    # BODY（例）
    # - linkedPublicKey: bytes[32]
    # - linkAction: uint8 (0=unlink,1=link)
  parity_with: ["sdk/javascript", "sdk/python"]
dependencies:
  header: "SymbolSdk\\Transaction\\TransactionHeader"
  base:   "SymbolSdk\\Transaction\\AbstractTransaction"
  io:     "SymbolSdk\\Io\\BinaryReader, SymbolSdk\\Io\\BinaryWriter"
---

{{> common-principles.md }}

# Task
- linkedPublicKey(32B固定)の長さ検証
- linkAction(u8) は enum `LinkAction` を `SymbolSdk\Transaction` 下に用意（0/1）

# Output
- **コードのみ**、保存先: `{{output_path}}`
