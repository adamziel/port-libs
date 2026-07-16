<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfoScanner;
use PortLibs\Syncthing\FolderScanApiCoordinator;
use PortLibs\Syncthing\FolderScanCheckpointStore;
use PortLibs\Syncthing\FolderScanScheduler;
use PortLibs\Syncthing\FolderScanService;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$mediaRoot = sys_get_temp_dir() . '/syncthing-wordpress-delayed-scan-' . bin2hex(random_bytes(6));

try {
    wordpress_delayed_scan_write($mediaRoot, 'wp-content/uploads/2026/05/hero.jpg', 'abcdefgh');

    $service = new FolderScanService(
        'wordpress-media',
        new FileInfoScanner($mediaRoot),
        new FolderScanCheckpointStore(),
        ttlSeconds: 1800,
    );
    $scheduler = new FolderScanScheduler();
    $scheduler->addFolder('wordpress-media', $service);

    $api = new FolderScanApiCoordinator($scheduler);
    $accepted = $api->postDbScan([
        'folder' => 'wordpress-media',
        'sub' => 'wp-content/uploads/2026/05',
        'hashBlocks' => true,
        'blockSize' => 4,
        'next' => 30,
    ], now: 1000);

    $scheduledBeforeDue = $scheduler->scheduledScanStatuses(1029);
    $beforeDue = $scheduler->scanDueDelayedFolders(hashBlocks: true, blockSize: 4, now: 1029);
    $due = $scheduler->scanDueDelayedFolders(hashBlocks: true, blockSize: 4, now: 1030);

    echo json_encode([
        'route' => '/wp-json/local-first/v1/syncthing/db/scan',
        'method' => 'POST',
        'accepted' => $accepted->toArray(),
        'scheduledBeforeDue' => $scheduledBeforeDue,
        'beforeDueResult' => $beforeDue->toRestStatus(),
        'dueResult' => $due->toRestStatus(),
        'latestCheckpointRevision' => $service->checkpoint(1030)?->revision,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    wordpress_delayed_scan_rm($mediaRoot);
}

function wordpress_delayed_scan_write(string $root, string $name, string $bytes): void
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create delayed scan example directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write delayed scan example file');
    }
}

function wordpress_delayed_scan_rm(string $path): void
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
        wordpress_delayed_scan_rm($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}
