<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\FilterRuleSet;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$source = new MemoryProvider();
$target = new MemoryProvider();
$tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';

$source->put('wp-content/uploads/2026/05/hero-renamed.jpg', $tree['wp-content/uploads/2026/05/hero.jpg']);
$source->put('exports/site.wxr', $tree['exports/site.wxr']);
$source->put('database/site.sql', $tree['database/site.sql']);

$target->put('wp-content/uploads/2026/05/hero.jpg', $tree['wp-content/uploads/2026/05/hero.jpg']);
$target->put('exports/old-site.wxr', '<rss>old export</rss>');
$target->put('wp-content/cache/orphan.html', '<html>cache</html>');

$filter = FilterRuleSet::fromRules([
    '- archive/**',
    '- wp-content/cache/**',
    '+ wp-content/uploads/**',
    '+ exports/*.wxr',
    '+ database/*.sql',
    '- *',
]);

$plan = new SyncPlan();
$result = $plan->syncWithTrackRenames(
    $source,
    $target,
    $filter,
    backupPrefix: 'archive/2026-05-22',
    suffix: '-previous',
    suffixKeepExtension: true,
);

return [
    'renamedPaths' => array_map(static fn ($info) => $info->path, $result['renamed']),
    'copiedPaths' => array_map(static fn ($info) => $info->path, $result['copied']),
    'archivedDeletes' => array_map(static fn ($info) => $info->path, $result['deleted']),
    'renamedUploadBytes' => $target->get('wp-content/uploads/2026/05/hero-renamed.jpg'),
    'archivedOldExportBytes' => $target->get('archive/2026-05-22/exports/old-site-previous.wxr'),
    'excludedCacheBytes' => $target->get('wp-content/cache/orphan.html'),
    'remainingDeletePaths' => $plan->deletePaths($source, $target, $filter),
];
