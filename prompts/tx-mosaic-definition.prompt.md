---
id: tx-mosaic-definition
version: 1.0.0
purpose: "MosaicDefinitionTransaction のPHP実装"
namespace: "SymbolSdk\\Transaction"
class_name: "MosaicDefinitionTransaction"
output_path: "src/Transaction/MosaicDefinitionTransaction.php"
references:
  catbuffer: |
    # BODY（例）
    # - nonce: uint32
    # - mosaicId: uint64
    # - flags: uint8 (bitset)
    # - divisibility: uint8
    # - duration: uint64
  parity_with: ["sdk/javascript", "sdk/python"]
dependencies:
  header: "SymbolSdk\\Transaction\\TransactionHeader"
  base:   "SymbolSdk\\Transaction\\AbstractTransaction"
  io:     "SymbolSdk\\Io\\BinaryReader, SymbolSdk\\Io\\BinaryWriter"
  models: "SymbolSdk\\Model\\MosaicId, SymbolSdk\\Model\\Amount"
---

{{> common-principles.md }}
{{> partials/common-php-guardrails.md }}

# Role
catbuffer準拠の順序とサイズでボディを実装。flagsはbit演算でgetterを用意。

# Task
- fromBinary(): nonce(u32LE), mosaicId(u64), flags(u8), divisibility(u8), duration(u64)
- serialize(): 同順でLE出力
- size(): ヘッダ＋固定ボディ長
- flags は `isSupplyMutable()`, `isTransferable()` など **ビットgetter** を提供

# Output
- **コードのみ**、保存先: `{{output_path}}`
