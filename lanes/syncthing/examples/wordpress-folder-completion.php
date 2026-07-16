<?php

declare(strict_types=1);

use PortLibs\Syncthing\FolderCompletion;
use PortLibs\Syncthing\FolderCounts;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$remoteMedia = FolderCompletion::fromCounts(
    global: new FolderCounts(bytes: 512 * 1024 * 1024, files: 248, directories: 12),
    need: new FolderCounts(bytes: 128 * 1024 * 1024, files: 4),
    sequence: 330,
    remoteState: FolderCompletion::REMOTE_VALID,
    downloadedBytes: 64 * 1024 * 1024,
);

$pendingDeleteOnly = FolderCompletion::fromCounts(
    global: new FolderCounts(),
    need: new FolderCounts(deleted: 1),
    sequence: 331,
    remoteState: FolderCompletion::REMOTE_VALID,
);

echo json_encode([
    'mediaFolder' => $remoteMedia->map(),
    'deleteOnlyCompletion' => $pendingDeleteOnly->map(),
    'aggregate' => $remoteMedia->add($pendingDeleteOnly)->map(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
