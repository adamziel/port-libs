<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\FileInfoScanner;
use PortLibs\Syncthing\RequestServer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-scanner-temp-cleanup-' . bin2hex(random_bytes(6));
$dir = 'wp-content/uploads/2026/05';

try {
    wordpress_scanner_temp_cleanup_write($root, $dir . '/published.jpg', 'final media bytes');

    $freshTemp = RequestServer::temporaryName($dir . '/fresh-upload.jpg');
    $staleTemp = RequestServer::temporaryName($dir . '/abandoned-upload.jpg');
    $windowsTemp = $dir . '/~syncthing~windows-upload.jpg.tmp';

    $freshPath = wordpress_scanner_temp_cleanup_write($root, $freshTemp, 'fresh partial media bytes');
    $stalePath = wordpress_scanner_temp_cleanup_write($root, $staleTemp, 'stale partial media bytes');
    $windowsPath = wordpress_scanner_temp_cleanup_write($root, $windowsTemp, 'stale windows partial media bytes');

    $now = time();
    touch($freshPath, $now - 60);
    touch($stalePath, $now - 7200);
    touch($windowsPath, $now - 7200);
    clearstatcache();

    $scanner = new FileInfoScanner($root, tempLifetimeSeconds: 3600);
    $files = $scanner->walk([$dir], hashBlocks: true, blockSize: 8);

    echo json_encode([
        'folder' => 'wordpress-media',
        'advertisedItems' => array_map(
            static fn (FileInfo $file): array => [
                'name' => $file->name,
                'type' => $file->type,
                'bytes' => $file->size,
            ],
            $files,
        ),
        'freshTemporaryKept' => file_exists($freshPath),
        'staleUnixTemporaryRemoved' => !file_exists($stalePath),
        'staleWindowsTemporaryRemoved' => !file_exists($windowsPath),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    wordpress_scanner_temp_cleanup_rm($root);
}

function wordpress_scanner_temp_cleanup_write(string $root, string $name, string $bytes): string
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create scanner temporary cleanup example directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write scanner temporary cleanup example file');
    }

    return $path;
}

function wordpress_scanner_temp_cleanup_rm(string $path): void
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
        wordpress_scanner_temp_cleanup_rm($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}
