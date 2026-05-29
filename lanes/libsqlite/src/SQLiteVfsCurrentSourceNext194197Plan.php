<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsCurrentSourceNext194197Plan
{
    /**
     * @param list<array<string, mixed>|string> $operations
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function run(array $operations, array $options = []): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS current-source next194-197 requires operations');
        }

        $state = self::hydrate($options['current'] ?? []);
        $current = self::summary($state);
        $events = [];

        foreach ($operations as $operation) {
            $op = self::operation($operation);
            $before = self::summary($state);

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

            if ($op['kind'] === 'open') {
                $source = self::sourceName((string) $op['source']);
                $path = self::pathName((string) $op['path']);
                $owner = self::owner($path);
                $state['sequence']++;
                $state['owner_generations'][$owner] = (int) ($state['owner_generations'][$owner] ?? 0) + 1;
                $state['sources'][$source] = self::sourceState(
                    'vfs194197-' . $state['sequence'],
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

            $source = self::sourceFor($state, $op['source'] ?? null);
            if (!isset($state['sources'][$source]) || $state['sources'][$source]['closed'] === true) {
                $events[] = self::event($op['kind'], 'missing-source', $source, $before, self::summary($state), []);
                continue;
            }

            if ($op['kind'] === 'write') {
                $page = self::positiveInt($op['page'] ?? null, 'write page');
                $bytes = self::positiveInt($op['bytes'] ?? 4096, 'write bytes');
                if ($state['sources'][$source]['readonly'] === true || self::lockRank($state['sources'][$source]['lock']) < self::lockRank('reserved')) {
                    $events[] = self::event('write_receipt', 'blocked', $source, $before, self::summary($state), [
                        'page' => $page,
                        'bytes' => $bytes,
                        'readonly' => $state['sources'][$source]['readonly'],
                        'lock' => $state['sources'][$source]['lock'],
                    ]);
                    continue;
                }
                $receipt = [
                    'page' => $page,
                    'bytes' => $bytes,
                    'digest' => substr(hash('sha256', $state['sources'][$source]['handle'] . ':' . $page . ':' . $bytes), 0, 16),
                ];
                $state['sources'][$source]['write_receipts'][] = $receipt;
                $state['sources'][$source]['data_version']++;
                $events[] = self::event('write_receipt', 'recorded', $source, $before, self::summary($state), [
                    'receipt' => $receipt,
                    'write_count' => count($state['sources'][$source]['write_receipts']),
                    'data_version' => $state['sources'][$source]['data_version'],
                ]);
                continue;
            }

            if ($op['kind'] === 'sync') {
                $mode = self::syncMode((string) ($op['mode'] ?? 'normal'));
                $pending = count($state['sources'][$source]['write_receipts']) - count($state['sources'][$source]['durable_receipts']);
                if ($pending <= 0) {
                    $events[] = self::event('sync_receipt', 'noop', $source, $before, self::summary($state), [
                        'mode' => $mode,
                        'pending_receipts' => 0,
                    ]);
                    continue;
                }
                $state['sources'][$source]['sync_count']++;
                $state['sources'][$source]['durable_receipts'] = $state['sources'][$source]['write_receipts'];
                $events[] = self::event('sync_receipt', 'synced', $source, $before, self::summary($state), [
                    'mode' => $mode,
                    'pending_receipts' => $pending,
                    'sync_count' => $state['sources'][$source]['sync_count'],
                    'durable_count' => count($state['sources'][$source]['durable_receipts']),
                ]);
                continue;
            }

            if ($op['kind'] === 'barrier') {
                $token = self::token((string) ($op['token'] ?? 'barrier'), 'barrier token');
                $state['sources'][$source]['barriers'][] = [
                    'token' => $token,
                    'data_version' => $state['sources'][$source]['data_version'],
                    'durable_count' => count($state['sources'][$source]['durable_receipts']),
                ];
                $events[] = self::event('barrier', 'recorded', $source, $before, self::summary($state), [
                    'token' => $token,
                    'barrier_count' => count($state['sources'][$source]['barriers']),
                ]);
                continue;
            }

            if ($op['kind'] === 'close') {
                $state['sources'][$source]['closed'] = true;
                if ($state['current_source'] === $source) {
                    $state['current_source'] = self::firstOpenSource($state);
                }
                $events[] = self::event('close', 'closed', $source, $before, self::summary($state), [
                    'durable_count' => count($state['sources'][$source]['durable_receipts']),
                    'barrier_count' => count($state['sources'][$source]['barriers']),
                ]);
                continue;
            }

            throw new \InvalidArgumentException('SQLite VFS current-source next194-197 operation is unsupported');
        }

        return [
            'status' => (string) ($events[array_key_last($events)]['status'] ?? 'ok'),
            'current' => $current,
            'next' => self::summary($state),
            'events' => $events,
            'dependencies' => [
                'vfs-current-source-temp-dir-readonly-next182-185',
                'vfs-current-source-lock-filecontrol-next186-189',
                'vfs-current-source-ready-next190-193',
                'vfs-current-source-durable-receipts-next194-197',
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
                is_array($source['barriers'] ?? null) ? array_values($source['barriers']) : [],
                self::nonNegativeInt($source['sync_count'] ?? 0, 'sync count'),
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
                'xsync', 'durablesync' => 'sync',
                'xshmbarrier', 'syncbarrier' => 'barrier',
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
        if (preg_match('/^sync\s*\(\s*(?<mode>normal|full|dataonly)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'sync', 'mode' => $matches['mode']];
        }
        if (preg_match('/^barrier\s*\(\s*(?<token>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'barrier', 'token' => $matches['token']];
        }
        if (preg_match('/^close\s*\(\s*(?<source>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'close', 'source' => $matches['source']];
        }
        throw new \InvalidArgumentException('SQLite VFS current-source next194-197 operation is unsupported');
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
        array $barriers = [],
        int $syncCount = 0,
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
            'barriers' => $barriers,
            'sync_count' => $syncCount,
            'closed' => $closed,
        ];
    }

    private static function sourceFor(array $state, mixed $source): string
    {
        if ($source !== null && $source !== '') {
            return self::sourceName((string) $source);
        }
        if (!is_string($state['current_source'])) {
            throw new \InvalidArgumentException('SQLite VFS current-source next194-197 has no selected source');
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
            throw new \InvalidArgumentException('SQLite VFS current-source next194-197 lock level is unsupported');
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

    private static function syncMode(string $mode): string
    {
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['normal', 'full', 'dataonly'], true)) {
            throw new \InvalidArgumentException('SQLite VFS current-source next194-197 sync mode is unsupported');
        }
        return $mode;
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
            throw new \InvalidArgumentException("SQLite VFS current-source next194-197 {$label} is unsupported");
        }
        return $value;
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \InvalidArgumentException("SQLite VFS current-source next194-197 {$label} must be positive");
        }
        $int = (int) $value;
        if ($int <= 0) {
            throw new \InvalidArgumentException("SQLite VFS current-source next194-197 {$label} must be positive");
        }
        return $int;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit((string) $value))) {
            throw new \InvalidArgumentException("SQLite VFS current-source next194-197 {$label} must be non-negative");
        }
        $int = (int) $value;
        if ($int < 0) {
            throw new \InvalidArgumentException("SQLite VFS current-source next194-197 {$label} must be non-negative");
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
