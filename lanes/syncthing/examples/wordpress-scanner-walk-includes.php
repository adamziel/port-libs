<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\FileInfoScanner;
use PortLibs\Syncthing\IgnoreMatcher;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-scanner-walk-' . bin2hex(random_bytes(6));

try {
    wordpress_scanner_walk_write($root, '.stignore', "*\n!wp-content/uploads/2026/public\n");
    wordpress_scanner_walk_write($root, '.stfolder/marker', 'internal marker');
    wordpress_scanner_walk_write($root, 'wp-content/uploads/2026/public/hero.jpg', 'public wordpress media bytes');
    wordpress_scanner_walk_write($root, 'wp-content/uploads/2026/private/export.zip', 'private export');
    wordpress_scanner_walk_write($root, 'wp-content/uploads/2026/public/.syncthing.hero.jpg.tmp', 'stale temp');

    $scanner = new FileInfoScanner($root);
    $matcher = IgnoreMatcher::fromLines([
        '!wp-content/uploads/2026/public',
        '*',
    ]);

    $files = $scanner->walk(ignoreMatcher: $matcher, hashBlocks: true, blockSize: 16);

    echo json_encode([
        'scannedNames' => array_map(static fn (FileInfo $file): string => $file->name, $files),
        'fileTypes' => array_map(static fn (FileInfo $file): string => match ($file->type) {
            FileInfo::TYPE_DIRECTORY => 'directory',
            FileInfo::TYPE_FILE => 'file',
            FileInfo::TYPE_SYMLINK => 'symlink',
            default => 'other',
        }, $files),
        'hashedMediaBlocks' => count($files[array_key_last($files)]->blocks),
        'privateExportVisible' => in_array('wp-content/uploads/2026/private/export.zip', array_map(static fn (FileInfo $file): string => $file->name, $files), true),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    wordpress_scanner_walk_rm($root);
}

function wordpress_scanner_walk_write(string $root, string $name, string $bytes): void
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create scanner walk example directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write scanner walk example file');
    }
}

function wordpress_scanner_walk_rm(string $path): void
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
        wordpress_scanner_walk_rm($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}
