<?php

declare(strict_types=1);

use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\PullTemporaryFile;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-directory-scan-guard-' . bin2hex(random_bytes(4));
mkdir($root, 0777, true);

try {
    $name = 'wp-content/uploads/2026/05/generated-gallery';
    $localChild = $name . '/thumbs/local-only.jpg';
    $newBytes = str_repeat('Playground generated gallery archive ', 2400);
    $blocks = (new BlockList())->fromBytes($newBytes);
    $current = new FileInfo(
        name: $name,
        modifiedS: 1700001550,
        version: VersionVector::fromCounters([101 => 8]),
        type: FileInfo::TYPE_DIRECTORY,
        permissions: 0755,
        modifiedBy: 101,
    );
    $remote = new FileInfo(
        name: $name,
        modifiedS: 1700001600,
        version: VersionVector::fromCounters([202 => 3]),
        size: strlen($newBytes),
        rawBlockSize: BlockList::MIN_BLOCK_SIZE,
        permissions: 0644,
        blocks: $blocks,
        modifiedBy: 202,
    );

    $localChildPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $localChild);
    mkdir(dirname($localChildPath), 0777, true);
    file_put_contents($localChildPath, 'thumbnail generated locally by WordPress');

    $assembler = new PullTemporaryFile(
        $remote,
        $root,
        currentFile: $current,
        knownDirectoryChildren: [],
    );
    $assembler->writeBlock($blocks[0], $newBytes, source: 'playgroundPeer');
    $result = $assembler->finalize();

    echo json_encode([
        'target' => $name,
        'finalized' => $result->toArray(),
        'directoryStillPresent' => is_dir($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name)),
        'localThumbnailPreserved' => file_exists($localChildPath),
        'scanScheduledFor' => $result->scanNames,
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
