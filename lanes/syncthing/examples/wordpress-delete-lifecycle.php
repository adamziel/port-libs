<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\IgnoreMatcher;
use PortLibs\Syncthing\PullDbUpdater;
use PortLibs\Syncthing\PullItemUpdater;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-delete-' . bin2hex(random_bytes(6));
mkdir($root, 0777, true);

try {
    $folderId = 'wordpress-media';
    wordpress_delete_write($root, 'wp-content/uploads/2026/old.jpg', 'old media bytes', 1_700_004_000);
    wordpress_delete_write($root, 'wp-content/uploads/2026/private-cache/review.zip', 'local review cache', 1_700_004_100);

    $matcher = IgnoreMatcher::fromLines(['(?d)wp-content/uploads/2026/private-cache']);
    $itemUpdater = new PullItemUpdater($root, folderId: $folderId, ignoreMatcher: $matcher);

    $currentFile = new FileInfo(
        name: 'wp-content/uploads/2026/old.jpg',
        modifiedS: 1_700_004_000,
        version: VersionVector::fromCounters([101 => 18]),
        size: strlen('old media bytes'),
        type: FileInfo::TYPE_FILE,
        permissions: 0644,
        rawBlockSize: strlen('old media bytes'),
        modifiedBy: 101,
    );
    $itemUpdater->deleteFile(new FileInfo(
        name: $currentFile->name,
        version: VersionVector::fromCounters([101 => 18, 202 => 19]),
        deleted: true,
        type: FileInfo::TYPE_FILE,
        permissions: 0644,
        modifiedBy: 202,
    ), $currentFile);

    $cacheDir = new FileInfo(
        name: 'wp-content/uploads/2026/private-cache',
        version: VersionVector::fromCounters([101 => 20]),
        type: FileInfo::TYPE_DIRECTORY,
        permissions: 0755,
        modifiedBy: 101,
    );
    $itemUpdater->deleteDirectory(new FileInfo(
        name: $cacheDir->name,
        version: VersionVector::fromCounters([101 => 20, 202 => 21]),
        deleted: true,
        type: FileInfo::TYPE_DIRECTORY,
        permissions: 0755,
        modifiedBy: 202,
    ), $cacheDir);

    $dbUpdater = new PullDbUpdater(folderId: $folderId, folderLabel: 'Media');
    foreach ($itemUpdater->dbUpdates() as $update) {
        $dbUpdater->append($update['file'], $update['type']);
    }
    $changed = $dbUpdater->close();

    echo json_encode([
        'folder' => $folderId,
        'oldUploadExists' => file_exists($root . '/wp-content/uploads/2026/old.jpg'),
        'privateCacheExists' => file_exists($root . '/wp-content/uploads/2026/private-cache'),
        'itemStarted' => $itemUpdater->itemStartedEvents(),
        'itemFinished' => $itemUpdater->itemFinishedEvents(),
        'scanNames' => $itemUpdater->scanNames(),
        'pullErrors' => $itemUpdater->pullErrors(),
        'dbChangedJobs' => $changed,
        'dbUpdateTypes' => array_map(static fn (array $update): string => $update['type'], $itemUpdater->dbUpdates()),
        'remoteChanges' => $dbUpdater->remoteChangeEvents(),
        'receivedFiles' => $dbUpdater->receivedFiles(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    wordpress_delete_rm($root);
}

function wordpress_delete_write(string $root, string $name, string $bytes, int $mtime): void
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create fixture directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write fixture file');
    }
    touch($path, $mtime);
}

function wordpress_delete_rm(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($path);
}
