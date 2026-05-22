<?php

declare(strict_types=1);

use PortLibs\Syncthing\BepFrameStream;
use PortLibs\Syncthing\BepSession;
use PortLibs\Syncthing\BepSessionHandlers;
use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\ClusterConfig;
use PortLibs\Syncthing\DownloadProgress;
use PortLibs\Syncthing\FileDownloadProgressUpdate;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\Folder;
use PortLibs\Syncthing\Index;
use PortLibs\Syncthing\IndexUpdate;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$mediaBytes = 'local-first wordpress media callback bytes';
$blockList = new BlockList();
$blocks = $blockList->fromBytes($mediaBytes, 16);
$version = VersionVector::fromCounters([101 => 1700001100]);
$file = new FileInfo(
    name: 'wp-content\\uploads\\2026\\callback-hero.jpg',
    modifiedS: 1700001100,
    version: $version,
    size: strlen($mediaBytes),
    blocksHash: $blockList->hashBlocks($blocks),
    rawBlockSize: 16,
    sequence: 201,
    blocks: array_map(
        static fn (Block $block): Block => new Block($block->offset, $block->size, $block->hashHex),
        $blocks,
    ),
    modifiedBy: 101,
);

$wire = fopen('php://temp', 'w+b');
$stream = BepFrameStream::from($wire);
$stream->writeClusterConfig(new ClusterConfig([
    new Folder(id: 'wordpress-media', label: 'WordPress Media'),
]));
$stream->writeIndex(new Index('wordpress-media', [$file], lastSequence: 201), directorySeparator: '\\');
$stream->writeIndexUpdate(new IndexUpdate('wordpress-media', [$file->withSequence(202)], lastSequence: 202, prevSequence: 201), directorySeparator: '\\');
$stream->writeDownloadProgress(new DownloadProgress('wordpress-media', [
    new FileDownloadProgressUpdate(
        updateType: FileDownloadProgressUpdate::TYPE_APPEND,
        name: 'wp-content\\uploads\\2026\\callback-hero.jpg',
        version: $version,
        blockIndexes: [0, 1],
        blockSize: BlockList::MIN_BLOCK_SIZE,
    ),
]), directorySeparator: '\\');
rewind($wire);

$localCatalog = [];
$temporaryBlocks = [];
$handlers = BepSessionHandlers::model(
    index: static function (Index $index) use (&$localCatalog): array {
        foreach ($index->files as $file) {
            $localCatalog[$file->name] = 'full-' . $file->sequence;
        }

        return ['indexed' => count($index->files)];
    },
    indexUpdate: static function (IndexUpdate $indexUpdate) use (&$localCatalog): array {
        foreach ($indexUpdate->files as $file) {
            $localCatalog[$file->name] = 'updated-' . $file->sequence;
        }

        return ['lastSequence' => $indexUpdate->lastSequence];
    },
    downloadProgress: static function (DownloadProgress $progress) use (&$temporaryBlocks): array {
        foreach ($progress->updates as $update) {
            $temporaryBlocks[$update->name] = $update->blockIndexes;
        }

        return ['temporaryFiles' => count($temporaryBlocks)];
    },
);

$session = new BepSession();
$events = [];
for ($i = 0; $i < 4; $i++) {
    $event = $stream->receiveNext($session, $handlers);
    $events[] = [
        'type' => $event->type,
        'handlerResult' => $event->handlerResult,
    ];
}

echo json_encode([
    'events' => $events,
    'localCatalog' => $localCatalog,
    'temporaryBlocks' => $temporaryBlocks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
