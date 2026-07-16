<?php

declare(strict_types=1);

use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\PullTemporaryFile;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-version-archive-' . bin2hex(random_bytes(4));
mkdir($root, 0777, true);

try {
    $name = 'wp-content/uploads/2026/05/hero.jpg';
    $oldBytes = str_repeat('published WordPress crop ', 3200);
    $newBytes = str_repeat('Playground normalized crop ', 3200);
    $blocks = (new BlockList())->fromBytes($newBytes);
    $current = new FileInfo(
        name: $name,
        modifiedS: 1700001200,
        version: VersionVector::fromCounters([101 => 7]),
        size: strlen($oldBytes),
        permissions: 0640,
        modifiedBy: 101,
    );
    $remote = new FileInfo(
        name: $name,
        modifiedS: 1700001300,
        version: VersionVector::fromCounters([101 => 7, 202 => 2]),
        size: strlen($newBytes),
        rawBlockSize: BlockList::MIN_BLOCK_SIZE,
        permissions: 0644,
        blocks: $blocks,
        modifiedBy: 202,
    );

    $finalPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    mkdir(dirname($finalPath), 0777, true);
    file_put_contents($finalPath, $oldBytes);
    chmod($finalPath, 0640);

    $archiveTimestamp = strtotime('2026-05-23 12:34:56 UTC');
    if ($archiveTimestamp === false) {
        throw new RuntimeException('Failed to create archive timestamp');
    }
    $assembler = new PullTemporaryFile(
        $remote,
        $root,
        currentFile: $current,
        archiveRootPath: '.stversions',
        archiveTimestamp: $archiveTimestamp,
    );
    $assembler->writeBlock($blocks[0], $newBytes, source: 'playgroundPeer');
    $result = $assembler->finalize();

    $archivePath = $result->archivedName === null
        ? null
        : $root . DIRECTORY_SEPARATOR . '.stversions' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $result->archivedName);

    echo json_encode([
        'media' => $name,
        'finalized' => $result->toArray(),
        'publishedSha256' => hash_file('sha256', $finalPath),
        'archivedSha256' => $archivePath === null ? null : hash_file('sha256', $archivePath),
        'oldPublishedMediaArchived' => $archivePath !== null && file_get_contents($archivePath) === $oldBytes,
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
