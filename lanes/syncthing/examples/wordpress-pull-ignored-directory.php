<?php

declare(strict_types=1);

use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\IgnoreMatcher;
use PortLibs\Syncthing\PullTemporaryFile;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-ignored-directory-' . bin2hex(random_bytes(4));
mkdir($root, 0777, true);

try {
    $name = 'wp-content/uploads/2026/05/private-cache';
    $ignoredDir = $name . '/local-review';
    $ignoredChild = $ignoredDir . '/keep.txt';
    $newBytes = str_repeat('Playground private media archive ', 2400);
    $blocks = (new BlockList())->fromBytes($newBytes);
    $current = new FileInfo(
        name: $name,
        modifiedS: 1700001750,
        version: VersionVector::fromCounters([101 => 10]),
        type: FileInfo::TYPE_DIRECTORY,
        permissions: 0755,
        modifiedBy: 101,
    );
    $remote = new FileInfo(
        name: $name,
        modifiedS: 1700001800,
        version: VersionVector::fromCounters([202 => 5]),
        size: strlen($newBytes),
        rawBlockSize: BlockList::MIN_BLOCK_SIZE,
        permissions: 0644,
        blocks: $blocks,
        modifiedBy: 202,
    );

    $ignoredChildPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $ignoredChild);
    mkdir(dirname($ignoredChildPath), 0777, true);
    file_put_contents($ignoredChildPath, 'review cache that must remain local');

    $assembler = new PullTemporaryFile(
        $remote,
        $root,
        currentFile: $current,
        knownDirectoryChildren: [],
        ignoreMatcher: IgnoreMatcher::fromLines([$ignoredDir]),
    );
    $assembler->writeBlock($blocks[0], $newBytes, source: 'playgroundPeer');
    $result = $assembler->finalize();

    echo json_encode([
        'target' => $name,
        'finalized' => $result->toArray(),
        'ignoredReviewCachePreserved' => file_get_contents($ignoredChildPath) === 'review cache that must remain local',
        'directoryStillPresent' => is_dir($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name)),
        'temporaryArchiveBytesRetained' => file_exists($assembler->tempPath()) ? filesize($assembler->tempPath()) : 0,
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
