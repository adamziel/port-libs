<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\FileInfoScanResult;
use PortLibs\Syncthing\FileInfoScanner;
use PortLibs\Syncthing\FolderScanCheckpoint;
use PortLibs\Syncthing\FolderScanEventCollector;
use PortLibs\Syncthing\FolderScanProgress;

return [
    'checkpoint merges cancelled and resumed scanner results for CurrentFiler reuse' => static function (TestRunner $t): void {
        $root = syncthing_folder_scan_checkpoint_root();
        try {
            $dir = 'wp-content/uploads/2026/05';
            syncthing_folder_scan_checkpoint_write($root, $dir . '/hero.jpg', 'abcdefgh');
            syncthing_folder_scan_checkpoint_write($root, $dir . '/thumb.jpg', '12345');

            $scanner = new FileInfoScanner($root);
            $cancelAfterFirstHash = false;
            $first = $scanner->walkWithCheckpoint(
                [$dir],
                hashBlocks: true,
                blockSize: 4,
                progressLogger: static function (FolderScanProgress $progress) use (&$cancelAfterFirstHash): void {
                    $cancelAfterFirstHash = true;
                },
                folder: 'wordpress-media',
                shouldCancel: static function (?string $path) use (&$cancelAfterFirstHash): bool {
                    return $cancelAfterFirstHash && $path !== null;
                },
                eventCollector: new FolderScanEventCollector('wordpress-media'),
            );
            $checkpoint = FolderScanCheckpoint::fromResult('wordpress-media', $first);

            $t->same('cancelled', $checkpoint->state());
            $t->true(!$checkpoint->isComplete());
            $t->same(1, $checkpoint->attempts());
            $t->same($dir . '/thumb.jpg', $checkpoint->cancelledAt());
            $t->same([$dir], $checkpoint->resumeSubs());
            $t->same([$dir, $dir . '/hero.jpg'], $checkpoint->completedPaths());
            $t->same($dir . '/hero.jpg', $checkpoint->currentFile($dir . '/hero.jpg')?->name);

            $resumed = $scanner->walkWithCheckpoint(
                $checkpoint->resumeSubs(),
                hashBlocks: true,
                blockSize: 4,
                currentFiles: $checkpoint->resumeCurrentFiles(),
                folder: 'wordpress-media',
                eventCollector: new FolderScanEventCollector('wordpress-media'),
            );
            $merged = $checkpoint->withResult($resumed);
            $status = $merged->toRestStatus();

            $t->same('complete', $merged->state());
            $t->true($merged->isComplete());
            $t->same(2, $merged->attempts());
            $t->same(null, $merged->cancelledAt());
            $t->same([], $merged->resumeSubs());
            $t->same([$dir, $dir . '/hero.jpg', $dir . '/thumb.jpg'], $merged->completedPaths());
            $t->same(hash('sha256', '1234'), $merged->currentFile($dir . '/thumb.jpg')?->blocks[0]->hashHex);
            $t->same('complete', $status['state']);
            $t->same(3, $status['currentFileCount']);
            $t->same(0, $status['scanErrorCount']);
            $t->same(0, $status['failureCount']);
            $t->same(['folder' => 'wordpress-media', 'current' => 5, 'total' => 6, 'rate' => 0.0], $status['progress']);
            $t->same(2, $status['eventCount']);
        } finally {
            syncthing_folder_scan_checkpoint_rm($root);
        }
    },
    'checkpoint replaces rescanned paths without duplicating completed status rows' => static function (TestRunner $t): void {
        $name = 'wp-content/uploads/2026/05/hero.jpg';
        $old = new FileInfo(
            name: $name,
            size: 4,
            blocksHash: hash('sha256', 'old'),
        );
        $new = new FileInfo(
            name: $name,
            size: 8,
            blocksHash: hash('sha256', 'new'),
        );
        $thumb = new FileInfo(
            name: 'wp-content/uploads/2026/05/thumb.jpg',
            size: 5,
            blocksHash: hash('sha256', 'thumb'),
        );

        $checkpoint = FolderScanCheckpoint::fromResult(
            'wordpress-media',
            new FileInfoScanResult([$old], cancelled: true, cancelledAt: $name, resumeSubs: ['wp-content/uploads/2026/05']),
        );
        $merged = $checkpoint->withResult(new FileInfoScanResult([$new, $thumb]));

        $t->same('complete', $merged->state());
        $t->same([$name, $thumb->name], $merged->completedPaths());
        $t->same(8, $merged->currentFile($name)?->size);
        $t->same(hash('sha256', 'new'), $merged->currentFile($name)?->blocksHash);
        $t->same([], $merged->resumeSubs());
    },
    'checkpoint status exposes path errors and upstream Failure events for REST payloads' => static function (TestRunner $t): void {
        $collector = new FolderScanEventCollector('wordpress-media');
        $collector->recordScanError('wp-content/uploads/private-cache', 'permission denied', 'scan');
        $collector->recordFailure(FileInfoScanner::WALK_FAILURE_EVENT, [
            'description' => FileInfoScanner::WALK_FAILURE_EVENT_DESCRIPTION,
            'sub' => 'wp-content/uploads',
            'error' => 'stale filesystem handle',
        ]);

        $checkpoint = FolderScanCheckpoint::fromResult(
            'wordpress-media',
            new FileInfoScanResult([], eventCollector: $collector),
        );
        $status = $checkpoint->toRestStatus();

        $t->same('failed', $checkpoint->state());
        $t->true(!$checkpoint->isComplete());
        $t->same(1, $status['attempts']);
        $t->same(1, $status['scanErrorCount']);
        $t->same(1, $status['failureCount']);
        $t->same($collector->scanErrors(), $status['scanErrors']);
        $t->same($collector->failureEvents(), $status['failureEvents']);
        $t->throws(InvalidArgumentException::class, static fn () => new FolderScanCheckpoint(''));
    },
];

function syncthing_folder_scan_checkpoint_root(): string
{
    $root = sys_get_temp_dir() . '/syncthing-folder-scan-checkpoint-' . bin2hex(random_bytes(6));
    if (!mkdir($root, 0777, true) && !is_dir($root)) {
        throw new RuntimeException('Failed to create temporary folder scan checkpoint root');
    }

    return $root;
}

function syncthing_folder_scan_checkpoint_write(string $root, string $name, string $bytes): string
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create folder scan checkpoint test directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write folder scan checkpoint test file');
    }

    return $path;
}

function syncthing_folder_scan_checkpoint_rm(string $path): void
{
    if (!file_exists($path) && !is_link($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        syncthing_folder_scan_checkpoint_rm($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}
