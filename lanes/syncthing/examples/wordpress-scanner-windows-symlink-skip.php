<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\FileInfoScanner;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-scanner-windows-symlink-' . bin2hex(random_bytes(6));
$dir = 'wp-content/uploads/2026/05';

try {
    wordpress_scanner_windows_symlink_write($root, $dir . '/library/original.jpg', 'original wordpress media');

    $shortcutName = $dir . '/latest.jpg';
    $shortcutPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $shortcutName);
    if (!@symlink('library/original.jpg', $shortcutPath)) {
        throw new RuntimeException('Failed to create media symlink');
    }

    $linkedDirName = $dir . '/linked-library';
    $linkedDirPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $linkedDirName);
    if (!@symlink('library', $linkedDirPath)) {
        throw new RuntimeException('Failed to create media directory symlink');
    }

    $posixFiles = (new FileInfoScanner($root, platformFamily: 'Linux'))->walk([$dir]);
    $windowsFiles = (new FileInfoScanner($root, platformFamily: 'Windows'))->walk([$dir]);

    echo json_encode([
        'folder' => 'wordpress-media',
        'posixNames' => array_map(static fn (FileInfo $file): string => $file->name, $posixFiles),
        'windowsNames' => array_map(static fn (FileInfo $file): string => $file->name, $windowsFiles),
        'windowsSkippedSymlinks' => [
            $shortcutName,
            $linkedDirName,
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    wordpress_scanner_windows_symlink_rm($root);
}

function wordpress_scanner_windows_symlink_write(string $root, string $name, string $bytes): void
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create scanner symlink example directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write scanner symlink example file');
    }
}

function wordpress_scanner_windows_symlink_rm(string $path): void
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
        wordpress_scanner_windows_symlink_rm($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}
