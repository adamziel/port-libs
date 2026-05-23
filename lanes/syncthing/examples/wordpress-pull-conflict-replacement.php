<?php

declare(strict_types=1);

use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\PullTemporaryFile;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-conflict-' . bin2hex(random_bytes(4));
mkdir($root, 0777, true);

try {
    $name = 'wp-content/uploads/2026/05/concurrent-hero.jpg';
    $localBytes = str_repeat('local wordpress crop ', 4500);
    $remoteBytes = str_repeat('remote playground crop ', 4500);
    $blockList = new BlockList();
    $remoteBlocks = $blockList->fromBytes($remoteBytes);
    $current = new FileInfo(
        name: $name,
        modifiedS: 1700001200,
        version: VersionVector::fromCounters([101 => 4]),
        size: strlen($localBytes),
        rawBlockSize: BlockList::MIN_BLOCK_SIZE,
        permissions: 0644,
        blocks: $blockList->fromBytes($localBytes),
        modifiedBy: 101,
    );
    $remote = new FileInfo(
        name: $name,
        modifiedS: 1700001300,
        version: VersionVector::fromCounters([202 => 2]),
        size: strlen($remoteBytes),
        rawBlockSize: BlockList::MIN_BLOCK_SIZE,
        permissions: 0644,
        blocks: $remoteBlocks,
        modifiedBy: 202,
    );

    $finalPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    mkdir(dirname($finalPath), 0777, true);
    file_put_contents($finalPath, $localBytes);

    $assembler = new PullTemporaryFile($remote, $root, currentFile: $current);
    $assembler->writeBlock($remoteBlocks[0], $remoteBytes, source: 'playgroundPeer');
    $result = $assembler->finalize();
    $conflictPath = $result->conflictName === null
        ? null
        : $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $result->conflictName);

    echo json_encode([
        'media' => $name,
        'finalized' => $result->toArray(),
        'publishedSha256' => hash_file('sha256', $finalPath),
        'conflictSha256' => $conflictPath === null ? null : hash_file('sha256', $conflictPath),
        'conflictRetainedLocalEdit' => $conflictPath !== null && file_get_contents($conflictPath) === $localBytes,
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
