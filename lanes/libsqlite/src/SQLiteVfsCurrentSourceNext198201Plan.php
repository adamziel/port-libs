<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsCurrentSourceNext198201Plan
{
    /**
     * @param list<array<string, mixed>|string> $operations
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function run(array $operations, array $options = []): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS current-source next198-201 requires operations');
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
                    'vfs198201-' . $state['sequence'],
                    $path,
                    $owner,
                    self::lockLevel((string) ($op['lock'] ?? 'none')),
                    (bool) ($op['readonly'] ?? false),
                    self::nonNegativeInt($op['data_version'] ?? 0, 'data version')
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
                    'dirty_count' => count($state['sources'][$source]['dirty_pages']),
                ]);
                continue;
            }

            $source = self::sourceFor($state, $op['source'] ?? null);
            if (!isset($state['sources'][$source]) || $state['sources'][$source]['closed'] === true) {
                $events[] = self::event($op['kind'], 'missing-source', $source, $before, self::summary($state), []);
                continue;
            }

            if ($op['kind'] === 'write') {
                $page = self::positiveInt($op['page'] ?? null, 'write page');
                $bytes = self::positiveInt($op['bytes'] ?? 4096, 'write bytes');
                if ($state['sources'][$source]['readonly'] === true || self::lockRank($state['sources'][$source]['lock']) < self::lockRank('reserved')) {
                    $events[] = self::event('dirty_page', 'blocked', $source, $before, self::summary($state), [
                        'page' => $page,
                        'bytes' => $bytes,
                        'lock' => $state['sources'][$source]['lock'],
                        'readonly' => $state['sources'][$source]['readonly'],
                    ]);
                    continue;
                }
                $receipt = self::receipt($state['sources'][$source]['handle'], $page, $bytes);
                $state['sources'][$source]['write_receipts'][] = $receipt;
                $state['sources'][$source]['dirty_pages'][$page] = $receipt;
                ksort($state['sources'][$source]['dirty_pages']);
                $state['sources'][$source]['data_version']++;
                $events[] = self::event('dirty_page', 'recorded', $source, $before, self::summary($state), [
                    'receipt' => $receipt,
                    'dirty_count' => count($state['sources'][$source]['dirty_pages']),
                    'data_version' => $state['sources'][$source]['data_version'],
                ]);
                continue;
            }

            if ($op['kind'] === 'flush') {
                $mode = self::flushMode((string) ($op['mode'] ?? 'normal'));
                $dirtyPages = array_values($state['sources'][$source]['dirty_pages']);
                if ($dirtyPages === []) {
                    $events[] = self::event('flush', 'clean', $source, $before, self::summary($state), [
                        'mode' => $mode,
                        'flushed_count' => 0,
                    ]);
                    continue;
                }
                $state['sources'][$source]['flush_count']++;
                $state['sources'][$source]['durable_receipts'] = self::mergeReceipts($state['sources'][$source]['durable_receipts'], $dirtyPages);
                $state['sources'][$source]['dirty_pages'] = [];
                $events[] = self::event('flush', 'flushed', $source, $before, self::summary($state), [
                    'mode' => $mode,
                    'flushed_count' => count($dirtyPages),
                    'durable_count' => count($state['sources'][$source]['durable_receipts']),
                    'flush_count' => $state['sources'][$source]['flush_count'],
                ]);
                continue;
            }

            if ($op['kind'] === 'checkpoint') {
                $token = self::token((string) ($op['token'] ?? 'checkpoint'), 'checkpoint token');
                $dirtyCount = count($state['sources'][$source]['dirty_pages']);
                $status = $dirtyCount === 0 ? 'recorded' : 'blocked-dirty';
                if ($dirtyCount === 0) {
                    $state['sources'][$source]['checkpoints'][] = [
                        'token' => $token,
                        'data_version' => $state['sources'][$source]['data_version'],
                        'durable_count' => count($state['sources'][$source]['durable_receipts']),
                    ];
                }
                $events[] = self::event('checkpoint', $status, $source, $before, self::summary($state), [
                    'token' => $token,
                    'dirty_count' => $dirtyCount,
                    'checkpoint_count' => count($state['sources'][$source]['checkpoints']),
                ]);
                continue;
            }

            if ($op['kind'] === 'close') {
                $dirtyCount = count($state['sources'][$source]['dirty_pages']);
                if ($dirtyCount > 0 && (bool) ($op['force'] ?? false) !== true) {
                    $events[] = self::event('close', 'blocked-dirty', $source, $before, self::summary($state), [
                        'dirty_count' => $dirtyCount,
                    ]);
                    continue;
                }
                $state['sources'][$source]['closed'] = true;
                if ($state['current_source'] === $source) {
                    $state['current_source'] = self::firstOpenSource($state);
                }
                $events[] = self::event('close', 'closed', $source, $before, self::summary($state), [
                    'dirty_count' => $dirtyCount,
                    'durable_count' => count($state['sources'][$source]['durable_receipts']),
                ]);
                continue;
            }

            throw new \InvalidArgumentException('SQLite VFS current-source next198-201 operation is unsupported');
        }

        return [
            'status' => (string) ($events[array_key_last($events)]['status'] ?? 'ok'),
            'current' => $current,
            'next' => self::summary($state),
            'events' => $events,
            'dependencies' => [
                'vfs-current-source-ready-next190-193',
                'vfs-current-source-durable-receipts-next194-197',
                'vfs-current-source-dirty-flush-checkpoint-next198-201',
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
                self::lockLevel((string) ($source['lock'] ?? 'none')),
                (bool) ($source['readonly'] ?? false),
                self::nonNegativeInt($source['data_version'] ?? 0, 'data version'),
                self::receiptList($source['write_receipts'] ?? []),
                self::receiptList($source['durable_receipts'] ?? []),
                self::dirtyPageMap($source['dirty_pages'] ?? []),
                is_array($source['checkpoints'] ?? null) ? array_values($source['checkpoints']) : [],
                self::nonNegativeInt($source['flush_count'] ?? 0, 'flush count'),
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
                'xwrite', 'writepage' => 'write',
                'xsync', 'xflush', 'flushdirty' => 'flush',
                'xcheckpoint', 'checkpointbarrier' => 'checkpoint',
                default => $kind,
            }];
        }
        $trimmed = trim($operation);
        if (preg_match('/^source\s*\(\s*(?<source>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'source', 'source' => $matches['source']];
        }
        if (preg_match('/^write\s*\(\s*(?<page>[0-9]+)\s*,\s*(?<bytes>[0-9]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'write', 'page' => (int) $matches['page'], 'bytes' => (int) $matches['bytes']];
        }
        if (preg_match('/^flush\s*\(\s*(?<mode>normal|full|dataonly)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'flush', 'mode' => $matches['mode']];
        }
        if (preg_match('/^checkpoint\s*\(\s*(?<token>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'checkpoint', 'token' => $matches['token']];
        }
        if (preg_match('/^close\s*\(\s*(?<source>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'close', 'source' => $matches['source']];
        }
        throw new \InvalidArgumentException('SQLite VFS current-source next198-201 operation is unsupported');
    }

    private static function sourceState(
        string $handle,
        string $path,
        string $owner,
        string $lock,
        bool $readonly,
        int $dataVersion,
        array $writeReceipts = [],
        array $durableReceipts = [],
        array $dirtyPages = [],
        array $checkpoints = [],
        int $flushCount = 0,
        bool $closed = false
    ): array {
        return [
            'handle' => $handle,
            'path' => $path,
            'owner' => $owner,
            'lock' => $lock,
            'readonly' => $readonly,
            'data_version' => $dataVersion,
            'write_receipts' => $writeReceipts,
            'durable_receipts' => $durableReceipts,
            'dirty_pages' => $dirtyPages,
            'checkpoints' => $checkpoints,
            'flush_count' => $flushCount,
            'closed' => $closed,
        ];
    }

    private static function sourceFor(array $state, mixed $source): string
    {
        if ($source !== null && $source !== '') {
            return self::sourceName((string) $source);
        }
        if (!is_string($state['current_source'])) {
            throw new \InvalidArgumentException('SQLite VFS current-source next198-201 has no selected source');
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

    private static function lockLevel(string $level): string
    {
        $level = strtolower(trim($level));
        if (!isset(self::lockRanks()[$level])) {
            throw new \InvalidArgumentException('SQLite VFS current-source next198-201 lock level is unsupported');
        }
        return $level;
    }

    private static function lockRank(string $level): int
    {
        return self::lockRanks()[$level];
    }

    /** @return array<string, int> */
    private static function lockRanks(): array
    {
        return ['none' => 0, 'shared' => 1, 'reserved' => 2, 'pending' => 3, 'exclusive' => 4];
    }

    private static function flushMode(string $mode): string
    {
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['normal', 'full', 'dataonly'], true)) {
            throw new \InvalidArgumentException('SQLite VFS current-source next198-201 flush mode is unsupported');
        }
        return $mode;
    }

    private static function receipt(string $handle, int $page, int $bytes): array
    {
        return [
            'page' => $page,
            'bytes' => $bytes,
            'digest' => substr(hash('sha256', $handle . ':' . $page . ':' . $bytes), 0, 16),
        ];
    }

    private static function mergeReceipts(array $durable, array $dirty): array
    {
        $byPage = [];
        foreach (array_merge($durable, $dirty) as $receipt) {
            if (is_array($receipt) && isset($receipt['page'])) {
                $byPage[(int) $receipt['page']] = $receipt;
            }
        }
        ksort($byPage);
        return array_values($byPage);
    }

    private static function dirtyPageMap(mixed $dirtyPages): array
    {
        if (!is_array($dirtyPages)) {
            return [];
        }
        $map = [];
        foreach ($dirtyPages as $key => $receipt) {
            if (!is_array($receipt)) {
                continue;
            }
            $page = isset($receipt['page']) ? (int) $receipt['page'] : (int) $key;
            if ($page > 0) {
                $map[$page] = $receipt + ['page' => $page];
            }
        }
        ksort($map);
        return $map;
    }

    private static function receiptList(mixed $receipts): array
    {
        return is_array($receipts) ? array_values($receipts) : [];
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

    private static function token(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:-]+$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite VFS current-source next198-201 {$label} is unsupported");
        }
        return $value;
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \InvalidArgumentException("SQLite VFS current-source next198-201 {$label} must be positive");
        }
        $int = (int) $value;
        if ($int <= 0) {
            throw new \InvalidArgumentException("SQLite VFS current-source next198-201 {$label} must be positive");
        }
        return $int;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit((string) $value))) {
            throw new \InvalidArgumentException("SQLite VFS current-source next198-201 {$label} must be non-negative");
        }
        $int = (int) $value;
        if ($int < 0) {
            throw new \InvalidArgumentException("SQLite VFS current-source next198-201 {$label} must be non-negative");
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
