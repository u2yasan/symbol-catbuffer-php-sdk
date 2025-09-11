---
id: model-mosaic-id
version: 1.0.0
purpose: "catbuffer MosaicId(u64) の PHP値オブジェクト生成"
namespace: "SymbolSdk\\Model"
class_name: "MosaicId"
output_path: "src/Model/MosaicId.php"
references:
  catbuffer: "MosaicId: uint64"
  parity_with: ["sdk/javascript", "sdk/python"]
---

{{> common-principles.md }}

# Role
あなたは Symbol/catapult の catbuffer と JS/Python SDKに精通した PHP 8.3 エキスパートです。

# Task
以下の要件に従い {{namespace}}\\{{class_name}} を実装してください。
- 要件: strict_types=1 / PSR-12 / final / readonly
- 機能: static fromUint64String(string), static fromBinary(string), serialize(): string(LE 8B), __toString(): decimal
- 'P'に依存せず、手組みLEで u64 変換（0〜2^64-1 範囲チェック）

# Input
catbuffer 定義: {{ references.catbuffer }}

# Output
- 完成したPHPファイルのみ（<?php から終了まで）
- ファイルパス: {{output_path}}
