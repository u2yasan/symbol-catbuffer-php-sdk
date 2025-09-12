---
id: test-tx-transfer
version: 1.0.0
purpose: "TransferTransaction のPHPUnitテスト生成（固定HEXベクタ）"
namespace: "SymbolSdk\\Tests\\Transaction"
class_name: "TransferTransactionTest"
output_path: "tests/Transaction/TransferTransactionTest.php"
inputs:
  vectors:
    valid_min: "HEXを貼る"
    valid_std: "HEXを貼る"
    valid_max: "HEXを貼る"
    invalid_size: "HEXを貼る（期待は例外）"
---

{{> common-php-guardrails.md }}
{{> common-prompt-guidelines.md }}
{{> common-principles.md }}

# Role
PHPUnit 10+ で、decode→re-encode 一致、主要フィールドの期待値一致、例外検証を dataProvider で作成。

# Output
- **テストコードのみ**、保存先: `{{output_path}}`
