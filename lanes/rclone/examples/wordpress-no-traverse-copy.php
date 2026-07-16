<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

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

$stats = null;
$copied = (new SyncPlan())->copyChanged(
    $source,
    $target,
    $filter,
    noTraverse: true,
    noTraverseStats: $stats,
);

return [
    'copied' => array_map(static fn ($info): string => $info->path, $copied),
    'targetLookups' => $stats['targetLookups'],
    'targetMatches' => $stats['targetMatches'],
    'targetMisses' => $stats['targetMisses'],
    'sourceOnlyDirectories' => $stats['sourceOnlyDirectories'],
    'targetListUsed' => $stats['targetListUsed'],
    'cacheLeftUntouched' => $target->get('wp-content/cache/orphan.html'),
];
