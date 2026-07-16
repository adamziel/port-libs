<?php

declare(strict_types=1);

use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\FileInfoScanner;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-scanner-blocks-' . bin2hex(random_bytes(6));
$name = 'wp-content/uploads/2026/05/hero.jpg';
$bytes = 'existing wordpress media bytes';

try {
    wordpress_scanner_blocks_write($root, $name, $bytes);

    $currentIndexFile = new FileInfo(
        name: $name,
        size: strlen($bytes),
        type: FileInfo::TYPE_FILE,
        rawBlockSize: 256 << 10,
    );
    $scanner = new FileInfoScanner($root);
    $fresh = $scanner->scan($name, hashBlocks: true);
    $retained = $scanner->scan($name, hashBlocks: true, currentFile: $currentIndexFile);

    echo json_encode([
        'path' => $name,
        'defaultBlockSize' => $fresh->rawBlockSize,
        'retainedBlockSize' => $retained->rawBlockSize,
        'previousIndexedBlockSize' => $currentIndexFile->blockSize(),
        'hysteresisExamplesFor500MiB' => [
            'noPrevious' => BlockList::blockSizeForFileSize(500 << 20),
            'previous256KiB' => BlockList::blockSizeForFileSize(500 << 20, 256 << 10),
            'previous1MiB' => BlockList::blockSizeForFileSize(500 << 20, 1 << 20),
            'previous128KiB' => BlockList::blockSizeForFileSize(500 << 20, 128 << 10),
            'previous2MiB' => BlockList::blockSizeForFileSize(500 << 20, 2 << 20),
        ],
        'blockHash' => $retained->blocks[0]->hashHex,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    wordpress_scanner_blocks_rm($root);
}

function wordpress_scanner_blocks_write(string $root, string $name, string $bytes): void
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create scanner block example directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write scanner block example file');
    }
}

function wordpress_scanner_blocks_rm(string $path): void
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
        wordpress_scanner_blocks_rm($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}
