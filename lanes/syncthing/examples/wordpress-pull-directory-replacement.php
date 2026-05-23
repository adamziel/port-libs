<?php

declare(strict_types=1);

use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\PullTemporaryFile;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-dir-replace-' . bin2hex(random_bytes(4));
mkdir($root, 0777, true);

try {
    $name = 'wp-content/uploads/2026/05/gallery-export.zip';
    $oldDirectory = 'wp-content/uploads/2026/05/gallery-export.zip';
    $remoteBytes = str_repeat('playground generated media archive ', 3000);
    $blocks = (new BlockList())->fromBytes($remoteBytes);
    $current = new FileInfo(
        name: $oldDirectory,
        modifiedS: 1700001200,
        version: VersionVector::fromCounters([101 => 5]),
        type: FileInfo::TYPE_DIRECTORY,
        permissions: 0755,
        modifiedBy: 101,
    );
    $remote = new FileInfo(
        name: $name,
        modifiedS: 1700001300,
        version: VersionVector::fromCounters([202 => 1]),
        size: strlen($remoteBytes),
        rawBlockSize: BlockList::MIN_BLOCK_SIZE,
        permissions: 0644,
        blocks: $blocks,
        modifiedBy: 202,
    );

    $finalPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    mkdir($finalPath . DIRECTORY_SEPARATOR . 'thumbs', 0777, true);
    file_put_contents($finalPath . DIRECTORY_SEPARATOR . 'thumbs' . DIRECTORY_SEPARATOR . 'old.jpg', 'stale thumbnail directory');

    $assembler = new PullTemporaryFile($remote, $root, currentFile: $current);
    $assembler->writeBlock($blocks[0], $remoteBytes, source: 'playgroundPeer');
    $result = $assembler->finalize();

    echo json_encode([
        'media' => $name,
        'oldDirectoryRemoved' => is_file($finalPath),
        'finalized' => $result->toArray(),
        'publishedSha256' => hash_file('sha256', $finalPath),
        'conflictCopyCreated' => $result->conflictName !== null,
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
