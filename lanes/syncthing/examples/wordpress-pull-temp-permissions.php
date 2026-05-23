<?php

declare(strict_types=1);

use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\PullTemporaryFile;
use PortLibs\Syncthing\RequestServer;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-temp-perms-' . bin2hex(random_bytes(4));
mkdir($root, 0777, true);

try {
    $bytes = str_repeat('private media draft ', 6000);
    $blocks = (new BlockList())->fromBytes($bytes);
    $file = new FileInfo(
        name: 'wp-content/uploads/private/draft-product-photo.jpg',
        modifiedS: 1700000500,
        version: VersionVector::fromCounters([202 => 9]),
        size: strlen($bytes),
        rawBlockSize: BlockList::MIN_BLOCK_SIZE,
        permissions: 0400,
        blocks: $blocks,
    );

    $tempName = RequestServer::temporaryName($file->name);
    $tempPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $tempName);
    mkdir(dirname($tempPath), 0777, true);
    file_put_contents($tempPath, 'stale restart bytes');
    chmod($tempPath, 0400);

    $assembler = new PullTemporaryFile($file, $root, $tempName);
    $assembler->writeBlock($blocks[0], $bytes, source: 'resumedPrivateUpload');
    $tempMode = fileperms($tempPath) & 0777;

    $result = $assembler->finalize();
    $finalPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file->name);

    echo json_encode([
        'media' => $file->name,
        'temporaryName' => $tempName,
        'temporaryModeDuringPull' => decoct($tempMode),
        'finalMode' => decoct(fileperms($finalPath) & 0777),
        'finalized' => $result->toArray(),
        'finalSha256' => hash_file('sha256', $finalPath),
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
