<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\FileInfoScanner;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-scanner-symlink-parent-' . bin2hex(random_bytes(6));
$dir = 'wp-content/uploads/2026/05';

try {
    wordpress_scanner_symlink_parent_write($root, $dir . '/library/original.jpg', 'original wordpress media');

    $linkedDirName = $dir . '/linked-library';
    $linkedDirPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $linkedDirName);
    if (!@symlink('library', $linkedDirPath)) {
        throw new RuntimeException('Failed to create media directory symlink');
    }

    $scanner = new FileInfoScanner($root);
    $directAlias = $scanner->walk([$linkedDirName]);
    $belowAlias = $scanner->walk([$linkedDirName . '/original.jpg'], hashBlocks: true, blockSize: 8);
    $canonical = $scanner->walk([$dir . '/library/original.jpg'], hashBlocks: true, blockSize: 8);

    echo json_encode([
        'folder' => 'wordpress-media',
        'symlinkAliasAdvertised' => array_map(static fn (FileInfo $file): string => $file->name, $directAlias),
        'subBelowSymlinkParentSkipped' => $belowAlias === [],
        'canonicalMediaAdvertised' => array_map(static fn (FileInfo $file): array => [
            'name' => $file->name,
            'bytes' => $file->size,
            'blocks' => count($file->blocks),
        ], $canonical),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    wordpress_scanner_symlink_parent_rm($root);
}

function wordpress_scanner_symlink_parent_write(string $root, string $name, string $bytes): void
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create scanner symlink-parent example directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write scanner symlink-parent example file');
    }
}

function wordpress_scanner_symlink_parent_rm(string $path): void
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
        wordpress_scanner_symlink_parent_rm($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}
