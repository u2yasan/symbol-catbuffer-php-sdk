宣言:
- declare(strict_types=1);
- PSR-12、final/readonly、public APIは不変（イミュータブル）
- catbuffer準拠（Little Endian、size自己一致を厳守）
- 例外は InvalidArgumentException / RuntimeException
- PHPStan max、PHPUnit 10+、JS/Python の固定ベクタで往復一致
出力ルール（厳守）:
- 出力は **コードのみ**。説明・見出し・注釈・サンプル・Markdownフェンスは禁止。
- 先頭は `<?php` から開始し、`declare(strict_types=1);` → `namespace ...;` の順。
- 末尾に不要なフェンス（``` や ===END===）を出力しない。