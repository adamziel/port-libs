<?php

declare(strict_types=1);

use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\PullTemporaryFile;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-receive-only-directory-' . bin2hex(random_bytes(4));
mkdir($root, 0777, true);

try {
    $name = 'wp-content/uploads/2026/05/receive-only-gallery';
    $localChild = $name . '/editor-local-crop.jpg';
    $localBytes = str_repeat('local receive-only media crop ', 1600);
    $remoteBytes = str_repeat('Playground gallery replacement archive ', 2200);
    $blocks = (new BlockList())->fromBytes($remoteBytes);
    $current = new FileInfo(
        name: $name,
        modifiedS: 1700001850,
        version: VersionVector::fromCounters([101 => 11]),
        type: FileInfo::TYPE_DIRECTORY,
        permissions: 0755,
        modifiedBy: 101,
    );
    $knownReceiveOnlyChild = new FileInfo(
        name: $localChild,
        modifiedS: 1700001860,
        version: VersionVector::fromCounters([101 => 12]),
        localFlags: FileInfo::FLAG_LOCAL_RECEIVE_ONLY,
        size: strlen($localBytes),
        type: FileInfo::TYPE_FILE,
        permissions: 0644,
        rawBlockSize: BlockList::MIN_BLOCK_SIZE,
        modifiedBy: 101,
    );
    $remote = new FileInfo(
        name: $name,
        modifiedS: 1700001900,
        version: VersionVector::fromCounters([202 => 6]),
        size: strlen($remoteBytes),
        rawBlockSize: BlockList::MIN_BLOCK_SIZE,
        permissions: 0644,
        blocks: $blocks,
        modifiedBy: 202,
    );

    $localChildPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $localChild);
    mkdir(dirname($localChildPath), 0777, true);
    file_put_contents($localChildPath, $localBytes);

    $assembler = new PullTemporaryFile(
        $remote,
        $root,
        currentFile: $current,
        knownDirectoryChildren: [$knownReceiveOnlyChild],
        receiveOnlyFolder: true,
    );
    $assembler->writeBlock($blocks[0], $remoteBytes, source: 'playgroundPeer');
    $result = $assembler->finalize();

    echo json_encode([
        'target' => $name,
        'finalized' => $result->toArray(),
        'replacementPublished' => file_get_contents($assembler->finalPath()) === $remoteBytes,
        'receiveOnlyChildRemovedFromDisk' => !file_exists($localChildPath),
        'scanScheduledForResurrection' => $result->scanNames,
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
