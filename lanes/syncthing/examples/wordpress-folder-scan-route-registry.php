<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfoScanner;
use PortLibs\Syncthing\FolderScanApiCoordinator;
use PortLibs\Syncthing\FolderScanApiRequestQueue;
use PortLibs\Syncthing\FolderScanCheckpointStore;
use PortLibs\Syncthing\FolderScanRouteRegistry;
use PortLibs\Syncthing\FolderScanScheduler;
use PortLibs\Syncthing\FolderScanService;
use PortLibs\Syncthing\FolderWatchScanScheduler;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$mediaRoot = sys_get_temp_dir() . '/syncthing-wordpress-route-registry-' . bin2hex(random_bytes(6));

try {
    wordpress_scan_route_write($mediaRoot, 'wp-content/uploads/2026/05/hero.jpg', 'abcdefgh');

    $scheduler = new FolderScanScheduler();
    $scheduler->addFolder(
        'wordpress-media',
        new FolderScanService('wordpress-media', new FileInfoScanner($mediaRoot), new FolderScanCheckpointStore(), ttlSeconds: 1800),
    );

    $coordinator = new FolderScanApiCoordinator($scheduler);
    $queue = new FolderScanApiRequestQueue($coordinator);
    $watchScheduler = new FolderWatchScanScheduler(
        $scheduler,
        notifyDelaySeconds: 5,
        watchRestartInitialDelaySeconds: 5,
        recentCleanupTtlSeconds: 20,
    );
    $registry = FolderScanRouteRegistry::forScanApi($coordinator, $queue, $watchScheduler);

    $accepted = $registry->dispatch('POST', '/wp-json/local-first/v1/syncthing/db/scan/queue', [
        'folder' => 'wordpress-media',
        'sub' => 'wp-content/uploads/2026/05',
        'hashBlocks' => true,
        'blockSize' => 4,
    ], now: 1000);
    $completed = $queue->runNext(now: 1001);
    $scanStatus = $registry->dispatch('GET', '/wp-json/local-first/v1/syncthing/db/scan/status', [
        'limit' => 10,
    ], now: 1002);
    $watchScheduler->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/hero.jpg', now: 1010);
    $pendingWatchStatus = $registry->dispatch('GET', '/wp-json/local-first/v1/syncthing/db/watch/status', [], now: 1014);
    $pendingMediaWatchStatus = $registry->dispatch('GET', '/wp-json/local-first/v1/syncthing/db/watch/status', [
        'folder' => 'wordpress-media',
    ], now: 1014);
    $watchScheduler->recordWatcherError('wordpress-media', 'watch backend closed after media edit', scanOnWatchError: false, now: 1011);
    $restartStatus = $registry->dispatch('GET', '/wp-json/local-first/v1/syncthing/db/watch/restarts', [], now: 1016);
    $mediaRestartStatus = $registry->dispatch('GET', '/wp-json/local-first/v1/syncthing/db/watch/restarts', [
        'folder' => 'wordpress-media',
    ], now: 1016);
    $restartCompleted = $registry->dispatch('POST', '/wp-json/local-first/v1/syncthing/db/watch/restarts/complete', [
        'folder' => 'wordpress-media',
    ], now: 1016);
    $delayedWatchScan = $watchScheduler->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 1020);

    $watchScheduler->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/hero.jpg', now: 1030);
    $scheduler->removeFolder('wordpress-media');
    $watchScheduler->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 1035);
    $cleanupStatus = $registry->dispatch('GET', '/wp-json/local-first/v1/syncthing/db/watch/cleanups', [], now: 1035);
    $mediaCleanupStatus = $registry->dispatch('GET', '/wp-json/local-first/v1/syncthing/db/watch/cleanups', [
        'folder' => 'wordpress-media',
    ], now: 1035);
    $cleanupAcknowledged = $registry->dispatch('POST', '/wp-json/local-first/v1/syncthing/db/watch/cleanups/ack', [
        'folder' => 'wordpress-media',
    ], now: 1035);
    $watchRouteCatalog = $registry->dispatch('GET', '/wp-json/local-first/v1/syncthing/db/routes', [
        'pathPrefix' => '/syncthing/db/watch',
        'limit' => 3,
    ], now: 1035);

    echo json_encode([
        'registeredRoutes' => $registry->routes(),
        'watchRouteCatalog' => $watchRouteCatalog->toArray(),
        'accepted' => $accepted->toArray(),
        'completed' => $completed?->toArray(),
        'scanStatus' => $scanStatus->toArray(),
        'pendingWatchStatus' => $pendingWatchStatus->toArray(),
        'pendingMediaWatchStatus' => $pendingMediaWatchStatus->toArray(),
        'restartStatus' => $restartStatus->toArray(),
        'mediaRestartStatus' => $mediaRestartStatus->toArray(),
        'restartCompleted' => $restartCompleted->toArray(),
        'delayedWatchScanRevision' => $delayedWatchScan->snapshot('wordpress-media')?->revision,
        'cleanupStatus' => $cleanupStatus->toArray(),
        'mediaCleanupStatus' => $mediaCleanupStatus->toArray(),
        'cleanupAcknowledged' => $cleanupAcknowledged->toArray(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    wordpress_scan_route_rm($mediaRoot);
}

function wordpress_scan_route_write(string $root, string $name, string $bytes): void
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create scan route example directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write scan route example file');
    }
}

function wordpress_scan_route_rm(string $path): void
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
        wordpress_scan_route_rm($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}
