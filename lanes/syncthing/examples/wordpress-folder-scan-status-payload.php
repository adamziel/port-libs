<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfoScanner;
use PortLibs\Syncthing\FolderScanCheckpoint;
use PortLibs\Syncthing\FolderScanEventCollector;
use PortLibs\Syncthing\FolderScanProgress;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-folder-scan-status-' . bin2hex(random_bytes(6));
$dir = 'wp-content/uploads/2026/05';

try {
    wordpress_folder_scan_status_write($root, $dir . '/hero.jpg', 'abcdefgh');
    wordpress_folder_scan_status_write($root, $dir . '/thumb.jpg', '12345');

    $scanner = new FileInfoScanner($root);
    $cancelAfterFirstHash = false;
    $first = $scanner->walkWithCheckpoint(
        [$dir],
        hashBlocks: true,
        blockSize: 4,
        progressLogger: static function (FolderScanProgress $progress) use (&$cancelAfterFirstHash): void {
            $cancelAfterFirstHash = true;
        },
        folder: 'wordpress-media',
        shouldCancel: static function (?string $path) use (&$cancelAfterFirstHash): bool {
            return $cancelAfterFirstHash && $path !== null;
        },
        eventCollector: new FolderScanEventCollector('wordpress-media'),
    );

    $checkpoint = FolderScanCheckpoint::fromResult('wordpress-media', $first);
    $resume = $scanner->walkWithCheckpoint(
        $checkpoint->resumeSubs(),
        hashBlocks: true,
        blockSize: 4,
        currentFiles: $checkpoint->resumeCurrentFiles(),
        folder: 'wordpress-media',
        eventCollector: new FolderScanEventCollector('wordpress-media'),
    );
    $checkpoint = $checkpoint->withResult($resume);

    echo json_encode([
        'route' => '/wp-json/local-first/v1/folder-scan/wordpress-media',
        'status' => $checkpoint->toRestStatus(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    wordpress_folder_scan_status_rm($root);
}

function wordpress_folder_scan_status_write(string $root, string $name, string $bytes): void
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create folder scan status example directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write folder scan status example file');
    }
}

function wordpress_folder_scan_status_rm(string $path): void
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
        wordpress_folder_scan_status_rm($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}
