<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class FolderScanRouteRegistry
{
    public const HTTP_METHOD_NOT_ALLOWED = 405;

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $routes = [];

    public function __construct(private readonly string $namespace = 'local-first/v1')
    {
        if (trim($this->namespace, '/') === '') {
            throw new \InvalidArgumentException('Folder scan route registry namespace must not be empty');
        }
    }

    public static function forScanApi(
        FolderScanApiCoordinator $coordinator,
        ?FolderScanApiRequestQueue $queue = null,
        ?FolderWatchScanScheduler $watchScheduler = null,
        string $namespace = 'local-first/v1',
    ): self {
        $registry = new self($namespace);
        $registry->register('POST', '/syncthing/db/scan', $coordinator->postDbScan(...), [
            'upstreamRoute' => '/rest/db/scan',
            'wordpressRoute' => $registry->wordpressRoute('/syncthing/db/scan'),
            'queued' => false,
        ]);
        $registry->register('GET', '/syncthing/db/scan/status', static function (array $payload, ?int $now = null) use ($coordinator): FolderScanApiResponse {
            $page = $coordinator->scheduler()->folderStatusPage($payload, $now);

            return new FolderScanApiResponse(FolderScanApiCoordinator::HTTP_OK, [
                'ok' => true,
                'status' => 'ok',
                'folders' => $page['folders'],
                'page' => $page['page'],
            ]);
        }, [
            'upstreamRoute' => 'lib/model model.ScanFolders folder paused/running status and scan checkpoint state',
            'wordpressRoute' => $registry->wordpressRoute('/syncthing/db/scan/status'),
            'queued' => false,
            'scanStatusCatalog' => true,
        ]);

        if ($queue !== null) {
            $registry->register('POST', '/syncthing/db/scan/queue', $queue->enqueue(...), [
                'upstreamRoute' => '/rest/db/scan',
                'wordpressRoute' => $registry->wordpressRoute('/syncthing/db/scan/queue'),
                'queued' => true,
            ]);
        }

        if ($watchScheduler !== null) {
            $registry->register('GET', '/syncthing/db/watch/status', static function (array $payload, ?int $now = null) use ($watchScheduler): FolderScanApiResponse {
                $folder = self::payloadFolder($payload);

                return new FolderScanApiResponse(FolderScanApiCoordinator::HTTP_OK, [
                    'ok' => true,
                    'status' => 'ok',
                    'watchers' => $folder === null
                        ? $watchScheduler->watchStatuses($now)
                        : self::oneFolderMap($folder, $watchScheduler->watchStatus($folder, $now)),
                ]);
            }, [
                'upstreamRoute' => 'lib/model/folder.go watchChan pending event status',
                'wordpressRoute' => $registry->wordpressRoute('/syncthing/db/watch/status'),
                'queued' => false,
                'watcherPendingStatus' => true,
            ]);
            $registry->register('GET', '/syncthing/db/watch/restarts', static function (array $payload, ?int $now = null) use ($watchScheduler): FolderScanApiResponse {
                $folder = self::payloadFolder($payload);
                $restarts = $watchScheduler->dueWatcherRestarts($now);

                return new FolderScanApiResponse(FolderScanApiCoordinator::HTTP_OK, [
                    'ok' => true,
                    'status' => 'ok',
                    'restarts' => $folder === null
                        ? $restarts
                        : (isset($restarts[$folder]) ? [$folder => $restarts[$folder]] : []),
                ]);
            }, [
                'upstreamRoute' => 'lib/model/folder.go restartWatchChan due watcher restart status',
                'wordpressRoute' => $registry->wordpressRoute('/syncthing/db/watch/restarts'),
                'queued' => false,
                'watcherRestartStatus' => true,
            ]);
            $registry->register('POST', '/syncthing/db/watch/restarts/complete', static function (array $payload, ?int $now = null) use ($watchScheduler): FolderScanApiResponse {
                $folder = isset($payload['folder']) ? trim((string) $payload['folder']) : '';
                if ($folder === '') {
                    return new FolderScanApiResponse(FolderScanApiCoordinator::HTTP_BAD_REQUEST, [
                        'ok' => false,
                        'status' => 'error',
                        'error' => 'missing_folder',
                        'message' => 'watcher restart completion requires a folder',
                    ]);
                }

                $completed = $watchScheduler->completeDueWatcherRestart($folder, $now);

                return new FolderScanApiResponse(FolderScanApiCoordinator::HTTP_OK, [
                    'ok' => true,
                    'status' => $completed === null ? 'not_due' : 'completed',
                    'folder' => $folder,
                    'completed' => $completed,
                    'restarts' => $watchScheduler->dueWatcherRestarts($now),
                ]);
            }, [
                'upstreamRoute' => 'lib/model/folder.go restartWatchChan watcher restart completion',
                'wordpressRoute' => $registry->wordpressRoute('/syncthing/db/watch/restarts/complete'),
                'queued' => false,
                'watcherRestartComplete' => true,
            ]);
            $registry->register('GET', '/syncthing/db/watch/cleanups', static function (array $payload, ?int $now = null) use ($watchScheduler): FolderScanApiResponse {
                $folder = self::payloadFolder($payload);
                $cleanups = $watchScheduler->recentCleanupStatuses($now);

                return new FolderScanApiResponse(FolderScanApiCoordinator::HTTP_OK, [
                    'ok' => true,
                    'status' => 'ok',
                    'cleanups' => $folder === null
                        ? $cleanups
                        : (isset($cleanups[$folder]) ? [$folder => $cleanups[$folder]] : []),
                ]);
            }, [
                'upstreamRoute' => 'lib/model/folder.go watcher lifecycle status',
                'wordpressRoute' => $registry->wordpressRoute('/syncthing/db/watch/cleanups'),
                'queued' => false,
                'watcherCleanupStatus' => true,
            ]);
            $registry->register('POST', '/syncthing/db/watch/cleanups/ack', static function (array $payload, ?int $now = null) use ($watchScheduler): FolderScanApiResponse {
                $folder = isset($payload['folder']) ? trim((string) $payload['folder']) : '';
                if ($folder !== '') {
                    $acknowledged = $watchScheduler->acknowledgeRecentCleanup($folder, $now);

                    return new FolderScanApiResponse(FolderScanApiCoordinator::HTTP_OK, [
                        'ok' => true,
                        'status' => $acknowledged ? 'acknowledged' : 'missing',
                        'folder' => $folder,
                        'acknowledged' => $acknowledged ? 1 : 0,
                        'cleanups' => $watchScheduler->recentCleanupStatuses($now),
                    ]);
                }

                $acknowledged = $watchScheduler->acknowledgeRecentCleanups($now);

                return new FolderScanApiResponse(FolderScanApiCoordinator::HTTP_OK, [
                    'ok' => true,
                    'status' => 'acknowledged',
                    'acknowledged' => $acknowledged,
                    'cleanups' => $watchScheduler->recentCleanupStatuses($now),
                ]);
            }, [
                'upstreamRoute' => 'lib/model/folder.go watcher lifecycle cleanup consumption',
                'wordpressRoute' => $registry->wordpressRoute('/syncthing/db/watch/cleanups/ack'),
                'queued' => false,
                'watcherCleanupAck' => true,
            ]);
        }

        $registry->register('GET', '/syncthing/db/routes', static function (array $payload) use ($registry): FolderScanApiResponse {
            $page = $registry->routePage($payload);

            return new FolderScanApiResponse(FolderScanApiCoordinator::HTTP_OK, [
                'ok' => true,
                'status' => 'ok',
                'routes' => $page['routes'],
                'page' => $page['page'],
            ]);
        }, [
            'upstreamRoute' => 'WordPress REST route catalog for bounded scan/watch API discovery',
            'wordpressRoute' => $registry->wordpressRoute('/syncthing/db/routes'),
            'queued' => false,
            'routeCatalog' => true,
        ]);

        return $registry;
    }

    /**
     * @param callable(array<string, mixed>, ?int): FolderScanApiResponse $handler
     * @param array<string, mixed> $metadata
     */
    public function register(string $method, string $path, callable $handler, array $metadata = []): void
    {
        $method = $this->normalizeMethod($method);
        $path = $this->normalizePath($path);
        $key = self::routeKey($method, $path);
        if (isset($this->routes[$key])) {
            throw new \InvalidArgumentException('Folder scan route already registered: ' . $method . ' ' . $path);
        }

        $this->routes[$key] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'metadata' => $metadata,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function dispatch(string $method, string $path, array $payload = [], ?int $now = null): FolderScanApiResponse
    {
        $method = $this->normalizeMethod($method);
        $path = $this->normalizePath($path);
        $key = self::routeKey($method, $path);
        if (isset($this->routes[$key])) {
            return ($this->routes[$key]['handler'])($payload, $now);
        }

        if ($this->pathExists($path)) {
            return new FolderScanApiResponse(self::HTTP_METHOD_NOT_ALLOWED, [
                'ok' => false,
                'status' => 'error',
                'error' => 'method_not_allowed',
                'message' => 'folder scan route method not allowed',
                'allowedMethods' => $this->allowedMethods($path),
            ]);
        }

        return new FolderScanApiResponse(FolderScanApiCoordinator::HTTP_NOT_FOUND, [
            'ok' => false,
            'status' => 'error',
            'error' => 'route_missing',
            'message' => 'folder scan route missing',
        ]);
    }

    /**
     * @return list<array{method:string, path:string, wordpressRoute:string, metadata:array<string, mixed>}>
     */
    public function routes(): array
    {
        $routes = [];
        foreach ($this->routes as $route) {
            $metadata = $route['metadata'];
            $routes[] = [
                'method' => $route['method'],
                'path' => $route['path'],
                'wordpressRoute' => $metadata['wordpressRoute'] ?? $this->wordpressRoute($route['path']),
                'metadata' => $metadata,
            ];
        }

        usort($routes, static fn (array $a, array $b): int => [$a['path'], $a['method']] <=> [$b['path'], $b['method']]);
        return $routes;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{routes:list<array{method:string, path:string, wordpressRoute:string, metadata:array<string, mixed>}>, page:array{offset:int, limit:int, total:int, returned:int, nextOffset:null|int}}
     */
    public function routePage(array $payload = []): array
    {
        $routes = $this->routes();
        $method = isset($payload['method']) && trim((string) $payload['method']) !== ''
            ? $this->normalizeMethod((string) $payload['method'])
            : null;
        $pathPrefix = isset($payload['pathPrefix']) && trim((string) $payload['pathPrefix']) !== ''
            ? $this->normalizePath((string) $payload['pathPrefix'])
            : null;

        if ($method !== null || $pathPrefix !== null) {
            $routes = array_values(array_filter(
                $routes,
                static fn (array $route): bool => ($method === null || $route['method'] === $method)
                    && ($pathPrefix === null || $route['path'] === $pathPrefix || str_starts_with($route['path'], rtrim($pathPrefix, '/') . '/')),
            ));
        }

        $total = count($routes);
        $offset = self::boundedInt($payload['offset'] ?? 0, min: 0, max: $total);
        $limit = self::boundedInt($payload['limit'] ?? 50, min: 1, max: 100);
        $pageRoutes = array_slice($routes, $offset, $limit);
        $nextOffset = $offset + count($pageRoutes);

        return [
            'routes' => $pageRoutes,
            'page' => [
                'offset' => $offset,
                'limit' => $limit,
                'total' => $total,
                'returned' => count($pageRoutes),
                'nextOffset' => $nextOffset < $total ? $nextOffset : null,
            ],
        ];
    }

    public function wordpressRoute(string $path): string
    {
        return '/wp-json/' . trim($this->namespace, '/') . $this->normalizePath($path);
    }

    private function normalizeMethod(string $method): string
    {
        $method = strtoupper(trim($method));
        if ($method === '' || preg_match('/^[A-Z]+$/', $method) !== 1) {
            throw new \InvalidArgumentException('Folder scan route method must be alphabetic');
        }

        return $method;
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            throw new \InvalidArgumentException('Folder scan route path must not be empty');
        }

        $path = '/' . trim($path, '/');
        if (str_contains($path, "\0")) {
            throw new \InvalidArgumentException('Folder scan route path must not contain NUL bytes');
        }

        $wpPrefix = '/wp-json/' . trim($this->namespace, '/');
        if ($path === $wpPrefix) {
            return '/';
        }
        if (str_starts_with($path, $wpPrefix . '/')) {
            return substr($path, strlen($wpPrefix));
        }

        return $path;
    }

    private function pathExists(string $path): bool
    {
        foreach ($this->routes as $route) {
            if ($route['path'] === $path) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function allowedMethods(string $path): array
    {
        $methods = [];
        foreach ($this->routes as $route) {
            if ($route['path'] === $path) {
                $methods[] = $route['method'];
            }
        }
        sort($methods, SORT_STRING);

        return $methods;
    }

    private static function routeKey(string $method, string $path): string
    {
        return $method . ' ' . $path;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function payloadFolder(array $payload): ?string
    {
        if (!array_key_exists('folder', $payload)) {
            return null;
        }

        $folder = trim((string) $payload['folder']);

        return $folder === '' ? null : $folder;
    }

    /**
     * @param null|array<string, mixed> $status
     * @return array<string, array<string, mixed>>
     */
    private static function oneFolderMap(string $folder, ?array $status): array
    {
        return $status === null ? [] : [$folder => $status];
    }

    private static function boundedInt(mixed $value, int $min, int $max): int
    {
        $int = is_numeric($value) ? (int) $value : $min;
        return max($min, min($max, $int));
    }
}
