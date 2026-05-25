<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfoScanner;
use PortLibs\Syncthing\FolderScanCheckpointStore;
use PortLibs\Syncthing\FolderScanScheduler;
use PortLibs\Syncthing\FolderScanService;
use PortLibs\Syncthing\FolderWatchEventAggregator;
use PortLibs\Syncthing\FolderWatchScanScheduler;

return [
    'watch scan scheduler coalesces media events into a delayed subdir checkpoint' => static function (TestRunner $t): void {
        $root = syncthing_folder_watch_scan_root();
        try {
            syncthing_folder_watch_scan_write($root, 'wp-content/uploads/2026/05/hero.jpg', 'abcdefgh');
            syncthing_folder_watch_scan_write($root, 'wp-content/uploads/2026/05/poster.webp', 'ijklmnop');

            $service = new FolderScanService('wordpress-media', new FileInfoScanner($root), new FolderScanCheckpointStore(), ttlSeconds: 60);
            $scheduler = new FolderScanScheduler();
            $scheduler->addFolder('wordpress-media', $service);
            $watch = new FolderWatchScanScheduler($scheduler, notifyDelaySeconds: 10, notifyTimeoutSeconds: 30, maxFilesPerDir: 1);

            $watch->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/hero.jpg', now: 1000);
            $status = $watch->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/poster.webp', now: 1000);

            $t->same(['wp-content/uploads/2026/05'], $status['pendingPaths'] ?? []);
            $t->same(1010, $status['nextScanAt'] ?? null);
            $t->same(false, $watch->watchStatus('wordpress-media', 1009)['due'] ?? null);
            $t->same([], $watch->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 1009)->snapshots());
            $t->same(null, $service->checkpoint(1009));

            $due = $watch->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 1010);
            $snapshot = $due->snapshot('wordpress-media');

            $t->true($due->successful());
            $t->same(1, $snapshot?->revision);
            $t->same(1070, $snapshot?->expiresAt);
            $t->same([
                'wp-content/uploads/2026/05',
                'wp-content/uploads/2026/05/hero.jpg',
                'wp-content/uploads/2026/05/poster.webp',
            ], $snapshot?->checkpoint->completedPaths());
            $t->same([
                'wordpress-media' => [
                    [
                        'eventType' => FolderWatchEventAggregator::EVENT_NON_REMOVE,
                        'paths' => ['wp-content/uploads/2026/05'],
                        'count' => 1,
                    ],
                ],
            ], $watch->lastDispatchedBatches());
            $t->same(null, $watch->watchStatus('wordpress-media', 1010)['nextScanAt'] ?? null);
        } finally {
            syncthing_folder_watch_scan_rm($root);
        }
    },
    'watch scan scheduler ignores Syncthing-owned changes and does not consume paused folder events' => static function (TestRunner $t): void {
        $root = syncthing_folder_watch_scan_root();
        try {
            syncthing_folder_watch_scan_write($root, 'wp-content/uploads/2026/05/hero.jpg', 'abcdefgh');
            $service = new FolderScanService('wordpress-media', new FileInfoScanner($root), new FolderScanCheckpointStore());
            $scheduler = new FolderScanScheduler();
            $scheduler->addFolder('wordpress-media', $service);
            $watch = new FolderWatchScanScheduler($scheduler, notifyDelaySeconds: 10, notifyTimeoutSeconds: 30);

            $watch->markItemStarted('wordpress-media', 'wp-content/uploads/2026/05/hero.jpg');
            $watch->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/hero.jpg', now: 2000);
            $t->same(0, $watch->watchStatus('wordpress-media', 2000)['pendingEventCount'] ?? -1);

            $watch->markItemFinished('wordpress-media', 'wp-content/uploads/2026/05/hero.jpg');
            $watch->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/hero.jpg', now: 2000);
            $scheduler->pauseFolder('wordpress-media');

            $t->same(null, $watch->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/poster.webp', now: 2001));
            $paused = $watch->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 2010);
            $t->same([], $paused->snapshots());
            $t->same(null, $service->checkpoint(2010));
            $t->same(['wp-content/uploads/2026/05/hero.jpg'], $watch->watchStatus('wordpress-media', 2010)['pendingPaths'] ?? []);

            $scheduler->resumeFolder('wordpress-media');
            $due = $watch->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 2010);
            $t->same(1, $due->snapshot('wordpress-media')?->revision);
            $t->same(['wp-content/uploads/2026/05/hero.jpg'], $due->snapshot('wordpress-media')?->checkpoint->completedPaths());
        } finally {
            syncthing_folder_watch_scan_rm($root);
        }
    },
    'watch scan scheduler falls back to a full scan and backs off watcher restarts after errors' => static function (TestRunner $t): void {
        $root = syncthing_folder_watch_scan_root();
        try {
            syncthing_folder_watch_scan_write($root, 'wp-content/uploads/2026/05/hero.jpg', 'abcdefgh');

            $service = new FolderScanService('wordpress-media', new FileInfoScanner($root), new FolderScanCheckpointStore(), ttlSeconds: 60);
            $scheduler = new FolderScanScheduler();
            $scheduler->addFolder('wordpress-media', $service);
            $watch = new FolderWatchScanScheduler(
                $scheduler,
                notifyDelaySeconds: 10,
                notifyTimeoutSeconds: 30,
                watchRestartInitialDelaySeconds: 5,
                watchRestartMaxDelaySeconds: 20,
            );

            $first = $watch->recordWatcherError('wordpress-media', 'inotify queue overflow', hashBlocks: true, blockSize: 4, now: 3000);
            $restart = $watch->watchStatus('wordpress-media', 3001)['watcherRestart'] ?? null;

            $t->true($first->successful());
            $t->same(1, $first->snapshot('wordpress-media')?->revision);
            $t->same([
                'wp-content',
                'wp-content/uploads',
                'wp-content/uploads/2026',
                'wp-content/uploads/2026/05',
                'wp-content/uploads/2026/05/hero.jpg',
            ], $first->snapshot('wordpress-media')?->checkpoint->completedPaths());
            $t->same('inotify queue overflow', $restart['lastError'] ?? null);
            $t->same(1, $restart['restartAttempt'] ?? null);
            $t->same(5, $restart['restartDelaySeconds'] ?? null);
            $t->same(3005, $restart['restartAt'] ?? null);
            $t->same(4, $restart['remainingSeconds'] ?? null);
            $t->same(false, $restart['due'] ?? null);
            $t->same(true, $restart['scanOnWatchError'] ?? null);

            syncthing_folder_watch_scan_write($root, 'wp-content/uploads/2026/05/poster.webp', 'ijklmnop');
            $second = $watch->recordWatcherError(
                'wordpress-media',
                new RuntimeException('watcher closed'),
                scanOnWatchError: false,
                now: 3002,
            );
            $restart = $watch->watchStatus('wordpress-media', 3012)['watcherRestart'] ?? null;

            $t->same([], $second->snapshots());
            $t->same(1, $service->checkpoint(3012)?->revision);
            $t->same('watcher closed', $restart['lastError'] ?? null);
            $t->same(2, $restart['restartAttempt'] ?? null);
            $t->same(10, $restart['restartDelaySeconds'] ?? null);
            $t->same(3012, $restart['restartAt'] ?? null);
            $t->same(0, $restart['remainingSeconds'] ?? null);
            $t->same(true, $restart['due'] ?? null);
            $t->same(false, $restart['scanOnWatchError'] ?? null);

            $t->same(true, $watch->markWatcherRestarted('wordpress-media'));
            $t->same(null, $watch->watchStatus('wordpress-media', 3012));
            $third = $watch->recordWatcherError('wordpress-media', 'watcher restarted then failed', now: 3020);
            $t->same(1, $watch->watchStatus('wordpress-media', 3020)['watcherRestart']['restartAttempt'] ?? null);
            $t->same(2, $third->snapshot('wordpress-media')?->revision);
        } finally {
            syncthing_folder_watch_scan_rm($root);
        }
    },
    'watch scan scheduler cleanup clears restart and in-progress state without consuming pending events' => static function (TestRunner $t): void {
        $root = syncthing_folder_watch_scan_root();
        try {
            syncthing_folder_watch_scan_write($root, 'wp-content/uploads/2026/05/hero.jpg', 'abcdefgh');
            syncthing_folder_watch_scan_write($root, 'wp-content/uploads/2026/05/poster.webp', 'ijklmnop');

            $service = new FolderScanService('wordpress-media', new FileInfoScanner($root), new FolderScanCheckpointStore());
            $scheduler = new FolderScanScheduler();
            $scheduler->addFolder('wordpress-media', $service);
            $watch = new FolderWatchScanScheduler(
                $scheduler,
                notifyDelaySeconds: 10,
                notifyTimeoutSeconds: 30,
                watchRestartInitialDelaySeconds: 5,
                watchRestartMaxDelaySeconds: 20,
            );

            $watch->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/hero.jpg', now: 4000);
            $watch->markItemStarted('wordpress-media', 'wp-content/uploads/2026/05/poster.webp');
            $watch->recordWatcherError('wordpress-media', 'watcher overflow', scanOnWatchError: false, now: 4001);
            $scheduler->pauseFolder('wordpress-media');

            $t->same(true, $watch->stopWatchingFolder('wordpress-media'));
            $status = $watch->watchStatus('wordpress-media', 4010);
            $t->same(['wp-content/uploads/2026/05/hero.jpg'], $status['pendingPaths'] ?? []);
            $t->same([], $status['inProgressPaths'] ?? ['missing']);
            $t->true(array_key_exists('watcherRestart', $status));
            $t->same(null, $status['watcherRestart']);
            $t->same([], $watch->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 4010)->snapshots());
            $t->same(null, $service->checkpoint(4010));

            $scheduler->resumeFolder('wordpress-media');
            $due = $watch->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 4010);
            $t->same(1, $due->snapshot('wordpress-media')?->revision);
            $t->same(['wp-content/uploads/2026/05/hero.jpg'], $due->snapshot('wordpress-media')?->checkpoint->completedPaths());

            $watch->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/poster.webp', now: 4020);
            $t->same(true, $watch->stopWatchingFolder('wordpress-media', discardPendingEvents: true));
            $t->same(null, $watch->watchStatus('wordpress-media', 4030));
            $t->same(false, $watch->stopWatchingFolder('wordpress-media'));
        } finally {
            syncthing_folder_watch_scan_rm($root);
        }
    },
    'watch scan scheduler removal discards pending events and restart state after unshare' => static function (TestRunner $t): void {
        $root = syncthing_folder_watch_scan_root();
        try {
            syncthing_folder_watch_scan_write($root, 'wp-content/uploads/2026/05/hero.jpg', 'abcdefgh');

            $service = new FolderScanService('wordpress-media', new FileInfoScanner($root), new FolderScanCheckpointStore());
            $scheduler = new FolderScanScheduler();
            $scheduler->addFolder('wordpress-media', $service);
            $watch = new FolderWatchScanScheduler(
                $scheduler,
                notifyDelaySeconds: 10,
                notifyTimeoutSeconds: 30,
                watchRestartInitialDelaySeconds: 5,
                watchRestartMaxDelaySeconds: 20,
            );

            $watch->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/hero.jpg', now: 5000);
            $watch->recordWatcherError('wordpress-media', 'watcher closed during unshare', scanOnWatchError: false, now: 5001);
            $t->same(true, $scheduler->removeFolder('wordpress-media'));

            $t->same(true, $watch->removeWatchingFolder('wordpress-media'));
            $t->same(null, $watch->watchStatus('wordpress-media', 5010));
            $t->same([], $watch->watchStatuses(5010));
            $t->same([], $watch->lastDispatchedBatches());
            $t->same([], $watch->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 5010)->snapshots());
            $t->same(null, $watch->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/poster.webp', now: 5011));
            $t->same(false, $watch->removeWatchingFolder('wordpress-media'));
        } finally {
            syncthing_folder_watch_scan_rm($root);
        }
    },
    'watch scan scheduler exposes due restarts for adapters without scanning' => static function (TestRunner $t): void {
        $root = syncthing_folder_watch_scan_root();
        try {
            syncthing_folder_watch_scan_write($root, 'wp-content/uploads/2026/05/hero.jpg', 'abcdefgh');

            $service = new FolderScanService('wordpress-media', new FileInfoScanner($root), new FolderScanCheckpointStore());
            $scheduler = new FolderScanScheduler();
            $scheduler->addFolder('wordpress-media', $service);
            $watch = new FolderWatchScanScheduler(
                $scheduler,
                notifyDelaySeconds: 10,
                notifyTimeoutSeconds: 30,
                watchRestartInitialDelaySeconds: 5,
                watchRestartMaxDelaySeconds: 20,
            );

            $watch->recordWatcherError('wordpress-media', 'watch backend closed', scanOnWatchError: false, now: 6000);

            $t->same([], $watch->dueWatcherRestarts(6004));
            $t->same(null, $watch->completeDueWatcherRestart('wordpress-media', 6004));
            $t->same(0, $service->checkpoint(6004)?->revision ?? 0);

            $due = $watch->dueWatcherRestarts(6005);
            $t->same(['wordpress-media'], array_keys($due));
            $t->same('watch backend closed', $due['wordpress-media']['lastError'] ?? null);
            $t->same(true, $due['wordpress-media']['due'] ?? null);
            $t->same(false, $due['wordpress-media']['scanOnWatchError'] ?? null);

            $completed = $watch->completeDueWatcherRestart('wordpress-media', 6005);
            $t->same('wordpress-media', $completed['folder'] ?? null);
            $t->same(1, $completed['restartAttempt'] ?? null);
            $t->same(null, $watch->watchStatus('wordpress-media', 6005));
            $t->same([], $watch->dueWatcherRestarts(6005));
            $t->same(null, $service->checkpoint(6005));
        } finally {
            syncthing_folder_watch_scan_rm($root);
        }
    },
];

function syncthing_folder_watch_scan_root(): string
{
    $root = sys_get_temp_dir() . '/syncthing-folder-watch-scan-' . bin2hex(random_bytes(6));
    if (!mkdir($root, 0777, true) && !is_dir($root)) {
        throw new RuntimeException('Failed to create temporary folder watch scan root');
    }

    return $root;
}

function syncthing_folder_watch_scan_write(string $root, string $name, string $bytes): void
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create folder watch scan test directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write folder watch scan test file');
    }
}

function syncthing_folder_watch_scan_rm(string $path): void
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
        syncthing_folder_watch_scan_rm($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}
