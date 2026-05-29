<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsCurrentSourceNext158161Plan
{
    public static function run(array $operations, array $options = []): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS current-source next158-161 requires operations');
        }

        $state = self::hydrate($options['current'] ?? []);
        $current = self::summary($state);
        $events = [];

        foreach ($operations as $operation) {
            $op = self::operation($operation);
            $before = self::snapshot($state);

            if ($op['kind'] === 'source') {
                $source = self::sourceName((string) $op['source']);
                if (!isset($state['sources'][$source]) || $state['sources'][$source]['closed'] === true) {
                    $events[] = self::event('source', 'missing-source', $source, $before, self::snapshot($state), []);
                    continue;
                }
                $state['current_source'] = $source;
                $events[] = self::event('source', 'ok', $source, $before, self::snapshot($state), [
                    'handle' => $state['sources'][$source]['handle'],
                    'generation' => $state['sources'][$source]['generation'],
                ]);
                continue;
            }

            if ($op['kind'] === 'open') {
                $source = self::sourceName((string) $op['source']);
                $path = self::pathName((string) $op['path']);
                if (isset($state['sources'][$source]) && $state['sources'][$source]['closed'] !== true) {
                    $state['current_source'] = $source;
                    $events[] = self::event('open', 'reused-current-source', $source, $before, self::snapshot($state), [
                        'handle' => $state['sources'][$source]['handle'],
                        'generation' => $state['sources'][$source]['generation'],
                    ]);
                    continue;
                }

                $state['sequence']++;
                $owner = self::owner($path);
                $state['owner_generations'][$owner] = (int) ($state['owner_generations'][$owner] ?? 0) + 1;
                $state['sources'][$source] = self::sourceState(
                    'vfs158161-' . $state['sequence'],
                    $path,
                    (bool) ($op['readonly'] ?? false),
                    $state['owner_generations'][$owner],
                    self::nonNegativeInt($op['size'] ?? 0, 'source size')
                );
                $state['current_source'] = $source;
                $events[] = self::event('open', 'open', $source, $before, self::snapshot($state), $state['sources'][$source]);
                continue;
            }

            $source = self::sourceFor($state, $op['source'] ?? null);
            if (!isset($state['sources'][$source]) || $state['sources'][$source]['closed'] === true) {
                $events[] = self::event($op['kind'], 'missing-source', $source, $before, self::snapshot($state), []);
                continue;
            }

            if ($op['kind'] === 'mmap') {
                $limit = self::nonNegativeInt($op['limit'] ?? $op['size'] ?? null, 'mmap limit');
                $state['sources'][$source]['mmap_limit'] = $limit;
                $events[] = self::event('mmap', 'ok', $source, $before, self::snapshot($state), [
                    'mmap_limit' => $limit,
                    'mapped' => min($limit, $state['sources'][$source]['size']),
                ]);
                continue;
            }

            if ($op['kind'] === 'fetch') {
                $offset = self::nonNegativeInt($op['offset'] ?? null, 'fetch offset');
                $amount = self::positiveInt($op['amount'] ?? $op['bytes'] ?? null, 'fetch amount');
                $limit = (int) $state['sources'][$source]['mmap_limit'];
                if ($limit === 0 || $offset + $amount > min($limit, $state['sources'][$source]['size'])) {
                    $events[] = self::event('fetch', 'blocked', $source, $before, self::snapshot($state), [
                        'offset' => $offset,
                        'amount' => $amount,
                        'reason' => 'fetch range exceeds current-source mmap window',
                    ]);
                    continue;
                }
                $state['sources'][$source]['fetches'][] = ['offset' => $offset, 'amount' => $amount];
                $events[] = self::event('fetch', 'ok', $source, $before, self::snapshot($state), [
                    'offset' => $offset,
                    'amount' => $amount,
                    'fetch_count' => count($state['sources'][$source]['fetches']),
                ]);
                continue;
            }

            if ($op['kind'] === 'unfetch') {
                $released = array_pop($state['sources'][$source]['fetches']);
                $events[] = self::event('unfetch', $released === null ? 'noop' : 'ok', $source, $before, self::snapshot($state), [
                    'released' => $released,
                    'fetch_count' => count($state['sources'][$source]['fetches']),
                ]);
                continue;
            }

            if ($op['kind'] === 'shmmap') {
                $page = self::nonNegativeInt($op['page'] ?? null, 'shm page');
                $size = self::positiveInt($op['size'] ?? 32768, 'shm page size');
                $extend = (bool) ($op['extend'] ?? false);
                if ($state['sources'][$source]['readonly'] === true && $extend === true) {
                    $events[] = self::event('shm_map', 'blocked', $source, $before, self::snapshot($state), [
                        'page' => $page,
                        'size' => $size,
                        'reason' => 'readonly current-source cannot extend shared memory',
                    ]);
                    continue;
                }
                $state['sources'][$source]['shm_pages'][$page] = ['size' => $size, 'extended' => $extend];
                $events[] = self::event('shm_map', 'ok', $source, $before, self::snapshot($state), [
                    'page' => $page,
                    'size' => $size,
                    'extended' => $extend,
                    'page_count' => count($state['sources'][$source]['shm_pages']),
                ]);
                continue;
            }

            if ($op['kind'] === 'shmlock') {
                $offset = self::nonNegativeInt($op['offset'] ?? null, 'shm lock offset');
                $count = self::positiveInt($op['count'] ?? 1, 'shm lock count');
                $mode = self::shmMode((string) ($op['mode'] ?? 'shared'));
                if ($state['sources'][$source]['readonly'] === true && $mode === 'exclusive') {
                    $events[] = self::event('shm_lock', 'blocked', $source, $before, self::snapshot($state), [
                        'offset' => $offset,
                        'count' => $count,
                        'mode' => $mode,
                        'reason' => 'readonly current-source cannot take exclusive shared-memory lock',
                    ]);
                    continue;
                }
                $state['sources'][$source]['shm_locks'][] = ['offset' => $offset, 'count' => $count, 'mode' => $mode];
                $events[] = self::event('shm_lock', 'ok', $source, $before, self::snapshot($state), [
                    'offset' => $offset,
                    'count' => $count,
                    'mode' => $mode,
                    'lock_count' => count($state['sources'][$source]['shm_locks']),
                ]);
                continue;
            }

            if ($op['kind'] === 'shmunmap') {
                $delete = (bool) ($op['delete'] ?? false);
                $state['sources'][$source]['shm_pages'] = [];
                $state['sources'][$source]['shm_locks'] = [];
                $events[] = self::event('shm_unmap', 'ok', $source, $before, self::snapshot($state), [
                    'delete' => $delete,
                    'released_pages' => count($before['sources'][$source]['shm_pages'] ?? []),
                    'released_locks' => count($before['sources'][$source]['shm_locks'] ?? []),
                ]);
                continue;
            }

            if ($op['kind'] === 'close') {
                $state['sources'][$source]['fetches'] = [];
                $state['sources'][$source]['shm_locks'] = [];
                $state['sources'][$source]['closed'] = true;
                if ($state['current_source'] === $source) {
                    $state['current_source'] = self::firstOpenSource($state);
                }
                $events[] = self::event('close', 'closed', $source, $before, self::snapshot($state), [
                    'released_fetches' => count($before['sources'][$source]['fetches'] ?? []),
                    'released_shm_locks' => count($before['sources'][$source]['shm_locks'] ?? []),
                ]);
                continue;
            }

            throw new \InvalidArgumentException('SQLite VFS current-source next158-161 operation is unsupported');
        }

        return [
            'status' => (string) ($events[array_key_last($events)]['status'] ?? 'ok'),
            'current' => $current,
            'next' => self::summary($state),
            'events' => $events,
            'dependencies' => [
                'vfs-current-source-io-methods-next154-157',
                'vfs-current-source-mmap-shm-next158-161',
            ],
        ];
    }

    private static function hydrate(mixed $current): array
    {
        $state = ['sequence' => 0, 'current_source' => null, 'owner_generations' => [], 'sources' => []];
        if (!is_array($current)) {
            return $state;
        }
        foreach (is_array($current['owner_generations'] ?? null) ? $current['owner_generations'] : [] as $owner => $generation) {
            $state['owner_generations'][self::pathName((string) $owner)] = self::positiveInt($generation, 'owner generation');
        }
        foreach (is_array($current['sources'] ?? null) ? $current['sources'] : [] as $name => $source) {
            if (!is_array($source)) {
                continue;
            }
            $sourceName = self::sourceName((string) $name);
            $path = self::pathName((string) ($source['path'] ?? ''));
            $owner = self::owner($path);
            $generation = self::positiveInt($source['generation'] ?? ($state['owner_generations'][$owner] ?? 1), 'source generation');
            $state['owner_generations'][$owner] = max((int) ($state['owner_generations'][$owner] ?? 0), $generation);
            $state['sources'][$sourceName] = self::sourceState(
                self::handleName((string) ($source['handle'] ?? '')),
                $path,
                (bool) ($source['readonly'] ?? false),
                $generation,
                self::nonNegativeInt($source['size'] ?? 0, 'source size'),
                self::nonNegativeInt($source['mmap_limit'] ?? 0, 'mmap limit'),
                is_array($source['fetches'] ?? null) ? array_values($source['fetches']) : [],
                is_array($source['shm_pages'] ?? null) ? $source['shm_pages'] : [],
                is_array($source['shm_locks'] ?? null) ? array_values($source['shm_locks']) : [],
                (bool) ($source['closed'] ?? false)
            );
        }
        if (isset($current['current_source'])) {
            $currentSource = self::sourceName((string) $current['current_source']);
            if (!isset($state['sources'][$currentSource])) {
                throw new \InvalidArgumentException('SQLite VFS hydrated current source has no handle');
            }
            $state['current_source'] = $currentSource;
        }
        $state['sequence'] = count($state['sources']);
        return $state;
    }

    private static function operation(string|array $operation): array
    {
        if (is_array($operation)) {
            $kind = strtolower(str_replace(['_', '-'], '', (string) ($operation['op'] ?? $operation['kind'] ?? '')));
            return $operation + ['kind' => match ($kind) {
                'xfetch' => 'fetch',
                'xunfetch' => 'unfetch',
                'xmmap', 'mmaplimit' => 'mmap',
                'xshmmap' => 'shmmap',
                'xshmlock' => 'shmlock',
                'xshmunmap' => 'shmunmap',
                default => $kind,
            }];
        }
        $trimmed = trim($operation);
        if (preg_match('/^source\s*\(\s*(?<source>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'source', 'source' => $matches['source']];
        }
        if (preg_match('/^open\s*\(\s*(?<source>[A-Za-z0-9_.:-]+)\s*,\s*(?<path>[^)]*)\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'open', 'source' => $matches['source'], 'path' => trim($matches['path'])];
        }
        if (preg_match('/^mmap\s*\(\s*(?<limit>[0-9]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'mmap', 'limit' => (int) $matches['limit']];
        }
        if (preg_match('/^fetch\s*\(\s*(?<offset>[0-9]+)\s*,\s*(?<amount>[0-9]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'fetch', 'offset' => (int) $matches['offset'], 'amount' => (int) $matches['amount']];
        }
        if (preg_match('/^unfetch\s*\(\s*\)$/', $trimmed) === 1) {
            return ['kind' => 'unfetch'];
        }
        if (preg_match('/^close\s*\(\s*(?<source>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'close', 'source' => $matches['source']];
        }
        throw new \InvalidArgumentException('SQLite VFS current-source next158-161 operation is unsupported');
    }

    private static function sourceState(
        string $handle,
        string $path,
        bool $readonly,
        int $generation,
        int $size,
        int $mmapLimit = 0,
        array $fetches = [],
        array $shmPages = [],
        array $shmLocks = [],
        bool $closed = false
    ): array {
        return [
            'handle' => $handle,
            'owner' => self::owner($path),
            'path' => $path,
            'readonly' => $readonly,
            'generation' => $generation,
            'size' => $size,
            'mmap_limit' => $mmapLimit,
            'fetches' => $fetches,
            'shm_pages' => $shmPages,
            'shm_locks' => $shmLocks,
            'closed' => $closed,
        ];
    }

    private static function sourceFor(array $state, mixed $source): string
    {
        if ($source !== null && $source !== '') {
            return self::sourceName((string) $source);
        }
        if (!is_string($state['current_source'])) {
            throw new \InvalidArgumentException('SQLite VFS current-source next158-161 has no selected source');
        }
        return $state['current_source'];
    }

    private static function firstOpenSource(array $state): ?string
    {
        foreach ($state['sources'] as $name => $source) {
            if ($source['closed'] !== true) {
                return (string) $name;
            }
        }
        return null;
    }

    private static function sourceName(string $source): string
    {
        $source = strtolower(trim($source));
        if ($source === '' || preg_match('/^[a-z0-9_.:-]+$/', $source) !== 1) {
            throw new \InvalidArgumentException('SQLite VFS current source name is unsupported');
        }
        return $source;
    }

    private static function pathName(string $path): string
    {
        $path = trim($path);
        if ($path === '' || str_contains($path, "\0")) {
            throw new \InvalidArgumentException('SQLite VFS source path is required');
        }
        return $path;
    }

    private static function owner(string $path): string
    {
        return preg_replace('/-(?:wal|shm|journal)$/', '', $path) ?? $path;
    }

    private static function handleName(string $handle): string
    {
        $handle = trim($handle);
        if ($handle === '' || preg_match('/^[A-Za-z0-9_.:-]+$/', $handle) !== 1) {
            throw new \InvalidArgumentException('SQLite VFS hydrated handle name is unsupported');
        }
        return $handle;
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \InvalidArgumentException("SQLite VFS current-source next158-161 {$label} must be positive");
        }
        $int = (int) $value;
        if ($int <= 0) {
            throw new \InvalidArgumentException("SQLite VFS current-source next158-161 {$label} must be positive");
        }
        return $int;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \InvalidArgumentException("SQLite VFS current-source next158-161 {$label} must be non-negative");
        }
        $int = (int) $value;
        if ($int < 0) {
            throw new \InvalidArgumentException("SQLite VFS current-source next158-161 {$label} must be non-negative");
        }
        return $int;
    }

    private static function shmMode(string $mode): string
    {
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['shared', 'exclusive', 'unlock'], true)) {
            throw new \InvalidArgumentException('SQLite VFS current-source next158-161 shared-memory lock mode is unsupported');
        }
        return $mode;
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
            'owner_generations' => $state['owner_generations'],
            'sources' => $state['sources'],
        ];
    }

    private static function snapshot(array $state): array
    {
        return self::summary($state);
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
