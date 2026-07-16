<?php

declare(strict_types=1);

use PortLibs\Syncthing\ActiveDownload;
use PortLibs\Syncthing\BepWire;
use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\Device;
use PortLibs\Syncthing\DownloadProgress;
use PortLibs\Syncthing\FileDownloadProgressUpdate;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\ProgressConnection;
use PortLibs\Syncthing\ProgressEmitter;
use PortLibs\Syncthing\ProgressEmitterScheduler;
use PortLibs\Syncthing\VersionVector;
use PortLibs\Syncthing\WireProgressConnection;

return [
    'maps upstream timer gate and wire connection sends' => static function (TestRunner $t): void {
        $emitter = new ProgressEmitter(minBlocks: 10);
        $frames = [];
        $connection = new WireProgressConnection(
            'playground-importer',
            static function (string $deviceId, string $frame, DownloadProgress $progress) use (&$frames): void {
                $frames[] = [$deviceId, $frame, $progress];
            },
            Device::COMPRESSION_NEVER,
            '\\',
        );
        $scheduler = new ProgressEmitterScheduler($emitter, intervalSeconds: 60);
        $scheduler->subscribe($connection, ['wordpress-media']);

        $idle = $scheduler->tick();
        $t->true(!$idle->changed);
        $t->same(null, $idle->nextIntervalSeconds);
        $t->same(0, $idle->sentCount());

        $version = VersionVector::fromCounters([101 => 1]);
        $emitter->register(progressSchedulerDownload(
            'wordpress-media',
            'wp-content\\uploads\\2026\\hero.jpg',
            $version,
            12,
            [0],
            availableUpdated: 10,
            created: 1,
        ));

        $first = $scheduler->tick();
        $t->true($first->changed);
        $t->same(60, $first->nextIntervalSeconds);
        $t->same(1, $first->sentCount());
        $t->same(['wordpress-media'], array_keys($first->event));
        $t->same(1, count($frames));

        $decoded = BepWire::decodeDownloadProgressMessage($frames[0][1]);
        $t->same('playground-importer', $frames[0][0]);
        $t->same('wordpress-media', $decoded->folder);
        $t->same('wp-content/uploads/2026/hero.jpg', $decoded->updates[0]->name);
        $t->same([0], $decoded->updates[0]->blockIndexes);

        $unchanged = $scheduler->tick();
        $t->true(!$unchanged->changed);
        $t->same(1, count($frames));

        $emitter->register(progressSchedulerDownload(
            'wordpress-media',
            'wp-content\\uploads\\2026\\hero.jpg',
            $version,
            12,
            [0, 2],
            availableUpdated: 10,
            created: 1,
        ));
        $sameTimestamp = $scheduler->tick();
        $t->true(!$sameTimestamp->changed);
        $t->same(1, count($frames));

        $emitter->register(progressSchedulerDownload(
            'wordpress-media',
            'wp-content\\uploads\\2026\\hero.jpg',
            $version,
            12,
            [0, 2],
            availableUpdated: 11,
            created: 1,
        ));

        $delta = $scheduler->tick();
        $t->true($delta->changed);
        $t->same(2, count($frames));
        $decodedDelta = BepWire::decodeDownloadProgressMessage($frames[1][1]);
        $t->same([2], $decodedDelta->updates[0]->blockIndexes);
    },
    'maps upstream deregister cleanup and idle timer stop' => static function (TestRunner $t): void {
        $emitter = new ProgressEmitter(minBlocks: 10);
        $frames = [];
        $scheduler = new ProgressEmitterScheduler($emitter, intervalSeconds: 30);
        $scheduler->subscribe(new WireProgressConnection(
            'peer-a',
            static function (string $deviceId, string $frame) use (&$frames): void {
                $frames[] = [$deviceId, $frame];
            },
        ), ['wordpress-media']);

        $version = VersionVector::fromCounters([101 => 1]);
        $emitter->register(progressSchedulerDownload('wordpress-media', 'wp-content/uploads/2026/hero.jpg', $version, 12, [1], 1, 1));
        $scheduler->tick();

        $emitter->deregister('wordpress-media', 'wp-content/uploads/2026/hero.jpg');
        $cleanup = $scheduler->tick();
        $t->true($cleanup->changed);
        $t->same(null, $cleanup->nextIntervalSeconds);
        $t->same(2, count($frames));
        $decoded = BepWire::decodeDownloadProgressMessage($frames[1][1]);
        $t->same(FileDownloadProgressUpdate::TYPE_FORGET, $decoded->updates[0]->updateType);
        $t->same([], $decoded->updates[0]->blockIndexes);

        $idle = $scheduler->tick();
        $t->true(!$idle->changed);
        $t->same(2, count($frames));
    },
    'records send failures after state advances without retrying unchanged updates' => static function (TestRunner $t): void {
        $emitter = new ProgressEmitter(minBlocks: 10);
        $scheduler = new ProgressEmitterScheduler($emitter);
        $frames = [];
        $failing = new class implements ProgressConnection {
            public int $attempts = 0;

            public function deviceId(): string
            {
                return 'blocked-peer';
            }

            public function sendDownloadProgress(DownloadProgress $progress): void
            {
                $this->attempts++;
                throw new RuntimeException('blocked send');
            }
        };

        $scheduler->subscribe($failing, ['wordpress-media']);
        $scheduler->subscribe(new WireProgressConnection(
            'healthy-peer',
            static function (string $deviceId, string $frame) use (&$frames): void {
                $frames[] = [$deviceId, $frame];
            },
        ), ['wordpress-media']);

        $version = VersionVector::fromCounters([101 => 1]);
        $emitter->register(progressSchedulerDownload('wordpress-media', 'wp-content/uploads/2026/hero.jpg', $version, 12, [1], 1, 1));

        $first = $scheduler->tick();
        $t->true($first->changed);
        $t->same(2, count($first->batches));
        $t->true($first->failed());
        $t->same('blocked-peer', $first->failures[0]->deviceId);
        $t->same('wordpress-media', $first->failures[0]->folder);
        $t->same(1, $failing->attempts);
        $t->same(0, count($frames));

        $unchanged = $scheduler->tick();
        $t->true(!$unchanged->changed);
        $t->same(1, $failing->attempts);
        $t->same(0, count($frames));
    },
    'rejects malformed progress scheduler inputs' => static function (TestRunner $t): void {
        $emitter = new ProgressEmitter();

        $t->throws(InvalidArgumentException::class, static fn () => new ProgressEmitterScheduler($emitter, intervalSeconds: 0));
        $t->throws(InvalidArgumentException::class, static fn () => new WireProgressConnection('peer', static fn () => null, compressionMode: 99));
        $t->throws(InvalidArgumentException::class, static fn () => new WireProgressConnection('peer', static fn () => null, directorySeparator: ''));
    },
];

/**
 * @return list<Block>
 */
function progressSchedulerBlocks(int $count): array
{
    $blocks = [];
    for ($i = 0; $i < $count; $i++) {
        $blocks[] = new Block($i * BlockList::MIN_BLOCK_SIZE, BlockList::MIN_BLOCK_SIZE, hash('sha256', 'progress-scheduler-block-' . $i));
    }

    return $blocks;
}

function progressSchedulerFile(string $name, VersionVector $version, int $blocks): FileInfo
{
    return new FileInfo(
        name: $name,
        version: $version,
        size: $blocks * BlockList::MIN_BLOCK_SIZE,
        rawBlockSize: BlockList::MIN_BLOCK_SIZE,
        blocks: progressSchedulerBlocks($blocks),
    );
}

/**
 * @param list<int> $available
 */
function progressSchedulerDownload(
    string $folder,
    string $name,
    VersionVector $version,
    int $blocks,
    array $available,
    int $availableUpdated,
    int $created,
): ActiveDownload {
    return new ActiveDownload(
        folder: $folder,
        file: progressSchedulerFile($name, $version, $blocks),
        availableBlockIndexes: $available,
        availableUpdated: $availableUpdated,
        created: $created,
    );
}
