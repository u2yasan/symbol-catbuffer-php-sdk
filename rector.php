<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $config): void {
    $config->paths([__DIR__ . '/src', __DIR__ . '/tests']);

    $config->sets([
        LevelSetList::UP_TO_PHP_84,
        SetList::TYPE_DECLARATION,      // 型付けの自動補完
        SetList::EARLY_RETURN,          // ネスト削減
        SetList::CODE_QUALITY,          // 可読性・品質
    ]);

    // 既存方針：u64 は decimal-string → 直列化/復元は専用ヘルパ使用
    // Rector が int 化等を提案した場合でも、ここは手動審査に。
};
