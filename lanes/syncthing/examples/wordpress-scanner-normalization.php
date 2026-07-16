<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\FileInfoScanner;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-scanner-normalization-' . bin2hex(random_bytes(6));
$dir = 'wp-content/uploads/2026/05';
$decomposedName = $dir . '/Cafe' . "\u{0301}" . '.jpg';
$normalizedName = $dir . '/Caf' . "\u{00e9}" . '.jpg';
$decomposedPath = wordpress_scanner_normalization_path($root, $decomposedName);
$normalizedPath = wordpress_scanner_normalization_path($root, $normalizedName);

try {
    wordpress_scanner_normalization_write($decomposedPath, 'normalized wordpress media');

    $strictScanner = new FileInfoScanner($root);
    $strictError = '';
    try {
        $strictScanner->walk([$dir]);
    } catch (RuntimeException $exception) {
        $strictError = $exception->getMessage();
    }

    $normalizingScanner = new FileInfoScanner($root, autoNormalize: true);
    $files = $normalizingScanner->walk([$dir], hashBlocks: true, blockSize: 8);
    $mediaFiles = array_values(array_filter(
        $files,
        static fn (FileInfo $file): bool => $file->type === FileInfo::TYPE_FILE,
    ));

    echo json_encode([
        'strictError' => $strictError,
        'normalizedName' => $mediaFiles[0]->name ?? null,
        'decomposedPathExists' => file_exists($decomposedPath),
        'normalizedPathExists' => file_exists($normalizedPath),
        'firstBlockHash' => $mediaFiles[0]->blocks[0]->hashHex ?? null,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    wordpress_scanner_normalization_rm($root);
}

function wordpress_scanner_normalization_path(string $root, string $name): string
{
    return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
}

function wordpress_scanner_normalization_write(string $path, string $bytes): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create scanner normalization example directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write scanner normalization example file');
    }
}

function wordpress_scanner_normalization_rm(string $path): void
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
        wordpress_scanner_normalization_rm($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}
