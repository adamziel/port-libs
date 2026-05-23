<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\PullDbUpdater;
use PortLibs\Syncthing\PullItemUpdater;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-metadata-shortcut-' . bin2hex(random_bytes(6));
mkdir($root, 0777, true);

try {
    $name = 'wp-content/uploads/2026/05/hero.jpg';
    $bytes = 'published WordPress media bytes';
    $blocksHash = hash('sha256', 'published hero block list');
    wordpress_metadata_shortcut_write($root, $name, $bytes, 1_700_005_000, 0644);

    $current = new FileInfo(
        name: $name,
        modifiedS: 1_700_005_000,
        version: VersionVector::fromCounters([101 => 80]),
        size: strlen($bytes),
        blocksHash: $blocksHash,
        type: FileInfo::TYPE_FILE,
        permissions: 0644,
        rawBlockSize: strlen($bytes),
        modifiedBy: 101,
    );
    $metadataOnly = new FileInfo(
        name: $name,
        modifiedS: 1_700_005_100,
        version: VersionVector::fromCounters([202 => 81]),
        size: strlen($bytes),
        blocksHash: $blocksHash,
        type: FileInfo::TYPE_FILE,
        permissions: 0600,
        rawBlockSize: strlen($bytes),
        modifiedBy: 202,
    );
    $needsDownload = new FileInfo(
        name: 'wp-content/uploads/2026/05/new-image.jpg',
        modifiedS: 1_700_005_200,
        version: VersionVector::fromCounters([202 => 82]),
        size: strlen('new media bytes'),
        blocksHash: hash('sha256', 'new image block list'),
        type: FileInfo::TYPE_FILE,
        permissions: 0644,
        rawBlockSize: strlen('new media bytes'),
        modifiedBy: 202,
    );

    $updater = new PullItemUpdater($root, folderId: 'wordpress-media');
    $remainingFiles = $updater->processMetadataShortcuts([$metadataOnly, $needsDownload], [$current]);

    $dbUpdater = new PullDbUpdater(folderId: 'wordpress-media', folderLabel: 'Media');
    foreach ($updater->dbUpdates() as $update) {
        $dbUpdater->append($update['file'], $update['type']);
    }
    $dbUpdater->close();

    $path = wordpress_metadata_shortcut_path($root, $name);
    echo json_encode([
        'folder' => 'wordpress-media',
        'bytesUnchanged' => file_get_contents($path) === $bytes,
        'mode' => substr(sprintf('%o', fileperms($path) & 0777), -4),
        'mtime' => filemtime($path),
        'remainingFullPulls' => array_map(static fn (FileInfo $file): string => $file->name, $remainingFiles),
        'lifecycle' => $updater->itemFinishedEvents(),
        'dbUpdateTypes' => array_column($updater->dbUpdates(), 'type'),
        'remoteChanges' => $dbUpdater->remoteChangeEvents(),
        'receivedFiles' => $dbUpdater->receivedFiles(),
        'pullErrors' => $updater->pullErrors(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    wordpress_metadata_shortcut_rm($root);
}

function wordpress_metadata_shortcut_path(string $root, string $name): string
{
    return rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
}

function wordpress_metadata_shortcut_write(string $root, string $name, string $bytes, int $mtime, int $mode): void
{
    $path = wordpress_metadata_shortcut_path($root, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create example directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write example file');
    }
    chmod($path, $mode);
    touch($path, $mtime);
}

function wordpress_metadata_shortcut_rm(string $path): void
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
