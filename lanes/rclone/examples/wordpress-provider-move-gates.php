<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\FilterRuleSet;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$source = new MemoryProvider();
$target = new MemoryProvider(
    serverSideMove: false,
    serverSideCopy: true,
    serverSideDirMove: false,
);
$tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';

$source->put('wp-content/uploads/2026/05/hero-renamed.jpg', $tree['wp-content/uploads/2026/05/hero.jpg']);
$source->put('exports/site.wxr', $tree['exports/site.wxr']);
$source->put('database/site.sql', $tree['database/site.sql']);

$target->mkdir('wp-content/uploads/2026/05', [
    'modTime' => '2026-05-22T00:00:00Z',
    'metadata' => ['wp-scope' => 'uploads-month'],
]);
$target->put('wp-content/uploads/2026/05/hero.jpg', $tree['wp-content/uploads/2026/05/hero.jpg']);
$target->put('wp-content/uploads/2026/05/old-thumb.jpg', 'old thumbnail bytes');

$filter = FilterRuleSet::fromRules([
    '+ wp-content/uploads/**',
    '+ exports/*.wxr',
    '+ database/*.sql',
    '- *',
]);

$plan = new SyncPlan();
$sync = $plan->syncWithTrackRenames($source, $target, $filter);
$archive = $plan->moveDirectory($target, 'wp-content/uploads/2026/05', 'archive/uploads/2026/05');

return [
    'trackRenamesEnabled' => $sync['trackRenamesEnabled'],
    'renamedByCopyDelete' => array_map(static fn ($info) => $info->path, $sync['renamed']),
    'copiedPortableArtifacts' => array_map(static fn ($info) => $info->path, $sync['copied']),
    'dirMoveUsed' => $archive['usedDirMove'],
    'dirMoveFallbackReason' => $archive['fallbackReason'],
    'archivedUploadPaths' => array_map(static fn ($info) => $info->path, $archive['moved']),
    'archivedMonthMetadata' => $target->directoryInfo('archive/uploads/2026/05')->metadata,
];
