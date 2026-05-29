<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsCurrentSourceNext178181Plan
{
    /**
     * @param list<array<string, mixed>|string> $operations
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function run(array $operations, array $options = []): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS current-source next178-181 requires operations');
        }

        $state = self::hydrate($options['current'] ?? []);
        $current = self::summary($state);
        $events = [];

        foreach ($operations as $operation) {
            $op = self::operation($operation);
            $before = self::summary($state);

            if ($op['kind'] === 'open') {
                $source = self::sourceName((string) $op['source']);
                $path = self::pathName((string) $op['path']);
                $owner = self::owner($path);
                $state['sequence']++;
                $state['owner_generations'][$owner] = (int) ($state['owner_generations'][$owner] ?? 0) + 1;
                $state['sources'][$source] = self::sourceState(
                    'vfs178181-' . $state['sequence'],
                    $path,
                    $owner,
                    self::nonNegativeInt($op['size'] ?? 0, 'file size'),
                    self::nonNegativeInt($op['reserved_bytes'] ?? 0, 'reserved bytes')
                );
                $state['current_source'] = $source;
                $events[] = self::event('open', 'open', $source, $before, self::summary($state), $state['sources'][$source]);
                continue;
            }

            if ($op['kind'] === 'source') {
                $source = self::sourceName((string) $op['source']);
                if (!isset($state['sources'][$source]) || $state['sources'][$source]['closed'] === true) {
                    $events[] = self::event('source', 'missing-source', $source, $before, self::summary($state), []);
                    continue;
                }
                $state['current_source'] = $source;
                $events[] = self::event('source', 'ok', $source, $before, self::summary($state), [
                    'path' => $state['sources'][$source]['path'],
                    'owner' => $state['sources'][$source]['owner'],
                ]);
                continue;
            }

            $source = self::sourceFor($state, $op['source'] ?? null);
            if (!isset($state['sources'][$source]) || $state['sources'][$source]['closed'] === true) {
                $events[] = self::event($op['kind'], 'missing-source', $source, $before, self::summary($state), []);
                continue;
            }

            if ($op['kind'] === 'write') {
                $offset = self::nonNegativeInt($op['offset'] ?? $state['sources'][$source]['size'], 'write offset');
                $bytes = self::positiveInt($op['bytes'] ?? $op['length'] ?? 0, 'write bytes');
                $state['sources'][$source]['size'] = max($state['sources'][$source]['size'], $offset + $bytes);
                $state['sources'][$source]['dirty_bytes'] += $bytes;
                $events[] = self::event('write', 'dirty', $source, $before, self::summary($state), [
                    'offset' => $offset,
                    'bytes' => $bytes,
                    'size' => $state['sources'][$source]['size'],
                    'dirty_bytes' => $state['sources'][$source]['dirty_bytes'],
                ]);
                continue;
            }

            if ($op['kind'] === 'sync') {
                $mode = self::syncMode((string) ($op['mode'] ?? 'normal'));
                $flushed = $state['sources'][$source]['dirty_bytes'];
                $state['sources'][$source]['sync_count']++;
                $state['sources'][$source]['last_sync'] = $mode;
                $state['sources'][$source]['dirty_bytes'] = 0;
                $events[] = self::event('sync', 'synced', $source, $before, self::summary($state), [
                    'mode' => $mode,
                    'flushed_bytes' => $flushed,
                    'sync_count' => $state['sources'][$source]['sync_count'],
                ]);
                continue;
            }

            if ($op['kind'] === 'truncate') {
                $size = self::nonNegativeInt($op['size'] ?? 0, 'truncate size');
                $state['sources'][$source]['size'] = $size;
                $state['sources'][$source]['dirty_bytes'] = min($state['sources'][$source]['dirty_bytes'], $size);
                $events[] = self::event('truncate', 'truncated', $source, $before, self::summary($state), [
                    'size' => $size,
                    'dirty_bytes' => $state['sources'][$source]['dirty_bytes'],
                ]);
                continue;
            }

            if ($op['kind'] === 'reserve') {
                $bytes = self::nonNegativeInt($op['bytes'] ?? $op['reserved_bytes'] ?? 0, 'reserved bytes');
                $state['sources'][$source]['reserved_bytes'] = $bytes;
                $events[] = self::event('reserve', 'reserved', $source, $before, self::summary($state), [
                    'reserved_bytes' => $bytes,
                    'usable_size' => max(0, $state['sources'][$source]['size'] - $bytes),
                ]);
                continue;
            }

            if ($op['kind'] === 'close') {
                $state['sources'][$source]['closed'] = true;
                if ($state['current_source'] === $source) {
                    $state['current_source'] = self::firstOpenSource($state);
                }
                $events[] = self::event('close', 'closed', $source, $before, self::summary($state), [
                    'size' => $state['sources'][$source]['size'],
                    'dirty_bytes' => $state['sources'][$source]['dirty_bytes'],
                ]);
                continue;
            }

            throw new \InvalidArgumentException('SQLite VFS current-source next178-181 operation is unsupported');
        }

        return [
            'status' => (string) ($events[array_key_last($events)]['status'] ?? 'ok'),
            'current' => $current,
            'next' => self::summary($state),
            'events' => $events,
            'dependencies' => [
                'vfs-current-source-close-reopen-next150-153',
                'vfs-current-source-io-methods-next154-157',
                'vfs-current-source-mmap-shm-next158-161',
                'vfs-current-source-environment-next162-165',
                'vfs-current-source-time-error-syscall-next166-169',
                'vfs-current-source-path-control-names-next170-173',
                'vfs-current-source-access-delete-random-sleep-next174-177',
                'vfs-current-source-sync-truncate-size-reserve-next178-181',
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
            $path = self::pathName((string) ($source['path'] ?? ''));
            $sourceName = self::sourceName((string) $name);
            $state['sources'][$sourceName] = self::sourceState(
                self::token((string) ($source['handle'] ?? $sourceName), 'handle'),
                $path,
                self::pathName((string) ($source['owner'] ?? self::owner($path))),
                self::nonNegativeInt($source['size'] ?? 0, 'file size'),
                self::nonNegativeInt($source['reserved_bytes'] ?? 0, 'reserved bytes'),
                self::nonNegativeInt($source['dirty_bytes'] ?? 0, 'dirty bytes'),
                self::nonNegativeInt($source['sync_count'] ?? 0, 'sync count'),
                self::syncMode((string) ($source['last_sync'] ?? 'none')),
                (bool) ($source['closed'] ?? false)
            );
        }
        if (isset($current['current_source'])) {
            $state['current_source'] = self::sourceName((string) $current['current_source']);
        }
        $state['sequence'] = count($state['sources']);
        return $state;
    }

    private static function operation(string|array $operation): array
    {
        if (is_array($operation)) {
            $kind = strtolower(str_replace(['_', '-'], '', (string) ($operation['op'] ?? $operation['kind'] ?? '')));
            return $operation + ['kind' => match ($kind) {
                'xwrite' => 'write',
                'xsync' => 'sync',
                'xtruncate' => 'truncate',
                'xfilecontrolchunksize' => 'reserve',
                'reservebytes' => 'reserve',
                default => $kind,
            }];
        }
        $trimmed = trim($operation);
        if (preg_match('/^source\s*\(\s*(?<source>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'source', 'source' => $matches['source']];
        }
        if (preg_match('/^write\s*\(\s*(?<bytes>[0-9]+)(?:\s*,\s*(?<offset>[0-9]+))?\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'write', 'bytes' => (int) $matches['bytes'], 'offset' => isset($matches['offset']) && $matches['offset'] !== '' ? (int) $matches['offset'] : null];
        }
        if (preg_match('/^sync\s*\(\s*(?<mode>normal|full|dataonly)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'sync', 'mode' => $matches['mode']];
        }
        if (preg_match('/^truncate\s*\(\s*(?<size>[0-9]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'truncate', 'size' => (int) $matches['size']];
        }
        if (preg_match('/^reserve\s*\(\s*(?<bytes>[0-9]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'reserve', 'bytes' => (int) $matches['bytes']];
        }
        if (preg_match('/^close\s*\(\s*(?<source>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'close', 'source' => $matches['source']];
        }
        throw new \InvalidArgumentException('SQLite VFS current-source next178-181 operation is unsupported');
    }

    private static function sourceState(
        string $handle,
        string $path,
        string $owner,
        int $size,
        int $reservedBytes = 0,
        int $dirtyBytes = 0,
        int $syncCount = 0,
        string $lastSync = 'none',
        bool $closed = false
    ): array {
        return [
            'handle' => $handle,
            'path' => $path,
            'owner' => $owner,
            'size' => $size,
            'reserved_bytes' => $reservedBytes,
            'dirty_bytes' => $dirtyBytes,
            'sync_count' => $syncCount,
            'last_sync' => $lastSync,
            'closed' => $closed,
        ];
    }

    private static function sourceFor(array $state, mixed $source): string
    {
        if ($source !== null && $source !== '') {
            return self::sourceName((string) $source);
        }
        if (!is_string($state['current_source'])) {
            throw new \InvalidArgumentException('SQLite VFS current-source next178-181 has no selected source');
        }
        return $state['current_source'];
    }

    private static function syncMode(string $mode): string
    {
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['none', 'normal', 'full', 'dataonly'], true)) {
            throw new \InvalidArgumentException('SQLite VFS current-source next178-181 sync mode is unsupported');
        }
        return $mode;
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

    private static function pathSet(mixed $paths): array
    {
        $out = [];
        foreach (is_array($paths) ? $paths : [] as $path) {
            $out[] = self::pathName((string) $path);
        }
        $out = array_values(array_unique($out));
        sort($out);
        return $out;
    }

    private static function token(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:-]+$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite VFS current-source next178-181 {$label} is unsupported");
        }
        return $value;
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \InvalidArgumentException("SQLite VFS current-source next178-181 {$label} must be positive");
        }
        $int = (int) $value;
        if ($int <= 0) {
            throw new \InvalidArgumentException("SQLite VFS current-source next178-181 {$label} must be positive");
        }
        return $int;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \InvalidArgumentException("SQLite VFS current-source next178-181 {$label} must be non-negative");
        }
        $int = (int) $value;
        if ($int < 0) {
            throw new \InvalidArgumentException("SQLite VFS current-source next178-181 {$label} must be non-negative");
        }
        return $int;
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
