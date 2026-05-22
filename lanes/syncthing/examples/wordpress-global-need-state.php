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
]);

$localNeed = $state->neededFiles('local');

return [
    'localNeedNames' => array_map(static fn (FileInfo $file): string => $file->name, $localNeed),
    'localNeedCounts' => $state->countNeed('local')->items(),
    'localNeedBytes' => $state->countNeed('local')->bytes,
    'globalBytes' => $state->countGlobal()->bytes,
    'ignoredSameVersionDoesNotDownload' => $state->deviceFile('local', 'wp-content/uploads/2026/local-ignored.jpg') !== null
        && !in_array('wp-content/uploads/2026/local-ignored.jpg', array_map(static fn (FileInfo $file): string => $file->name, $localNeed), true),
];
