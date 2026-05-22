<?php

declare(strict_types=1);

use PortLibs\Syncthing\BepWire;
use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\ReceiveEncrypted;
use PortLibs\Syncthing\Request;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$bytes = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-media-upload.bin');
$blockList = new BlockList();
$blocks = $blockList->fromBytes($bytes, 32);
$encryptedName = ReceiveEncrypted::slashifyBase32Hex('1' . str_repeat('A', 78));
$plainRequest = new Request(
    id: 9401,
    folder: 'wordpress-media',
    name: 'wp-content/uploads/2026/encrypted-media.bin',
    offset: $blocks[0]->offset,
    size: $blocks[0]->size,
    hashHex: $blocks[0]->hashHex,
    fromTemporary: true,
    blockNo: 0,
);
$encryptedRequest = ReceiveEncrypted::requestToEncryptedPeer(
    $plainRequest,
    $encryptedName,
    bin2hex('encrypted-hash-token'),
);
$decodedRequest = BepWire::decodeRequestMessage(BepWire::encodeRequestMessage($encryptedRequest));

$file = new FileInfo(
    name: 'wp-content\\uploads\\2026\\encrypted-media.bin',
    modifiedS: 1700001700,
    version: VersionVector::fromCounters([77 => 12]),
    size: strlen($bytes),
    blocksHash: $blockList->hashBlocks($blocks),
    rawBlockSize: 32,
    sequence: 91,
    blocks: [
        new Block($blocks[0]->offset, $blocks[0]->size, $blocks[0]->hashHex),
    ],
    modifiedBy: 77,
);
$withTrailer = ReceiveEncrypted::appendEncryptionTrailer($bytes, $file, '\\');
$trailer = ReceiveEncrypted::extractEncryptionTrailer($withTrailer);

echo json_encode([
    'encryptedRequest' => [
        'name' => $decodedRequest->name,
        'isSyntheticParent' => ReceiveEncrypted::isEncryptedParent(dirname($decodedRequest->name)),
        'offset' => $decodedRequest->offset,
        'size' => $decodedRequest->size,
        'blockNo' => $decodedRequest->blockNo,
        'fromTemporary' => $decodedRequest->fromTemporary,
        'hashTokenHex' => $decodedRequest->hashHex,
    ],
    'trailer' => [
        'totalBytes' => strlen($withTrailer),
        'mediaBytes' => strlen($trailer['data']),
        'trailerBytes' => $trailer['trailerSize'],
        'wireName' => $trailer['file']->name,
        'version' => $trailer['file']->version->humanString(),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
