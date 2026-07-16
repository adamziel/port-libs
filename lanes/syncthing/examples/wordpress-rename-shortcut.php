<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\PullDbUpdater;
use PortLibs\Syncthing\PullItemUpdater;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-rename-shortcut-' . bin2hex(random_bytes(6));
mkdir($root, 0777, true);

try {
    $sourceName = 'wp-content/uploads/imports/staged-hero.jpg';
    $targetName = 'wp-content/uploads/2026/05/hero.jpg';
    $bytes = 'already-downloaded WordPress media bytes';
    $blocksHash = hash('sha256', 'wordpress staged hero block list');

    wordpress_rename_shortcut_write($root, $sourceName, $bytes, 1_700_004_400);
    mkdir(dirname(wordpress_rename_shortcut_path($root, $targetName)), 0777, true);

    $currentSource = new FileInfo(
        name: $sourceName,
        modifiedS: 1_700_004_400,
        version: VersionVector::fromCounters([101 => 60]),
        size: strlen($bytes),
        blocksHash: $blocksHash,
        type: FileInfo::TYPE_FILE,
        permissions: 0644,
        rawBlockSize: strlen($bytes),
        modifiedBy: 101,
    );
    $sourceDelete = new FileInfo(
        name: $sourceName,
        version: VersionVector::fromCounters([101 => 60, 202 => 61]),
        deleted: true,
        type: FileInfo::TYPE_FILE,
        permissions: 0644,
        modifiedBy: 202,
    );
    $target = new FileInfo(
        name: $targetName,
        modifiedS: 1_700_004_500,
        version: VersionVector::fromCounters([202 => 62]),
        size: strlen($bytes),
        blocksHash: $blocksHash,
        type: FileInfo::TYPE_FILE,
        permissions: 0644,
        rawBlockSize: strlen($bytes),
        modifiedBy: 202,
    );

    $updater = new PullItemUpdater($root, folderId: 'wordpress-media');
    $remainingDeletes = $updater->processRenameShortcuts(
        [$target],
        [$sourceDelete],
        [$currentSource],
    );
    $updater->processDeletions($remainingDeletes, [], [$currentSource]);

    $dbUpdater = new PullDbUpdater(folderId: 'wordpress-media', folderLabel: 'Media');
    foreach ($updater->dbUpdates() as $update) {
        $dbUpdater->append($update['file'], $update['type']);
    }
    $dbUpdater->close();

    echo json_encode([
        'folder' => 'wordpress-media',
        'sourceExists' => file_exists(wordpress_rename_shortcut_path($root, $sourceName)),
        'targetBytes' => file_get_contents(wordpress_rename_shortcut_path($root, $targetName)),
        'remainingDeletes' => array_map(static fn (FileInfo $file): string => $file->name, $remainingDeletes),
        'lifecycle' => $updater->itemFinishedEvents(),
        'dbUpdateTypes' => array_column($updater->dbUpdates(), 'type'),
        'remoteChanges' => $dbUpdater->remoteChangeEvents(),
        'receivedFiles' => $dbUpdater->receivedFiles(),
        'scanNames' => $updater->scanNames(),
        'pullErrors' => $updater->pullErrors(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    wordpress_rename_shortcut_rm($root);
}

function wordpress_rename_shortcut_path(string $root, string $name): string
{
    return rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
}

function wordpress_rename_shortcut_write(string $root, string $name, string $bytes, int $mtime): void
{
    $path = wordpress_rename_shortcut_path($root, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create example directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write example file');
    }
    touch($path, $mtime);
}

function wordpress_rename_shortcut_rm(string $path): void
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
