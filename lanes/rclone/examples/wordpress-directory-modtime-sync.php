<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\LsJsonListing;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$source = new MemoryProvider();
$target = new MemoryProvider();

$source->mkdir('wp-content/uploads/2026/05', [
    'modTime' => '2026-05-22T00:00:00Z',
    'metadata' => ['mtime' => '2026-05-22T00:00:00Z', 'wp-scope' => 'uploads'],
]);
$source->mkdirModTime('exports/incremental', '2026-05-22T01:00:00Z');
$source->put('wp-content/uploads/2026/05/hero.jpg', 'new image bytes', [
    'modTime' => '2026-05-22T00:05:00Z',
]);

$target->put('wp-content/uploads/2026/05/hero.jpg', 'old image bytes', [
    'modTime' => '2026-05-20T00:05:00Z',
]);
$target->mkdirModTime('wp-content/uploads/2026/05', '2026-05-20T00:00:00Z');
$target->mkdirModTime('exports/incremental', '2026-05-20T01:00:00Z');

$plan = new SyncPlan();
$changedBefore = !$plan->dirsEqual($source, $target, 'wp-content/uploads/2026/05');
$copied = $plan->copyChanged($source, $target);
$directoryUpdates = $plan->setDelayedDirectoryModTimes(
    $source,
    $target,
    $copied,
    setDirMetadata: true,
);

return [
    'uploadsDirNeededTimestampRepair' => $changedBefore,
    'copiedPaths' => array_map(static fn ($info) => $info->path, $copied),
    'delayedDirectoryUpdates' => array_map(static fn ($info) => $info->path, $directoryUpdates),
    'uploadsDirEqualAfterRepair' => $plan->dirsEqual($source, $target, 'wp-content/uploads/2026/05'),
    'lsjsonDirectoryEntry' => LsJsonListing::stat($source, 'wp-content/uploads/2026/05', ['metadata' => true]),
];
