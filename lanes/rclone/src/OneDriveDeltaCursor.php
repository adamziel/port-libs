<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

/**
 * Focused native model of rclone's OneDrive delta-token change-notify path.
 *
 * Upstream uses `/root/delta?token=...`, parses the next token from the
 * returned `@odata.deltaLink`, and emits only file/folder changes inside the
 * configured rclone root.
 */
final class OneDriveDeltaCursor
{
    public const ENTRY_OBJECT = 'object';
    public const ENTRY_DIRECTORY = 'directory';
    public const GRAPH_DRIVES_ROOT = 'https://graph.microsoft.com/v1.0/drives';

    /**
     * Model backend/onedrive buildDriveDeltaOpts().
     *
     * @return array{method: string, rootUrl: string, path: string, parameters: array{token: list<string>}}
     */
    public static function buildDriveDeltaRequest(
        string $driveId,
        string $token,
        ?string $tenantUrl = null,
        string $graphDrivesRoot = self::GRAPH_DRIVES_ROOT,
    ): array {
        $rootUrl = $tenantUrl !== null && $tenantUrl !== ''
            ? rtrim($tenantUrl, '/') . '/v2.0/drives'
            : rtrim($graphDrivesRoot, '/');

        return [
            'method' => 'GET',
            'rootUrl' => $rootUrl,
            'path' => '/' . trim($driveId, '/') . '/root/delta',
            'parameters' => [
                'token' => [$token],
            ],
        ];
    }

    public static function tokenFromDeltaLink(string $deltaLink): string
    {
        $query = parse_url($deltaLink, PHP_URL_QUERY);
        if (!is_string($query)) {
            return '';
        }

        parse_str($query, $parameters);
        $token = $parameters['token'] ?? '';
        if (is_array($token)) {
            $token = reset($token);
        }

        return is_scalar($token) ? (string) $token : '';
    }

    /**
     * @param array<string, mixed> $deltaResponse
     */
    public static function startPageToken(array $deltaResponse): string
    {
        return self::tokenFromDeltaLink(self::responseString($deltaResponse, '@odata.deltaLink', 'deltaLink', 'DeltaLink'));
    }

    /**
     * Model changeNotifyRunner() for a single delta response.
     *
     * Invalid parent paths are logged upstream and skipped. The PHP model
     * returns those messages so callers can assert the same non-fatal boundary.
     *
     * @param array<string, mixed> $deltaResponse
     * @return array{nextToken: string, changes: list<array{path: string, type: string}>, errors: list<string>}
     */
    public static function notifications(array $deltaResponse, string $root): array
    {
        $root = self::normalizePath($root);
        $changes = [];
        $errors = [];
        $items = self::responseItems($deltaResponse);

        foreach ($items as $item) {
            $parentReference = self::parentReference($item);
            if ((self::optionalString($parentReference['id'] ?? $parentReference['ID'] ?? null) ?? '') === '') {
                continue;
            }

            try {
                $fullPath = self::itemFullPath($item);
            } catch (\RuntimeException $exception) {
                $errors[] = $exception->getMessage();
                continue;
            }

            if ($fullPath === $root) {
                continue;
            }

            [$relative, $insideRoot] = self::relativePathInsideBase($root, $fullPath);
            if (!$insideRoot) {
                continue;
            }

            if (self::file($item) !== null) {
                $changes[] = [
                    'path' => $relative,
                    'type' => self::ENTRY_OBJECT,
                ];
                continue;
            }

            if (self::folder($item) !== null) {
                $changes[] = [
                    'path' => $relative,
                    'type' => self::ENTRY_DIRECTORY,
                ];
            }
        }

        return [
            'nextToken' => self::startPageToken($deltaResponse),
            'changes' => $changes,
            'errors' => $errors,
        ];
    }

    /**
     * Deterministic model of ChangeNotify's polling loop.
     *
     * Upstream consumes interval updates until the channel closes, treats a
     * zero interval as paused polling, and logs runner failures without
     * stopping the listener. The PHP callback may return false or a Throwable
     * to model a caller-side cancellation boundary for tests.
     *
     * @param array<string, mixed> $startPage
     * @param array<string, array<string, mixed>|string> $pagesByToken
     * @param list<int|null> $pollIntervals
     * @param callable(string, string): (bool|\Throwable|null) $notify
     * @return array{startToken: string, finalToken: string, requests: list<array{method: string, rootUrl: string, path: string, parameters: array{token: list<string>}}>, notified: list<array{path: string, type: string}>, log: list<string>, stopped: bool}
     */
    public static function runChangeNotify(
        array $startPage,
        array $pagesByToken,
        array $pollIntervals,
        string $root,
        string $driveId,
        callable $notify,
    ): array {
        $token = self::startPageToken($startPage);
        $summary = [
            'startToken' => $token,
            'finalToken' => $token,
            'requests' => [],
            'notified' => [],
            'log' => [],
            'stopped' => false,
        ];

        if ($token === '') {
            $summary['log'][] = 'Could not get first deltaLink';
            $summary['stopped'] = true;

            return $summary;
        }

        foreach ($pollIntervals as $interval) {
            if ($interval === null) {
                $summary['stopped'] = true;
                break;
            }

            if ($interval === 0) {
                $summary['log'][] = 'polling paused';
                continue;
            }

            $summary['requests'][] = self::buildDriveDeltaRequest($driveId, $token);
            $page = $pagesByToken[$token] ?? 'missing delta page for token: ' . $token;
            if (is_string($page)) {
                $summary['log'][] = 'Change notify listener failure: ' . $page;
                $token = '';
                $summary['finalToken'] = $token;
                continue;
            }

            $delta = self::notifications($page, $root);
            $token = $delta['nextToken'];
            $summary['finalToken'] = $token;
            foreach ($delta['errors'] as $error) {
                $summary['log'][] = 'Could not get item full path: ' . $error;
            }

            foreach ($delta['changes'] as $change) {
                $result = $notify($change['path'], $change['type']);
                $summary['notified'][] = $change;
                if ($result instanceof \Throwable) {
                    $summary['log'][] = 'Change notify callback failure: ' . $result->getMessage();
                    $summary['stopped'] = true;
                    break 2;
                }
                if ($result === false) {
                    $summary['log'][] = 'Change notify callback cancelled';
                    $summary['stopped'] = true;
                    break 2;
                }
            }
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function itemFullPath(array $item): string
    {
        $name = self::itemName($item);
        $fullPath = $name;
        $parentReference = self::parentReference($item);
        $parentPath = self::optionalString($parentReference['path'] ?? $parentReference['Path'] ?? null) ?? '';

        if ($parentPath !== '') {
            $parts = explode(':', $parentPath, 2);
            if (count($parts) !== 2) {
                throw new \RuntimeException("invalid parent path: {$parentPath}");
            }
            if ($parts[1] !== '') {
                $fullPath = ltrim($parts[1], '/') . '/' . $name;
            }
        }

        return self::normalizePath($fullPath);
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private static function relativePathInsideBase(string $base, string $target): array
    {
        if ($base === '') {
            return [$target, true];
        }

        $baseSlash = $base . '/';
        if (str_starts_with($target . '/', $baseSlash)) {
            return [substr($target, strlen($baseSlash)), true];
        }

        return ['', false];
    }

    /**
     * @param array<string, mixed> $deltaResponse
     * @return list<array<string, mixed>>
     */
    private static function responseItems(array $deltaResponse): array
    {
        $items = $deltaResponse['value'] ?? $deltaResponse['Value'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, static fn (mixed $item): bool => is_array($item)));
    }

    /**
     * @param array<string, mixed> $response
     */
    private static function responseString(array $response, string ...$keys): string
    {
        foreach ($keys as $key) {
            $value = self::optionalString($response[$key] ?? null);
            if ($value !== null) {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function itemName(array $item): string
    {
        $remoteItem = self::remoteItem($item);
        $remoteName = self::optionalString($remoteItem['name'] ?? $remoteItem['Name'] ?? null);
        if ($remoteName !== null && $remoteName !== '') {
            return $remoteName;
        }

        return self::optionalString($item['name'] ?? $item['Name'] ?? null) ?? '';
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private static function parentReference(array $item): array
    {
        if (is_array($item['parentReference'] ?? null)) {
            return $item['parentReference'];
        }

        if (is_array($item['ParentReference'] ?? null)) {
            return $item['ParentReference'];
        }

        $remoteItem = self::remoteItem($item);
        if (is_array($remoteItem['parentReference'] ?? null)) {
            return $remoteItem['parentReference'];
        }

        return is_array($remoteItem['ParentReference'] ?? null) ? $remoteItem['ParentReference'] : [];
    }

    /**
     * @param array<string, mixed> $item
     * @return null|array<string, mixed>
     */
    private static function folder(array $item): ?array
    {
        $remoteItem = self::remoteItem($item);
        if (is_array($remoteItem['folder'] ?? null)) {
            return $remoteItem['folder'];
        }
        if (is_array($remoteItem['Folder'] ?? null)) {
            return $remoteItem['Folder'];
        }
        if (is_array($item['folder'] ?? null)) {
            return $item['folder'];
        }

        return is_array($item['Folder'] ?? null) ? $item['Folder'] : null;
    }

    /**
     * @param array<string, mixed> $item
     * @return null|array<string, mixed>
     */
    private static function file(array $item): ?array
    {
        $remoteItem = self::remoteItem($item);
        if (is_array($remoteItem['file'] ?? null)) {
            return $remoteItem['file'];
        }
        if (is_array($remoteItem['File'] ?? null)) {
            return $remoteItem['File'];
        }
        if (is_array($item['file'] ?? null)) {
            return $item['file'];
        }

        return is_array($item['File'] ?? null) ? $item['File'] : null;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private static function remoteItem(array $item): array
    {
        return is_array($item['remoteItem'] ?? null) ? $item['remoteItem'] : [];
    }

    private static function optionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return null;
    }

    private static function normalizePath(string $path): string
    {
        return trim(preg_replace('#/+#', '/', $path) ?? $path, '/');
    }
}
