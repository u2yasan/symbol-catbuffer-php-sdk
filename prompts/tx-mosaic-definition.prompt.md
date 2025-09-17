id: tx-mosaic-definition
version: 1.0.0
purpose: "MosaicDefinitionTransaction のPHP実装"
namespace: "SymbolSdk\\Transactions"
class_name: "MosaicDefinitionTransaction"
output_path: "src/Transactions/MosaicDefinitionTransaction.php"
references:
  catbuffer: |
    https://github.com/symbol/symbol/blob/main/catbuffer/schemas/symbol/mosaic/mosaic_definition.cats
  parity_with: ["sdk/javascript", "sdk/python"]
dependencies:
  header: "SymbolSdk\\Transactions\\TransactionHeader"
  base:   "SymbolSdk\\Transactions\\AbstractTransaction"
  io:     "SymbolSdk\\Io\\BinaryReader, SymbolSdk\\Io\\BinaryWriter"
  models: "SymbolSdk\\Models\\MosaicId, SymbolSdk\\Models\\Amount"
---

{{> common-principles.md }}
{{> common-php-guardrails.md }}
{{> common-prompt-guidelines.md }}

# Role
catbuffer準拠の順序とサイズでボディを実装。flagsはbit演算でgetterを用意。

# Task
- fromBinary(): nonce(u32LE), mosaicId(u64), flags(u8), divisibility(u8), duration(u64)
- serialize(): 同順でLE出力
- size(): ヘッダ＋固定ボディ長
- flags は `isSupplyMutable()`, `isTransferable()` など **ビットgetter** を提供

# Output
- **コードのみ**、保存先: `{{output_path}}`
