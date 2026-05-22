<?php

declare(strict_types=1);

use PortLibs\Syncthing\ActiveDownload;
use PortLibs\Syncthing\BepWire;
use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\FileDownloadProgressUpdate;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\ProgressEmitter;
use PortLibs\Syncthing\PullerProgress;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$version = VersionVector::fromCounters([101 => 1700001000]);
$media = new FileInfo(
    name: 'wp-content/uploads/2026/hero.jpg',
    version: $version,
    size: (11 * BlockList::MIN_BLOCK_SIZE) + 4096,
    rawBlockSize: BlockList::MIN_BLOCK_SIZE,
    blocks: wordpressProgressEmitterBlocks(12),
);
$pattern = new FileInfo(
    name: 'wp-content/themes/news/patterns/home.php',
    version: $version,
    size: (10 * BlockList::MIN_BLOCK_SIZE) + 2048,
    rawBlockSize: BlockList::MIN_BLOCK_SIZE,
    blocks: wordpressProgressEmitterBlocks(11),
);

$emitter = new ProgressEmitter(minBlocks: 10);
$emitter->temporaryIndexSubscribe('playground-importer', ['wordpress-media', 'wordpress-content']);
$emitter->temporaryIndexSubscribe('editor-preview', ['wordpress-content']);

$emitter->register(
    new ActiveDownload('wordpress-media', $media, [0, 1], availableUpdated: 1, created: 1),
    PullerProgress::fromCounters($media, reused: 1, copyTotal: 2, pullTotal: 9, copyNeeded: 0, pullNeeded: 8, copiedFromOrigin: 1),
);
$emitter->register(new ActiveDownload('wordpress-content', $pattern, [3], availableUpdated: 1, created: 1));

$initial = wordpressProgressEmitterMessages($emitter->computeProgressUpdates());

$emitter->register(
    new ActiveDownload('wordpress-media', $media, [0, 1, 5], availableUpdated: 2, created: 1),
    PullerProgress::fromCounters($media, reused: 1, copyTotal: 2, pullTotal: 9, copyNeeded: 0, pullNeeded: 7, copiedFromOrigin: 1),
);
$delta = wordpressProgressEmitterMessages($emitter->computeProgressUpdates());

echo json_encode([
    'initialMessages' => $initial,
    'deltaMessages' => $delta,
    'mediaBytesCompleted' => $emitter->bytesCompleted('wordpress-media'),
    'eventFolders' => array_keys($emitter->downloadProgressEvent()),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

/**
 * @return list<Block>
 */
function wordpressProgressEmitterBlocks(int $count): array
{
    $blocks = [];
    for ($i = 0; $i < $count; $i++) {
        $blocks[] = new Block($i * BlockList::MIN_BLOCK_SIZE, BlockList::MIN_BLOCK_SIZE, hash('sha256', 'wordpress-progress-block-' . $i));
    }

    return $blocks;
}

/**
 * @param list<PortLibs\Syncthing\ProgressUpdateBatch> $batches
 *
 * @return list<array<string, mixed>>
 */
function wordpressProgressEmitterMessages(array $batches): array
{
    $messages = [];
    foreach ($batches as $batch) {
        $frame = BepWire::encodeDownloadProgressMessage($batch->toDownloadProgress());
        $messages[] = [
            'device' => $batch->deviceId,
            'folder' => $batch->folder,
            'messageType' => BepWire::decodeMessageFrame($frame)['type'],
            'updates' => array_map(
                static fn (FileDownloadProgressUpdate $update): array => [
                    'name' => $update->name,
                    'type' => $update->isAppend() ? 'append' : 'forget',
                    'blocks' => $update->blockIndexes,
                ],
                $batch->updates,
            ),
        ];
    }

    return $messages;
}
