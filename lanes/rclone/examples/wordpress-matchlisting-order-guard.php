<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$source = new MemoryProvider();
$target = new MemoryProvider();
$tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';

foreach ($tree as $path => $bytes) {
    $source->put($path, $bytes);
}

$target->put('wp-content/cache/orphan.html', '<html>stale cache</html>');

$sourceEntries = [
    $source->info('wp-content/uploads/2026/05/hero.jpg'),
    $source->info('database/site.sql'),
    $source->info('exports/site.wxr'),
];

$matches = [];
$error = null;
try {
    $diagnostics = (new SyncPlan())->matchListingDiagnosticsFromEntries($sourceEntries, []);
    $matches = array_map(static fn (array $pair): string => $pair['source']->path, $diagnostics['matches']);
} catch (RuntimeException $throwable) {
    $error = $throwable->getMessage();
}

return [
    'sourceEntryOrder' => array_map(static fn ($info): string => $info->path, $sourceEntries),
    'error' => $error,
    'matches' => $matches,
    'cacheLeftUntouched' => $target->get('wp-content/cache/orphan.html'),
];
