<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\FilterRuleSet;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$source = new MemoryProvider();
$target = new MemoryProvider();

$source->put('database/site.sql', 'fresh sql dump', ['modTime' => '2026-05-22T00:00:00Z']);
$source->put('exports/site.wxr', '<rss>source export</rss>', ['modTime' => '2026-05-21T00:00:00Z']);
$source->put('wp-content/uploads/2026/05/hero.jpg', 'image-A', ['modTime' => '2026-05-22T00:00:00Z']);

$target->put('database/site.sql', 'stale sql dump', ['modTime' => '2026-05-21T00:00:00Z']);
$target->put('exports/site.wxr', '<rss>remote recovery export</rss>', ['modTime' => '2026-05-23T00:00:00Z']);
$target->put('wp-content/uploads/2026/05/hero.jpg', 'image-B', ['modTime' => '2026-05-22T00:00:00.500000Z']);
$target->put('wp-content/cache/orphan.html', '<html>cache</html>');

$filter = FilterRuleSet::fromRules([
    '- wp-content/cache/**',
    '+ wp-content/uploads/**',
    '+ exports/*.wxr',
    '+ database/*.sql',
    '- *',
]);

$copied = (new SyncPlan())->copyChanged(
    $source,
    $target,
    $filter,
    updateOlder: true,
    modifyWindowSeconds: 1,
    checksum: true,
);

return [
    'copied' => array_map(static fn ($info) => $info->path, $copied),
    'freshSql' => $target->get('database/site.sql'),
    'newerRemoteExportPreserved' => $target->get('exports/site.wxr'),
    'sameWindowChecksumRefresh' => $target->get('wp-content/uploads/2026/05/hero.jpg'),
    'excludedCacheLeftUntouched' => $target->get('wp-content/cache/orphan.html'),
];
