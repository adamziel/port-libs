<?php

declare(strict_types=1);

use PortLibs\Syncthing\Availability;
use PortLibs\Syncthing\BepWire;
use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\DownloadProgress;
use PortLibs\Syncthing\FileDownloadProgressUpdate;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\RemoteDownloadProgressTracker;
use PortLibs\Syncthing\VersionVector;

return [
    'maps upstream model download progress sharing guard and event summary' => static function (TestRunner $t): void {
        $tracker = new RemoteDownloadProgressTracker([
            'wordpress-media' => ['peer-a'],
        ]);
        $version = VersionVector::fromCounters([101 => 1]);
        $update = new FileDownloadProgressUpdate(
            updateType: FileDownloadProgressUpdate::TYPE_APPEND,
            name: 'wp-content/uploads/2026/hero.jpg',
            version: $version,
            blockIndexes: [0, 2],
            blockSize: 4096,
        );

        $t->same(null, $tracker->receiveDownloadProgress('peer-b', new DownloadProgress('wordpress-media', [$update])));
        $t->same(null, $tracker->receiveDownloadProgress('peer-a', new DownloadProgress('private-folder', [$update])));
        $t->same([], $tracker->remoteDownloadProgressEvents());
        $t->same([], $tracker->remoteBlockCounts('peer-a', 'wordpress-media'));

        $event = $tracker->receiveDownloadProgress('peer-a', new DownloadProgress('wordpress-media', [$update]));
        $t->same([
            'device' => 'peer-a',
            'folder' => 'wordpress-media',
            'state' => ['wp-content/uploads/2026/hero.jpg' => 2],
        ], $event);
        $t->same([$event], $tracker->remoteDownloadProgressEvents());
        $t->same(8192, $tracker->bytesDownloaded('peer-a', 'wordpress-media'));
    },
    'maps temporary block availability and fromTemporary request planning' => static function (TestRunner $t): void {
        $version = VersionVector::fromCounters([101 => 1]);
        $file = remoteProgressFile('wp-content/uploads/2026/hero.jpg', $version, 4);
        $block = $file->blocks[2];
        $tracker = new RemoteDownloadProgressTracker([
            'wordpress-media' => ['peer-a', 'peer-b'],
        ]);

        $tracker->receiveDownloadProgress('peer-a', new DownloadProgress('wordpress-media', [
            new FileDownloadProgressUpdate(
                updateType: FileDownloadProgressUpdate::TYPE_APPEND,
                name: $file->name,
                version: $version,
                blockIndexes: [2],
                blockSize: $file->blockSize(),
            ),
        ]));

        $t->same([
            ['device' => 'peer-a', 'fromTemporary' => true],
        ], array_map(static fn (Availability $availability): array => $availability->toArray(), $tracker->availability('wordpress-media', $file, $block)));

        $plan = $tracker->planBlockRequest('wordpress-media', $file, $block, requestId: 42);
        $t->true($plan !== null);
        $t->same('peer-a', $plan->deviceId);
        $t->true($plan->fromTemporary());
        $t->same(42, $plan->request->id);
        $t->same(2, $plan->request->blockNo);
        $t->same($block->offset, $plan->request->offset);
        $t->same($block->size, $plan->request->size);
        $t->same($block->hashHex, $plan->request->hashHex);

        $decoded = BepWire::decodeRequestMessage(BepWire::encodeRequestMessage($plan->request));
        $t->true($decoded->fromTemporary);
        $t->same(2, $decoded->blockNo);
    },
    'prefers full-file availability before temporary candidates and validates inputs' => static function (TestRunner $t): void {
        $version = VersionVector::fromCounters([101 => 1]);
        $file = remoteProgressFile('wp-content/uploads/2026/hero.jpg', $version, 3);
        $block = $file->blocks[1];
        $tracker = new RemoteDownloadProgressTracker([
            'wordpress-media' => ['peer-a', 'peer-b'],
        ]);
        $tracker->receiveDownloadProgress('peer-a', new DownloadProgress('wordpress-media', [
            new FileDownloadProgressUpdate(
                updateType: FileDownloadProgressUpdate::TYPE_APPEND,
                name: $file->name,
                version: $version,
                blockIndexes: [1],
                blockSize: $file->blockSize(),
            ),
        ]));

        $availability = $tracker->availability('wordpress-media', $file, $block, ['unshared-peer', 'peer-b']);
        $t->same([
            ['device' => 'peer-b', 'fromTemporary' => false],
            ['device' => 'peer-a', 'fromTemporary' => true],
        ], array_map(static fn (Availability $item): array => $item->toArray(), $availability));

        $plan = $tracker->planBlockRequest('wordpress-media', $file, $block, ['peer-b']);
        $t->true($plan !== null);
        $t->same('peer-b', $plan->deviceId);
        $t->true(!$plan->fromTemporary());
        $t->same(null, $tracker->planBlockRequest('unknown-folder', $file, $block));

        $t->throws(InvalidArgumentException::class, static fn () => new RemoteDownloadProgressTracker(['wordpress-media' => ['']]));
        $t->throws(InvalidArgumentException::class, static fn () => $tracker->availability('wordpress-media', $file, $block, ['']));
        $t->throws(InvalidArgumentException::class, static fn () => $tracker->requestForBlock('wordpress-media', $file, new Block(-1, 1, $block->hashHex), true));
    },
];

/**
 * @return list<Block>
 */
function remoteProgressBlocks(int $count): array
{
    $blocks = [];
    for ($i = 0; $i < $count; $i++) {
        $blocks[] = new Block($i * BlockList::MIN_BLOCK_SIZE, BlockList::MIN_BLOCK_SIZE, hash('sha256', 'remote-progress-block-' . $i));
    }

    return $blocks;
}

function remoteProgressFile(string $name, VersionVector $version, int $blocks): FileInfo
{
    return new FileInfo(
        name: $name,
        version: $version,
        size: $blocks * BlockList::MIN_BLOCK_SIZE,
        rawBlockSize: BlockList::MIN_BLOCK_SIZE,
        blocks: remoteProgressBlocks($blocks),
    );
}
