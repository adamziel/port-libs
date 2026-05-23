<?php

declare(strict_types=1);

use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\PullTemporaryFile;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-unscanned-local-edit-' . bin2hex(random_bytes(4));
mkdir($root, 0777, true);

try {
    $name = 'wp-content/uploads/2026/05/editor-crop.jpg';
    $scannedBytes = str_repeat('scanned wordpress crop ', 3200);
    $localEditBytes = str_repeat('unscanned local editor crop ', 3300);
    $remoteBytes = str_repeat('remote playground normalization ', 3400);
    $blocks = (new BlockList())->fromBytes($remoteBytes);
    $current = new FileInfo(
        name: $name,
        modifiedS: 1700000950,
        version: VersionVector::fromCounters([101 => 4]),
        size: strlen($scannedBytes),
        rawBlockSize: BlockList::MIN_BLOCK_SIZE,
        permissions: 0644,
        blocks: (new BlockList())->fromBytes($scannedBytes),
        modifiedBy: 101,
    );
    $remote = new FileInfo(
        name: $name,
        modifiedS: 1700001000,
        version: VersionVector::fromCounters([101 => 4, 202 => 1]),
        size: strlen($remoteBytes),
        rawBlockSize: BlockList::MIN_BLOCK_SIZE,
        permissions: 0644,
        blocks: $blocks,
        modifiedBy: 202,
    );

    $finalPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    mkdir(dirname($finalPath), 0777, true);
    file_put_contents($finalPath, $localEditBytes);
    touch($finalPath, 1700000975);

    $assembler = new PullTemporaryFile($remote, $root, currentFile: $current);
    $assembler->writeBlock($blocks[0], $remoteBytes, source: 'playgroundPeer');
    $result = $assembler->finalize();

    echo json_encode([
        'media' => $name,
        'finalized' => $result->toArray(),
        'localEditPreserved' => file_get_contents($finalPath) === $localEditBytes,
        'scanScheduledFor' => $result->scanNames,
        'temporaryBytesRetained' => file_exists($assembler->tempPath()) ? filesize($assembler->tempPath()) : 0,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    if (is_dir($root)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($root);
    }
}
