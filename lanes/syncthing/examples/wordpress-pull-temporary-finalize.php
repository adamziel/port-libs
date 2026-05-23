<?php

declare(strict_types=1);

use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\BlockPullResult;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\PullTemporaryFile;
use PortLibs\Syncthing\RequestServer;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-finalize-' . bin2hex(random_bytes(4));
mkdir($root, 0777, true);

try {
    $blockSize = BlockList::MIN_BLOCK_SIZE;
    $bytes = str_repeat('media header ', intdiv($blockSize, 13) + 1);
    $bytes = substr($bytes, 0, $blockSize)
        . str_repeat("\0", $blockSize)
        . str_repeat('restored media payload ', intdiv($blockSize, 23) + 1);
    $bytes = substr($bytes, 0, 3 * $blockSize);

    $blocks = (new BlockList())->fromBytes($bytes, $blockSize);
    $file = new FileInfo(
        name: 'wp-content/uploads/2026/05/hero-final.jpg',
        modifiedS: 1700000000,
        version: VersionVector::fromCounters([202 => 8]),
        size: strlen($bytes),
        rawBlockSize: $blockSize,
        permissions: 0644,
        blocks: $blocks,
    );

    $assembler = new PullTemporaryFile($file, $root);
    $assembler->writeBlock($blocks[0], substr($bytes, 0, $blockSize), source: 'copiedFromOrigin');
    $assembler->skipSparseBlock($blocks[1]);
    $assembler->applyPullResult(new BlockPullResult(
        block: $blocks[2],
        data: substr($bytes, 2 * $blockSize),
    ));

    $result = $assembler->finalize();
    $finalPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file->name);

    echo json_encode([
        'media' => $file->name,
        'temporaryName' => RequestServer::temporaryName($file->name),
        'finalized' => $result->toArray(),
        'sourcesByBlockIndex' => $assembler->sourcesByBlockIndex(),
        'finalBytes' => filesize($finalPath),
        'finalSha256' => hash_file('sha256', $finalPath),
        'temporaryLeftForRetry' => file_exists($assembler->tempPath()),
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
