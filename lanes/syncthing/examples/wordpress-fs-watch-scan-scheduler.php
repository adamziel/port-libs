<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfoScanner;
use PortLibs\Syncthing\FolderScanCheckpointStore;
use PortLibs\Syncthing\FolderScanScheduler;
use PortLibs\Syncthing\FolderScanService;
use PortLibs\Syncthing\FolderWatchScanScheduler;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$mediaRoot = sys_get_temp_dir() . '/syncthing-wordpress-fs-watch-' . bin2hex(random_bytes(6));

try {
    wordpress_fs_watch_write($mediaRoot, 'wp-content/uploads/2026/05/hero.jpg', 'abcdefgh');
    wordpress_fs_watch_write($mediaRoot, 'wp-content/uploads/2026/05/poster.webp', 'ijklmnop');

    $service = new FolderScanService(
        'wordpress-media',
        new FileInfoScanner($mediaRoot),
        new FolderScanCheckpointStore(),
        ttlSeconds: 1800,
    );
    $scheduler = new FolderScanScheduler();
    $scheduler->addFolder('wordpress-media', $service);

    $watchScheduler = new FolderWatchScanScheduler(
        $scheduler,
        notifyDelaySeconds: 10,
        notifyTimeoutSeconds: 30,
        maxFilesPerDir: 1,
        watchRestartInitialDelaySeconds: 5,
        watchRestartMaxDelaySeconds: 20,
        recentCleanupTtlSeconds: 20,
        recentCleanupMaxEntries: 2,
    );

    $watchScheduler->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/hero.jpg', now: 1000);
    $pendingStatus = $watchScheduler->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/poster.webp', now: 1000);
    $beforeDue = $watchScheduler->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 1009);
    $due = $watchScheduler->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 1010);
    $dispatchedBatches = $watchScheduler->lastDispatchedBatches();
    wordpress_fs_watch_write($mediaRoot, 'wp-content/uploads/2026/05/gallery.jpg', 'qrstuvwx');
    $watchErrorScan = $watchScheduler->recordWatcherError(
        'wordpress-media',
        'inotify queue overflow',
        hashBlocks: true,
        blockSize: 4,
        now: 1020,
    );
    $watchScheduler->recordWatcherError('wordpress-media', 'watch backend restarted too early', scanOnWatchError: false, now: 1021);
    $restartNotDue = $watchScheduler->dueWatcherRestarts(1025);
    $completedBeforeDue = $watchScheduler->completeDueWatcherRestart('wordpress-media', 1025);
    $restartDue = $watchScheduler->dueWatcherRestarts(1031);
    $completedRestart = $watchScheduler->completeDueWatcherRestart('wordpress-media', 1031);
    $watchScheduler->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/poster.webp', now: 1030);
    $watchScheduler->recordWatcherError('wordpress-media', 'watch backend paused before restart', scanOnWatchError: false, now: 1032);
    $scheduler->pauseFolder('wordpress-media');
    $pausedCleanup = $watchScheduler->cleanupWatchingFolder('wordpress-media', preserveRestart: true, now: 1032);
    $recentCleanupAfterPause = $watchScheduler->recentCleanupStatuses();
    $watchScheduler->recordWatcherError('wordpress-media', 'watch backend closed while paused', now: 1033);
    $watchScheduler->recordWatcherError('wordpress-media', 'watch backend overflow while paused', scanOnWatchError: false, now: 1034);
    $pausedIgnoredEvent = $watchScheduler->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/gallery.jpg', now: 1035);
    $pausedStatus = $watchScheduler->watchStatus('wordpress-media', 1040);
    $pausedRestartDue = $watchScheduler->dueWatcherRestarts(1040);
    $pausedScan = $watchScheduler->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 1040);
    $scheduler->resumeFolder('wordpress-media');
    $resumedRestartDue = $watchScheduler->dueWatcherRestarts(1054);
    $resumedRestartCompleted = $watchScheduler->completeDueWatcherRestart('wordpress-media', 1054);
    $resumedScanAfterRestart = $watchScheduler->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 1054);
    $watchScheduler->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/gallery.jpg', now: 1060);
    $watchScheduler->recordWatcherError('wordpress-media', 'watch backend closed with queued event', scanOnWatchError: false, now: 1061);
    $queuedRestartCompleted = $watchScheduler->completeDueWatcherRestart('wordpress-media', 1066);
    $queuedPendingAfterRestart = $watchScheduler->watchStatus('wordpress-media', 1066);
    $queuedScanBeforeDue = $watchScheduler->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 1066);
    $queuedScanAfterDue = $watchScheduler->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 1070);
    $watchScheduler->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/gallery.jpg', now: 1080);
    $watchScheduler->recordWatcherError('wordpress-media', 'legacy watcher closed with queued event', scanOnWatchError: false, now: 1081);
    $legacyRestartAcknowledged = $watchScheduler->markWatcherRestarted('wordpress-media');
    $legacyPendingAfterRestart = $watchScheduler->watchStatus('wordpress-media', 1086);
    $legacyScanBeforeDue = $watchScheduler->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 1086);
    $legacyScanAfterDue = $watchScheduler->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 1090);
    $watchScheduler->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/gallery.jpg', now: 1090);
    $watchScheduler->recordWatcherError('wordpress-media', 'legacy watcher closed during unshare', scanOnWatchError: false, now: 1091);
    $scheduler->removeFolder('wordpress-media');
    $removedWatchState = $watchScheduler->cleanupWatchingFolder('wordpress-media', discardPendingEvents: true, now: 1105);
    $recentCleanupAfterRemoval = $watchScheduler->recentCleanupStatuses();
    $removedLegacyRestartAcknowledged = $watchScheduler->markWatcherRestarted('wordpress-media');
    $removedStatusAfterAcknowledgement = $watchScheduler->watchStatus('wordpress-media', 1105);
    $removedStatus = $watchScheduler->watchStatus('wordpress-media', 1110);
    $scheduler->addFolder('wordpress-media-removed-during-dispatch', $service);
    $watchScheduler->recordEvent('wordpress-media-removed-during-dispatch', 'wp-content/uploads/2026/05/gallery.jpg', now: 1120);
    $watchScheduler->recordWatcherError(
        'wordpress-media-removed-during-dispatch',
        'watch teardown raced with delayed scan',
        scanOnWatchError: false,
        now: 1121,
    );
    $scheduler->removeFolder('wordpress-media-removed-during-dispatch');
    $removedDuringDispatchScan = $watchScheduler->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 1130);
    $removedDuringDispatchStatus = $watchScheduler->watchStatus('wordpress-media-removed-during-dispatch', 1130);
    $recentCleanupAfterRemovedDispatch = $watchScheduler->recentCleanupStatuses();
    $recentCleanupRetained = $watchScheduler->recentCleanupStatuses(1130);
    $recentCleanupAcknowledged = $watchScheduler->acknowledgeRecentCleanup('wordpress-media-removed-during-dispatch', 1130);
    $recentCleanupAfterAcknowledgement = $watchScheduler->recentCleanupStatuses(1130);
    $recentCleanupExpired = $watchScheduler->recentCleanupStatuses(1151);

    echo json_encode([
        'watcher' => 'Syncthing FSWatcherDelay-style media scan and restart fallback',
        'folder' => 'wordpress-media',
        'pendingStatus' => $pendingStatus,
        'beforeDueResult' => $beforeDue->toRestStatus(),
        'dispatchedBatches' => $dispatchedBatches,
        'dueResult' => $due->toRestStatus(),
        'watchErrorScanResult' => $watchErrorScan->toRestStatus(),
        'restartNotDue' => $restartNotDue,
        'completedBeforeDue' => $completedBeforeDue,
        'restartDue' => $restartDue,
        'completedRestart' => $completedRestart,
        'pausedIgnoredEvent' => $pausedIgnoredEvent,
        'pausedCleanup' => $pausedCleanup,
        'recentCleanupAfterPause' => $recentCleanupAfterPause,
        'pausedStatusAfterCleanup' => $pausedStatus,
        'pausedRestartDue' => $pausedRestartDue,
        'pausedScanResult' => $pausedScan->toRestStatus(),
        'resumedRestartDue' => $resumedRestartDue,
        'resumedRestartCompleted' => $resumedRestartCompleted,
        'resumedScanAfterRestart' => $resumedScanAfterRestart->toRestStatus(),
        'queuedRestartCompleted' => $queuedRestartCompleted,
        'queuedPendingAfterRestart' => $queuedPendingAfterRestart,
        'queuedScanBeforeDue' => $queuedScanBeforeDue->toRestStatus(),
        'queuedScanAfterDue' => $queuedScanAfterDue->toRestStatus(),
        'legacyRestartAcknowledged' => $legacyRestartAcknowledged,
        'legacyPendingAfterRestart' => $legacyPendingAfterRestart,
        'legacyScanBeforeDue' => $legacyScanBeforeDue->toRestStatus(),
        'legacyScanAfterDue' => $legacyScanAfterDue->toRestStatus(),
        'removedStatusAfterAcknowledgement' => $removedStatusAfterAcknowledgement,
        'removedLegacyRestartAcknowledged' => $removedLegacyRestartAcknowledged,
        'removedCleanup' => $removedWatchState,
        'recentCleanupAfterRemoval' => $recentCleanupAfterRemoval,
        'removedStatus' => $removedStatus,
        'removedDuringDispatchScan' => $removedDuringDispatchScan->toRestStatus(),
        'removedDuringDispatchStatus' => $removedDuringDispatchStatus,
        'recentCleanupAfterRemovedDispatch' => $recentCleanupAfterRemovedDispatch,
        'recentCleanupRetained' => $recentCleanupRetained,
        'recentCleanupAcknowledged' => $recentCleanupAcknowledged,
        'recentCleanupAfterAcknowledgement' => $recentCleanupAfterAcknowledgement,
        'recentCleanupExpired' => $recentCleanupExpired,
        'checkpointRevision' => $service->checkpoint(1021)?->revision,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    wordpress_fs_watch_rm($mediaRoot);
}

function wordpress_fs_watch_write(string $root, string $name, string $bytes): void
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create filesystem watcher example directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write filesystem watcher example file');
    }
}

function wordpress_fs_watch_rm(string $path): void
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
        wordpress_fs_watch_rm($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}
