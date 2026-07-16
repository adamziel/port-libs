<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\PullDbUpdater;
use PortLibs\Syncthing\PullItemUpdater;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-item-' . bin2hex(random_bytes(6));
mkdir($root, 0777, true);

try {
    $folderId = 'wordpress-media';
    $itemUpdater = new PullItemUpdater($root, folderId: $folderId, conflictTimestamp: 1_700_000_000);

    $directory = new FileInfo(
        name: 'wp-content/uploads/2026/05',
        version: VersionVector::fromCounters([202 => 21]),
        type: FileInfo::TYPE_DIRECTORY,
        permissions: 0755,
        modifiedBy: 202,
    );
    $itemUpdater->handleDirectory($directory);

    $latestLink = new FileInfo(
        name: 'wp-content/uploads/current/latest',
        version: VersionVector::fromCounters([202 => 22]),
        type: FileInfo::TYPE_SYMLINK,
        symlinkTarget: '../2026/05',
        permissions: 0644,
        modifiedBy: 202,
    );
    $itemUpdater->handleSymlink($latestLink);

    $dbUpdater = new PullDbUpdater(folderId: $folderId, folderLabel: 'Media');
    foreach ($itemUpdater->dbUpdates() as $update) {
        $dbUpdater->append($update['file'], $update['type']);
    }
    $changed = $dbUpdater->close();

    echo json_encode([
        'folder' => $folderId,
        'directoryExists' => is_dir($root . '/wp-content/uploads/2026/05'),
        'latestUploadLink' => readlink($root . '/wp-content/uploads/current/latest'),
        'itemStarted' => $itemUpdater->itemStartedEvents(),
        'itemFinished' => $itemUpdater->itemFinishedEvents(),
        'scanAfterParentCreation' => $itemUpdater->scanNames(),
        'dbChangedJobs' => $changed,
        'dbUpdateTypes' => array_map(static fn (array $update): string => $update['type'], $itemUpdater->dbUpdates()),
        'remoteChanges' => $dbUpdater->remoteChangeEvents(),
        'fsyncedDirectories' => $dbUpdater->fsyncedDirectories(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    if (is_dir($root)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($root);
    }
}
