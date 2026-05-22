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

$target->put('WP-CONTENT/UPLOADS/2026/05/HERO.JPG', $tree['wp-content/uploads/2026/05/hero.jpg']);
$target->put('EXPORTS/SITE.WXR', $tree['exports/site.wxr']);
$target->put('wp-content/cache/page/index.html', '<html>stale cache</html>');

$filter = FilterRuleSet::fromRules([
    '- wp-content/cache/**',
    '- *.log',
    '- *.psd',
    '+ wp-content/uploads/**',
    '+ exports/*.wxr',
    '+ database/*.sql',
    '- *',
], ignoreCase: true);

$plan = new SyncPlan();
$copied = $plan->copyChanged($source, $target, $filter, ignoreCaseSync: true);

return [
    'copiedPaths' => array_map(static fn ($info) => $info->path, $copied),
    'uploadRetainedPath' => $target->info('WP-CONTENT/UPLOADS/2026/05/HERO.JPG')->path,
    'exportRetainedPath' => $target->info('EXPORTS/SITE.WXR')->path,
    'destinationOnlyDeletes' => $plan->deletePaths($source, $target, $filter, ignoreCaseSync: true),
    'excludedCacheBytes' => $target->get('wp-content/cache/page/index.html'),
];
