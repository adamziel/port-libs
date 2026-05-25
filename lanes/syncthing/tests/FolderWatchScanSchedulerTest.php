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
            $t->same(false, $watch->markWatcherRestarted('wordpress-media'));
            $t->same(null, $watch->watchStatus('wordpress-media', 5002));
            $t->same([], $watch->watchStatuses(5002));
            $t->same([], $watch->dueWatcherRestarts(5006));

            $t->same(false, $watch->removeWatchingFolder('wordpress-media'));
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
    'watch scan scheduler preserves pending restart while paused and exposes it after resume' => static function (TestRunner $t): void {
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

            $watch->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/hero.jpg', now: 7000);
            $watch->markItemStarted('wordpress-media', 'wp-content/uploads/2026/05/poster.webp');
            $watch->recordWatcherError('wordpress-media', 'watch backend paused before restart', scanOnWatchError: false, now: 7001);
            $scheduler->pauseFolder('wordpress-media');

            $t->same(true, $watch->pauseWatchingFolder('wordpress-media'));
            $pausedStatus = $watch->watchStatus('wordpress-media', 7006);
            $t->same(['wp-content/uploads/2026/05/hero.jpg'], $pausedStatus['pendingPaths'] ?? []);
            $t->same([], $pausedStatus['inProgressPaths'] ?? ['missing']);
            $t->same(true, $pausedStatus['watcherRestart']['due'] ?? null);
            $t->same([], $watch->dueWatcherRestarts(7006));
            $t->same(null, $watch->completeDueWatcherRestart('wordpress-media', 7006));
            $t->same([], $watch->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 7010)->snapshots());
            $t->same(null, $service->checkpoint(7010));

            $scheduler->resumeFolder('wordpress-media');
            $dueRestart = $watch->dueWatcherRestarts(7010);
            $t->same(['wordpress-media'], array_keys($dueRestart));
            $t->same('watch backend paused before restart', $dueRestart['wordpress-media']['lastError'] ?? null);
            $completedRestart = $watch->completeDueWatcherRestart('wordpress-media', 7010);
            $t->same(1, $completedRestart['restartAttempt'] ?? null);

            $dueScan = $watch->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 7010);
            $t->same(1, $dueScan->snapshot('wordpress-media')?->revision);
            $t->same(['wp-content/uploads/2026/05/hero.jpg'], $dueScan->snapshot('wordpress-media')?->checkpoint->completedPaths());
            $finalStatus = $watch->watchStatus('wordpress-media', 7010);
            $t->same(0, $finalStatus['pendingEventCount'] ?? -1);
            $t->true(array_key_exists('watcherRestart', $finalStatus));
            $t->same(null, $finalStatus['watcherRestart']);
        } finally {
            syncthing_folder_watch_scan_rm($root);
        }
    },
    'watch scan scheduler records repeated watcher errors while paused without accepting new events' => static function (TestRunner $t): void {
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

            $watch->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/hero.jpg', now: 8000);
            $scheduler->pauseFolder('wordpress-media');
            $watch->pauseWatchingFolder('wordpress-media');

            $first = $watch->recordWatcherError('wordpress-media', 'watch backend closed while paused', now: 8001);
            $second = $watch->recordWatcherError('wordpress-media', 'watch backend overflow while paused', scanOnWatchError: false, now: 8002);
            $pausedEvent = $watch->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/poster.webp', now: 8003);
            $pausedStatus = $watch->watchStatus('wordpress-media', 8012);

            $t->same([], $first->snapshots());
            $t->same([], $second->snapshots());
            $t->same(null, $service->checkpoint(8012));
            $t->same(null, $pausedEvent);
            $t->same(['wp-content/uploads/2026/05/hero.jpg'], $pausedStatus['pendingPaths'] ?? []);
            $t->same('watch backend overflow while paused', $pausedStatus['watcherRestart']['lastError'] ?? null);
            $t->same(2, $pausedStatus['watcherRestart']['restartAttempt'] ?? null);
            $t->same(10, $pausedStatus['watcherRestart']['restartDelaySeconds'] ?? null);
            $t->same(8012, $pausedStatus['watcherRestart']['restartAt'] ?? null);
            $t->same(true, $pausedStatus['watcherRestart']['due'] ?? null);
            $t->same([], $watch->dueWatcherRestarts(8012));
            $t->same(null, $watch->completeDueWatcherRestart('wordpress-media', 8012));
            $t->same([], $watch->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 8012)->snapshots());

            $scheduler->resumeFolder('wordpress-media');
            $dueRestart = $watch->dueWatcherRestarts(8012);
            $t->same(['wordpress-media'], array_keys($dueRestart));
            $t->same('watch backend overflow while paused', $dueRestart['wordpress-media']['lastError'] ?? null);
            $t->same(2, $watch->completeDueWatcherRestart('wordpress-media', 8012)['restartAttempt'] ?? null);

            $dueScan = $watch->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 8012);
            $t->same(1, $dueScan->snapshot('wordpress-media')?->revision);
            $t->same(['wp-content/uploads/2026/05/hero.jpg'], $dueScan->snapshot('wordpress-media')?->checkpoint->completedPaths());
        } finally {
            syncthing_folder_watch_scan_rm($root);
        }
    },
    'watch scan scheduler leaves queued events pending after restart completion' => static function (TestRunner $t): void {
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

            $watch->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/hero.jpg', now: 9000);
            $watch->recordWatcherError('wordpress-media', 'watch backend closed after event', scanOnWatchError: false, now: 9001);

            $completedRestart = $watch->completeDueWatcherRestart('wordpress-media', 9006);
            $statusAfterRestart = $watch->watchStatus('wordpress-media', 9006);

            $t->same(1, $completedRestart['restartAttempt'] ?? null);
            $t->true(array_key_exists('watcherRestart', $statusAfterRestart));
            $t->same(null, $statusAfterRestart['watcherRestart']);
            $t->same(['wp-content/uploads/2026/05/hero.jpg'], $statusAfterRestart['pendingPaths'] ?? []);
            $t->same(9010, $statusAfterRestart['nextScanAt'] ?? null);
            $t->same(false, $statusAfterRestart['due'] ?? null);
            $t->same([], $watch->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 9006)->snapshots());
            $t->same(null, $service->checkpoint(9006));

            $dueScan = $watch->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 9010);
            $t->same(1, $dueScan->snapshot('wordpress-media')?->revision);
            $t->same(['wp-content/uploads/2026/05/hero.jpg'], $dueScan->snapshot('wordpress-media')?->checkpoint->completedPaths());
            $t->same([
                'wordpress-media' => [
                    [
                        'eventType' => FolderWatchEventAggregator::EVENT_NON_REMOVE,
                        'paths' => ['wp-content/uploads/2026/05/hero.jpg'],
                        'count' => 1,
                    ],
                ],
            ], $watch->lastDispatchedBatches());
        } finally {
            syncthing_folder_watch_scan_rm($root);
        }
    },
    'watch scan scheduler leaves queued events pending after legacy restart acknowledgement' => static function (TestRunner $t): void {
        $root = syncthing_folder_watch_scan_root();
        try {
            syncthing_folder_watch_scan_write($root, 'wp-content/uploads/2026/05/gallery.jpg', 'qrstuvwx');

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

            $watch->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/gallery.jpg', now: 9100);
            $watch->recordWatcherError('wordpress-media', 'legacy watcher restarted after event', scanOnWatchError: false, now: 9101);

            $t->same(true, $watch->markWatcherRestarted('wordpress-media'));
            $statusAfterRestart = $watch->watchStatus('wordpress-media', 9106);

            $t->true(array_key_exists('watcherRestart', $statusAfterRestart));
            $t->same(null, $statusAfterRestart['watcherRestart']);
            $t->same(['wp-content/uploads/2026/05/gallery.jpg'], $statusAfterRestart['pendingPaths'] ?? []);
            $t->same(9110, $statusAfterRestart['nextScanAt'] ?? null);
            $t->same(false, $statusAfterRestart['due'] ?? null);
            $t->same([], $watch->dueWatcherRestarts(9106));
            $t->same([], $watch->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 9106)->snapshots());
            $t->same(null, $service->checkpoint(9106));

            $dueScan = $watch->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 9110);
            $t->same(1, $dueScan->snapshot('wordpress-media')?->revision);
            $t->same(['wp-content/uploads/2026/05/gallery.jpg'], $dueScan->snapshot('wordpress-media')?->checkpoint->completedPaths());
            $t->same(false, $watch->markWatcherRestarted('wordpress-media'));
        } finally {
            syncthing_folder_watch_scan_rm($root);
        }
    },
    'watch scan scheduler ignores legacy restart acknowledgement after folder removal' => static function (TestRunner $t): void {
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

            $watch->recordWatcherError('wordpress-media', 'legacy watcher closed before unshare', scanOnWatchError: false, now: 9200);
            $t->same(true, $scheduler->removeFolder('wordpress-media'));

            $t->same(false, $watch->markWatcherRestarted('wordpress-media'));
            $t->same(null, $watch->watchStatus('wordpress-media', 9206));
            $t->same([], $watch->watchStatuses(9206));
            $t->same([], $watch->dueWatcherRestarts(9206));
            $t->same(null, $watch->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/poster.webp', now: 9207));
            $t->same([], $watch->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 9210)->snapshots());
            $t->same(null, $service->checkpoint(9210));
            $t->same(false, $watch->markWatcherRestarted('wordpress-media'));
        } finally {
            syncthing_folder_watch_scan_rm($root);
        }
    },
    'watch scan scheduler discards queued events when removed folder reaches delayed dispatch' => static function (TestRunner $t): void {
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

            $watch->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/hero.jpg', now: 9300);
            $watch->recordWatcherError('wordpress-media', 'watch teardown raced with delayed scan', scanOnWatchError: false, now: 9301);
            $t->same(true, $scheduler->removeFolder('wordpress-media'));

            $scan = $watch->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 9310);

            $t->same([], $scan->snapshots());
            $t->same([], $scan->errors());
            $t->same(null, $service->checkpoint(9310));
            $t->same(null, $watch->watchStatus('wordpress-media', 9310));
            $t->same([], $watch->watchStatuses(9310));
            $t->same([], $watch->dueWatcherRestarts(9310));
            $t->same([], $watch->lastDispatchedBatches());
            $t->same(false, $watch->removeWatchingFolder('wordpress-media'));
        } finally {
            syncthing_folder_watch_scan_rm($root);
        }
    },
    'watch scan scheduler reports paused cleanup payload with preserved queued events' => static function (TestRunner $t): void {
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

            $watch->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/hero.jpg', now: 9400);
            $watch->markItemStarted('wordpress-media', 'wp-content/uploads/2026/05/poster.webp');
            $watch->recordWatcherError('wordpress-media', 'watch paused for maintenance', scanOnWatchError: false, now: 9401);
            $scheduler->pauseFolder('wordpress-media');

            $cleanup = $watch->cleanupWatchingFolder('wordpress-media', preserveRestart: true, now: 9406);

            $t->same('wordpress-media', $cleanup['folder']);
            $t->same(true, $cleanup['hadState']);
            $t->same(true, $cleanup['folderExists']);
            $t->same(true, $cleanup['folderPaused']);
            $t->same(false, $cleanup['discardedPendingEvents']);
            $t->same(true, $cleanup['preservedPendingEvents']);
            $t->same(false, $cleanup['clearedRestart']);
            $t->same(true, $cleanup['clearedInProgress']);
            $t->same(1, $cleanup['pendingEventCountBefore']);
            $t->same(1, $cleanup['pendingEventCountAfter']);
            $t->same(9406, $cleanup['cleanupAt']);
            $t->same(['wp-content/uploads/2026/05/hero.jpg'], $cleanup['pendingPathsBefore']);
            $t->same(['wp-content/uploads/2026/05/hero.jpg'], $cleanup['pendingPathsAfter']);
            $t->same([], $cleanup['statusAfter']['inProgressPaths'] ?? ['missing']);
            $t->same('watch paused for maintenance', $cleanup['statusAfter']['watcherRestart']['lastError'] ?? null);
            $t->same(true, $cleanup['statusAfter']['watcherRestart']['due'] ?? null);
            $t->same(['wordpress-media'], array_keys($watch->recentCleanupStatuses()));
            $t->same(true, $watch->recentCleanupStatuses()['wordpress-media']['preservedPendingEvents'] ?? null);
            $t->same([], $watch->dueWatcherRestarts(9406));
            $t->same([], $watch->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 9410)->snapshots());

            $scheduler->resumeFolder('wordpress-media');
            $watch->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/poster.webp', now: 9407);
            $t->same([], $watch->recentCleanupStatuses());
            $t->same(['wordpress-media'], array_keys($watch->dueWatcherRestarts(9410)));
            $t->same(1, $watch->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 9410)->snapshot('wordpress-media')?->revision);
        } finally {
            syncthing_folder_watch_scan_rm($root);
        }
    },
    'watch scan scheduler reports removed cleanup payload with discarded queued events' => static function (TestRunner $t): void {
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

            $watch->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/hero.jpg', now: 9500);
            $watch->recordWatcherError('wordpress-media', 'watch removed during unshare', scanOnWatchError: false, now: 9501);
            $scheduler->removeFolder('wordpress-media');

            $cleanup = $watch->cleanupWatchingFolder('wordpress-media', discardPendingEvents: true, now: 9506);

            $t->same('wordpress-media', $cleanup['folder']);
            $t->same(true, $cleanup['hadState']);
            $t->same(false, $cleanup['folderExists']);
            $t->same(false, $cleanup['folderPaused']);
            $t->same(true, $cleanup['discardedPendingEvents']);
            $t->same(false, $cleanup['preservedPendingEvents']);
            $t->same(true, $cleanup['clearedRestart']);
            $t->same(false, $cleanup['clearedInProgress']);
            $t->same(1, $cleanup['pendingEventCountBefore']);
            $t->same(0, $cleanup['pendingEventCountAfter']);
            $t->same(9506, $cleanup['cleanupAt']);
            $t->same(['wp-content/uploads/2026/05/hero.jpg'], $cleanup['pendingPathsBefore']);
            $t->same([], $cleanup['pendingPathsAfter']);
            $t->same(null, $cleanup['statusAfter']);
            $t->same(null, $watch->watchStatus('wordpress-media', 9506));
            $t->same(['wordpress-media'], array_keys($watch->recentCleanupStatuses()));
            $t->same(true, $watch->recentCleanupStatuses()['wordpress-media']['discardedPendingEvents'] ?? null);
            $t->same([], $watch->dueWatcherRestarts(9506));
            $t->same([], $watch->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 9510)->snapshots());

            $secondCleanup = $watch->cleanupWatchingFolder('wordpress-media', discardPendingEvents: true, now: 9511);
            $t->same(false, $secondCleanup['hadState']);
            $t->same(false, $secondCleanup['discardedPendingEvents']);
            $t->same(9511, $watch->recentCleanupStatuses()['wordpress-media']['cleanupAt'] ?? null);
        } finally {
            syncthing_folder_watch_scan_rm($root);
        }
    },
    'watch scan scheduler records recent cleanup when removed folder reaches delayed dispatch' => static function (TestRunner $t): void {
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

            $watch->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/hero.jpg', now: 9600);
            $watch->recordWatcherError('wordpress-media', 'watch teardown raced with delayed scan', scanOnWatchError: false, now: 9601);
            $scheduler->removeFolder('wordpress-media');

            $scan = $watch->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 9610);
            $cleanups = $watch->recentCleanupStatuses();

            $t->same([], $scan->snapshots());
            $t->same(null, $watch->watchStatus('wordpress-media', 9610));
            $t->same(['wordpress-media'], array_keys($cleanups));
            $t->same(9610, $cleanups['wordpress-media']['cleanupAt'] ?? null);
            $t->same(true, $cleanups['wordpress-media']['hadState'] ?? null);
            $t->same(false, $cleanups['wordpress-media']['folderExists'] ?? null);
            $t->same(true, $cleanups['wordpress-media']['discardedPendingEvents'] ?? null);
            $t->same(true, $cleanups['wordpress-media']['clearedRestart'] ?? null);
            $t->same(1, $cleanups['wordpress-media']['pendingEventCountBefore'] ?? null);
            $t->same(0, $cleanups['wordpress-media']['pendingEventCountAfter'] ?? null);
            $t->same(['wp-content/uploads/2026/05/hero.jpg'], $cleanups['wordpress-media']['pendingPathsBefore'] ?? []);
            $t->true(array_key_exists('statusAfter', $cleanups['wordpress-media']));
            $t->same(null, $cleanups['wordpress-media']['statusAfter']);
        } finally {
            syncthing_folder_watch_scan_rm($root);
        }
    },
    'watch scan scheduler expires recent cleanup payloads after the retention window' => static function (TestRunner $t): void {
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
                recentCleanupTtlSeconds: 5,
            );

            $watch->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/hero.jpg', now: 9700);
            $scheduler->removeFolder('wordpress-media');
            $watch->cleanupWatchingFolder('wordpress-media', discardPendingEvents: true, now: 9701);

            $t->same(['wordpress-media'], array_keys($watch->recentCleanupStatuses(9706)));
            $t->same([], $watch->recentCleanupStatuses(9707));
        } finally {
            syncthing_folder_watch_scan_rm($root);
        }
    },
    'watch scan scheduler keeps only the newest bounded recent cleanup payloads' => static function (TestRunner $t): void {
        $root = syncthing_folder_watch_scan_root();
        try {
            syncthing_folder_watch_scan_write($root, 'wp-content/uploads/2026/05/hero.jpg', 'abcdefgh');

            $scheduler = new FolderScanScheduler();
            $watch = new FolderWatchScanScheduler(
                $scheduler,
                notifyDelaySeconds: 10,
                notifyTimeoutSeconds: 30,
                recentCleanupTtlSeconds: 60,
                recentCleanupMaxEntries: 2,
            );

            foreach (['media-a', 'media-b', 'media-c'] as $offset => $folderId) {
                $scheduler->addFolder($folderId, new FolderScanService($folderId, new FileInfoScanner($root), new FolderScanCheckpointStore()));
                $watch->recordEvent($folderId, 'wp-content/uploads/2026/05/hero.jpg', now: 9800 + $offset);
                $scheduler->removeFolder($folderId);
                $watch->cleanupWatchingFolder($folderId, discardPendingEvents: true, now: 9801 + $offset);
            }

            $cleanups = $watch->recentCleanupStatuses(9803);

            $t->same(['media-b', 'media-c'], array_keys($cleanups));
            $t->same(9802, $cleanups['media-b']['cleanupAt'] ?? null);
            $t->same(9803, $cleanups['media-c']['cleanupAt'] ?? null);
            $t->same(['wp-content/uploads/2026/05/hero.jpg'], $cleanups['media-c']['pendingPathsBefore'] ?? []);
        } finally {
            syncthing_folder_watch_scan_rm($root);
        }
    },
    'watch scan scheduler acknowledges one consumed recent cleanup payload' => static function (TestRunner $t): void {
        $root = syncthing_folder_watch_scan_root();
        try {
            syncthing_folder_watch_scan_write($root, 'wp-content/uploads/2026/05/hero.jpg', 'abcdefgh');

            $scheduler = new FolderScanScheduler();
            $watch = new FolderWatchScanScheduler($scheduler, notifyDelaySeconds: 10, notifyTimeoutSeconds: 30);

            foreach (['media-a', 'media-b'] as $offset => $folderId) {
                $scheduler->addFolder($folderId, new FolderScanService($folderId, new FileInfoScanner($root), new FolderScanCheckpointStore()));
                $watch->recordEvent($folderId, 'wp-content/uploads/2026/05/hero.jpg', now: 9900 + $offset);
                $scheduler->removeFolder($folderId);
                $watch->cleanupWatchingFolder($folderId, discardPendingEvents: true, now: 9901 + $offset);
            }

            $t->same(['media-a', 'media-b'], array_keys($watch->recentCleanupStatuses(9902)));
            $t->same(true, $watch->acknowledgeRecentCleanup('media-a', 9902));
            $t->same(false, $watch->acknowledgeRecentCleanup('media-a', 9902));
            $t->same(['media-b'], array_keys($watch->recentCleanupStatuses(9902)));
            $t->same(false, $watch->acknowledgeRecentCleanup('missing-media', 9902));
        } finally {
            syncthing_folder_watch_scan_rm($root);
        }
    },
    'watch scan scheduler acknowledges all retained recent cleanup payloads after pruning expired rows' => static function (TestRunner $t): void {
        $root = syncthing_folder_watch_scan_root();
        try {
            syncthing_folder_watch_scan_write($root, 'wp-content/uploads/2026/05/hero.jpg', 'abcdefgh');

            $scheduler = new FolderScanScheduler();
            $watch = new FolderWatchScanScheduler(
                $scheduler,
                notifyDelaySeconds: 10,
                notifyTimeoutSeconds: 30,
                recentCleanupTtlSeconds: 5,
            );

            foreach (['media-a', 'media-b'] as $offset => $folderId) {
                $scheduler->addFolder($folderId, new FolderScanService($folderId, new FileInfoScanner($root), new FolderScanCheckpointStore()));
                $watch->recordEvent($folderId, 'wp-content/uploads/2026/05/hero.jpg', now: 10000 + $offset);
                $scheduler->removeFolder($folderId);
                $watch->cleanupWatchingFolder($folderId, discardPendingEvents: true, now: 10001 + $offset);
            }

            $t->same(['media-a', 'media-b'], array_keys($watch->recentCleanupStatuses(10002)));
            $t->same(1, $watch->acknowledgeRecentCleanups(10007));
            $t->same([], $watch->recentCleanupStatuses(10007));
            $t->same(0, $watch->acknowledgeRecentCleanups(10007));
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
