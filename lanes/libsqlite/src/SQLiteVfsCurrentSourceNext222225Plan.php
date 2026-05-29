<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsCurrentSourceNext222225Plan
{
    /**
     * @param list<array<string, mixed>|string> $operations
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function run(array $operations, array $options = []): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS current-source next222-225 requires operations');
        }

        $state = self::hydrate($options['current'] ?? []);
        $events = [];

        foreach ($operations as $operation) {
            $op = self::operation($operation);
            $before = self::summary($state);
            $source = self::sourceFor($state, $op['source'] ?? null);

            if (!isset($state['sources'][$source]) || $state['sources'][$source]['closed'] === true) {
                $events[] = self::event($op['kind'], 'missing-source', $source, $before, self::summary($state), []);
                continue;
            }

            if ($op['kind'] === 'prepare') {
                $token = self::token((string) ($op['token'] ?? 'prepare'), 'prepare token');
                $priorPublishCount = count($state['sources'][$source]['publish_receipts']);
                $dirtyCount = count($state['sources'][$source]['dirty_pages']);
                $status = $priorPublishCount > 0 && $dirtyCount === 0 ? 'ready' : ($dirtyCount > 0 ? 'blocked-dirty' : 'blocked-no-prior-publish');
                if ($status === 'ready') {
                    $state['sources'][$source]['ready_receipts'][$token] = [
                        'token' => $token,
                        'prior_publish_count' => $priorPublishCount,
                        'data_version' => $state['sources'][$source]['data_version'],
                        'digest' => self::digest($source, $token, $state['sources'][$source]['data_version']),
                    ];
                }
                $events[] = self::event('prepare', $status, $source, $before, self::summary($state), [
                    'token' => $token,
                    'prior_publish_count' => $priorPublishCount,
                    'dirty_count' => $dirtyCount,
                    'ready_count' => count($state['sources'][$source]['ready_receipts']),
                ]);
                continue;
            }

            if ($op['kind'] === 'reuse') {
                $token = self::token((string) ($op['token'] ?? ''), 'reuse token');
                $ready = $state['sources'][$source]['ready_receipts'][$token] ?? null;
                if (!is_array($ready)) {
                    $events[] = self::event('reuse', 'missing-ready-receipt', $source, $before, self::summary($state), ['token' => $token]);
                    continue;
                }
                $sameVersion = (int) $ready['data_version'] === $state['sources'][$source]['data_version'];
                $clean = count($state['sources'][$source]['dirty_pages']) === 0;
                $status = $sameVersion && $clean ? 'reused' : ($clean ? 'stale-version' : 'blocked-dirty');
                if ($status === 'reused') {
                    $state['sources'][$source]['reuse_receipts'][] = [
                        'token' => $token,
                        'digest' => $ready['digest'],
                        'data_version' => $state['sources'][$source]['data_version'],
                        'after_ready' => true,
                    ];
                }
                $events[] = self::event('reuse', $status, $source, $before, self::summary($state), [
                    'token' => $token,
                    'ready_data_version' => $ready['data_version'],
                    'current_data_version' => $state['sources'][$source]['data_version'],
                    'reuse_count' => count($state['sources'][$source]['reuse_receipts']),
                ]);
                continue;
            }

            if ($op['kind'] === 'publish') {
                $token = self::token((string) ($op['token'] ?? 'publish'), 'publish token');
                $afterReadyReuseCount = 0;
                foreach ($state['sources'][$source]['reuse_receipts'] as $receipt) {
                    if (is_array($receipt) && ($receipt['after_ready'] ?? false) === true) {
                        ++$afterReadyReuseCount;
                    }
                }
                $dirtyCount = count($state['sources'][$source]['dirty_pages']);
                $status = $dirtyCount === 0 && $afterReadyReuseCount > 0 ? 'published' : ($dirtyCount > 0 ? 'blocked-dirty' : 'blocked-no-after-ready-reuse');
                if ($status === 'published') {
                    $state['sources'][$source]['publish_receipts'][] = [
                        'token' => $token,
                        'after_ready_reuse_count' => $afterReadyReuseCount,
                        'data_version' => $state['sources'][$source]['data_version'],
                    ];
                }
                $events[] = self::event('publish', $status, $source, $before, self::summary($state), [
                    'token' => $token,
                    'after_ready_reuse_count' => $afterReadyReuseCount,
                    'publish_count' => count($state['sources'][$source]['publish_receipts']),
                    'dirty_count' => $dirtyCount,
                ]);
                continue;
            }

            throw new \InvalidArgumentException('SQLite VFS current-source next222-225 operation is unsupported');
        }

        return [
            'status' => (string) ($events[array_key_last($events)]['status'] ?? 'ok'),
            'current' => self::summary(self::hydrate($options['current'] ?? [])),
            'next' => self::summary($state),
            'events' => $events,
            'dependencies' => [
                'vfs-current-source-snapshot-reuse-ready-next206-209',
                'vfs-current-source-snapshot-reuse-publish-next210-213',
                'vfs-current-source-after-ready-reuse-publish-next222-225',
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
                'publish_receipts' => self::list($source['publish_receipts'] ?? []),
                'ready_receipts' => self::receiptMap($source['ready_receipts'] ?? []),
                'reuse_receipts' => self::list($source['reuse_receipts'] ?? []),
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
                'preparereuse', 'reuseprepare' => 'prepare',
                'reusesnapshot', 'snapshotreuse' => 'reuse',
                'publishsnapshot', 'snapshotpublish' => 'publish',
                default => $kind,
            }];
        }
        $trimmed = trim($operation);
        if (preg_match('/^(prepare|reuse|publish)\s*\(\s*(?<token>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => $matches[1], 'token' => $matches['token']];
        }
        throw new \InvalidArgumentException('SQLite VFS current-source next222-225 operation is unsupported');
    }

    private static function sourceFor(array $state, mixed $source): string
    {
        if ($source !== null && $source !== '') {
            return self::sourceName((string) $source);
        }
        if (!is_string($state['current_source'])) {
            throw new \InvalidArgumentException('SQLite VFS current-source next222-225 has no selected source');
        }
        return $state['current_source'];
    }

    private static function receiptMap(mixed $receipts): array
    {
        if (!is_array($receipts)) {
            return [];
        }
        $map = [];
        foreach ($receipts as $token => $receipt) {
            if (is_array($receipt)) {
                $map[self::token((string) $token, 'receipt token')] = $receipt;
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
            throw new \InvalidArgumentException('SQLite VFS current-source next222-225 source name is unsupported');
        }
        return $source;
    }

    private static function pathName(string $path): string
    {
        $path = trim($path);
        if ($path === '' || str_contains($path, "\0")) {
            throw new \InvalidArgumentException('SQLite VFS current-source next222-225 source path is required');
        }
        return $path;
    }

    private static function token(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:-]+$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite VFS current-source next222-225 {$label} is unsupported");
        }
        return $value;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit((string) $value))) {
            throw new \InvalidArgumentException("SQLite VFS current-source next222-225 {$label} must be non-negative");
        }
        $int = (int) $value;
        if ($int < 0) {
            throw new \InvalidArgumentException("SQLite VFS current-source next222-225 {$label} must be non-negative");
        }
        return $int;
    }

    private static function digest(string $source, string $token, int $dataVersion): string
    {
        return substr(hash('sha256', $source . ':next222-225:' . $token . ':' . $dataVersion), 0, 16);
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
