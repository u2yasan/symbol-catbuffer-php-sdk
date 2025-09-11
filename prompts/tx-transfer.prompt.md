---
id: tx-transfer
version: 1.0.0
purpose: "TransferTransaction のPHP実装（ヘッダ＋ボディ）"
namespace: "SymbolSdk\\Transaction"
class_name: "TransferTransaction"
output_path: "src/Transaction/TransferTransaction.php"
references:
  catbuffer: |
    # catbuffer（例）
    # TransferTransaction BODY:
    # - recipientAddress: bytes[24]
    # - messageSize: uint16
    # - mosaicsCount: uint16
    # - message: bytes[messageSize]
    # - mosaics: array[Mosaic](length=mosaicsCount)
    # Mosaic struct:
    #   - mosaicId: uint64
    #   - amount:  uint64
  parity_with: ["sdk/javascript", "sdk/python"]
dependencies:
  header: "SymbolSdk\\Transaction\\TransactionHeader"
  base:   "SymbolSdk\\Transaction\\AbstractTransaction"
  io:     "SymbolSdk\\Io\\BinaryReader, SymbolSdk\\Io\\BinaryWriter"
  models: "SymbolSdk\\Model\\MosaicId, SymbolSdk\\Model\\Amount（u64）, 他必要あれば"
---

{{> common-principles.md }}

# Role
catbuffer順序で Transfer のボディを正確に実装し、ヘッダと連結して同一バイト列を保証します。

# Task
- recipientAddress(24B固定), messageSize(u16), mosaicsCount(u16), message(var bytes), mosaics(vector of struct)
- struct Mosaic { mosaicId(u64), amount(u64) } は**小さな値オブジェクトクラス**として `SymbolSdk\Model` 下に内製してもよい（serialize/fromBinary あり）。

## 実装要件
- final class {{class_name}} extends {{dependencies.base}}
- fromBinary():
  - readerでヘッダ→ボディ
  - アドレス長24B検証、messageは `messageSize` 分だけ読む
  - mosaicsCount 分だけ struct を読む
  - ヘッダ `size` と合致検証
- serialize():
  - writerでボディを書き、最後にヘッダと連結
- getter（recipientAddress(): string, message(): string, mosaics(): array など）を用意
- 例外/境界チェック徹底

# Output
- **コードのみ**（`<?php` 始まり、保存先: `{{output_path}}`）
