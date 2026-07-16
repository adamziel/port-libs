<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\FileInfoScanner;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-scanner-unchanged-' . bin2hex(random_bytes(6));
$name = 'wp-content/uploads/2026/05/unchanged.jpg';
$bytes = 'stable wordpress media bytes';

try {
    wordpress_scanner_unchanged_write($root, $name, $bytes);

    $scanner = new FileInfoScanner($root, localFlags: FileInfo::FLAG_LOCAL_RECEIVE_ONLY);
    $initial = $scanner->walk([$name], hashBlocks: true, blockSize: 8)[0];
    $unchanged = $scanner->walk([$name], hashBlocks: true, blockSize: 8, currentFiles: [$initial]);
    $ignoredCurrent = new FileInfo(
        name: $name,
        modifiedS: $initial->modifiedS,
        size: $initial->size,
        blocksHash: $initial->blocksHash,
        type: FileInfo::TYPE_FILE,
        permissions: $initial->permissions,
        rawBlockSize: $initial->rawBlockSize,
        blocks: $initial->blocks,
        localFlags: FileInfo::FLAG_LOCAL_IGNORED,
    );
    $rescan = $scanner->walk([$name], hashBlocks: true, blockSize: 8, currentFiles: [$ignoredCurrent]);

    echo json_encode([
        'path' => $name,
        'initialLocalFlags' => $initial->localFlags,
        'unchangedSecondScanItems' => count($unchanged),
        'ignoredPriorStateForcesRescan' => count($rescan) === 1,
        'rescannedLocalFlags' => $rescan[0]->localFlags ?? null,
        'previousBlocksHashCarried' => ($rescan[0]->previousBlocksHash ?? null) === $initial->blocksHash,
        'contentHash' => $initial->blocksHash,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    wordpress_scanner_unchanged_rm($root);
}

function wordpress_scanner_unchanged_write(string $root, string $name, string $bytes): void
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create scanner unchanged example directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write scanner unchanged example file');
    }
}

function wordpress_scanner_unchanged_rm(string $path): void
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
        wordpress_scanner_unchanged_rm($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}
