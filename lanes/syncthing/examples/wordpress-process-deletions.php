<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\PullDbUpdater;
use PortLibs\Syncthing\PullItemUpdater;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-process-deletions-' . bin2hex(random_bytes(6));
mkdir($root, 0777, true);

try {
    $fileName = 'wp-content/uploads/2026/05/old-banner.jpg';
    wordpress_process_deletions_write($root, $fileName, 'stale banner bytes', 1_700_004_200);

    $currentFile = new FileInfo(
        name: $fileName,
        modifiedS: 1_700_004_200,
        version: VersionVector::fromCounters([101 => 30]),
        size: strlen('stale banner bytes'),
        type: FileInfo::TYPE_FILE,
        permissions: 0644,
        rawBlockSize: strlen('stale banner bytes'),
        modifiedBy: 101,
    );
    $currentMonth = new FileInfo(
        name: 'wp-content/uploads/2026/05',
        version: VersionVector::fromCounters([101 => 30]),
        type: FileInfo::TYPE_DIRECTORY,
        permissions: 0755,
        modifiedBy: 101,
    );
    $currentYear = new FileInfo(
        name: 'wp-content/uploads/2026',
        version: VersionVector::fromCounters([101 => 30]),
        type: FileInfo::TYPE_DIRECTORY,
        permissions: 0755,
        modifiedBy: 101,
    );

    $updater = new PullItemUpdater($root, folderId: 'wordpress-media');
    $updater->processDeletions(
        [
            new FileInfo(
                name: $fileName,
                version: VersionVector::fromCounters([101 => 30, 202 => 31]),
                deleted: true,
                type: FileInfo::TYPE_FILE,
                permissions: 0644,
                modifiedBy: 202,
            ),
        ],
        [
            new FileInfo(
                name: $currentYear->name,
                version: VersionVector::fromCounters([101 => 30, 202 => 31]),
                deleted: true,
                type: FileInfo::TYPE_DIRECTORY,
                permissions: 0755,
                modifiedBy: 202,
            ),
            new FileInfo(
                name: $currentMonth->name,
                version: VersionVector::fromCounters([101 => 30, 202 => 31]),
                deleted: true,
                type: FileInfo::TYPE_DIRECTORY,
                permissions: 0755,
                modifiedBy: 202,
            ),
        ],
        [$currentFile, $currentMonth, $currentYear],
    );

    $dbUpdater = new PullDbUpdater(folderId: 'wordpress-media', folderLabel: 'Media');
    foreach ($updater->dbUpdates() as $update) {
        $dbUpdater->append($update['file'], $update['type']);
    }
    $dbUpdater->close();

    echo json_encode([
        'folder' => 'wordpress-media',
        'yearDirectoryExists' => file_exists($root . '/wp-content/uploads/2026'),
        'deletionOrder' => array_column($updater->itemStartedEvents(), 'item'),
        'dbUpdateTypes' => array_map(static fn (array $update): string => $update['type'], $updater->dbUpdates()),
        'remoteChanges' => $dbUpdater->remoteChangeEvents(),
        'receivedFiles' => $dbUpdater->receivedFiles(),
        'scanNames' => $updater->scanNames(),
        'pullErrors' => $updater->pullErrors(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    wordpress_process_deletions_rm($root);
}

function wordpress_process_deletions_write(string $root, string $name, string $bytes, int $mtime): void
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create example directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write example file');
    }
    touch($path, $mtime);
}

function wordpress_process_deletions_rm(string $path): void
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
