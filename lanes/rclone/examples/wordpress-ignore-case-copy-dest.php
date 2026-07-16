<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\FilterRuleSet;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$source = new MemoryProvider();
$target = new MemoryProvider();
$copyDest = new MemoryProvider();
$tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';
foreach ($tree as $path => $bytes) {
    $source->put($path, $bytes);
}

$target->put('WP-CONTENT/UPLOADS/2026/05/HERO.JPG', 'previous hero bytes');
$target->put('EXPORTS/SITE.WXR', '<rss>previous export</rss>');
$target->put('wp-content/cache/page/index.html', '<html>stale cache</html>');

$copyDest->put('WP-CONTENT/UPLOADS/2026/05/HERO.JPG', $tree['wp-content/uploads/2026/05/hero.jpg']);
$copyDest->put('EXPORTS/SITE.WXR', $tree['exports/site.wxr']);
$copyDest->put('wp-content/uploads/2026/05/hero.webp', $tree['wp-content/uploads/2026/05/hero.webp']);
$copyDest->put('database/site.sql', $tree['database/site.sql']);

$filter = FilterRuleSet::fromRules([
    '- archive/**',
    '- wp-content/cache/**',
    '- *.log',
    '- *.psd',
    '+ wp-content/uploads/**',
    '+ exports/*.wxr',
    '+ database/*.sql',
    '- *',
], ignoreCase: true);

$plan = new SyncPlan();
$copied = $plan->copyChanged(
    $source,
    $target,
    $filter,
    backupPrefix: 'archive/2026-05-22',
    copyDest: [$copyDest],
    ignoreCaseSync: true,
);

return [
    'copiedPaths' => array_map(static fn ($info) => $info->path, $copied),
    'retainedUploadCasing' => $target->info('WP-CONTENT/UPLOADS/2026/05/HERO.JPG')->path,
    'retainedExportCasing' => $target->info('EXPORTS/SITE.WXR')->path,
    'archivedUploadPath' => 'archive/2026-05-22/WP-CONTENT/UPLOADS/2026/05/HERO.JPG',
    'archivedExportPath' => 'archive/2026-05-22/EXPORTS/SITE.WXR',
    'destinationOnlyDeletes' => $plan->deletePaths($source, $target, $filter, ignoreCaseSync: true),
    'excludedCacheBytes' => $target->get('wp-content/cache/page/index.html'),
];
