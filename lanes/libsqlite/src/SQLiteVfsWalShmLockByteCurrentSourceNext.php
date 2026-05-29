<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsWalShmLockByteCurrentSourceNext
{
    /**
     * @param array<string,mixed> $current
     * @param list<array<string,mixed>|string> $operations
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function plan(array $current, array $operations): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS WAL SHM lock-byte current-source next89 requires operations');
        }

        $state = self::normalizeCurrent($current);
        $events = [];

        foreach ($operations as $operation) {
            $op = self::operation($operation);
            $path = self::path((string) ($op['path'] ?? $state['selected_path'] ?? '/srv/www/wp-content/database/.ht.sqlite'));
            $state['selected_path'] = $path;
            $state['sources'][$path] ??= self::source($path, []);
            $before = self::snapshot($state);

            if ($op['kind'] === 'lock') {
                $event = self::applyMainLock($state, $path, $op);
                $events[] = self::event('lock', $event['status'], $before, self::snapshot($state), $event);
                continue;
            }

            if ($op['kind'] === 'shm') {
                $event = self::applyShmLock($state, $path, $op);
                $events[] = self::event('shm', $event['status'], $before, self::snapshot($state), $event);
                continue;
            }

            if ($op['kind'] === 'yield') {
                $connection = self::connection((string) $op['connection']);
                unset($state['sources'][$path]['holders'][$connection]);
                unset($state['sources'][$path]['shared_slots'][$connection]);
                foreach ($state['sources'][$path]['shm_locks'] as $lock => $holders) {
                    unset($state['sources'][$path]['shm_locks'][$lock][$connection]);
                }
                $state['sources'][$path]['generation']++;
                $events[] = self::event('yield', 'released', $before, self::snapshot($state), [
                    'connection' => $connection,
                    'path' => $path,
                    'generation' => $state['sources'][$path]['generation'],
                ]);
                continue;
            }

            throw new \InvalidArgumentException('SQLite VFS WAL SHM lock-byte current-source next89 operation is unsupported');
        }

        return [
            'status' => (string) $events[array_key_last($events)]['status'],
            'current' => self::summary(self::normalizeCurrent($current)),
            'next' => self::summary($state),
            'events' => $events,
            'dependencies' => [
                'sqlite-lock-byte-range-current-next',
                'sqlite-wal-shm-locks',
                'vfs-wal-shm-lock-byte-current-source-next89',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $op
     * @return array<string,mixed>
     */
    private static function applyMainLock(array &$state, string $path, array $op): array
    {
        $source = &$state['sources'][$path];
        $connection = self::connection((string) $op['connection']);
        $level = self::level((string) $op['level']);
        $currentLevel = (string) ($source['holders'][$connection] ?? 'none');
        $currentSlot = (int) ($source['shared_slots'][$connection] ?? 0);
        $nextSlot = isset($op['shared_slot']) ? self::slot($op['shared_slot']) : $currentSlot;
        $nolock = (bool) ($source['nolock'] ?? false);

        $plan = SQLiteLockByteRangePlan::transition($path, $currentLevel, $level, $nolock, $level === 'none' ? null : $connection, $currentSlot, $nextSlot);
        $blocking = self::mainLockBlockers($source['holders'], $connection, $level);
        $status = $plan['status'];
        $reason = $plan['reason'];
        if ($status === 'planned' && $blocking !== []) {
            $status = 'blocked';
            $reason = 'main_lock_conflict';
        }

        if ($status === 'planned') {
            if ($level === 'none') {
                unset($source['holders'][$connection], $source['shared_slots'][$connection]);
            } else {
                $source['holders'][$connection] = $level;
                if (in_array($level, ['shared', 'reserved'], true)) {
                    $source['shared_slots'][$connection] = $nextSlot;
                }
            }
            $source['generation']++;
        }
        unset($source);

        return [
            'status' => $status,
            'connection' => $connection,
            'path' => $path,
            'level' => $level,
            'plan' => $plan,
            'blocking' => $blocking,
            'reason' => $reason,
            'generation' => $state['sources'][$path]['generation'],
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $op
     * @return array<string,mixed>
     */
    private static function applyShmLock(array &$state, string $path, array $op): array
    {
        $source = &$state['sources'][$path];
        $connection = self::connection((string) $op['connection']);
        $lock = self::shmLock((string) $op['lock']);
        $mode = self::shmMode((string) ($op['mode'] ?? 'shared'));

        if ($mode === 'unlock') {
            unset($source['shm_locks'][$lock][$connection]);
            $source['generation']++;
            unset($source);
            return [
                'status' => 'released',
                'connection' => $connection,
                'path' => $path,
                'lock' => $lock,
                'mode' => $mode,
                'blocking' => [],
                'reason' => null,
                'generation' => $state['sources'][$path]['generation'],
            ];
        }

        $blocking = self::shmLockBlockers($source['shm_locks'][$lock], $connection, $mode);
        $status = $blocking === [] ? 'acquired' : 'blocked';
        $reason = $blocking === [] ? null : 'shm_lock_conflict';
        if ($status === 'acquired') {
            $source['shm_locks'][$lock][$connection] = $mode;
            $source['generation']++;
        }
        unset($source);

        return [
            'status' => $status,
            'connection' => $connection,
            'path' => $path,
            'lock' => $lock,
            'mode' => $mode,
            'blocking' => $blocking,
            'reason' => $reason,
            'generation' => $state['sources'][$path]['generation'],
        ];
    }

    /**
     * @param array<string,string> $holders
     * @return list<string>
     */
    private static function mainLockBlockers(array $holders, string $connection, string $level): array
    {
        if ($level === 'none' || $level === 'shared') {
            return [];
        }

        $blocking = [];
        foreach ($holders as $holder => $held) {
            if ($holder === $connection) {
                continue;
            }
            if ($level === 'reserved' && in_array($held, ['reserved', 'pending', 'exclusive'], true)) {
                $blocking[] = $holder . ':' . $held;
            } elseif ($level === 'pending' && in_array($held, ['pending', 'exclusive'], true)) {
                $blocking[] = $holder . ':' . $held;
            } elseif ($level === 'exclusive') {
                $blocking[] = $holder . ':' . $held;
            }
        }

        sort($blocking);
        return $blocking;
    }

    /**
     * @param array<string,string> $holders
     * @return list<string>
     */
    private static function shmLockBlockers(array $holders, string $connection, string $mode): array
    {
        $blocking = [];
        foreach ($holders as $holder => $held) {
            if ($holder === $connection) {
                continue;
            }
            if ($mode === 'exclusive' || $held === 'exclusive') {
                $blocking[] = $holder . ':' . $held;
            }
        }

        sort($blocking);
        return $blocking;
    }

    /**
     * @param array<string,mixed> $current
     * @return array<string,mixed>
     */
    private static function normalizeCurrent(array $current): array
    {
        $state = [
            'selected_path' => isset($current['selected_path']) ? self::path((string) $current['selected_path']) : null,
            'sources' => [],
        ];

        $sources = is_array($current['sources'] ?? null) ? $current['sources'] : [];
        foreach ($sources as $path => $source) {
            $path = self::path((string) (($source['path'] ?? null) ?: $path));
            $state['sources'][$path] = self::source($path, is_array($source) ? $source : []);
        }

        return $state;
    }

    /**
     * @param array<string,mixed> $source
     * @return array<string,mixed>
     */
    private static function source(string $path, array $source): array
    {
        return [
            'path' => $path,
            'generation' => max(1, (int) ($source['generation'] ?? 1)),
            'nolock' => (bool) ($source['nolock'] ?? false),
            'holders' => self::stringMap($source['holders'] ?? []),
            'shared_slots' => self::intMap($source['shared_slots'] ?? []),
            'shm_locks' => self::shmLocks($source['shm_locks'] ?? []),
            'constants' => SQLiteLockByteRangePlan::constants(),
        ];
    }

    /**
     * @param mixed $locks
     * @return array<string,array<string,string>>
     */
    private static function shmLocks(mixed $locks): array
    {
        $normalized = ['read0' => [], 'read1' => [], 'read2' => [], 'read3' => [], 'read4' => [], 'write' => [], 'checkpoint' => [], 'recover' => []];
        if (!is_array($locks)) {
            return $normalized;
        }
        foreach ($locks as $name => $holders) {
            $lock = self::shmLock((string) $name);
            $normalized[$lock] = self::stringMap($holders);
        }

        return $normalized;
    }

    /**
     * @param mixed $values
     * @return array<string,string>
     */
    private static function stringMap(mixed $values): array
    {
        $out = [];
        if (is_array($values)) {
            foreach ($values as $key => $value) {
                if (is_string($key) && is_string($value) && $key !== '') {
                    $out[$key] = $value;
                }
            }
        }
        ksort($out);
        return $out;
    }

    /**
     * @param mixed $values
     * @return array<string,int>
     */
    private static function intMap(mixed $values): array
    {
        $out = [];
        if (is_array($values)) {
            foreach ($values as $key => $value) {
                if (is_string($key) && is_int($value)) {
                    $out[$key] = self::slot($value);
                }
            }
        }
        ksort($out);
        return $out;
    }

    /**
     * @return array<string,mixed>
     */
    private static function snapshot(array $state): array
    {
        return self::summary($state);
    }

    /**
     * @return array<string,mixed>
     */
    private static function summary(array $state): array
    {
        $sources = $state['sources'];
        ksort($sources);
        $selected = $state['selected_path'];
        $source = is_string($selected) && isset($sources[$selected]) ? $sources[$selected] : null;

        return [
            'selected_path' => $selected,
            'source_count' => count($sources),
            'sources' => $sources,
            'selected' => $source,
            'holder_count' => $source === null ? 0 : count($source['holders']),
            'shm_lock_count' => $source === null ? 0 : array_sum(array_map('count', $source['shm_locks'])),
        ];
    }

    /**
     * @param array<string,mixed> $detail
     * @return array<string,mixed>
     */
    private static function event(string $kind, string $status, array $current, array $next, array $detail): array
    {
        return $detail + [
            'kind' => $kind,
            'status' => $status,
            'current' => $current,
            'next' => $next,
        ];
    }

    /**
     * @param array<string,mixed>|string $operation
     * @return array<string,mixed>
     */
    private static function operation(array|string $operation): array
    {
        if (is_array($operation)) {
            $kind = strtolower((string) ($operation['op'] ?? $operation['kind'] ?? ''));
            return $operation + ['kind' => match ($kind) {
                'main', 'lock', 'byte', 'byte_lock' => 'lock',
                'shm', 'shmlock', 'shm_lock' => 'shm',
                'yield', 'release' => 'yield',
                default => $kind,
            }];
        }

        if (preg_match('/^lock\s+(?<level>none|shared|reserved|pending|exclusive)\s+(?<connection>[A-Za-z0-9_.:-]+)(?:\s+(?<slot>\d+))?$/i', trim($operation), $matches)) {
            return [
                'kind' => 'lock',
                'level' => strtolower($matches['level']),
                'connection' => $matches['connection'],
                'shared_slot' => isset($matches['slot']) ? (int) $matches['slot'] : null,
            ];
        }
        if (preg_match('/^shm\s+(?<lock>read[0-4]|write|checkpoint|recover)\s+(?<mode>shared|exclusive|unlock)\s+(?<connection>[A-Za-z0-9_.:-]+)$/i', trim($operation), $matches)) {
            return [
                'kind' => 'shm',
                'lock' => strtolower($matches['lock']),
                'mode' => strtolower($matches['mode']),
                'connection' => $matches['connection'],
            ];
        }
        if (preg_match('/^yield\s+(?<connection>[A-Za-z0-9_.:-]+)$/i', trim($operation), $matches)) {
            return ['kind' => 'yield', 'connection' => $matches['connection']];
        }

        throw new \InvalidArgumentException('SQLite VFS WAL SHM lock-byte current-source next89 operation string is unsupported');
    }

    private static function path(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            throw new \InvalidArgumentException('SQLite VFS WAL SHM lock-byte current-source next89 path is required');
        }

        return $path;
    }

    private static function connection(string $connection): string
    {
        $connection = trim($connection);
        if ($connection === '') {
            throw new \InvalidArgumentException('SQLite VFS WAL SHM lock-byte current-source next89 connection is required');
        }

        return $connection;
    }

    private static function level(string $level): string
    {
        $level = strtolower(trim($level));
        if (!in_array($level, ['none', 'shared', 'reserved', 'pending', 'exclusive'], true)) {
            throw new \InvalidArgumentException('SQLite VFS WAL SHM lock-byte current-source next89 lock level is unsupported');
        }

        return $level;
    }

    private static function shmLock(string $lock): string
    {
        $lock = strtolower(trim($lock));
        if (!in_array($lock, ['read0', 'read1', 'read2', 'read3', 'read4', 'write', 'checkpoint', 'recover'], true)) {
            throw new \InvalidArgumentException('SQLite VFS WAL SHM lock-byte current-source next89 SHM lock is unsupported');
        }

        return $lock;
    }

    private static function shmMode(string $mode): string
    {
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['shared', 'exclusive', 'unlock'], true)) {
            throw new \InvalidArgumentException('SQLite VFS WAL SHM lock-byte current-source next89 SHM mode is unsupported');
        }

        return $mode;
    }

    private static function slot(mixed $slot): int
    {
        if (!is_int($slot) || $slot < 0 || $slot >= SQLiteLockByteRangePlan::SHARED_SIZE) {
            throw new \InvalidArgumentException('SQLite VFS WAL SHM lock-byte current-source next89 shared slot is out of range');
        }

        return $slot;
    }
}
