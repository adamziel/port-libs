<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\FilterRuleSet;
use PortLibs\Rclone\HashSet;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$source = new MemoryProvider(false, new HashSet());
$target = new MemoryProvider(false, new HashSet());

$source->put('exports/site.wxr', '<rss>portable export</rss>', ['modTime' => '2026-05-22T00:00:00Z']);
$source->put('database/site.sql', 'insert into wp_posts values (...)', ['modTime' => '2026-05-22T00:00:00Z']);
$source->put('wp-content/uploads/2026/05/hero.jpg', 'new image bytes', ['modTime' => '2026-05-22T00:00:00Z']);

$target->put('exports/site.wxr', '<rss>portable export</rss>', ['modTime' => '2026-05-20T00:00:00Z']);
$target->put('database/site.sql', 'insert into wp_posts values (...)', ['modTime' => '2026-05-20T00:00:00Z']);
$target->put('wp-content/cache/orphan.html', '<html>cache</html>');

$filter = FilterRuleSet::fromRules([
    '- wp-content/cache/**',
    '+ wp-content/uploads/**',
    '+ exports/*.wxr',
    '+ database/*.sql',
    '- *',
]);

$copied = (new SyncPlan())->copyChanged($source, $target, $filter, refreshTimes: true);

return [
    'copied' => array_map(static fn ($info) => $info->path, $copied),
    'wxrTimestamp' => $target->info('exports/site.wxr')->modTime,
    'sqlTimestamp' => $target->info('database/site.sql')->modTime,
    'wxrBytesPreserved' => $target->get('exports/site.wxr'),
    'excludedCacheLeftUntouched' => $target->get('wp-content/cache/orphan.html'),
];
