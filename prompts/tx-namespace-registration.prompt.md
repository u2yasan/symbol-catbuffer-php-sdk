---
id: tx-namespace-registration
version: 1.0.0
purpose: "NamespaceRegistrationTransaction のPHP実装"
namespace: "SymbolSdk\\Transaction"
class_name: "NamespaceRegistrationTransaction"
output_path: "src/Transaction/NamespaceRegistrationTransaction.php"
references:
  catbuffer: |
    # BODY（例）
    # - registrationType: uint8 (0=root,1=child)
    # - duration: uint64 (rootのみ) or parentId: uint64 (childのみ)
    # - namespaceId: uint64
    # - nameSize: uint8
    # - name: bytes[nameSize]
  parity_with: ["sdk/javascript", "sdk/python"]
dependencies:
  header: "SymbolSdk\\Transaction\\TransactionHeader"
  base:   "SymbolSdk\\Transaction\\AbstractTransaction"
  io:     "SymbolSdk\\Io\\BinaryReader, SymbolSdk\\Io\\BinaryWriter"
  models: "SymbolSdk\\Model\\NamespaceId"
---

{{> common-principles.md }}
{{> partials/common-php-guardrails.md }}

# Task
- registrationType により分岐：root→duration(u64)、child→parentId(u64)
- name は `nameSize` で読む。UTF-8チェックは不要（バイト列として扱う）。
- serialize()/fromBinary() は同一分岐を対称に実装

# Output
- **コードのみ**、保存先: `{{output_path}}`
