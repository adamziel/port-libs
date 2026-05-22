<?php

declare(strict_types=1);

use PortLibs\Syncthing\ActiveDownload;
use PortLibs\Syncthing\BepWire;
use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\FileDownloadProgressUpdate;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\ProgressEmitter;
use PortLibs\Syncthing\ProgressUpdateBatch;
use PortLibs\Syncthing\PullerProgress;
use PortLibs\Syncthing\VersionVector;

return [
    'maps upstream progress emitter subscription grouping' => static function (TestRunner $t): void {
        $emitter = new ProgressEmitter(minBlocks: 10);
        $version = VersionVector::fromCounters([101 => 1]);

        $emitter->temporaryIndexSubscribe('peer-a', ['wordpress-media', 'wordpress-content']);
        $emitter->temporaryIndexSubscribe('peer-b', ['wordpress-content']);

        $emitter->register(progressEmitterDownload(
            'wordpress-media',
            'wp-content/uploads/2026/hero.jpg',
            $version,
            12,
            [0, 2],
            availableUpdated: 1,
            created: 1,
        ));
        $emitter->register(progressEmitterDownload(
            'wordpress-content',
            'wp-content/themes/news/patterns/home.php',
            $version,
            11,
            [4],
            availableUpdated: 1,
            created: 1,
        ));

        $batches = $emitter->computeProgressUpdates();
        $t->same([
            'peer-a|wordpress-content' => [
                ['wp-content/themes/news/patterns/home.php', FileDownloadProgressUpdate::TYPE_APPEND, [4], [101 => 1]],
            ],
            'peer-a|wordpress-media' => [
                ['wp-content/uploads/2026/hero.jpg', FileDownloadProgressUpdate::TYPE_APPEND, [0, 2], [101 => 1]],
            ],
            'peer-b|wordpress-content' => [
                ['wp-content/themes/news/patterns/home.php', FileDownloadProgressUpdate::TYPE_APPEND, [4], [101 => 1]],
            ],
        ], progressEmitterSummary($batches));

        $frame = BepWire::encodeDownloadProgressMessage($batches[0]->toDownloadProgress());
        $t->same(BepWire::MESSAGE_TYPE_DOWNLOAD_PROGRESS, BepWire::decodeMessageFrame($frame)['type']);
        $t->same([], $emitter->computeProgressUpdates());

        $emitter->register(progressEmitterDownload(
            'wordpress-media',
            'wp-content/uploads/2026/hero.jpg',
            $version,
            12,
            [0, 2, 7],
            availableUpdated: 2,
            created: 1,
        ));

        $t->same([
            'peer-a|wordpress-media' => [
                ['wp-content/uploads/2026/hero.jpg', FileDownloadProgressUpdate::TYPE_APPEND, [7], [101 => 1]],
            ],
        ], progressEmitterSummary($emitter->computeProgressUpdates()));
    },
    'maps upstream deregister folder unshare and disconnect cleanup' => static function (TestRunner $t): void {
        $emitter = new ProgressEmitter(minBlocks: 10);
        $version = VersionVector::fromCounters([101 => 1]);
        $media = progressEmitterDownload('wordpress-media', 'wp-content/uploads/2026/hero.jpg', $version, 12, [1, 2], 1, 1);
        $content = progressEmitterDownload('wordpress-content', 'wp-content/themes/news/patterns/home.php', $version, 11, [3], 1, 1);

        $emitter->temporaryIndexSubscribe('peer-a', ['wordpress-media', 'wordpress-content']);
        $emitter->register($media);
        $emitter->register($content);
        $emitter->computeProgressUpdates();

        $emitter->deregister('wordpress-media', 'wp-content/uploads/2026/hero.jpg');
        $t->same([
            'peer-a|wordpress-media' => [
                ['wp-content/uploads/2026/hero.jpg', FileDownloadProgressUpdate::TYPE_FORGET, [], [101 => 1]],
            ],
        ], progressEmitterSummary($emitter->computeProgressUpdates()));
        $t->same([], $emitter->computeProgressUpdates());

        $emitter->register($media);
        $emitter->computeProgressUpdates();
        $emitter->temporaryIndexSubscribe('peer-a', ['wordpress-media']);
        $t->same([], $emitter->computeProgressUpdates());

        $emitter->temporaryIndexSubscribe('peer-a', ['wordpress-content']);
        $t->same([
            'peer-a|wordpress-content' => [
                ['wp-content/themes/news/patterns/home.php', FileDownloadProgressUpdate::TYPE_APPEND, [3], [101 => 1]],
            ],
        ], progressEmitterSummary($emitter->computeProgressUpdates()));

        $emitter->temporaryIndexUnsubscribe('peer-a');
        $t->same([], $emitter->computeProgressUpdates());
        $t->same([], $emitter->sentStateDevices());
    },
    'maps upstream disable cleanup before clearing state' => static function (TestRunner $t): void {
        $emitter = new ProgressEmitter(minBlocks: 10);
        $version = VersionVector::fromCounters([101 => 1]);
        $download = progressEmitterDownload('wordpress-media', 'wp-content/uploads/2026/hero.jpg', $version, 12, [1], 1, 1);

        $emitter->temporaryIndexSubscribe('peer-a', ['wordpress-media']);
        $emitter->register($download);
        $emitter->computeProgressUpdates();

        $t->same([
            'peer-a|wordpress-media' => [
                ['wp-content/uploads/2026/hero.jpg', FileDownloadProgressUpdate::TYPE_FORGET, [], [101 => 1]],
            ],
        ], progressEmitterSummary($emitter->configure(progressUpdateIntervalSeconds: 0, tempIndexMinBlocks: 10)));
        $t->true($emitter->isDisabled());
        $t->same(0, $emitter->registeredCount());

        $emitter->temporaryIndexSubscribe('peer-a', ['wordpress-media']);
        $emitter->register($download);
        $t->same(0, $emitter->registeredCount());
        $t->same([], $emitter->computeProgressUpdates());
    },
    'maps upstream puller progress byte estimation and events' => static function (TestRunner $t): void {
        $version = VersionVector::fromCounters([101 => 1]);
        $size = (2 * BlockList::MIN_BLOCK_SIZE) + 7;
        $file = progressEmitterFile('wp-content/uploads/2026/hero.jpg', $version, 3, size: $size);
        $progress = PullerProgress::fromCounters(
            file: $file,
            reused: 1,
            copyTotal: 1,
            pullTotal: 1,
            copyNeeded: 0,
            pullNeeded: 1,
            copiedFromOrigin: 1,
        );

        $t->same(262151, $progress->bytesTotal);
        $t->same(174768, $progress->bytesDone);
        $t->same([
            'total' => 3,
            'reused' => 1,
            'copiedFromOrigin' => 1,
            'copiedFromOriginShifted' => 0,
            'copiedFromElsewhere' => 0,
            'pulled' => 0,
            'pulling' => 1,
            'bytesDone' => 174768,
            'bytesTotal' => 262151,
        ], $progress->toArray());

        $emitter = new ProgressEmitter(minBlocks: 10);
        $emitter->register(new ActiveDownload('wordpress-media', $file, [0, 1], availableUpdated: 1, created: 1), $progress);
        $event = $emitter->downloadProgressEvent();

        $t->same($progress->toArray(), $event['wordpress-media']['wp-content/uploads/2026/hero.jpg']->toArray());
        $t->same(174768, $emitter->bytesCompleted('wordpress-media'));

        $emitter->deregister('wordpress-media', 'wp-content/uploads/2026/hero.jpg');
        $t->same([], $emitter->downloadProgressEvent());
        $t->same(0, $emitter->bytesCompleted('wordpress-media'));
    },
    'rejects malformed progress emitter inputs' => static function (TestRunner $t): void {
        $version = VersionVector::fromCounters([101 => 1]);
        $file = progressEmitterFile('wp-content/uploads/2026/hero.jpg', $version, 3);
        $emitter = new ProgressEmitter();

        $t->throws(InvalidArgumentException::class, static fn () => new ProgressEmitter(minBlocks: -1));
        $t->throws(InvalidArgumentException::class, static fn () => PullerProgress::fromAvailable($file, -1));
        $t->throws(InvalidArgumentException::class, static fn () => PullerProgress::fromCounters($file, copyTotal: 1, copyNeeded: 2));
        $t->throws(InvalidArgumentException::class, static fn () => $emitter->temporaryIndexSubscribe('peer-a', [new stdClass()]));
        $t->throws(InvalidArgumentException::class, static fn () => $emitter->configure(0, -1));
    },
];

/**
 * @return list<Block>
 */
function progressEmitterBlocks(int $count): array
{
    $blocks = [];
    for ($i = 0; $i < $count; $i++) {
        $blocks[] = new Block($i * BlockList::MIN_BLOCK_SIZE, BlockList::MIN_BLOCK_SIZE, hash('sha256', 'progress-emitter-block-' . $i));
    }

    return $blocks;
}

function progressEmitterFile(string $name, VersionVector $version, int $blocks, int $type = FileInfo::TYPE_FILE, ?int $size = null): FileInfo
{
    return new FileInfo(
        name: $name,
        version: $version,
        size: $size ?? ($blocks * BlockList::MIN_BLOCK_SIZE),
        type: $type,
        rawBlockSize: BlockList::MIN_BLOCK_SIZE,
        blocks: progressEmitterBlocks($blocks),
    );
}

/**
 * @param list<int> $available
 */
function progressEmitterDownload(
    string $folder,
    string $name,
    VersionVector $version,
    int $blocks,
    array $available,
    int $availableUpdated,
    int $created,
    int $type = FileInfo::TYPE_FILE,
): ActiveDownload {
    return new ActiveDownload(
        folder: $folder,
        file: progressEmitterFile($name, $version, $blocks, $type),
        availableBlockIndexes: $available,
        availableUpdated: $availableUpdated,
        created: $created,
    );
}

/**
 * @param list<ProgressUpdateBatch> $batches
 *
 * @return array<string, list<array{0:string, 1:int, 2:list<int>, 3:array<int, int>}>>
 */
function progressEmitterSummary(array $batches): array
{
    $summary = [];
    foreach ($batches as $batch) {
        $key = $batch->deviceId . '|' . $batch->folder;
        foreach ($batch->updates as $update) {
            $summary[$key][] = [
                $update->name,
                $update->updateType,
                $update->blockIndexes,
                $update->version->toArray(),
            ];
        }
    }

    foreach ($summary as &$updates) {
        usort($updates, static fn (array $left, array $right): int => [$left[0], $left[1]] <=> [$right[0], $right[1]]);
    }
    unset($updates);
    ksort($summary, SORT_STRING);

    return $summary;
}
