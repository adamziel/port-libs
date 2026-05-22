<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\DeduplicateMode;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$remote = new MemoryProvider();

$remote->put('wp-content/uploads/2026/05/hero.jpg', 'published hero image', [
    'modTime' => '2026-05-22T00:00:00Z',
]);
$remote->put('wp-content/uploads/2026/05/gallery.jpg', 'published gallery image', [
    'modTime' => '2026-05-22T00:05:00Z',
]);
$remote->put('wp-content/uploads-duplicate/2026/05/hero.jpg', 'restored draft hero image', [
    'modTime' => '2026-05-21T23:00:00Z',
]);

$plan = new SyncPlan();
$merge = $plan->mergeDuplicateDirectories($remote, [
    'wp-content/uploads-duplicate/2026/05',
    'wp-content/uploads/2026/05',
]);
$dedupe = $plan->deduplicateByName($remote, DeduplicateMode::RENAME);

return [
    'mergeTarget' => $merge['target']?->path,
    'mergeOrder' => $merge['ordered'],
    'renamedConflicts' => array_map(
        static fn ($group) => array_map(static fn ($info) => $info->path, $group['renamed']),
        $dedupe['groups'],
    ),
    'remaining' => array_map(static fn ($info) => $info->path, $remote->list('wp-content')),
];
