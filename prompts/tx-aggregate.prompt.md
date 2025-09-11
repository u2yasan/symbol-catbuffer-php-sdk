---
id: tx-aggregate
version: 1.0.0
purpose: "Aggregate{Complete|Bonded}Transaction のPHP実装"
namespace: "SymbolSdk\\Transaction"
class_name: "AggregateCompleteTransaction"     # or AggregateBondedTransaction
output_path: "src/Transaction/AggregateCompleteTransaction.php"
references:
  catbuffer: |
    # BODY（例）
    # - transactionsHash: bytes[32]
    # - payloadSize: uint32
    # - payload: bytes[payloadSize]  # 連結されたインナートランザクション群
    # - cosignaturesCount: uint8
    # - cosignatures: array[Cosig] (count)
    # Cosig struct:
    #   - signerPublicKey: bytes[32]
    #   - signature: bytes[64]
    #   - version: uint8 (必要なら)
  parity_with: ["sdk/javascript", "sdk/python"]
dependencies:
  header: "SymbolSdk\\Transaction\\TransactionHeader"
  base:   "SymbolSdk\\Transaction\\AbstractTransaction"
  io:     "SymbolSdk\\Io\\BinaryReader, SymbolSdk\\Io\\BinaryWriter"
---

{{> common-principles.md }}

# Task
- payload は **インナートランザクションをそのままバイト列**として扱う最小版でも良い（まずはパススルー）。
- 将来は `InnerTransactionFactory` を用意し、type で分岐して具象Txを再帰デコード。
- transactionsHash(32B) は固定長検証。
- cosignatures は struct を vector で読む/書く。

# Output
- **コードのみ**、保存先: `{{output_path}}`
