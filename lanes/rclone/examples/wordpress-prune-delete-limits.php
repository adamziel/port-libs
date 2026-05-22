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

$target->put('exports/old-site.wxr', '<rss>old</rss>');
$target->put('wp-content/uploads/2024/01/obsolete.jpg', str_repeat('x', 32));
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
$before = array_map(static fn ($info) => $info->path, $target->list());
$planned = $plan->deletePaths($source, $target, $filter);
$error = null;

try {
    $plan->deleteDestinationOnly($source, $target, $filter, maxDelete: 1);
} catch (RuntimeException $throwable) {
    $error = $throwable->getMessage();
}

$remaining = array_map(static fn ($info) => $info->path, $target->list());

return [
    'planned' => $planned,
    'removedBeforeLimit' => array_values(array_diff($before, $remaining)),
    'error' => $error,
    'remaining' => $remaining,
];
