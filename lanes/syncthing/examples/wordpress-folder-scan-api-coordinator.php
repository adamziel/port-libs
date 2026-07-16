<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfoScanner;
use PortLibs\Syncthing\FolderScanApiCoordinator;
use PortLibs\Syncthing\FolderScanCheckpointStore;
use PortLibs\Syncthing\FolderScanScheduler;
use PortLibs\Syncthing\FolderScanService;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$mediaRoot = sys_get_temp_dir() . '/syncthing-wordpress-scan-api-media-' . bin2hex(random_bytes(6));
$contentRoot = sys_get_temp_dir() . '/syncthing-wordpress-scan-api-content-' . bin2hex(random_bytes(6));

try {
    wordpress_scan_api_write($mediaRoot, 'wp-content/uploads/2026/05/hero.jpg', 'abcdefgh');
    wordpress_scan_api_write($contentRoot, 'wp-content/plugins/local-first/plugin.php', '<?php');

    $scheduler = new FolderScanScheduler();
    $scheduler->addFolder(
        'wordpress-media',
        new FolderScanService('wordpress-media', new FileInfoScanner($mediaRoot), new FolderScanCheckpointStore(), ttlSeconds: 1800),
    );
    $scheduler->addFolder(
        'wordpress-content',
        new FolderScanService('wordpress-content', new FileInfoScanner($contentRoot), new FolderScanCheckpointStore(), ttlSeconds: 300),
        running: false,
    );

    $api = new FolderScanApiCoordinator($scheduler);
    $response = $api->postDbScan([
        'folders' => [
            'wordpress-media' => ['wp-content/uploads/2026/05'],
            'wordpress-content' => ['wp-content/plugins/local-first'],
        ],
        'hashBlocks' => true,
        'blockSize' => 4,
    ], now: 1000);

    echo json_encode([
        'route' => '/wp-json/local-first/v1/syncthing/scan',
        'method' => 'POST',
        'response' => $response->toArray(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    wordpress_scan_api_rm($mediaRoot);
    wordpress_scan_api_rm($contentRoot);
}

function wordpress_scan_api_write(string $root, string $name, string $bytes): void
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create scan API example directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write scan API example file');
    }
}

function wordpress_scan_api_rm(string $path): void
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
        wordpress_scan_api_rm($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}
