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

        if ($queue !== null) {
            $registry->register('POST', '/syncthing/db/scan/queue', $queue->enqueue(...), [
                'upstreamRoute' => '/rest/db/scan',
                'wordpressRoute' => $registry->wordpressRoute('/syncthing/db/scan/queue'),
                'queued' => true,
            ]);
        }

        if ($watchScheduler !== null) {
            $registry->register('GET', '/syncthing/db/watch/cleanups', static function (array $payload, ?int $now = null) use ($watchScheduler): FolderScanApiResponse {
                return new FolderScanApiResponse(FolderScanApiCoordinator::HTTP_OK, [
                    'ok' => true,
                    'status' => 'ok',
                    'cleanups' => $watchScheduler->recentCleanupStatuses($now),
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
}
