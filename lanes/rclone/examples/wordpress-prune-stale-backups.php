<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\FilterRuleSet;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$source = new MemoryProvider();
$target = new MemoryProvider();

foreach (require __DIR__ . '/../fixtures/wordpress-backup-tree.php' as $path => $bytes) {
    $source->put($path, $bytes);
}

$target->put('wp-content/uploads/2026/05/hero.jpg', 'old image bytes');
$target->put('wp-content/uploads/2024/01/obsolete.jpg', 'obsolete image bytes');
$target->put('exports/old-site.wxr', '<rss>old</rss>');
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
$copied = $plan->copyChanged($source, $target, $filter);
$deleted = $plan->deleteDestinationOnly($source, $target, $filter);

return [
    'copied' => array_map(static fn ($info) => $info->path, $copied),
    'deleted' => array_map(static fn ($info) => $info->path, $deleted),
    'remaining' => array_map(static fn ($info) => $info->path, $target->list()),
];
