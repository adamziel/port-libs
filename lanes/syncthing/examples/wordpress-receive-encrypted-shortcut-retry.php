<?php

declare(strict_types=1);

use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\EncryptionKey;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\PullItemUpdater;
use PortLibs\Syncthing\ReceiveEncrypted;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-receive-encrypted-retry-' . bin2hex(random_bytes(6));
mkdir($root, 0777, true);

try {
    $plainName = 'wp-content/uploads/private/2026/member-retry.bin';
    $plainBytes = str_repeat('private WordPress retry export ', 8);
    $blockList = new BlockList();
    $plainBlocks = $blockList->fromBytes($plainBytes, strlen($plainBytes));
    $blocksHash = $blockList->hashBlocks($plainBlocks);
    $folderKey = EncryptionKey::folderKeyFromPassword('wordpress-private-media', 'member media secret');
    $fileKey = ReceiveEncrypted::fileKey($plainName, $folderKey);

    $oldPlain = new FileInfo(
        name: $plainName,
        modifiedS: 1_700_005_500,
        version: VersionVector::fromCounters([101 => 90]),
        size: strlen($plainBytes),
        blocksHash: $blocksHash,
        permissions: 0644,
        rawBlockSize: strlen($plainBytes),
        sequence: 90,
        blocks: $plainBlocks,
        modifiedBy: 101,
    );
    $newPlain = new FileInfo(
        name: $plainName,
        modifiedS: 1_700_005_600,
        version: VersionVector::fromCounters([202 => 91]),
        size: strlen($plainBytes),
        blocksHash: $blocksHash,
        permissions: 0644,
        rawBlockSize: strlen($plainBytes),
        sequence: 91,
        blocks: $plainBlocks,
        modifiedBy: 202,
    );

    $oldEncrypted = ReceiveEncrypted::encryptFileInfo($oldPlain, $folderKey, str_repeat("\1", ReceiveEncrypted::NONCE_SIZE));
    $newEncrypted = ReceiveEncrypted::encryptFileInfo($newPlain, $folderKey, str_repeat("\2", ReceiveEncrypted::NONCE_SIZE));
    $encryptedData = ReceiveEncrypted::encryptBytes(
        str_pad($plainBytes, ReceiveEncrypted::MIN_PADDED_SIZE, 'P'),
        $fileKey,
        str_repeat("\3", ReceiveEncrypted::NONCE_SIZE),
    );
    $oldFinalized = ReceiveEncrypted::finalizeEncryptedFile($encryptedData, $oldEncrypted);

    $encryptedPath = wordpress_receive_encrypted_retry_path($root, $newEncrypted->name);
    $encryptedDir = dirname($encryptedPath);
    if (!is_dir($encryptedDir) && !mkdir($encryptedDir, 0777, true) && !is_dir($encryptedDir)) {
        throw new RuntimeException('Failed to create encrypted media retry directory');
    }
    file_put_contents($encryptedPath, $oldFinalized['bytes']);
    chmod($encryptedPath, 0444);

    try {
        $updater = new PullItemUpdater(
            $root,
            folderId: 'wordpress-private-media',
            ignorePerms: true,
            receiveEncryptedFolder: true,
        );
        $remainingFiles = $updater->processMetadataShortcuts([$newEncrypted], [$oldFinalized['file']]);
        $stillFinalized = ReceiveEncrypted::extractEncryptionTrailer((string) file_get_contents($encryptedPath));

        echo json_encode([
            'folder' => 'wordpress-private-media',
            'encryptedName' => $newEncrypted->name,
            'retryableShortcutFailure' => $updater->pullErrors() !== [] && $updater->dbUpdates() === [],
            'encryptedDataUnchanged' => $stillFinalized['data'] === $encryptedData,
            'trailerStillOld' => $stillFinalized['file']->encryptedPayload === $oldEncrypted->encryptedPayload,
            'newTrailerNotCommitted' => $stillFinalized['file']->encryptedPayload !== $newEncrypted->encryptedPayload,
            'dbUpdateTypes' => array_column($updater->dbUpdates(), 'type'),
            'remainingFullPulls' => array_map(static fn (FileInfo $file): string => $file->name, $remainingFiles),
            'pullErrors' => $updater->pullErrors(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } finally {
        @chmod($encryptedPath, 0644);
    }
} finally {
    wordpress_receive_encrypted_retry_rm($root);
}

function wordpress_receive_encrypted_retry_path(string $root, string $name): string
{
    return rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
}

function wordpress_receive_encrypted_retry_rm(string $path): void
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
