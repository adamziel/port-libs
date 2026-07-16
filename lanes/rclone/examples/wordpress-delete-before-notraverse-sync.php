<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\DeleteMode;
use PortLibs\Rclone\FilterRuleSet;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$source = new MemoryProvider();
$target = new MemoryProvider();
$tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';

foreach ($tree as $path => $bytes) {
    $source->put($path, $bytes);
}

$target->put('exports/site.wxr', '<rss>previous export</rss>');
$target->put('exports/old-site.wxr', '<rss>old export</rss>');
$target->put('wp-content/uploads/2026/05/hero.jpg', 'previous hero bytes');
$target->put('wp-content/cache/orphan.html', '<html>stale cache</html>');

$filter = FilterRuleSet::fromRules([
    '- wp-content/cache/**',
    '- *.log',
    '- *.psd',
    '+ wp-content/uploads/**',
    '+ exports/*.wxr',
    '+ database/*.sql',
    '- *',
]);

$plan = new SyncPlan();
$copyPassStats = null;
$result = $plan->syncWithDeleteMode(
    $source,
    $target,
    $filter,
    deleteMode: DeleteMode::BEFORE,
    noTraverse: true,
    noTraverseStats: $copyPassStats,
);

return [
    'copied' => array_map(static fn ($info): string => $info->path, $result['copied']),
    'deleted' => array_map(static fn ($info): string => $info->path, $result['deleted']),
    'deletePassNoTraverseEnabled' => $result['deletePassNoTraverse']['enabled'],
    'deletePassNoTraverseReason' => $result['deletePassNoTraverse']['disabledReason'],
    'copyPassNoTraverseEnabled' => $copyPassStats['enabled'],
    'copyPassTargetListUsed' => $copyPassStats['targetListUsed'],
    'copyPassTargetLookups' => $copyPassStats['targetLookups'],
    'cacheLeftUntouched' => $target->get('wp-content/cache/orphan.html'),
];
