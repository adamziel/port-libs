<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\FilterRuleSet;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$source = new MemoryProvider();
$target = new MemoryProvider(true);
$tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';
foreach ($tree as $path => $bytes) {
    $source->put($path, $bytes);
}

$target->put('WP-CONTENT/UPLOADS/2026/05/HERO.JPG', $tree['wp-content/uploads/2026/05/hero.jpg']);
$target->put('EXPORTS/SITE.WXR', '<rss>stale</rss>');
$target->put('WP-CONTENT/CACHE/PAGE/INDEX.HTML', '<html>old cache</html>');

$filter = FilterRuleSet::fromRules([
    '- wp-content/cache/**',
    '- *.log',
    '- *.psd',
    '+ wp-content/uploads/**',
    '+ exports/*.wxr',
    '+ database/*.sql',
    '- *',
]);

$copied = (new SyncPlan())->copyChanged($source, $target, $filter, fixCase: true);

return [
    'copiedPaths' => array_map(static fn ($info) => $info->path, $copied),
    'uploadCanonicalPath' => $target->info('WP-CONTENT/UPLOADS/2026/05/HERO.JPG')->path,
    'exportCanonicalPath' => $target->info('EXPORTS/SITE.WXR')->path,
    'excludedCacheCanonicalPath' => $target->info('wp-content/cache/page/index.html')->path,
];
