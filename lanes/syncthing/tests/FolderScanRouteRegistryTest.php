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

return [
    'route registry dispatches WordPress scan route to coordinator' => static function (TestRunner $t): void {
        $root = syncthing_folder_scan_route_root();
        try {
            syncthing_folder_scan_route_write($root, 'wp-content/uploads/2026/05/hero.jpg', 'abcdefgh');
            $scheduler = new FolderScanScheduler();
            $scheduler->addFolder('wordpress-media', new FolderScanService('wordpress-media', new FileInfoScanner($root), new FolderScanCheckpointStore()));
            $registry = FolderScanRouteRegistry::forScanApi(new FolderScanApiCoordinator($scheduler), namespace: 'local-first/v1');

            $response = $registry->dispatch('post', '/syncthing/db/scan', [
                'folder' => 'wordpress-media',
                'sub' => 'wp-content/uploads/2026/05',
                'hashBlocks' => true,
                'blockSize' => 4,
            ], now: 1000);

            $t->same(FolderScanApiCoordinator::HTTP_OK, $response->statusCode);
            $t->same('ok', $response->body['status']);
            $t->same(['wp-content/uploads/2026/05'], $response->body['request']['folders']['wordpress-media']);
            $t->same(1, $response->body['result']['folders']['wordpress-media']['revision']);
            $scanRoute = syncthing_folder_scan_route_by_path($registry->routes(), '/syncthing/db/scan', 'POST');
            $t->same('/wp-json/local-first/v1/syncthing/db/scan', $scanRoute['wordpressRoute']);
            $t->same('/rest/db/scan', $scanRoute['metadata']['upstreamRoute']);
        } finally {
            syncthing_folder_scan_route_rm($root);
        }
    },
    'route registry dispatches optional queue route and preserves coalescing' => static function (TestRunner $t): void {
        $root = syncthing_folder_scan_route_root();
        try {
            syncthing_folder_scan_route_write($root, 'wp-content/uploads/2026/05/hero.jpg', 'abcdefgh');
            $scheduler = new FolderScanScheduler();
            $scheduler->addFolder('wordpress-media', new FolderScanService('wordpress-media', new FileInfoScanner($root), new FolderScanCheckpointStore()));
            $coordinator = new FolderScanApiCoordinator($scheduler);
            $queue = new FolderScanApiRequestQueue($coordinator, maxPending: 2);
            $registry = FolderScanRouteRegistry::forScanApi($coordinator, $queue);

            $first = $registry->dispatch('POST', '/wp-json/local-first/v1/syncthing/db/scan/queue', [
                'folder' => 'wordpress-media',
                'sub' => 'wp-content/uploads/2026/05',
            ], now: 1100);
            $second = $registry->dispatch('POST', '/syncthing/db/scan/queue/', [
                'folder' => 'wordpress-media',
                'sub' => 'wp-content/uploads/2026/05',
            ], now: 1101);
            $completed = $queue->runNext(now: 1102);

            $t->same(FolderScanApiRequestQueue::HTTP_ACCEPTED, $first->statusCode);
            $t->same('queued', $first->body['status']);
            $t->same(FolderScanApiCoordinator::HTTP_OK, $second->statusCode);
            $t->same('coalesced', $second->body['status']);
            $t->same(1, $second->body['queue']['pendingCount']);
            $t->same(FolderScanApiCoordinator::HTTP_OK, $completed?->statusCode);
            $t->same(1, $completed?->body['request']['duplicateCount']);
        } finally {
            syncthing_folder_scan_route_rm($root);
        }
    },
    'route registry returns REST-shaped missing and method errors' => static function (TestRunner $t): void {
        $scheduler = new FolderScanScheduler();
        $registry = FolderScanRouteRegistry::forScanApi(new FolderScanApiCoordinator($scheduler));

        $wrongMethod = $registry->dispatch('GET', '/syncthing/db/scan', [], now: 1200);
        $missingRoute = $registry->dispatch('POST', '/syncthing/db/status', [], now: 1200);

        $t->same(FolderScanRouteRegistry::HTTP_METHOD_NOT_ALLOWED, $wrongMethod->statusCode);
        $t->same('method_not_allowed', $wrongMethod->body['error']);
        $t->same(['POST'], $wrongMethod->body['allowedMethods']);
        $t->same(FolderScanApiCoordinator::HTTP_NOT_FOUND, $missingRoute->statusCode);
        $t->same('route_missing', $missingRoute->body['error']);
        $t->throws(InvalidArgumentException::class, static fn () => new FolderScanRouteRegistry('/'));
    },
    'route registry exposes watcher cleanup status and one-folder acknowledgement' => static function (TestRunner $t): void {
        $root = syncthing_folder_scan_route_root();
        try {
            syncthing_folder_scan_route_write($root, 'wp-content/uploads/2026/05/hero.jpg', 'abcdefgh');
            $scheduler = new FolderScanScheduler();
            $scheduler->addFolder('wordpress-media', new FolderScanService('wordpress-media', new FileInfoScanner($root), new FolderScanCheckpointStore()));
            $watchScheduler = new FolderWatchScanScheduler($scheduler, notifyDelaySeconds: 5, recentCleanupTtlSeconds: 20);
            $registry = FolderScanRouteRegistry::forScanApi(new FolderScanApiCoordinator($scheduler), watchScheduler: $watchScheduler);

            $watchScheduler->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/hero.jpg', now: 2000);
            $scheduler->removeFolder('wordpress-media');
            $watchScheduler->scanDueWatchEvents(now: 2005);

            $status = $registry->dispatch('GET', '/wp-json/local-first/v1/syncthing/db/watch/cleanups', [], now: 2005);
            $ack = $registry->dispatch('POST', '/syncthing/db/watch/cleanups/ack', [
                'folder' => 'wordpress-media',
            ], now: 2005);
            $afterAck = $registry->dispatch('GET', '/syncthing/db/watch/cleanups', [], now: 2005);

            $t->same(FolderScanApiCoordinator::HTTP_OK, $status->statusCode);
            $t->same('ok', $status->body['status']);
            $t->same(['wordpress-media'], array_keys($status->body['cleanups']));
            $t->same(true, $status->body['cleanups']['wordpress-media']['discardedPendingEvents']);
            $t->same(FolderScanApiCoordinator::HTTP_OK, $ack->statusCode);
            $t->same('acknowledged', $ack->body['status']);
            $t->same(1, $ack->body['acknowledged']);
            $t->same([], $ack->body['cleanups']);
            $t->same([], $afterAck->body['cleanups']);
        } finally {
            syncthing_folder_scan_route_rm($root);
        }
    },
    'route registry exposes pending watcher event status without consuming queued scans' => static function (TestRunner $t): void {
        $root = syncthing_folder_scan_route_root();
        try {
            syncthing_folder_scan_route_write($root, 'wp-content/uploads/2026/05/hero.jpg', 'abcdefgh');
            syncthing_folder_scan_route_write($root, 'wp-content/uploads/2026/05/poster.webp', 'ijklmnop');
            $scheduler = new FolderScanScheduler();
            $scheduler->addFolder('wordpress-media', new FolderScanService('wordpress-media', new FileInfoScanner($root), new FolderScanCheckpointStore()));
            $watchScheduler = new FolderWatchScanScheduler($scheduler, notifyDelaySeconds: 10, notifyTimeoutSeconds: 30, maxFilesPerDir: 1);
            $registry = FolderScanRouteRegistry::forScanApi(new FolderScanApiCoordinator($scheduler), watchScheduler: $watchScheduler);

            $watchScheduler->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/hero.jpg', now: 3500);
            $watchScheduler->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/poster.webp', now: 3500);

            $status = $registry->dispatch('GET', '/wp-json/local-first/v1/syncthing/db/watch/status', [], now: 3505);
            $scanBeforeDelay = $watchScheduler->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 3505);
            $scanAfterDelay = $watchScheduler->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 3510);
            $routes = $registry->routes();

            $t->same(FolderScanApiCoordinator::HTTP_OK, $status->statusCode);
            $t->same('ok', $status->body['status']);
            $t->same(['wordpress-media'], array_keys($status->body['watchers']));
            $t->same(1, $status->body['watchers']['wordpress-media']['pendingEventCount'] ?? null);
            $t->same(['wp-content/uploads/2026/05'], $status->body['watchers']['wordpress-media']['pendingPaths'] ?? []);
            $t->same(3510, $status->body['watchers']['wordpress-media']['nextScanAt'] ?? null);
            $t->same(false, $status->body['watchers']['wordpress-media']['due'] ?? null);
            $t->same([], $scanBeforeDelay->snapshots());
            $t->same(1, $scanAfterDelay->snapshot('wordpress-media')?->revision);
            $statusRoute = syncthing_folder_scan_route_by_path($routes, '/syncthing/db/watch/status', 'GET');
            $t->same('/wp-json/local-first/v1/syncthing/db/watch/status', $statusRoute['wordpressRoute']);
            $t->same(true, $statusRoute['metadata']['watcherPendingStatus'] ?? null);
        } finally {
            syncthing_folder_scan_route_rm($root);
        }
    },
    'route registry filters watcher status routes to one requested folder' => static function (TestRunner $t): void {
        $root = syncthing_folder_scan_route_root();
        try {
            syncthing_folder_scan_route_write($root, 'wp-content/uploads/2026/05/hero.jpg', 'abcdefgh');
            syncthing_folder_scan_route_write($root, 'wp-content/private/secret.jpg', 'ijklmnop');
            $scheduler = new FolderScanScheduler();
            $scheduler->addFolder('wordpress-media', new FolderScanService('wordpress-media', new FileInfoScanner($root), new FolderScanCheckpointStore()));
            $scheduler->addFolder('wordpress-private', new FolderScanService('wordpress-private', new FileInfoScanner($root), new FolderScanCheckpointStore()));
            $watchScheduler = new FolderWatchScanScheduler($scheduler, notifyDelaySeconds: 10, watchRestartInitialDelaySeconds: 5, recentCleanupTtlSeconds: 20);
            $registry = FolderScanRouteRegistry::forScanApi(new FolderScanApiCoordinator($scheduler), watchScheduler: $watchScheduler);

            $watchScheduler->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/hero.jpg', now: 3600);
            $watchScheduler->recordEvent('wordpress-private', 'wp-content/private/secret.jpg', now: 3600);
            $watchScheduler->recordWatcherError('wordpress-media', 'watch backend closed for media', scanOnWatchError: false, now: 3601);
            $watchScheduler->recordWatcherError('wordpress-private', 'watch backend closed for private uploads', scanOnWatchError: false, now: 3601);
            $scheduler->removeFolder('wordpress-private');
            $watchScheduler->scanDueWatchEvents(now: 3610);

            $watchStatus = $registry->dispatch('GET', '/syncthing/db/watch/status', [
                'folder' => 'wordpress-media',
            ], now: 3606);
            $restartStatus = $registry->dispatch('GET', '/syncthing/db/watch/restarts', [
                'folder' => 'wordpress-media',
            ], now: 3606);
            $cleanupStatus = $registry->dispatch('GET', '/syncthing/db/watch/cleanups', [
                'folder' => 'wordpress-private',
            ], now: 3610);
            $missingCleanupStatus = $registry->dispatch('GET', '/syncthing/db/watch/cleanups', [
                'folder' => 'wordpress-media',
            ], now: 3610);
            $missingWatchStatus = $registry->dispatch('GET', '/syncthing/db/watch/status', [
                'folder' => 'missing-folder',
            ], now: 3610);

            $t->same(['wordpress-media'], array_keys($watchStatus->body['watchers']));
            $t->same('watch backend closed for media', $watchStatus->body['watchers']['wordpress-media']['watcherRestart']['lastError'] ?? null);
            $t->same(['wordpress-media'], array_keys($restartStatus->body['restarts']));
            $t->same('watch backend closed for media', $restartStatus->body['restarts']['wordpress-media']['lastError'] ?? null);
            $t->same(['wordpress-private'], array_keys($cleanupStatus->body['cleanups']));
            $t->same(true, $cleanupStatus->body['cleanups']['wordpress-private']['discardedPendingEvents'] ?? null);
            $t->same([], $missingCleanupStatus->body['cleanups']);
            $t->same([], $missingWatchStatus->body['watchers']);
        } finally {
            syncthing_folder_scan_route_rm($root);
        }
    },
    'route registry exposes paged route catalog for WordPress REST discovery' => static function (TestRunner $t): void {
        $scheduler = new FolderScanScheduler();
        $coordinator = new FolderScanApiCoordinator($scheduler);
        $queue = new FolderScanApiRequestQueue($coordinator);
        $watchScheduler = new FolderWatchScanScheduler($scheduler);
        $registry = FolderScanRouteRegistry::forScanApi($coordinator, $queue, $watchScheduler, namespace: 'local-first/v1');

        $firstPage = $registry->dispatch('GET', '/wp-json/local-first/v1/syncthing/db/routes', [
            'pathPrefix' => '/syncthing/db/watch',
            'method' => 'GET',
            'offset' => 1,
            'limit' => 2,
        ], now: 3700);
        $oversizedPage = $registry->dispatch('GET', '/syncthing/db/routes', [
            'pathPrefix' => '/syncthing/db/watch',
            'limit' => 1000,
        ], now: 3700);
        $missingPage = $registry->dispatch('GET', '/syncthing/db/routes', [
            'pathPrefix' => '/syncthing/db/missing',
        ], now: 3700);

        $t->same(FolderScanApiCoordinator::HTTP_OK, $firstPage->statusCode);
        $t->same('ok', $firstPage->body['status']);
        $t->same(3, $firstPage->body['page']['total']);
        $t->same(2, $firstPage->body['page']['returned']);
        $t->same(null, $firstPage->body['page']['nextOffset']);
        $t->same('/syncthing/db/watch/restarts', $firstPage->body['routes'][0]['path'] ?? null);
        $t->same('/syncthing/db/watch/status', $firstPage->body['routes'][1]['path'] ?? null);
        $t->same('GET', $firstPage->body['routes'][0]['method'] ?? null);
        $t->same(100, $oversizedPage->body['page']['limit']);
        $t->same(5, $oversizedPage->body['page']['total']);
        $t->same([], $missingPage->body['routes']);
        $t->same(0, $missingPage->body['page']['total']);
    },
    'route registry exposes paged scan status for many WordPress folders' => static function (TestRunner $t): void {
        $mediaRoot = syncthing_folder_scan_route_root();
        $contentRoot = syncthing_folder_scan_route_root();
        $privateRoot = syncthing_folder_scan_route_root();
        try {
            syncthing_folder_scan_route_write($mediaRoot, 'wp-content/uploads/2026/05/hero.jpg', 'abcdefgh');
            syncthing_folder_scan_route_write($contentRoot, 'wp-content/themes/twentytwentyseven/style.css', 'body{}');
            syncthing_folder_scan_route_write($privateRoot, 'wp-content/private/secret.jpg', 'private');
            $scheduler = new FolderScanScheduler();
            $scheduler->addFolder('wordpress-media', new FolderScanService('wordpress-media', new FileInfoScanner($mediaRoot), new FolderScanCheckpointStore(), ttlSeconds: 60));
            $scheduler->addFolder('wordpress-content', new FolderScanService('wordpress-content', new FileInfoScanner($contentRoot), new FolderScanCheckpointStore(), ttlSeconds: 120));
            $scheduler->addFolder('wordpress-private', new FolderScanService('wordpress-private', new FileInfoScanner($privateRoot), new FolderScanCheckpointStore()), running: false);
            $registry = FolderScanRouteRegistry::forScanApi(new FolderScanApiCoordinator($scheduler));

            $registry->dispatch('POST', '/syncthing/db/scan', [
                'folders' => [
                    'wordpress-media' => ['wp-content/uploads/2026/05'],
                    'wordpress-content' => ['wp-content/themes/twentytwentyseven'],
                ],
                'hashBlocks' => true,
                'blockSize' => 4,
                'next' => 30,
            ], now: 5000);

            $firstPage = $registry->dispatch('GET', '/wp-json/local-first/v1/syncthing/db/scan/status', [
                'offset' => 0,
                'limit' => 2,
            ], now: 5010);
            $paused = $registry->dispatch('GET', '/syncthing/db/scan/status', [
                'state' => 'paused',
            ], now: 5010);
            $oneFolder = $registry->dispatch('GET', '/syncthing/db/scan/status', [
                'folder' => 'wordpress-media',
            ], now: 5010);
            $missing = $registry->dispatch('GET', '/syncthing/db/scan/status', [
                'folder' => 'missing-folder',
            ], now: 5010);
            $route = syncthing_folder_scan_route_by_path($registry->routes(), '/syncthing/db/scan/status', 'GET');

            $t->same(FolderScanApiCoordinator::HTTP_OK, $firstPage->statusCode);
            $t->same(3, $firstPage->body['page']['total']);
            $t->same(2, $firstPage->body['page']['returned']);
            $t->same(2, $firstPage->body['page']['nextOffset']);
            $t->same(['wordpress-content', 'wordpress-media'], array_keys($firstPage->body['folders']));
            $t->same('complete', $firstPage->body['folders']['wordpress-media']['checkpoint']['state'] ?? null);
            $t->same(5030, $firstPage->body['folders']['wordpress-media']['scheduledScan']['scheduledAt'] ?? null);
            $t->same(false, $firstPage->body['folders']['wordpress-media']['scheduledScan']['due'] ?? null);
            $t->same(['wordpress-private'], array_keys($paused->body['folders']));
            $t->same('paused', $paused->body['folders']['wordpress-private']['state'] ?? null);
            $t->same(null, $paused->body['folders']['wordpress-private']['checkpoint'] ?? null);
            $t->same(['wordpress-media'], array_keys($oneFolder->body['folders']));
            $t->same([], $missing->body['folders']);
            $t->same('/wp-json/local-first/v1/syncthing/db/scan/status', $route['wordpressRoute']);
            $t->same(true, $route['metadata']['scanStatusCatalog'] ?? null);
        } finally {
            syncthing_folder_scan_route_rm($mediaRoot);
            syncthing_folder_scan_route_rm($contentRoot);
            syncthing_folder_scan_route_rm($privateRoot);
        }
    },
    'route registry acknowledges all watcher cleanup payloads after retention pruning' => static function (TestRunner $t): void {
        $root = syncthing_folder_scan_route_root();
        try {
            $scheduler = new FolderScanScheduler();
            $scheduler->addFolder('wordpress-media-old', new FolderScanService('wordpress-media-old', new FileInfoScanner($root), new FolderScanCheckpointStore()));
            $scheduler->addFolder('wordpress-media-new', new FolderScanService('wordpress-media-new', new FileInfoScanner($root), new FolderScanCheckpointStore()));
            $watchScheduler = new FolderWatchScanScheduler($scheduler, notifyDelaySeconds: 5, recentCleanupTtlSeconds: 10);
            $registry = FolderScanRouteRegistry::forScanApi(new FolderScanApiCoordinator($scheduler), watchScheduler: $watchScheduler);

            $watchScheduler->recordEvent('wordpress-media-old', 'wp-content/uploads/old.jpg', now: 3000);
            $scheduler->removeFolder('wordpress-media-old');
            $watchScheduler->scanDueWatchEvents(now: 3005);
            $watchScheduler->recordEvent('wordpress-media-new', 'wp-content/uploads/new.jpg', now: 3020);
            $scheduler->removeFolder('wordpress-media-new');
            $watchScheduler->scanDueWatchEvents(now: 3025);

            $ack = $registry->dispatch('POST', '/syncthing/db/watch/cleanups/ack', [], now: 3025);
            $missing = $registry->dispatch('POST', '/syncthing/db/watch/cleanups/ack', [
                'folder' => 'wordpress-media-new',
            ], now: 3025);
            $routes = $registry->routes();

            $t->same('acknowledged', $ack->body['status']);
            $t->same(1, $ack->body['acknowledged']);
            $t->same([], $ack->body['cleanups']);
            $t->same('missing', $missing->body['status']);
            $t->same(0, $missing->body['acknowledged']);
            $cleanupRoute = syncthing_folder_scan_route_by_path($routes, '/syncthing/db/watch/cleanups', 'GET');
            $cleanupAckRoute = syncthing_folder_scan_route_by_path($routes, '/syncthing/db/watch/cleanups/ack', 'POST');
            $t->same('/wp-json/local-first/v1/syncthing/db/watch/cleanups', $cleanupRoute['wordpressRoute']);
            $t->same('/wp-json/local-first/v1/syncthing/db/watch/cleanups/ack', $cleanupAckRoute['wordpressRoute']);
        } finally {
            syncthing_folder_scan_route_rm($root);
        }
    },
    'route registry exposes due watcher restart status and completion without consuming queued events' => static function (TestRunner $t): void {
        $root = syncthing_folder_scan_route_root();
        try {
            syncthing_folder_scan_route_write($root, 'wp-content/uploads/2026/05/hero.jpg', 'abcdefgh');
            $scheduler = new FolderScanScheduler();
            $scheduler->addFolder('wordpress-media', new FolderScanService('wordpress-media', new FileInfoScanner($root), new FolderScanCheckpointStore()));
            $watchScheduler = new FolderWatchScanScheduler(
                $scheduler,
                notifyDelaySeconds: 10,
                notifyTimeoutSeconds: 30,
                watchRestartInitialDelaySeconds: 5,
                watchRestartMaxDelaySeconds: 20,
            );
            $registry = FolderScanRouteRegistry::forScanApi(new FolderScanApiCoordinator($scheduler), watchScheduler: $watchScheduler);

            $watchScheduler->recordEvent('wordpress-media', 'wp-content/uploads/2026/05/hero.jpg', now: 4000);
            $watchScheduler->recordWatcherError('wordpress-media', 'watch backend closed after event', scanOnWatchError: false, now: 4001);

            $notDue = $registry->dispatch('GET', '/wp-json/local-first/v1/syncthing/db/watch/restarts', [], now: 4004);
            $missingFolder = $registry->dispatch('POST', '/syncthing/db/watch/restarts/complete', [], now: 4005);
            $due = $registry->dispatch('GET', '/syncthing/db/watch/restarts', [], now: 4006);
            $completed = $registry->dispatch('POST', '/syncthing/db/watch/restarts/complete', [
                'folder' => 'wordpress-media',
            ], now: 4006);
            $afterComplete = $watchScheduler->watchStatus('wordpress-media', 4006);
            $scanAfterDelay = $watchScheduler->scanDueWatchEvents(hashBlocks: true, blockSize: 4, now: 4010);
            $routes = $registry->routes();

            $t->same(FolderScanApiCoordinator::HTTP_OK, $notDue->statusCode);
            $t->same([], $notDue->body['restarts']);
            $t->same(FolderScanApiCoordinator::HTTP_BAD_REQUEST, $missingFolder->statusCode);
            $t->same('missing_folder', $missingFolder->body['error']);
            $t->same(['wordpress-media'], array_keys($due->body['restarts']));
            $t->same('watch backend closed after event', $due->body['restarts']['wordpress-media']['lastError'] ?? null);
            $t->same(true, $due->body['restarts']['wordpress-media']['due'] ?? null);
            $t->same('completed', $completed->body['status']);
            $t->same(1, $completed->body['completed']['restartAttempt'] ?? null);
            $t->same([], $completed->body['restarts']);
            $t->same(['wp-content/uploads/2026/05/hero.jpg'], $afterComplete['pendingPaths'] ?? []);
            $t->same(4010, $afterComplete['nextScanAt'] ?? null);
            $t->same(1, $scanAfterDelay->snapshot('wordpress-media')?->revision);
            $restartRoute = syncthing_folder_scan_route_by_path($routes, '/syncthing/db/watch/restarts', 'GET');
            $restartCompleteRoute = syncthing_folder_scan_route_by_path($routes, '/syncthing/db/watch/restarts/complete', 'POST');
            $t->same('/wp-json/local-first/v1/syncthing/db/watch/restarts', $restartRoute['wordpressRoute']);
            $t->same('/wp-json/local-first/v1/syncthing/db/watch/restarts/complete', $restartCompleteRoute['wordpressRoute']);
        } finally {
            syncthing_folder_scan_route_rm($root);
        }
    },
];

function syncthing_folder_scan_route_root(): string
{
    $root = sys_get_temp_dir() . '/syncthing-folder-scan-route-' . bin2hex(random_bytes(6));
    if (!mkdir($root, 0777, true) && !is_dir($root)) {
        throw new RuntimeException('Failed to create temporary folder scan route root');
    }

    return $root;
}

function syncthing_folder_scan_route_write(string $root, string $name, string $bytes): void
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create folder scan route test directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write folder scan route test file');
    }
}

/**
 * @param list<array{method:string, path:string, wordpressRoute:string, metadata:array<string, mixed>}> $routes
 * @return array{method:string, path:string, wordpressRoute:string, metadata:array<string, mixed>}
 */
function syncthing_folder_scan_route_by_path(array $routes, string $path, string $method): array
{
    foreach ($routes as $route) {
        if ($route['path'] === $path && $route['method'] === $method) {
            return $route;
        }
    }

    throw new RuntimeException('Missing route ' . $method . ' ' . $path);
}

function syncthing_folder_scan_route_rm(string $path): void
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
        syncthing_folder_scan_route_rm($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}
