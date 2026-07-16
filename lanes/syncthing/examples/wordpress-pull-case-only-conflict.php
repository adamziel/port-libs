<?php

declare(strict_types=1);

use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\PullTemporaryFile;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-case-conflict-' . bin2hex(random_bytes(4));
mkdir($root, 0777, true);

try {
    $localName = 'wp-content/uploads/2026/05/plugin-banner.png';
    $remoteName = 'wp-content/uploads/2026/05/Plugin-Banner.png';
    $bytes = str_repeat('local-first WordPress plugin banner ', 2600);
    $blocks = (new BlockList())->fromBytes($bytes);
    $current = new FileInfo(
        name: $localName,
        modifiedS: 1700002100,
        version: VersionVector::fromCounters([101 => 8]),
        size: strlen($bytes),
        rawBlockSize: BlockList::MIN_BLOCK_SIZE,
        permissions: 0644,
        blocks: $blocks,
        modifiedBy: 101,
    );
    $remote = new FileInfo(
        name: $remoteName,
        modifiedS: 1700002200,
        version: VersionVector::fromCounters([202 => 2]),
        size: strlen($bytes),
        rawBlockSize: BlockList::MIN_BLOCK_SIZE,
        permissions: 0644,
        blocks: $blocks,
        modifiedBy: 202,
    );

    $localPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $localName);
    mkdir(dirname($localPath), 0777, true);
    file_put_contents($localPath, $bytes);
    touch($localPath, $current->modifiedS);

    $assembler = new PullTemporaryFile($remote, $root, currentFile: $current, detectCaseConflicts: true);
    $assembler->writeBlock($blocks[0], $bytes, source: 'playgroundPeer');
    $result = $assembler->finalize();

    echo json_encode([
        'localName' => $localName,
        'remoteName' => $remoteName,
        'caseDetectingFilesystem' => true,
        'finalized' => $result->toArray(),
        'localFileRetained' => file_get_contents($localPath) === $bytes,
        'temporaryFileRetainedForRetry' => file_exists($assembler->tempPath()),
        'scanScheduled' => $result->scanNames,
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
