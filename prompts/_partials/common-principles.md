宣言:
- declare(strict_types=1);
- PSR-12、final/readonly、public APIは不変（イミュータブル）
- catbuffer準拠（Little Endian、size自己一致を厳守）
- 例外は InvalidArgumentException / RuntimeException
- PHPStan max、PHPUnit 10+、JS/Python の固定ベクタで往復一致
