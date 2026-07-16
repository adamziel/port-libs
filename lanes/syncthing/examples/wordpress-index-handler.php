<?php

declare(strict_types=1);

use PortLibs\Syncthing\BepWire;
use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\IndexHandler;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$bytes = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-media-upload.bin');
$blockList = new BlockList();
$blocks = $blockList->fromBytes($bytes, 32);

$remoteVisible = new FileInfo(
    name: 'wp-content\\uploads\\private\\synced-photo.jpg',
    modifiedS: 1_700_003_041,
    version: VersionVector::fromCounters([101 => 41]),
    size: strlen($bytes),
    blocksHash: $blockList->hashBlocks($blocks),
    permissions: 0644,
    rawBlockSize: 32,
    sequence: 41,
    blocks: array_map(
        static fn (Block $block): Block => new Block($block->offset, $block->size, $block->hashHex),
        $blocks,
    ),
    modifiedBy: 101,
);

$localOnlyChange = new FileInfo(
    name: 'wp-content\\uploads\\private\\local-draft.jpg',
    modifiedS: 1_700_003_042,
    version: VersionVector::fromCounters([101 => 42]),
    localFlags: FileInfo::FLAG_LOCAL_RECEIVE_ONLY,
    size: 2048,
    permissions: 0644,
    rawBlockSize: 2048,
    sequence: 42,
    modifiedBy: 101,
);

$handler = new IndexHandler(
    folder: 'wordpress-private-media',
    localPrevSequence: 40,
    sentPrevSequence: 40,
    folderIsReceiveEncrypted: true,
);
$frames = $handler->buildIndexFrames([$remoteVisible, $localOnlyChange], directorySeparator: '\\');
$update = BepWire::decodeIndexUpdateMessage($frames[0]);

$forget = IndexHandler::forgetUpdatesForReceivedIndex([
    $remoteVisible->withName('wp-content/uploads/private/synced-photo.jpg'),
    new FileInfo(name: 'wp-content/uploads/private', type: FileInfo::TYPE_DIRECTORY, sequence: 43),
    new FileInfo(name: 'wp-content/uploads/private/old.jpg', deleted: true, sequence: 44),
]);

echo json_encode([
    'folder' => $update->folder,
    'messageType' => BepWire::MESSAGE_TYPE_INDEX_UPDATE,
    'wireNames' => array_map(static fn (FileInfo $file): string => $file->name, $update->files),
    'prevSequence' => $update->prevSequence,
    'lastSentSequence' => $update->lastSequence,
    'localPrevSequence' => $handler->localPrevSequence(),
    'sentPrevSequence' => $handler->sentPrevSequence(),
    'skippedLocalReceiveOnlyChange' => $handler->localPrevSequence() === 42 && $handler->sentPrevSequence() === 41,
    'forgetUpdatesAfterRemoteIndex' => array_map(static fn ($update): string => $update->name, $forget),
    'frameBytes' => strlen($frames[0]),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
