<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsLockByteUriShmCurrentSourceNext93
{
    /**
     * @param array<string,mixed> $current
     * @param list<array<string,mixed>|string> $operations
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function plan(array $current, array $operations, string $filename): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS lock-byte URI SHM current-source next93 requires operations');
        }

        $open = self::open($filename);
        $state = self::normalizeCurrent($current);
        $sourceKey = $open['source_key'];
        $state['selected_source'] = $sourceKey;
        $state['sources'][$sourceKey] ??= self::source($open, []);

        $events = [];
        foreach ($operations as $operation) {
            $op = self::operation($operation);
            $before = self::summary($state);
            $source = &$state['sources'][$sourceKey];

            if ($op['kind'] === 'main') {
                $event = self::mainLock($source, $open, $op);
                unset($source);
                $events[] = self::event('main', $event['status'], $before, self::summary($state), $event);
                continue;
            }

            if ($op['kind'] === 'shm') {
                $event = self::shmLock($source, $open, $op);
                unset($source);
                $events[] = self::event('shm', $event['status'], $before, self::summary($state), $event);
                continue;
            }

            if ($op['kind'] === 'release') {
                $connection = self::connection((string) $op['connection']);
                unset($source['main_holders'][$connection], $source['shared_slots'][$connection]);
                foreach ($source['shm_locks'] as $lock => $holders) {
                    unset($source['shm_locks'][$lock][$connection]);
                }
                $source['generation']++;
                $event = [
                    'connection' => $connection,
                    'source_key' => $sourceKey,
                    'generation' => $source['generation'],
                    'status' => 'released',
                    'reason' => null,
                ];
                unset($source);
                $events[] = self::event('release', 'released', $before, self::summary($state), $event);
                continue;
            }

            unset($source);
            throw new \InvalidArgumentException('SQLite VFS lock-byte URI SHM current-source next93 operation is unsupported');
        }

        return [
            'status' => (string) $events[array_key_last($events)]['status'],
            'current' => self::summary(self::normalizeCurrent($current)),
            'next' => self::summary($state),
            'events' => $events,
            'dependencies' => [
                'sqlite-file-uri',
                'sqlite-lock-byte-range-current-next',
                'sqlite-wal-shm-locks',
                'vfs-lock-byte-uri-shm-current-source-next93',
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function open(string $filename): array
    {
        $uri = SQLiteFileUri::parse($filename);
        $memory = ($uri['mode'] ?? null) === 'memory' || $uri['path'] === ':memory:';
        $path = (string) $uri['path'];
        $sourceKey = $memory ? 'memory:' . sha1($filename) : $path;
        if (!$memory && $sourceKey === '') {
            throw new \InvalidArgumentException('SQLite VFS lock-byte URI SHM current-source next93 requires a database path');
        }

        return [
            'filename' => $filename,
            'uri' => $uri,
            'path' => $path,
            'source_key' => $sourceKey,
            'shm_key' => $memory ? $sourceKey . ':private-shm' : $sourceKey . '-shm',
            'persistent' => !$memory,
            'readonly' => ($uri['mode'] ?? null) === 'ro' || ($uri['immutable'] ?? false) === true,
            'immutable' => ($uri['immutable'] ?? false) === true,
            'nolock' => ($uri['nolock'] ?? false) === true,
        ];
    }

    /**
     * @param array<string,mixed> $source
     * @param array<string,mixed> $open
     * @param array<string,mixed> $op
     * @return array<string,mixed>
     */
    private static function mainLock(array &$source, array $open, array $op): array
    {
        $connection = self::connection((string) $op['connection']);
        $level = self::level((string) $op['level']);
        $currentLevel = (string) ($source['main_holders'][$connection] ?? 'none');
        $currentSlot = (int) ($source['shared_slots'][$connection] ?? 0);
        $nextSlot = isset($op['shared_slot']) ? self::slot($op['shared_slot']) : $currentSlot;

        $blockedReason = null;
        if (!$open['persistent']) {
            $blockedReason = 'memory_uri_has_private_lock_bytes';
        } elseif ($open['immutable']) {
            $blockedReason = 'immutable_uri_disables_lock_bytes';
        } elseif ($open['nolock']) {
            $blockedReason = 'nolock_uri_disables_lock_bytes';
        } elseif ($open['readonly'] && in_array($level, ['reserved', 'pending', 'exclusive'], true)) {
            $blockedReason = 'readonly_uri_disables_writer_lock';
        }

        $plan = SQLiteLockByteRangePlan::transition(
            (string) $open['source_key'],
            $currentLevel,
            $level,
            $blockedReason !== null,
            $level === 'none' ? null : $connection,
            $currentSlot,
            $nextSlot
        );
        $blocking = $blockedReason === null ? self::mainBlockers($source['main_holders'], $connection, $level) : [];
        $status = $blockedReason === null && $blocking === [] && $plan['status'] === 'planned' ? 'planned' : 'blocked';
        $reason = $blockedReason ?? ($blocking === [] ? $plan['reason'] : 'main_lock_conflict');

        if ($status === 'planned') {
            if ($level === 'none') {
                unset($source['main_holders'][$connection], $source['shared_slots'][$connection]);
            } else {
                $source['main_holders'][$connection] = $level;
                if (in_array($level, ['shared', 'reserved'], true)) {
                    $source['shared_slots'][$connection] = $nextSlot;
                }
            }
            $source['generation']++;
        }

        return [
            'status' => $status,
            'connection' => $connection,
            'level' => $level,
            'source_key' => $open['source_key'],
            'shm_key' => $open['shm_key'],
            'plan' => $plan,
            'blocking' => $blocking,
            'reason' => $reason,
            'generation' => $source['generation'],
        ];
    }

    /**
     * @param array<string,mixed> $source
     * @param array<string,mixed> $open
     * @param array<string,mixed> $op
     * @return array<string,mixed>
     */
    private static function shmLock(array &$source, array $open, array $op): array
    {
        $connection = self::connection((string) $op['connection']);
        $lock = self::shmName((string) $op['lock']);
        $mode = self::shmMode((string) ($op['mode'] ?? 'shared'));

        if ($mode === 'unlock') {
            unset($source['shm_locks'][$lock][$connection]);
            $source['generation']++;
            return [
                'status' => 'released',
                'connection' => $connection,
                'lock' => $lock,
                'mode' => $mode,
                'source_key' => $open['source_key'],
                'shm_key' => $open['shm_key'],
                'blocking' => [],
                'reason' => null,
                'generation' => $source['generation'],
            ];
        }

        $blockedReason = null;
        if (!$open['persistent']) {
            $blockedReason = 'memory_uri_has_private_shm';
        } elseif ($open['immutable']) {
            $blockedReason = 'immutable_uri_disables_shm_locking';
        } elseif ($open['nolock']) {
            $blockedReason = 'nolock_uri_disables_shm_locking';
        } elseif ($open['readonly'] && $mode === 'exclusive') {
            $blockedReason = 'readonly_uri_disables_exclusive_shm_lock';
        }

        $blocking = $blockedReason === null ? self::shmBlockers($source['shm_locks'][$lock], $connection, $mode) : [];
        $status = $blockedReason === null && $blocking === [] ? 'acquired' : 'blocked';
        $reason = $blockedReason ?? ($blocking === [] ? null : 'shm_lock_conflict');
        if ($status === 'acquired') {
            $source['shm_locks'][$lock][$connection] = $mode;
            $source['generation']++;
        }

        return [
            'status' => $status,
            'connection' => $connection,
            'lock' => $lock,
            'mode' => $mode,
            'source_key' => $open['source_key'],
            'shm_key' => $open['shm_key'],
            'blocking' => $blocking,
            'reason' => $reason,
            'generation' => $source['generation'],
        ];
    }

    /**
     * @param array<string,string> $holders
     * @return list<string>
     */
    private static function mainBlockers(array $holders, string $connection, string $level): array
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
    private static function shmBlockers(array $holders, string $connection, string $mode): array
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
            'selected_source' => isset($current['selected_source']) ? (string) $current['selected_source'] : null,
            'sources' => [],
        ];
        foreach ((is_array($current['sources'] ?? null) ? $current['sources'] : []) as $key => $source) {
            if (!is_array($source)) {
                continue;
            }
            $sourceKey = (string) (($source['source_key'] ?? null) ?: $key);
            $state['sources'][$sourceKey] = self::source([
                'source_key' => $sourceKey,
                'shm_key' => (string) ($source['shm_key'] ?? ($sourceKey . '-shm')),
                'path' => (string) ($source['path'] ?? $sourceKey),
                'uri' => is_array($source['uri'] ?? null) ? $source['uri'] : SQLiteFileUri::parse((string) ($source['path'] ?? $sourceKey)),
                'persistent' => (bool) ($source['persistent'] ?? true),
                'readonly' => (bool) ($source['readonly'] ?? false),
                'immutable' => (bool) ($source['immutable'] ?? false),
                'nolock' => (bool) ($source['nolock'] ?? false),
            ], $source);
        }

        return $state;
    }

    /**
     * @param array<string,mixed> $open
     * @param array<string,mixed> $source
     * @return array<string,mixed>
     */
    private static function source(array $open, array $source): array
    {
        return [
            'source_key' => (string) $open['source_key'],
            'path' => (string) $open['path'],
            'shm_key' => (string) $open['shm_key'],
            'uri' => $open['uri'],
            'persistent' => (bool) $open['persistent'],
            'readonly' => (bool) $open['readonly'],
            'immutable' => (bool) $open['immutable'],
            'nolock' => (bool) $open['nolock'],
            'generation' => max(1, (int) ($source['generation'] ?? 1)),
            'main_holders' => self::stringMap($source['main_holders'] ?? []),
            'shared_slots' => self::intMap($source['shared_slots'] ?? []),
            'shm_locks' => self::shmLocks($source['shm_locks'] ?? []),
            'constants' => SQLiteLockByteRangePlan::constants(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function summary(array $state): array
    {
        $sources = $state['sources'];
        ksort($sources);
        $selected = $state['selected_source'];
        $source = is_string($selected) && isset($sources[$selected]) ? $sources[$selected] : null;

        return [
            'selected_source' => $selected,
            'source_count' => count($sources),
            'sources' => $sources,
            'selected' => $source,
            'main_holder_count' => $source === null ? 0 : count($source['main_holders']),
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
            $kind = strtolower(str_replace(['_', '-'], '', (string) ($operation['op'] ?? $operation['kind'] ?? '')));
            return $operation + ['kind' => match ($kind) {
                'main', 'lock', 'bytelock' => 'main',
                'shm', 'shmlock' => 'shm',
                'release', 'yield' => 'release',
                default => $kind,
            }];
        }

        $trimmed = trim($operation);
        if (preg_match('/^main\s+(?<level>none|shared|reserved|pending|exclusive)\s+(?<connection>[A-Za-z0-9_.:-]+)(?:\s+(?<slot>\d+))?$/i', $trimmed, $matches)) {
            return [
                'kind' => 'main',
                'level' => strtolower($matches['level']),
                'connection' => $matches['connection'],
                'shared_slot' => isset($matches['slot']) ? (int) $matches['slot'] : null,
            ];
        }
        if (preg_match('/^shm\s+(?<lock>read[0-4]|write|checkpoint|recover)\s+(?<mode>shared|exclusive|unlock)\s+(?<connection>[A-Za-z0-9_.:-]+)$/i', $trimmed, $matches)) {
            return [
                'kind' => 'shm',
                'lock' => strtolower($matches['lock']),
                'mode' => strtolower($matches['mode']),
                'connection' => $matches['connection'],
            ];
        }
        if (preg_match('/^release\s+(?<connection>[A-Za-z0-9_.:-]+)$/i', $trimmed, $matches)) {
            return ['kind' => 'release', 'connection' => $matches['connection']];
        }

        throw new \InvalidArgumentException('SQLite VFS lock-byte URI SHM current-source next93 operation string is unsupported');
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
            $normalized[self::shmName((string) $name)] = self::stringMap($holders);
        }

        return $normalized;
    }

    private static function connection(string $connection): string
    {
        $connection = trim($connection);
        if ($connection === '') {
            throw new \InvalidArgumentException('SQLite VFS lock-byte URI SHM current-source next93 connection is required');
        }

        return $connection;
    }

    private static function level(string $level): string
    {
        $level = strtolower(trim($level));
        if (!in_array($level, ['none', 'shared', 'reserved', 'pending', 'exclusive'], true)) {
            throw new \InvalidArgumentException('SQLite VFS lock-byte URI SHM current-source next93 lock level is unsupported');
        }

        return $level;
    }

    private static function shmName(string $lock): string
    {
        $lock = strtolower(trim($lock));
        if (!in_array($lock, ['read0', 'read1', 'read2', 'read3', 'read4', 'write', 'checkpoint', 'recover'], true)) {
            throw new \InvalidArgumentException('SQLite VFS lock-byte URI SHM current-source next93 SHM lock is unsupported');
        }

        return $lock;
    }

    private static function shmMode(string $mode): string
    {
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['shared', 'exclusive', 'unlock'], true)) {
            throw new \InvalidArgumentException('SQLite VFS lock-byte URI SHM current-source next93 SHM mode is unsupported');
        }

        return $mode;
    }

    private static function slot(mixed $slot): int
    {
        if (!is_int($slot) || $slot < 0 || $slot >= SQLiteLockByteRangePlan::SHARED_SIZE) {
            throw new \InvalidArgumentException('SQLite VFS lock-byte URI SHM current-source next93 shared slot is out of range');
        }

        return $slot;
    }
}
