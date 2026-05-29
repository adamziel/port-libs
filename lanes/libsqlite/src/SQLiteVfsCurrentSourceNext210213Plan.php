<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsCurrentSourceNext210213Plan
{
    /**
     * @param list<array<string, mixed>|string> $operations
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function run(array $operations, array $options = []): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS current-source next210-213 requires operations');
        }

        $state = self::hydrate($options['current'] ?? []);
        $current = self::summary($state);
        $events = [];

        foreach ($operations as $operation) {
            $op = self::operation($operation);
            $before = self::summary($state);
            $source = self::sourceFor($state, $op['source'] ?? null);

            if (!isset($state['sources'][$source]) || $state['sources'][$source]['closed'] === true) {
                $events[] = self::event($op['kind'], 'missing-source', $source, $before, self::summary($state), []);
                continue;
            }

            if ($op['kind'] === 'snapshot') {
                $token = self::token((string) ($op['token'] ?? 'snapshot'), 'snapshot token');
                $dirtyCount = count($state['sources'][$source]['dirty_pages']);
                $receipt = [
                    'token' => $token,
                    'source' => $source,
                    'data_version' => $state['sources'][$source]['data_version'],
                    'durable_count' => count($state['sources'][$source]['durable_receipts']),
                    'dirty_count' => $dirtyCount,
                    'digest' => self::digest($source, $token, $state['sources'][$source]['data_version']),
                ];
                $state['sources'][$source]['snapshots'][$token] = $receipt;
                $events[] = self::event('snapshot', $dirtyCount === 0 ? 'captured' : 'captured-dirty', $source, $before, self::summary($state), $receipt);
                continue;
            }

            if ($op['kind'] === 'reuse') {
                $token = self::token((string) ($op['token'] ?? ''), 'reuse token');
                $snapshot = $state['sources'][$source]['snapshots'][$token] ?? null;
                if (!is_array($snapshot)) {
                    $events[] = self::event('reuse', 'missing-snapshot', $source, $before, self::summary($state), ['token' => $token]);
                    continue;
                }
                $sameVersion = (int) $snapshot['data_version'] === $state['sources'][$source]['data_version'];
                $clean = count($state['sources'][$source]['dirty_pages']) === 0;
                $status = $sameVersion && $clean ? 'reused' : ($clean ? 'stale-version' : 'blocked-dirty');
                if ($status === 'reused') {
                    $state['sources'][$source]['reuse_receipts'][] = [
                        'token' => $token,
                        'digest' => $snapshot['digest'],
                        'data_version' => $state['sources'][$source]['data_version'],
                    ];
                }
                $events[] = self::event('reuse', $status, $source, $before, self::summary($state), [
                    'token' => $token,
                    'snapshot_data_version' => $snapshot['data_version'],
                    'current_data_version' => $state['sources'][$source]['data_version'],
                    'reuse_count' => count($state['sources'][$source]['reuse_receipts']),
                ]);
                continue;
            }

            if ($op['kind'] === 'publish') {
                $token = self::token((string) ($op['token'] ?? 'publish'), 'publish token');
                $reuseCount = count($state['sources'][$source]['reuse_receipts']);
                $dirtyCount = count($state['sources'][$source]['dirty_pages']);
                $status = $dirtyCount === 0 && $reuseCount > 0 ? 'published' : ($dirtyCount > 0 ? 'blocked-dirty' : 'blocked-no-reuse');
                if ($status === 'published') {
                    $state['sources'][$source]['publish_receipts'][] = [
                        'token' => $token,
                        'reuse_count' => $reuseCount,
                        'data_version' => $state['sources'][$source]['data_version'],
                        'durable_count' => count($state['sources'][$source]['durable_receipts']),
                    ];
                }
                $events[] = self::event('publish', $status, $source, $before, self::summary($state), [
                    'token' => $token,
                    'reuse_count' => $reuseCount,
                    'publish_count' => count($state['sources'][$source]['publish_receipts']),
                    'dirty_count' => $dirtyCount,
                ]);
                continue;
            }

            throw new \InvalidArgumentException('SQLite VFS current-source next210-213 operation is unsupported');
        }

        return [
            'status' => (string) ($events[array_key_last($events)]['status'] ?? 'ok'),
            'current' => $current,
            'next' => self::summary($state),
            'events' => $events,
            'dependencies' => [
                'vfs-current-source-dirty-flush-checkpoint-next198-201',
                'vfs-current-source-snapshot-reuse-ready-next206-209',
                'vfs-current-source-snapshot-reuse-publish-next210-213',
            ],
        ];
    }

    private static function hydrate(mixed $current): array
    {
        $state = ['current_source' => null, 'sources' => []];
        if (!is_array($current)) {
            return $state;
        }
        foreach (is_array($current['sources'] ?? null) ? $current['sources'] : [] as $name => $source) {
            if (!is_array($source)) {
                continue;
            }
            $sourceName = self::sourceName((string) $name);
            $state['sources'][$sourceName] = [
                'handle' => self::token((string) ($source['handle'] ?? $sourceName), 'handle'),
                'path' => self::pathName((string) ($source['path'] ?? '')),
                'data_version' => self::nonNegativeInt($source['data_version'] ?? 0, 'data version'),
                'dirty_pages' => self::pageMap($source['dirty_pages'] ?? []),
                'durable_receipts' => self::list($source['durable_receipts'] ?? []),
                'snapshots' => self::snapshotMap($source['snapshots'] ?? []),
                'reuse_receipts' => self::list($source['reuse_receipts'] ?? []),
                'publish_receipts' => self::list($source['publish_receipts'] ?? []),
                'closed' => (bool) ($source['closed'] ?? false),
            ];
        }
        if (isset($current['current_source'])) {
            $state['current_source'] = self::sourceName((string) $current['current_source']);
        }
        return $state;
    }

    private static function operation(string|array $operation): array
    {
        if (is_array($operation)) {
            $kind = strtolower(str_replace(['_', '-'], '', (string) ($operation['op'] ?? $operation['kind'] ?? '')));
            return $operation + ['kind' => match ($kind) {
                'capturesnapshot', 'snapshotcapture' => 'snapshot',
                'reusesnapshot', 'snapshotreuse' => 'reuse',
                'publishsnapshot', 'snapshotpublish' => 'publish',
                default => $kind,
            }];
        }
        $trimmed = trim($operation);
        if (preg_match('/^(snapshot|reuse|publish)\s*\(\s*(?<token>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => $matches[1], 'token' => $matches['token']];
        }
        throw new \InvalidArgumentException('SQLite VFS current-source next210-213 operation is unsupported');
    }

    private static function sourceFor(array $state, mixed $source): string
    {
        if ($source !== null && $source !== '') {
            return self::sourceName((string) $source);
        }
        if (!is_string($state['current_source'])) {
            throw new \InvalidArgumentException('SQLite VFS current-source next210-213 has no selected source');
        }
        return $state['current_source'];
    }

    private static function snapshotMap(mixed $snapshots): array
    {
        if (!is_array($snapshots)) {
            return [];
        }
        $map = [];
        foreach ($snapshots as $token => $snapshot) {
            if (is_array($snapshot)) {
                $map[self::token((string) $token, 'snapshot token')] = $snapshot;
            }
        }
        return $map;
    }

    private static function pageMap(mixed $pages): array
    {
        if (!is_array($pages)) {
            return [];
        }
        $map = [];
        foreach ($pages as $page => $receipt) {
            $pageNumber = is_array($receipt) && isset($receipt['page']) ? (int) $receipt['page'] : (int) $page;
            if ($pageNumber > 0) {
                $map[$pageNumber] = is_array($receipt) ? $receipt + ['page' => $pageNumber] : ['page' => $pageNumber];
            }
        }
        ksort($map);
        return $map;
    }

    private static function list(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    private static function sourceName(string $source): string
    {
        $source = strtolower(trim($source));
        if ($source === '' || preg_match('/^[a-z0-9_.:-]+$/', $source) !== 1) {
            throw new \InvalidArgumentException('SQLite VFS current-source next210-213 source name is unsupported');
        }
        return $source;
    }

    private static function pathName(string $path): string
    {
        $path = trim($path);
        if ($path === '' || str_contains($path, "\0")) {
            throw new \InvalidArgumentException('SQLite VFS current-source next210-213 source path is required');
        }
        return $path;
    }

    private static function token(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:-]+$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite VFS current-source next210-213 {$label} is unsupported");
        }
        return $value;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit((string) $value))) {
            throw new \InvalidArgumentException("SQLite VFS current-source next210-213 {$label} must be non-negative");
        }
        $int = (int) $value;
        if ($int < 0) {
            throw new \InvalidArgumentException("SQLite VFS current-source next210-213 {$label} must be non-negative");
        }
        return $int;
    }

    private static function digest(string $source, string $token, int $dataVersion): string
    {
        return substr(hash('sha256', $source . ':' . $token . ':' . $dataVersion), 0, 16);
    }

    private static function summary(array $state): array
    {
        $open = 0;
        foreach ($state['sources'] as $source) {
            $open += $source['closed'] === true ? 0 : 1;
        }
        return [
            'current_source' => $state['current_source'],
            'source_count' => count($state['sources']),
            'open_source_count' => $open,
            'sources' => $state['sources'],
        ];
    }

    private static function event(string $operation, string $status, string $source, array $before, array $next, array $extra): array
    {
        return array_merge([
            'operation' => $operation,
            'status' => $status,
            'source' => $source,
            'before' => $before,
            'next' => $next,
        ], $extra);
    }
}
