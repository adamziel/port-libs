<?php

declare(strict_types=1);

use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\EncryptionKey;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\PullTemporaryFile;
use PortLibs\Syncthing\ReceiveEncrypted;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-recvenc-pull-' . bin2hex(random_bytes(4));
mkdir($root, 0777, true);

try {
    $folder = 'wordpress-private-media';
    $plainBytes = str_repeat('receive encrypted wordpress media ', 22);
    $plainBlocks = (new BlockList())->fromBytes($plainBytes, strlen($plainBytes));
    $plainFile = new FileInfo(
        name: 'wp-content/uploads/2026/private/finalized-pull.bin',
        modifiedS: 1700002300,
        version: VersionVector::fromCounters([77 => 16]),
        size: strlen($plainBytes),
        blocksHash: (new BlockList())->hashBlocks($plainBlocks),
        rawBlockSize: strlen($plainBytes),
        sequence: 161,
        blocks: $plainBlocks,
        modifiedBy: 77,
    );

    $folderKey = EncryptionKey::folderKeyFromPassword($folder, 'wordpress media sync secret');
    $fileKey = ReceiveEncrypted::fileKey($plainFile->name, $folderKey);
    $encryptedFile = ReceiveEncrypted::encryptFileInfo(
        $plainFile,
        $folderKey,
        str_repeat("\12", ReceiveEncrypted::NONCE_SIZE),
    );
    $encryptedBlockBytes = ReceiveEncrypted::encryptBytes(
        $plainBytes . str_repeat('P', ReceiveEncrypted::MIN_PADDED_SIZE - strlen($plainBytes)),
        $fileKey,
        str_repeat("\13", ReceiveEncrypted::NONCE_SIZE),
    );

    $assembler = new PullTemporaryFile($encryptedFile, $root);
    $assembler->writeBlock(
        $encryptedFile->blocks[0],
        $encryptedBlockBytes,
        receiveEncrypted: true,
        source: 'receiveEncryptedPull',
    );

    $result = $assembler->finalize();
    $finalBytes = (string) file_get_contents($assembler->finalPath());
    $verified = ReceiveEncrypted::verifyFinalizedEncryptedFile($finalBytes, $folderKey);
    $remoteIndexFile = ReceiveEncrypted::prepareFinalizedFileInfoForIndex(
        $encryptedFile->withSize($result->finalSize),
        $result->encryptionTrailerSize,
    );

    echo json_encode([
        'folder' => $folder,
        'plainName' => $verified['plainFile']->name,
        'encryptedName' => $encryptedFile->name,
        'finalized' => $result->toArray(),
        'localFinalizedBytes' => strlen($finalBytes),
        'remoteIndexSize' => $remoteIndexFile->size,
        'plaintextVerified' => $verified['plaintext'] === $plainBytes,
        'trailerVerified' => $verified['trailerSize'] === $result->encryptionTrailerSize,
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
