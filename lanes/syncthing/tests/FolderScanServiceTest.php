<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\FileInfoScanResult;
use PortLibs\Syncthing\FileInfoScanner;
use PortLibs\Syncthing\FolderScanCheckpoint;
use PortLibs\Syncthing\FolderScanCheckpointConflictException;
use PortLibs\Syncthing\FolderScanCheckpointStore;
use PortLibs\Syncthing\FolderScanProgress;
use PortLibs\Syncthing\FolderScanService;

return [
    'scan service persists cancelled checkpoint and resumes with CurrentFiler files' => static function (TestRunner $t): void {
        $root = syncthing_folder_scan_service_root();
        try {
            $dir = 'wp-content/uploads/2026/05';
            syncthing_folder_scan_service_write($root, $dir . '/hero.jpg', 'abcdefgh');
            syncthing_folder_scan_service_write($root, $dir . '/thumb.jpg', '12345');

            $store = new FolderScanCheckpointStore();
            $service = new FolderScanService(
                'wordpress-media',
                new FileInfoScanner($root),
                $store,
                ttlSeconds: 60,
            );
            $cancelAfterFirstHash = false;
            $first = $service->scan(
                [$dir],
                hashBlocks: true,
                blockSize: 4,
                progressLogger: static function (FolderScanProgress $progress) use (&$cancelAfterFirstHash): void {
                    $cancelAfterFirstHash = true;
                },
                shouldCancel: static function (?string $path) use (&$cancelAfterFirstHash): bool {
                    return $cancelAfterFirstHash && $path !== null;
                },
                now: 1000,
            );

            $t->same(1, $first->revision);
            $t->same(1060, $first->expiresAt);
            $t->same('cancelled', $first->checkpoint->state());
            $t->same($dir . '/thumb.jpg', $first->checkpoint->cancelledAt());
            $t->same([$dir], $first->checkpoint->resumeSubs());
            $t->same([$dir, $dir . '/hero.jpg'], $first->checkpoint->completedPaths());

            $resumed = $service->scan(hashBlocks: true, blockSize: 4, now: 1010);
            $status = $resumed->toRestStatus();

            $t->same(2, $resumed->revision);
            $t->same(1070, $resumed->expiresAt);
            $t->same('complete', $resumed->checkpoint->state());
            $t->same(2, $resumed->checkpoint->attempts());
            $t->same([], $resumed->checkpoint->resumeSubs());
            $t->same([$dir, $dir . '/hero.jpg', $dir . '/thumb.jpg'], $resumed->checkpoint->completedPaths());
            $t->same(hash('sha256', '1234'), $resumed->checkpoint->currentFile($dir . '/thumb.jpg')?->blocks[0]->hashHex);
            $t->same(2, $status['revision']);
            $t->same(1070, $status['expiresAt']);
            $t->same(3, $status['currentFileCount']);
            $t->same(['folder' => 'wordpress-media', 'current' => 5, 'total' => 6, 'rate' => 0.0], $status['progress']);
            $t->same(2, $status['eventCount']);
        } finally {
            syncthing_folder_scan_service_rm($root);
        }
    },
    'checkpoint store rejects stale revisions and supports conflict-safe deletes' => static function (TestRunner $t): void {
        $store = new FolderScanCheckpointStore();
        $checkpoint = FolderScanCheckpoint::fromResult(
            'wordpress-media',
            new FileInfoScanResult([
                new FileInfo(name: 'wp-content/uploads/2026/05/hero.jpg', size: 4),
            ]),
        );

        $snapshot = $store->save($checkpoint, expectedRevision: 0, now: 20, ttlSeconds: 10);
        $t->same(1, $snapshot->revision);

        $t->throws(
            FolderScanCheckpointConflictException::class,
            static fn () => $store->save($checkpoint, expectedRevision: 0, now: 21, ttlSeconds: 10),
        );
        $t->throws(
            FolderScanCheckpointConflictException::class,
            static fn () => $store->delete('wordpress-media', expectedRevision: 2, now: 21),
        );
        $t->true($store->delete('wordpress-media', expectedRevision: 1, now: 21));
        $t->true(!$store->delete('wordpress-media', expectedRevision: 0, now: 21));
    },
    'checkpoint store expires stale folder snapshots before loading or listing' => static function (TestRunner $t): void {
        $store = new FolderScanCheckpointStore();
        $store->save(new FolderScanCheckpoint('wordpress-media'), expectedRevision: 0, now: 100, ttlSeconds: 10);
        $store->save(new FolderScanCheckpoint('wordpress-content'), expectedRevision: 0, now: 100, ttlSeconds: 30);

        $t->true($store->load('wordpress-media', 109) !== null);
        $t->same(null, $store->load('wordpress-media', 110));
        $t->same(1, count($store->snapshots(110)));
        $t->same(1, $store->forgetExpired(130));
        $t->same([], $store->snapshots(130));
        $t->throws(InvalidArgumentException::class, static fn () => $store->save(new FolderScanCheckpoint('x'), now: -1));
        $t->throws(InvalidArgumentException::class, static fn () => $store->save(new FolderScanCheckpoint('x'), ttlSeconds: -1));
    },
    'scan service detects concurrent checkpoint updates before publishing scan result' => static function (TestRunner $t): void {
        $root = syncthing_folder_scan_service_root();
        try {
            $dir = 'wp-content/uploads/2026/05';
            syncthing_folder_scan_service_write($root, $dir . '/hero.jpg', 'abcdefgh');

            $store = new FolderScanCheckpointStore();
            $service = new FolderScanService('wordpress-media', new FileInfoScanner($root), $store, ttlSeconds: 60);
            $injected = false;

            $t->throws(
                FolderScanCheckpointConflictException::class,
                static function () use ($service, $store, $dir, &$injected): void {
                    $service->scan(
                        [$dir],
                        hashBlocks: true,
                        blockSize: 4,
                        progressLogger: static function () use ($store, &$injected): void {
                            if ($injected) {
                                return;
                            }
                            $injected = true;
                            $store->save(new FolderScanCheckpoint('wordpress-media'), expectedRevision: 0, now: 200, ttlSeconds: 60);
                        },
                        now: 200,
                    );
                },
            );

            $t->same(1, $store->load('wordpress-media', 200)?->revision);
            $t->same('idle', $store->load('wordpress-media', 200)?->checkpoint->state());
        } finally {
            syncthing_folder_scan_service_rm($root);
        }
    },
    'checkpoint store merges scan results when revision matches latest snapshot' => static function (TestRunner $t): void {
        $store = new FolderScanCheckpointStore();
        $dir = new FileInfo(name: 'wp-content/uploads/2026/05', type: FileInfo::TYPE_DIRECTORY);
        $hero = new FileInfo(name: 'wp-content/uploads/2026/05/hero.jpg', size: 8);
        $thumb = new FileInfo(name: 'wp-content/uploads/2026/05/thumb.jpg', size: 5);

        $first = $store->mergeResult(
            'wordpress-media',
            new FileInfoScanResult([$dir, $hero], cancelled: true, cancelledAt: $thumb->name, resumeSubs: ['wp-content/uploads/2026/05']),
            expectedRevision: 0,
            now: 300,
            ttlSeconds: 60,
        );
        $second = $store->mergeResult(
            'wordpress-media',
            new FileInfoScanResult([$thumb]),
            expectedRevision: $first->revision,
            now: 301,
            ttlSeconds: 60,
        );

        $t->same(2, $second->revision);
        $t->same('complete', $second->checkpoint->state());
        $t->same([$dir->name, $hero->name, $thumb->name], $second->checkpoint->completedPaths());
    },
];

function syncthing_folder_scan_service_root(): string
{
    $root = sys_get_temp_dir() . '/syncthing-folder-scan-service-' . bin2hex(random_bytes(6));
    if (!mkdir($root, 0777, true) && !is_dir($root)) {
        throw new RuntimeException('Failed to create temporary folder scan service root');
    }

    return $root;
}

function syncthing_folder_scan_service_write(string $root, string $name, string $bytes): void
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create folder scan service test directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write folder scan service test file');
    }
}

function syncthing_folder_scan_service_rm(string $path): void
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
        syncthing_folder_scan_service_rm($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}
