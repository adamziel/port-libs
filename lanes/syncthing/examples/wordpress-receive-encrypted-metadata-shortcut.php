<?php

declare(strict_types=1);

use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\EncryptionKey;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\PullDbUpdater;
use PortLibs\Syncthing\PullItemUpdater;
use PortLibs\Syncthing\ReceiveEncrypted;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-receive-encrypted-shortcut-' . bin2hex(random_bytes(6));
mkdir($root, 0777, true);

try {
    $plainName = 'wp-content/uploads/private/2026/member-export.bin';
    $plainBytes = str_repeat('private WordPress media export ', 32);
    $blockList = new BlockList();
    $plainBlocks = $blockList->fromBytes($plainBytes, strlen($plainBytes));
    $folderKey = EncryptionKey::folderKeyFromPassword('wordpress-private-media', 'member media secret');
    $fileKey = ReceiveEncrypted::fileKey($plainName, $folderKey);

    $oldPlain = new FileInfo(
        name: $plainName,
        modifiedS: 1_700_005_300,
        version: VersionVector::fromCounters([101 => 82]),
        size: strlen($plainBytes),
        blocksHash: $blockList->hashBlocks($plainBlocks),
        permissions: 0644,
        rawBlockSize: strlen($plainBytes),
        sequence: 82,
        blocks: $plainBlocks,
        modifiedBy: 101,
    );
    $newPlain = new FileInfo(
        name: $plainName,
        modifiedS: 1_700_005_400,
        version: VersionVector::fromCounters([202 => 83]),
        size: strlen($plainBytes),
        blocksHash: $blockList->hashBlocks($plainBlocks),
        permissions: 0644,
        rawBlockSize: strlen($plainBytes),
        sequence: 83,
        blocks: $plainBlocks,
        modifiedBy: 202,
    );

    $oldEncrypted = ReceiveEncrypted::encryptFileInfo($oldPlain, $folderKey, str_repeat("\1", ReceiveEncrypted::NONCE_SIZE));
    $newEncrypted = ReceiveEncrypted::encryptFileInfo($newPlain, $folderKey, str_repeat("\2", ReceiveEncrypted::NONCE_SIZE));
    $encryptedData = ReceiveEncrypted::encryptBytes(
        $plainBytes . str_repeat('P', ReceiveEncrypted::MIN_PADDED_SIZE - strlen($plainBytes)),
        $fileKey,
        str_repeat("\3", ReceiveEncrypted::NONCE_SIZE),
    );
    $oldFinalized = ReceiveEncrypted::finalizeEncryptedFile($encryptedData, $oldEncrypted);

    $encryptedPath = wordpress_receive_encrypted_shortcut_path($root, $newEncrypted->name);
    $encryptedDir = dirname($encryptedPath);
    if (!is_dir($encryptedDir) && !mkdir($encryptedDir, 0777, true) && !is_dir($encryptedDir)) {
        throw new RuntimeException('Failed to create encrypted media directory');
    }
    file_put_contents($encryptedPath, $oldFinalized['bytes']);

    $updater = new PullItemUpdater($root, folderId: 'wordpress-private-media', receiveEncryptedFolder: true);
    $remainingFiles = $updater->processMetadataShortcuts([$newEncrypted], [$oldFinalized['file']]);

    $rewritten = ReceiveEncrypted::extractEncryptionTrailer((string) file_get_contents($encryptedPath));
    $dbUpdater = new PullDbUpdater(folderId: 'wordpress-private-media', folderLabel: 'Private Media');
    foreach ($updater->dbUpdates() as $update) {
        $dbUpdater->append($update['file'], $update['type']);
    }
    $dbUpdater->close();

    echo json_encode([
        'folder' => 'wordpress-private-media',
        'plainName' => $plainName,
        'encryptedName' => $newEncrypted->name,
        'encryptedDataUnchanged' => $rewritten['data'] === $encryptedData,
        'trailerPayloadUpdated' => $rewritten['file']->encryptedPayload === $newEncrypted->encryptedPayload,
        'localDbSizeIncludesTrailer' => $updater->dbUpdates()[0]['file']->size,
        'remoteIndexSizeWithoutTrailer' => ReceiveEncrypted::prepareFinalizedFileInfoForIndex(
            $updater->dbUpdates()[0]['file'],
            $rewritten['trailerSize'],
        )->size,
        'dbUpdateTypes' => array_column($updater->dbUpdates(), 'type'),
        'remainingFullPulls' => array_map(static fn (FileInfo $file): string => $file->name, $remainingFiles),
        'receivedFiles' => $dbUpdater->receivedFiles(),
        'pullErrors' => $updater->pullErrors(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    wordpress_receive_encrypted_shortcut_rm($root);
}

function wordpress_receive_encrypted_shortcut_path(string $root, string $name): string
{
    return rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
}

function wordpress_receive_encrypted_shortcut_rm(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($path);
}
