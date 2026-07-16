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

$target->put('exports/site.wxr', $tree['exports/site.wxr']);
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
$stats = null;
$copied = $plan->copyChanged(
    $source,
    $target,
    $filter,
    noTraverse: true,
    noTraverseStats: $stats,
    syncDeleteMode: DeleteMode::AFTER,
);
$deleted = $plan->deleteDestinationOnly($source, $target, $filter, DeleteMode::AFTER);

return [
    'copied' => array_map(static fn ($info): string => $info->path, $copied),
    'deleted' => array_map(static fn ($info): string => $info->path, $deleted),
    'noTraverseEnabled' => $stats['enabled'],
    'noTraverseDisabledReason' => $stats['disabledReason'],
    'targetListUsed' => $stats['targetListUsed'],
    'targetLookups' => $stats['targetLookups'],
    'cacheLeftUntouched' => $target->get('wp-content/cache/orphan.html'),
];
