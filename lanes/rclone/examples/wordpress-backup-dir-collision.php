<?php

declare(strict_types=1);

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

require_once __DIR__ . '/../../../tools/bootstrap.php';

$local = new MemoryProvider();
$remote = new MemoryProvider();

$local->put('staging/uploads/hero.jpg', 'fresh hero bytes', [
    'modTime' => '2026-05-23T10:00:00Z',
    'metadata' => ['wp-artifact' => 'staged-media'],
]);
$remote->put('publish/uploads/hero.jpg', 'published hero bytes', [
    'modTime' => '2026-05-22T10:00:00Z',
    'mimeType' => 'image/jpeg',
    'metadata' => ['wp-artifact' => 'published-media', 'alt' => 'homepage'],
]);
$remote->put('archive/publish/uploads/hero-previous.jpg', 'stale archive bytes', [
    'modTime' => '2026-05-21T10:00:00Z',
    'metadata' => ['wp-artifact' => 'stale-archive'],
]);

$result = (new SyncPlan())->copyFile(
    $remote,
    $local,
    'publish/uploads/hero.jpg',
    'staging/uploads/hero.jpg',
    [
        'backupPrefix' => 'archive',
        'suffix' => '-previous',
        'suffixKeepExtension' => true,
    ],
);

$backupPath = $result['backup']?->path ?? '';
$archived = $remote->info($backupPath);

return [
    'backupPath' => $backupPath,
    'archivedBytes' => $remote->get($backupPath),
    'archivedModTime' => $archived->modTime,
    'archivedMetadata' => $archived->metadata,
    'publishedBytes' => $remote->get('publish/uploads/hero.jpg'),
    'staleArchiveReplaced' => $remote->get($backupPath) !== 'stale archive bytes',
    'sourcePreserved' => $local->pathExists('staging/uploads/hero.jpg'),
];
