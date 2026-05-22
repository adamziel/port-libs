<?php

declare(strict_types=1);

use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\EncryptionKey;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\ReceiveEncrypted;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$plainBytes = str_repeat('private WordPress export block ', 24);
$blockList = new BlockList();
$blocks = $blockList->fromBytes($plainBytes, strlen($plainBytes));
$plainFile = new FileInfo(
    name: 'wp-content\\uploads\\2026\\private\\finalized-export.bin',
    modifiedS: 1700002200,
    version: VersionVector::fromCounters([77 => 15]),
    size: strlen($plainBytes),
    blocksHash: $blockList->hashBlocks($blocks),
    rawBlockSize: strlen($plainBytes),
    sequence: 151,
    blocks: $blocks,
    modifiedBy: 77,
);

$folderKey = EncryptionKey::folderKeyFromPassword('wordpress-private-media', 'wordpress media sync secret');
$wireFile = $plainFile->withName('wp-content/uploads/2026/private/finalized-export.bin');
$fileKey = ReceiveEncrypted::fileKey($wireFile->name, $folderKey);
$encryptedFile = ReceiveEncrypted::encryptFileInfo(
    $wireFile,
    $folderKey,
    str_repeat("\12", ReceiveEncrypted::NONCE_SIZE),
);
$encryptedData = ReceiveEncrypted::encryptBytes(
    $plainBytes . str_repeat('P', ReceiveEncrypted::MIN_PADDED_SIZE - strlen($plainBytes)),
    $fileKey,
    str_repeat("\13", ReceiveEncrypted::NONCE_SIZE),
);

$finalized = ReceiveEncrypted::finalizeEncryptedFile($encryptedData, $encryptedFile, '\\');
$remoteIndexFile = ReceiveEncrypted::prepareFinalizedFileInfoForIndex($finalized['file'], $finalized['trailerSize']);
$verified = ReceiveEncrypted::verifyFinalizedEncryptedFile($finalized['bytes'], $folderKey);

$insertedGarbage = $encryptedData . 'x' . substr($finalized['bytes'], strlen($encryptedData));
$extraDataRejected = false;
try {
    ReceiveEncrypted::verifyFinalizedEncryptedFile($insertedGarbage, $folderKey);
} catch (LengthException) {
    $extraDataRejected = true;
}

echo json_encode([
    'folder' => 'wordpress-private-media',
    'plainName' => $verified['plainFile']->name,
    'encryptedName' => $verified['encryptedFile']->name,
    'encryptedDataBytes' => strlen($verified['encryptedData']),
    'trailerBytes' => $verified['trailerSize'],
    'localFinalizedBytes' => strlen($finalized['bytes']),
    'localIndexedSize' => $finalized['file']->size,
    'remoteIndexSize' => $remoteIndexFile->size,
    'plaintextVerified' => $verified['plaintext'] === $plainBytes,
    'blockHashVerified' => $verified['plainFile']->blocksHash === $plainFile->blocksHash,
    'extraDataBeforeTrailerRejected' => $extraDataRejected,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
