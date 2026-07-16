<?php

declare(strict_types=1);

use PortLibs\Syncthing\ActiveDownload;
use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\ProgressEmitter;
use PortLibs\Syncthing\PullDbUpdater;
use PortLibs\Syncthing\PullFinisher;
use PortLibs\Syncthing\PullJobQueue;
use PortLibs\Syncthing\PullTemporaryFile;
use PortLibs\Syncthing\VersionVector;

return [
    'finisherRoutine promotes successful pulls completes queue and emits item finished' => static function (TestRunner $t): void {
        $root = syncthing_finisher_root();
        try {
            $blockSize = BlockList::MIN_BLOCK_SIZE;
            $bytes = str_repeat('A', $blockSize)
                . str_repeat('B', $blockSize)
                . str_repeat('C', $blockSize);
            $file = syncthing_finisher_file('wp-content/uploads/2026/finished-hero.jpg', $bytes, sequence: 71);
            $state = new PullTemporaryFile($file, $root);
            $state->writeBlock($file->blocks[0], substr($bytes, 0, $blockSize), source: 'reusedTemp');
            $state->writeBlock($file->blocks[1], substr($bytes, $blockSize, $blockSize), source: 'copiedFromOrigin');
            $state->writeBlock($file->blocks[2], substr($bytes, 2 * $blockSize), source: 'pulled');

            $queue = syncthing_finisher_started_queue($file->name);
            $emitter = new ProgressEmitter();
            $emitter->register(new ActiveDownload('wordpress-media', $file, [0, 1], availableUpdated: 1, created: 1));
            $updater = new PullDbUpdater(disableFsync: true);
            $callbackEvents = [];
            $finisher = new PullFinisher(
                $queue,
                $emitter,
                $updater,
                folderId: 'wordpress-media',
                itemFinished: static function (array $event) use (&$callbackEvents): void {
                    $callbackEvents[] = $event;
                },
            );

            $result = $finisher->finish($state, reused: 1, copyTotal: 1, pullTotal: 1, copyOrigin: 1);
            $t->true($result->handled);
            $t->true($result->finalization->finalized);
            $t->same(null, $result->pullError);
            $t->same(0, $queue->progressCount());
            $t->same(0, $queue->queuedCount());
            $t->same(0, $emitter->registeredCount());
            $t->same([
                'total' => 3,
                'reused' => 1,
                'pulled' => 1,
                'copyOrigin' => 1,
                'copyElsewhere' => 0,
            ], $finisher->blockStats());
            $t->same([
                [
                    'folder' => 'wordpress-media',
                    'item' => $file->name,
                    'error' => null,
                    'type' => 'file',
                    'action' => 'update',
                ],
            ], $finisher->itemFinishedEvents());
            $t->same($finisher->itemFinishedEvents(), $callbackEvents);
            $t->same([], $finisher->pullErrors());
            $t->same(1, $updater->close());
            $t->same($file->name, $updater->updateBatches()[0][0]->name);
            $t->same(0, $updater->updateBatches()[0][0]->sequence);
            $t->same($bytes, (string) file_get_contents($state->finalPath()));
        } finally {
            syncthing_finisher_rm($root);
        }
    },
    'finisherRoutine records failed final close as temp pull error and is idempotent' => static function (TestRunner $t): void {
        $root = syncthing_finisher_root();
        try {
            $bytes = str_repeat('failed media block ', 8000);
            $file = syncthing_finisher_file('wp-content/uploads/2026/failed-hero.jpg', $bytes, sequence: 72);
            $state = new PullTemporaryFile($file, $root);
            $state->fail('peer response hash mismatch');

            $queue = syncthing_finisher_started_queue($file->name);
            $emitter = new ProgressEmitter();
            $emitter->register(new ActiveDownload('wordpress-media', $file, [], availableUpdated: 1, created: 1));
            $updater = new PullDbUpdater(disableFsync: true);
            $finisher = new PullFinisher($queue, $emitter, $updater, folderId: 'wordpress-media');

            $result = $finisher->finish($state);
            $again = $finisher->finish($state);

            $t->true($result->handled);
            $t->true(!$result->finalization->finalized);
            $t->same('peer response hash mismatch', $result->itemFinishedEvent['error'] ?? null);
            $t->same('finishing: peer response hash mismatch', $result->pullError);
            $t->true(!$again->handled);
            $t->same(1, count($finisher->itemFinishedEvents()));
            $t->same([
                ['path' => $file->name, 'error' => 'finishing: peer response hash mismatch'],
            ], $finisher->pullErrors());
            $t->same(0, $queue->progressCount());
            $t->same(0, $emitter->registeredCount());
            $t->same(0, $updater->close());
            $t->same([
                'total' => 0,
                'reused' => 0,
                'pulled' => 0,
                'copyOrigin' => 0,
                'copyElsewhere' => 0,
            ], $finisher->blockStats());
            $t->true(file_exists($state->tempPath()));
            $t->true(!file_exists($state->finalPath()));
        } finally {
            syncthing_finisher_rm($root);
        }
    },
    'finisherRoutine ignores not-ready states without lifecycle side effects' => static function (TestRunner $t): void {
        $root = syncthing_finisher_root();
        try {
            $blockSize = BlockList::MIN_BLOCK_SIZE;
            $bytes = str_repeat('partial-a', intdiv($blockSize, 9))
                . str_repeat('partial-b', intdiv($blockSize, 9));
            $file = syncthing_finisher_file('wp-content/uploads/2026/partial-hero.jpg', $bytes, sequence: 73);
            $state = new PullTemporaryFile($file, $root);
            $state->writeBlock($file->blocks[0], substr($bytes, 0, $blockSize), source: 'copiedFromOrigin');

            $queue = syncthing_finisher_started_queue($file->name);
            $emitter = new ProgressEmitter();
            $emitter->register(new ActiveDownload('wordpress-media', $file, [0], availableUpdated: 1, created: 1));
            $finisher = new PullFinisher($queue, $emitter, folderId: 'wordpress-media');

            $result = $finisher->finish($state, copyTotal: 1, copyOrigin: 1);

            $t->true(!$result->handled);
            $t->true(!$result->finalization->closed);
            $t->same(1, $queue->progressCount());
            $t->same(1, $emitter->registeredCount());
            $t->same([], $finisher->itemFinishedEvents());
            $t->same([], $finisher->pullErrors());
            $t->same([
                'total' => 0,
                'reused' => 0,
                'pulled' => 0,
                'copyOrigin' => 0,
                'copyElsewhere' => 0,
            ], $finisher->blockStats());
        } finally {
            syncthing_finisher_rm($root);
        }
    },
    'finisherRoutine leaves receive-encrypted progress emitter registrations alone' => static function (TestRunner $t): void {
        $root = syncthing_finisher_root();
        try {
            $bytes = str_repeat('encrypted folder payload ', 7000);
            $file = syncthing_finisher_file('wp-content/private/2026/export.bin', $bytes, sequence: 74);
            $state = new PullTemporaryFile($file, $root);
            foreach ($file->blocks as $block) {
                $state->writeBlock(
                    $block,
                    substr($bytes, $block->offset, $block->size),
                    source: 'receiveEncryptedPull',
                );
            }

            $queue = syncthing_finisher_started_queue($file->name);
            $emitter = new ProgressEmitter();
            $emitter->register(new ActiveDownload('wordpress-private', $file, [0], availableUpdated: 1, created: 1));
            $finisher = new PullFinisher(
                $queue,
                $emitter,
                folderId: 'wordpress-private',
                receiveEncryptedFolder: true,
            );

            $result = $finisher->finish($state, pullTotal: 1);

            $t->true($result->handled);
            $t->true($result->finalization->finalized);
            $t->same(0, $queue->progressCount());
            $t->same(1, $emitter->registeredCount());
            $t->same(null, $result->itemFinishedEvent['error'] ?? null);
            $t->same([
                'total' => 1,
                'reused' => 0,
                'pulled' => 1,
                'copyOrigin' => 0,
                'copyElsewhere' => 0,
            ], $finisher->blockStats());
            $t->throws(InvalidArgumentException::class, static fn () => $finisher->finish($state, copyTotal: 1, copyOrigin: 2));
        } finally {
            syncthing_finisher_rm($root);
        }
    },
];

function syncthing_finisher_file(string $name, string $bytes, int $sequence): FileInfo
{
    $blockList = new BlockList();
    $blocks = $blockList->fromBytes($bytes, BlockList::MIN_BLOCK_SIZE);

    return new FileInfo(
        name: $name,
        modifiedS: 1_700_003_000 + $sequence,
        version: VersionVector::fromCounters([202 => $sequence]),
        size: strlen($bytes),
        rawBlockSize: BlockList::MIN_BLOCK_SIZE,
        permissions: 0644,
        sequence: $sequence,
        blocks: $blocks,
        modifiedBy: 202,
    );
}

function syncthing_finisher_started_queue(string $name): PullJobQueue
{
    $queue = new PullJobQueue();
    $queue->push($name);
    $queue->pop();

    return $queue;
}

function syncthing_finisher_root(): string
{
    $root = sys_get_temp_dir() . '/syncthing-finisher-' . bin2hex(random_bytes(6));
    if (!mkdir($root, 0777, true) && !is_dir($root)) {
        throw new RuntimeException('Failed to create temporary finisher root');
    }

    return $root;
}

function syncthing_finisher_rm(string $path): void
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
