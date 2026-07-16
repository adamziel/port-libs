<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfoScanner;
use PortLibs\Syncthing\FolderScanProgress;
use PortLibs\Syncthing\FolderScanService;
use PortLibs\Syncthing\SqliteCheckpointStore;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-folder-scan-sqlite-store-' . bin2hex(random_bytes(6));
$siteRoot = $root . '/site';
$dbPath = $root . '/checkpoints.sqlite';
$dir = 'wp-content/uploads/2026/05';

try {
    wordpress_folder_scan_sqlite_store_write($siteRoot, $dir . '/hero.jpg', 'abcdefgh');
    wordpress_folder_scan_sqlite_store_write($siteRoot, $dir . '/thumb.jpg', '12345');

    $service = new FolderScanService(
        'wordpress-media',
        new FileInfoScanner($siteRoot),
        SqliteCheckpointStore::open($dbPath),
        ttlSeconds: 3600,
    );

    $cancelAfterFirstHash = false;
    $first = $service->scan(
        [$dir],
        hashBlocks: true,
        blockSize: 4,
        progressLogger: static function (FolderScanProgress $progress) use (&$cancelAfterFirstHash): void {
            $cancelAfterFirstHash = true;
        },
        shouldCancel: static function (?string $path) use (&$cancelAfterFirstHash): bool {
            return $cancelAfterFirstHash && $path !== null;
        },
        now: 3000,
    );

    $laterRequest = new FolderScanService(
        'wordpress-media',
        new FileInfoScanner($siteRoot),
        SqliteCheckpointStore::open($dbPath),
        ttlSeconds: 3600,
    );
    $resumed = $laterRequest->scan(hashBlocks: true, blockSize: 4, now: 3015);
    $dbSnapshot = SqliteCheckpointStore::open($dbPath)->load('wordpress-media', 3015);

    echo json_encode([
        'route' => '/wp-json/local-first/v1/folder-scan/wordpress-media',
        'storage' => [
            'kind' => 'sqlite',
            'database' => basename($dbPath),
            'table' => SqliteCheckpointStore::DEFAULT_TABLE,
            'storedRevision' => $dbSnapshot?->revision,
            'compareAndSwapRevision' => $resumed->revision,
        ],
        'firstStatus' => $first->toRestStatus(),
        'resumedStatus' => $resumed->toRestStatus(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    wordpress_folder_scan_sqlite_store_rm($root);
}

function wordpress_folder_scan_sqlite_store_write(string $root, string $name, string $bytes): void
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create folder scan SQLite-store example directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write folder scan SQLite-store example file');
    }
}

function wordpress_folder_scan_sqlite_store_rm(string $path): void
{
    if (!file_exists($path) && !is_link($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        wordpress_folder_scan_sqlite_store_rm($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}
