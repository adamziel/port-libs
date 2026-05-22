<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\FolderIndexState;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$state = new FolderIndexState();
$base = VersionVector::fromCounters([101 => 1]);
$remoteEdit = VersionVector::fromCounters([101 => 1, 202 => 2]);
$remoteDelete = VersionVector::fromCounters([101 => 1, 202 => 3]);

$state->update('local', [
    new FileInfo(
        name: 'wp-content/uploads/2026/hero.jpg',
        version: $base,
        size: 2048,
        rawBlockSize: 2048,
        sequence: 1,
    ),
    new FileInfo(
        name: 'wp-content/uploads/2026/local-ignored.jpg',
        version: $base,
        localFlags: FileInfo::FLAG_LOCAL_IGNORED,
        size: 1024,
        rawBlockSize: 1024,
        sequence: 2,
    ),
]);

$state->update('playground-peer', [
    new FileInfo(
        name: 'wp-content/uploads/2026/hero.jpg',
        version: $remoteEdit,
        size: 4096,
        rawBlockSize: 4096,
        sequence: 20,
    ),
    new FileInfo(
        name: 'wp-content/uploads/2026/local-ignored.jpg',
        version: $base,
        size: 1024,
        rawBlockSize: 1024,
        sequence: 21,
    ),
    new FileInfo(
        name: 'wp-content/uploads/2026/removed.jpg',
        version: $remoteDelete,
        deleted: true,
        sequence: 22,
    ),
    new FileInfo(
        name: 'wp-content/uploads/2026/theme-preview.css',
        version: VersionVector::fromCounters([202 => 4]),
        size: 256,
        rawBlockSize: 256,
        sequence: 23,
    ),
    new FileInfo(
        name: 'wp-content/uploads/2026/gallery-archive.zip',
        version: VersionVector::fromCounters([202 => 5]),
        size: 16_384,
        rawBlockSize: 16_384,
        sequence: 24,
    ),
]);

$localNeed = $state->neededFiles('local');
$smallestFirst = $state->neededFiles('local', order: FolderIndexState::PULL_ORDER_SMALLEST_FIRST);
$newestFirst = $state->neededFiles('local', order: FolderIndexState::PULL_ORDER_NEWEST_FIRST);
$globalUploads = $state->globalFilesPrefix('wp-content/uploads/2026/');
$afterPeerDrop = clone $state;
$afterPeerDrop->dropDevice('playground-peer');

return [
    'localNeedNames' => array_map(static fn (FileInfo $file): string => $file->name, $localNeed),
    'smallestFirstNeedNames' => array_map(static fn (FileInfo $file): string => $file->name, $smallestFirst),
    'newestFirstNeedNames' => array_map(static fn (FileInfo $file): string => $file->name, $newestFirst),
    'globalUploadNames' => array_map(static fn (FileInfo $file): string => $file->name, $globalUploads),
    'afterPeerDropNeedNames' => array_map(static fn (FileInfo $file): string => $file->name, $afterPeerDrop->neededFiles('local')),
    'afterPeerDropGlobalBytes' => $afterPeerDrop->countGlobal()->bytes,
    'localNeedCounts' => $state->countNeed('local')->items(),
    'localNeedBytes' => $state->countNeed('local')->bytes,
    'globalBytes' => $state->countGlobal()->bytes,
    'ignoredSameVersionDoesNotDownload' => $state->deviceFile('local', 'wp-content/uploads/2026/local-ignored.jpg') !== null
        && !in_array('wp-content/uploads/2026/local-ignored.jpg', array_map(static fn (FileInfo $file): string => $file->name, $localNeed), true),
];
