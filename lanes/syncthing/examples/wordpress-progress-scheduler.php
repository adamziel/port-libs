<?php

declare(strict_types=1);

use PortLibs\Syncthing\ActiveDownload;
use PortLibs\Syncthing\BepWire;
use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\DownloadProgress;
use PortLibs\Syncthing\FileDownloadProgressUpdate;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\ProgressEmitter;
use PortLibs\Syncthing\ProgressEmitterScheduler;
use PortLibs\Syncthing\VersionVector;
use PortLibs\Syncthing\WireProgressConnection;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$frames = [];
$connection = new WireProgressConnection(
    'playground-importer',
    static function (string $deviceId, string $frame, DownloadProgress $progress) use (&$frames): void {
        $frames[] = [
            'device' => $deviceId,
            'frame' => $frame,
            'progress' => $progress,
        ];
    },
    directorySeparator: '\\',
);

$emitter = new ProgressEmitter(minBlocks: 10);
$scheduler = new ProgressEmitterScheduler($emitter, intervalSeconds: 30);
$scheduler->subscribe($connection, ['wordpress-media']);

$version = VersionVector::fromCounters([101 => 1700001100]);
$file = new FileInfo(
    name: 'wp-content\\uploads\\2026\\hero.jpg',
    version: $version,
    size: 12 * BlockList::MIN_BLOCK_SIZE,
    rawBlockSize: BlockList::MIN_BLOCK_SIZE,
    blocks: wordpressProgressSchedulerBlocks(12),
);

$idle = $scheduler->tick();

$emitter->register(new ActiveDownload('wordpress-media', $file, [0, 1], availableUpdated: 1, created: 1));
$initial = $scheduler->tick();

$emitter->register(new ActiveDownload('wordpress-media', $file, [0, 1, 5], availableUpdated: 2, created: 1));
$delta = $scheduler->tick();

echo json_encode([
    'idleChanged' => $idle->changed,
    'initialSent' => $initial->sentCount(),
    'deltaSent' => $delta->sentCount(),
    'nextIntervalSeconds' => $delta->nextIntervalSeconds,
    'messages' => array_map(static function (array $entry): array {
        $decoded = BepWire::decodeDownloadProgressMessage($entry['frame']);

        return [
            'device' => $entry['device'],
            'messageType' => BepWire::decodeMessageFrame($entry['frame'])['type'],
            'folder' => $decoded->folder,
            'updates' => array_map(
                static fn (FileDownloadProgressUpdate $update): array => [
                    'name' => $update->name,
                    'type' => $update->isAppend() ? 'append' : 'forget',
                    'blocks' => $update->blockIndexes,
                ],
                $decoded->updates,
            ),
        ];
    }, $frames),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

/**
 * @return list<Block>
 */
function wordpressProgressSchedulerBlocks(int $count): array
{
    $blocks = [];
    for ($i = 0; $i < $count; $i++) {
        $blocks[] = new Block($i * BlockList::MIN_BLOCK_SIZE, BlockList::MIN_BLOCK_SIZE, hash('sha256', 'wordpress-progress-scheduler-' . $i));
    }

    return $blocks;
}
