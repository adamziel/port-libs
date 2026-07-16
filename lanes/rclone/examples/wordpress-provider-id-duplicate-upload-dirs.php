<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\DeduplicateMode;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$remote = new MemoryProvider();
$remote->mkdir('wp-content/uploads', ['id' => 'uploads-root']);
$remote->mkdir('wp-content/uploads/2026', ['id' => 'uploads-2026', 'parentId' => 'uploads-root']);
$remote->mkdirUnchecked('wp-content/uploads/2026/05', [
    'id' => 'month-published',
    'parentId' => 'uploads-2026',
]);
$remote->mkdirUnchecked('wp-content/uploads/2026/05', [
    'id' => 'month-recovered',
    'parentId' => 'uploads-2026',
]);
$remote->mkdirUnchecked('wp-content/uploads/2026/05/thumbs', [
    'id' => 'thumbs-published',
    'parentId' => 'month-published',
]);

$remote->putUnchecked('wp-content/uploads/2026/05/hero.jpg', 'published hero image', [
    'id' => 'hero-published',
    'parentId' => 'month-published',
]);
$remote->putUnchecked('wp-content/uploads/2026/05/gallery.jpg', 'published gallery image', [
    'id' => 'gallery-published',
    'parentId' => 'month-published',
]);
$remote->putUnchecked('wp-content/uploads/2026/05/hero.jpg', 'recovered draft hero image', [
    'id' => 'hero-recovered',
    'parentId' => 'month-recovered',
]);

$plan = new SyncPlan();
$duplicateDirs = $plan->findDuplicateDirectories($remote);
$merge = $plan->mergeDuplicateDirectories($remote, $duplicateDirs[0]['directories']);
$dedupe = $plan->deduplicateByName($remote, DeduplicateMode::RENAME);

return [
    'duplicateDirectory' => $duplicateDirs[0]['path'],
    'keptProviderId' => $merge['target']?->id,
    'remainingDirectoryIds' => array_values(array_filter(array_map(
        static fn ($info) => $info->id,
        $remote->directories('wp-content/uploads/2026/05'),
    ))),
    'renamedConflicts' => array_map(
        static fn ($group) => array_map(static fn ($info) => $info->path, $group['renamed']),
        $dedupe['groups'],
    ),
    'remainingObjects' => array_map(static fn ($info) => $info->path, $remote->list('wp-content/uploads/2026/05')),
];
