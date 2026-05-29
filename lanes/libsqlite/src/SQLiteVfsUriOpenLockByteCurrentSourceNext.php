<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsUriOpenLockByteCurrentSourceNext
{
    /**
     * @param list<array<string,mixed>|string> $operations
     * @param array<string,mixed> $current
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function plan(array $operations, array $current = []): array
    {
        if (self::looksLikeSourceList($operations)) {
            return self::legacy84Plan($operations);
        }

        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS URI open lock-byte current-source next100 requires operations');
        }

        $state = self::normalizeCurrent($current);
        $initial = self::summary($state);
        $events = [];
        $dependencies = [
            'sqlite-file-uri',
            'sqlite-open-plan',
            'sqlite-lock-byte-range-current-next',
            'vfs-uri-open-lock-byte-current-source-next100',
        ];

        foreach ($operations as $index => $operation) {
            $op = self::operation($operation);
            $before = self::summary($state);

            if ($op['kind'] === 'open') {
                $event = self::open($state, $op);
            } elseif ($op['kind'] === 'lock') {
                $event = self::lock($state, $op);
            } elseif ($op['kind'] === 'close') {
                $event = self::close($state, $op);
            } else {
                throw new \InvalidArgumentException('SQLite VFS URI open lock-byte current-source next100 operation is unsupported');
            }

            $events[] = [
                'ordinal' => $index,
                'kind' => $op['kind'],
                'status' => $event['status'],
                'source' => $event['source'],
                'connection' => $event['connection'] ?? null,
                'current' => $before,
                'next' => self::summary($state),
                'result' => $event,
            ];
            $dependencies = self::dependencies($dependencies, $event['dependencies']);
        }

        return [
            'status' => (string) $events[array_key_last($events)]['status'],
            'current' => $initial,
            'next' => self::summary($state),
            'events' => $events,
            'dependencies' => $dependencies,
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $op
     * @return array<string,mixed>
     */
    private static function open(array &$state, array $op): array
    {
        $source = self::name($op['source'] ?? null);
        $filename = self::string($op['filename'], 'filename');
        $open = SQLiteOpenPlan::forFilename(
            $filename,
            (bool) ($op['file_exists'] ?? true),
            (bool) ($op['directory_writable'] ?? true),
            (bool) ($op['lock_available'] ?? true),
            isset($op['busy_timeout']) ? SQLiteBusyHandler::timeout(self::nonNegativeInt($op['busy_timeout'], 'busy_timeout')) : null
        );
        $path = (string) $open['path'];
        $entry = self::sourceEntry($source, $open, $state['sources'][$source] ?? null);
        $entry['open_count']++;
        $entry['last_connection'] = isset($op['connection']) ? self::name($op['connection']) : $entry['last_connection'];
        $entry['generation']++;
        $state['sources'][$source] = $entry;
        $state['selected_source'] = $source;
        $state['path_sources'][$path] ??= [];
        if (!in_array($source, $state['path_sources'][$path], true)) {
            $state['path_sources'][$path][] = $source;
            sort($state['path_sources'][$path]);
        }

        return [
            'status' => $open['can_open'] ? (($entry['open_count'] === 1) ? 'opened' : 'reopened') : 'blocked',
            'source' => $source,
            'connection' => $entry['last_connection'],
            'path' => $path,
            'open_count' => $entry['open_count'],
            'open' => $open,
            'dependencies' => self::dependencies(['vfs-uri-open-current-source'], $open['dependencies']),
            'reason' => $open['reason'],
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $op
     * @return array<string,mixed>
     */
    private static function lock(array &$state, array $op): array
    {
        $source = self::name($op['source'] ?? null);
        if (!isset($state['sources'][$source])) {
            throw new \InvalidArgumentException('SQLite VFS URI open lock-byte current-source next100 source is not open');
        }

        $connection = self::name($op['connection']);
        $level = self::level((string) $op['level']);
        $entry = &$state['sources'][$source];
        $currentLevel = (string) ($entry['holders'][$connection] ?? 'none');
        $currentSlot = (int) ($entry['shared_slots'][$connection] ?? 0);
        $nextSlot = isset($op['shared_slot']) ? self::slot($op['shared_slot']) : $currentSlot;
        $blockedReason = self::lockBlocker($entry, $level);
        $plan = SQLiteLockByteRangePlan::transition(
            (string) $entry['path'],
            $currentLevel,
            $level,
            $blockedReason !== null,
            $level === 'none' ? null : $connection,
            $currentSlot,
            $nextSlot
        );
        $blocking = $blockedReason === null ? self::blocking(self::pathHolders($state, (string) $entry['path']), $connection, $level) : [];
        $status = $blockedReason === null && $blocking === [] && $plan['status'] === 'planned' ? ($level === 'none' ? 'released' : 'planned') : 'blocked';
        $reason = $blockedReason ?? ($blocking === [] ? $plan['reason'] : 'byte_lock_conflict');

        if ($status !== 'blocked') {
            if ($level === 'none') {
                unset($entry['holders'][$connection], $entry['shared_slots'][$connection]);
            } else {
                $entry['holders'][$connection] = $level;
                if (in_array($level, ['shared', 'reserved'], true)) {
                    $entry['shared_slots'][$connection] = $nextSlot;
                } else {
                    unset($entry['shared_slots'][$connection]);
                }
            }
            $entry['generation']++;
        }
        $state['selected_source'] = $source;
        unset($entry);

        return [
            'status' => $status,
            'source' => $source,
            'connection' => $connection,
            'level' => $level,
            'plan' => $plan,
            'blocking' => $blocking,
            'dependencies' => self::dependencies(['vfs-uri-lock-byte-current-source'], $plan['dependencies']),
            'reason' => $reason,
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $op
     * @return array<string,mixed>
     */
    private static function close(array &$state, array $op): array
    {
        $source = self::name($op['source'] ?? null);
        if (!isset($state['sources'][$source])) {
            throw new \InvalidArgumentException('SQLite VFS URI open lock-byte current-source next100 close source is not open');
        }

        $connection = isset($op['connection']) ? self::name($op['connection']) : null;
        $entry = &$state['sources'][$source];
        $released = [];
        if ($connection !== null) {
            if (isset($entry['holders'][$connection])) {
                $released[$connection] = $entry['holders'][$connection];
            }
            unset($entry['holders'][$connection], $entry['shared_slots'][$connection]);
        } else {
            $released = $entry['holders'];
            $entry['holders'] = [];
            $entry['shared_slots'] = [];
        }

        $entry['open_count'] = max(0, (int) $entry['open_count'] - 1);
        $entry['generation']++;
        $status = $entry['open_count'] === 0 ? 'closed' : 'decremented';
        $path = (string) $entry['path'];
        if ($entry['open_count'] === 0) {
            unset($state['sources'][$source]);
            $state['path_sources'][$path] = array_values(array_diff($state['path_sources'][$path] ?? [], [$source]));
            if ($state['path_sources'][$path] === []) {
                unset($state['path_sources'][$path]);
            }
            $state['selected_source'] = array_key_last($state['sources']);
        } else {
            $state['selected_source'] = $source;
        }
        unset($entry);

        return [
            'status' => $status,
            'source' => $source,
            'connection' => $connection,
            'path' => $path,
            'released' => $released,
            'dependencies' => ['vfs-uri-open-lock-byte-current-source-next100', 'sqlite-lock-byte-release-on-close'],
            'reason' => null,
        ];
    }

    /**
     * @param array<string,mixed>|null $existing
     * @return array<string,mixed>
     */
    private static function sourceEntry(string $source, array $open, ?array $existing): array
    {
        $entry = is_array($existing) ? $existing : [];
        $entry['source'] = $source;
        $entry['path'] = (string) $open['path'];
        $entry['input'] = (string) ($open['uri']['input'] ?? $open['path']);
        $entry['uri'] = $open['uri'];
        $entry['mode'] = (string) $open['mode'];
        $entry['cache'] = $open['cache'];
        $entry['vfs'] = $open['vfs'];
        $entry['read_only'] = (bool) $open['read_only'];
        $entry['memory'] = (bool) $open['memory'];
        $entry['immutable'] = (bool) $open['immutable'];
        $entry['nolock'] = (bool) $open['nolock'];
        $entry['can_open'] = (bool) $open['can_open'];
        $entry['open_count'] = (int) ($entry['open_count'] ?? 0);
        $entry['holders'] = self::stringMap($entry['holders'] ?? []);
        $entry['shared_slots'] = self::intMap($entry['shared_slots'] ?? []);
        $entry['last_connection'] = isset($entry['last_connection']) && is_string($entry['last_connection']) ? $entry['last_connection'] : null;
        $entry['generation'] = (int) ($entry['generation'] ?? 0);

        return $entry;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function summary(array $state): array
    {
        $sources = [];
        $holderCount = 0;
        foreach ($state['sources'] as $source => $entry) {
            $holders = self::stringMap($entry['holders'] ?? []);
            $holderCount += count($holders);
            $sources[$source] = [
                'source' => $source,
                'path' => (string) $entry['path'],
                'input' => (string) $entry['input'],
                'mode' => (string) $entry['mode'],
                'cache' => $entry['cache'],
                'vfs' => $entry['vfs'],
                'read_only' => (bool) $entry['read_only'],
                'memory' => (bool) $entry['memory'],
                'immutable' => (bool) $entry['immutable'],
                'nolock' => (bool) $entry['nolock'],
                'can_open' => (bool) $entry['can_open'],
                'open_count' => (int) $entry['open_count'],
                'holders' => $holders,
                'shared_slots' => self::intMap($entry['shared_slots'] ?? []),
                'last_connection' => $entry['last_connection'],
                'generation' => (int) $entry['generation'],
            ];
        }

        return [
            'selected_source' => $state['selected_source'],
            'source_count' => count($sources),
            'holder_count' => $holderCount,
            'sources' => $sources,
            'path_sources' => $state['path_sources'],
            'constants' => SQLiteLockByteRangePlan::constants(),
        ];
    }

    /**
     * @param array<string,mixed> $current
     * @return array{sources:array<string,mixed>,path_sources:array<string,list<string>>,selected_source:string|null}
     */
    private static function normalizeCurrent(array $current): array
    {
        $state = [
            'sources' => [],
            'path_sources' => [],
            'selected_source' => isset($current['selected_source']) && is_string($current['selected_source']) ? $current['selected_source'] : null,
        ];

        foreach (($current['sources'] ?? []) as $source => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $sourceName = self::name(is_string($source) ? $source : ($entry['source'] ?? null));
            $path = self::string($entry['path'] ?? null, 'current path');
            $entry['source'] = $sourceName;
            $entry['path'] = $path;
            $entry['input'] = isset($entry['input']) && is_string($entry['input']) ? $entry['input'] : $path;
            $entry['uri'] = is_array($entry['uri'] ?? null) ? $entry['uri'] : SQLiteFileUri::parse($entry['input']);
            $entry['mode'] = isset($entry['mode']) && is_string($entry['mode']) ? $entry['mode'] : 'rwc';
            $entry['cache'] = $entry['cache'] ?? null;
            $entry['vfs'] = $entry['vfs'] ?? null;
            $entry['read_only'] = (bool) ($entry['read_only'] ?? false);
            $entry['memory'] = (bool) ($entry['memory'] ?? false);
            $entry['immutable'] = (bool) ($entry['immutable'] ?? false);
            $entry['nolock'] = (bool) ($entry['nolock'] ?? false);
            $entry['can_open'] = (bool) ($entry['can_open'] ?? true);
            $entry['open_count'] = max(0, (int) ($entry['open_count'] ?? 1));
            $entry['holders'] = self::stringMap($entry['holders'] ?? []);
            $entry['shared_slots'] = self::intMap($entry['shared_slots'] ?? []);
            $entry['last_connection'] = isset($entry['last_connection']) && is_string($entry['last_connection']) ? $entry['last_connection'] : null;
            $entry['generation'] = max(0, (int) ($entry['generation'] ?? 0));
            $state['sources'][$sourceName] = $entry;
            $state['path_sources'][$path] ??= [];
            $state['path_sources'][$path][] = $sourceName;
        }

        foreach ($state['path_sources'] as &$sources) {
            $sources = array_values(array_unique($sources));
            sort($sources);
        }
        unset($sources);

        return $state;
    }

    /**
     * @return array<string,mixed>
     */
    private static function operation(array|string $operation): array
    {
        if (is_string($operation)) {
            $parts = preg_split('/\s+/', trim($operation)) ?: [];
            return match ($parts[0] ?? '') {
                'open' => [
                    'kind' => 'open',
                    'source' => $parts[1] ?? null,
                    'filename' => $parts[2] ?? null,
                    'connection' => $parts[3] ?? null,
                ],
                'lock' => [
                    'kind' => 'lock',
                    'source' => $parts[1] ?? null,
                    'level' => $parts[2] ?? null,
                    'connection' => $parts[3] ?? null,
                    'shared_slot' => isset($parts[4]) ? (int) $parts[4] : null,
                ],
                'close' => [
                    'kind' => 'close',
                    'source' => $parts[1] ?? null,
                    'connection' => $parts[2] ?? null,
                ],
                default => throw new \InvalidArgumentException('SQLite VFS URI open lock-byte current-source next100 operation string is unsupported'),
            };
        }

        if (!is_array($operation)) {
            throw new \InvalidArgumentException('SQLite VFS URI open lock-byte current-source next100 operation must be a string or array');
        }

        $operation['kind'] = strtolower(self::string($operation['kind'] ?? $operation['op'] ?? null, 'operation kind'));
        return $operation;
    }

    /**
     * @param array<string,string> $holders
     * @return list<string>
     */
    private static function blocking(array $holders, string $connection, string $level): array
    {
        if ($level === 'none') {
            return [];
        }

        $blocking = [];
        foreach ($holders as $holder => $held) {
            if ($holder === $connection) {
                continue;
            }
            if ($level === 'shared' && in_array($held, ['pending', 'exclusive'], true)) {
                $blocking[] = $holder . ':' . $held;
            } elseif ($level === 'reserved' && in_array($held, ['reserved', 'pending', 'exclusive'], true)) {
                $blocking[] = $holder . ':' . $held;
            } elseif ($level === 'pending' && in_array($held, ['reserved', 'pending', 'exclusive'], true)) {
                $blocking[] = $holder . ':' . $held;
            } elseif ($level === 'exclusive') {
                $blocking[] = $holder . ':' . $held;
            }
        }

        sort($blocking);
        return $blocking;
    }

    /**
     * @param array<string,mixed> $entry
     */
    private static function lockBlocker(array $entry, string $level): ?string
    {
        if ($entry['open_count'] <= 0 || $entry['can_open'] !== true) {
            return 'source_is_not_open';
        }
        if ($entry['memory']) {
            return 'memory_uri_has_private_lock_bytes';
        }
        if ($entry['immutable']) {
            return 'immutable_uri_disables_lock_bytes';
        }
        if ($entry['nolock']) {
            return 'nolock_uri_disables_lock_bytes';
        }
        if ($entry['read_only'] && in_array($level, ['reserved', 'pending', 'exclusive'], true)) {
            return 'readonly_uri_disables_writer_lock';
        }

        return null;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,string>
     */
    private static function pathHolders(array $state, string $path): array
    {
        $holders = [];
        foreach ($state['sources'] as $source => $entry) {
            if (($entry['path'] ?? null) !== $path) {
                continue;
            }
            foreach (self::stringMap($entry['holders'] ?? []) as $connection => $level) {
                $holders[$connection] = $level;
            }
        }

        return $holders;
    }

    private static function level(string $level): string
    {
        $level = strtolower(trim($level));
        if (!in_array($level, ['none', 'shared', 'reserved', 'pending', 'exclusive'], true)) {
            throw new \InvalidArgumentException('SQLite VFS URI open lock-byte current-source next100 lock level is unsupported');
        }

        return $level;
    }

    private static function slot(mixed $slot): int
    {
        if (!is_int($slot) || $slot < 0 || $slot >= SQLiteLockByteRangePlan::SHARED_SIZE) {
            throw new \InvalidArgumentException('SQLite VFS URI open lock-byte current-source next100 shared slot is out of range');
        }

        return $slot;
    }

    private static function name(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException('SQLite VFS URI open lock-byte current-source next100 name is required');
        }

        return trim($value);
    }

    private static function string(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite VFS URI open lock-byte current-source next100 {$label} is required");
        }

        return $value;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite VFS URI open lock-byte current-source next100 {$label} must be a non-negative integer");
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @return array<string,string>
     */
    private static function stringMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && is_string($item)) {
                $out[$key] = $item;
            }
        }

        return $out;
    }

    /**
     * @param mixed $value
     * @return array<string,int>
     */
    private static function intMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && is_int($item)) {
                $out[$key] = $item;
            }
        }

        return $out;
    }

    /**
     * @param list<string> ...$sets
     * @return list<string>
     */
    private static function dependencies(array ...$sets): array
    {
        return array_values(array_unique(array_merge(...$sets)));
    }

    /**
     * @param list<array<string,mixed>|string> $operations
     */
    private static function looksLikeSourceList(array $operations): bool
    {
        if ($operations === []) {
            return false;
        }

        $first = $operations[array_key_first($operations)];
        if (is_string($first)) {
            return !preg_match('/^(open|lock|close)\b/i', trim($first));
        }

        return is_array($first) && (array_key_exists('filename', $first) || array_key_exists('name', $first)) && !array_key_exists('kind', $first) && !array_key_exists('op', $first);
    }

/**
     * @param list<array<string, mixed>> $sources
     * @return array{status:string,count:int,current:array<string, mixed>,next:array<string, mixed>,events:list<array<string, mixed>>,dependencies:list<string>}
     */
    private static function legacy84Plan(array $sources): array
    {
        if ($sources === []) {
            throw new \InvalidArgumentException('SQLite VFS URI open lock-byte current-source next84 requires sources');
        }

        $locks = new SQLiteVfsLockState();
        $current = ['sources' => [], 'holders' => [], 'selected_source' => null];
        $events = [];
        $dependencies = ['vfs-uri-open-lock-byte-current-source-next84'];

        foreach ($sources as $ordinal => $source) {
            $name = self::legacy84SourceName($source['name'] ?? ('source' . $ordinal));
            $filename = self::legacy84SourceString($source['filename'] ?? null, 'filename');
            $open = SQLiteOpenPlan::forFilename(
                $filename,
                (bool) ($source['file_exists'] ?? true),
                (bool) ($source['directory_writable'] ?? true),
                (bool) ($source['lock_available'] ?? true),
                isset($source['busy_timeout']) ? SQLiteBusyHandler::timeout(self::legacy84NonNegativeInt($source['busy_timeout'], 'busy_timeout')) : null
            );
            $operations = self::legacy84Operations($source['operations'] ?? []);
            $sourceCurrent = self::legacy84SourceSnapshot($name, $open, $locks);
            $dependencies = self::legacy84Dependencies($dependencies, $open['dependencies']);

            if (($open['can_open'] ?? false) !== true) {
                $next = $current;
                $next['sources'][$name] = $sourceCurrent;
                $events[] = [
                    'ordinal' => $ordinal,
                    'kind' => 'open',
                    'source' => $name,
                    'status' => 'blocked',
                    'current_source' => null,
                    'open' => $open,
                    'next_source' => $sourceCurrent,
                    'reason' => $open['reason'],
                    'dependencies' => self::legacy84Dependencies(['vfs-uri-open-source'], $open['dependencies']),
                ];
                $current = $next;
                continue;
            }

            $next = $current;
            $next['sources'][$name] = $sourceCurrent;
            $next['selected_source'] = $name;
            $events[] = [
                'ordinal' => $ordinal,
                'kind' => 'open',
                'source' => $name,
                'status' => 'ready',
                'current_source' => $current['sources'][$name] ?? null,
                'open' => $open,
                'next_source' => $sourceCurrent,
                'reason' => null,
                'dependencies' => self::legacy84Dependencies(['vfs-uri-open-source'], $open['dependencies']),
            ];
            $current = $next;

            foreach ($operations as $operation) {
                $before = self::legacy84SourceSnapshot($name, $open, $locks);
                $lockPlan = SQLiteLockByteRangePlan::forOpenPlan(
                    $open,
                    $operation['level'],
                    $operation['connection'],
                    $operation['shared_slot']
                );
                $result = $locks->acquire($lockPlan);
                $after = self::legacy84SourceSnapshot($name, $open, $locks);
                $current['sources'][$name] = $after;
                $current['holders'] = self::legacy84AllHolders($current['sources']);
                $current['selected_source'] = $name;
                $dependencies = self::legacy84Dependencies($dependencies, $lockPlan['dependencies'], $result['dependencies']);

                $events[] = [
                    'ordinal' => count($events),
                    'kind' => 'lock',
                    'source' => $name,
                    'level' => $operation['level'],
                    'connection' => $operation['connection'],
                    'current_source' => $before,
                    'plan' => $lockPlan,
                    'result' => $result,
                    'next_source' => $after,
                    'dependencies' => self::legacy84Dependencies(['vfs-uri-open-lock-byte-current-source-next84'], $lockPlan['dependencies'], $result['dependencies']),
                ];
            }
        }

        return [
            'status' => self::legacy84Status($events),
            'count' => count($events),
            'current' => ['sources' => [], 'holders' => [], 'selected_source' => null],
            'next' => $current,
            'events' => $events,
            'dependencies' => array_values(array_unique($dependencies)),
        ];
    }

    /**
     * @param array<string, mixed> $open
     * @return array{name:string,path:string,input:string,is_uri:bool,mode:string,cache:string|null,vfs:string|null,nolock:bool,immutable:bool,can_open:bool,holders:array<string, string>,constants:array<string, int>,dependencies:list<string>}
     */
    private static function legacy84SourceSnapshot(string $name, array $open, SQLiteVfsLockState $locks): array
    {
        $path = (string) $open['path'];

        return [
            'name' => $name,
            'path' => $path,
            'input' => (string) ($open['uri']['input'] ?? $path),
            'is_uri' => (bool) ($open['uri']['is_uri'] ?? false),
            'mode' => (string) $open['mode'],
            'cache' => $open['cache'],
            'vfs' => $open['vfs'],
            'nolock' => (bool) $open['nolock'],
            'immutable' => (bool) $open['immutable'],
            'can_open' => (bool) $open['can_open'],
            'holders' => $locks->holders($path),
            'constants' => SQLiteLockByteRangePlan::constants(),
            'dependencies' => self::legacy84Dependencies(['vfs-current-source-open'], $open['dependencies']),
        ];
    }

    /**
     * @param mixed $operations
     * @return list<array{level:string,connection:string,shared_slot:int}>
     */
    private static function legacy84Operations(mixed $operations): array
    {
        if (!is_array($operations)) {
            throw new \InvalidArgumentException('SQLite VFS next84 source operations must be a list');
        }

        $normalized = [];
        foreach ($operations as $operation) {
            if (is_string($operation)) {
                if (!preg_match('/^(?<level>shared|reserved|pending|exclusive)\s+(?<connection>[A-Za-z0-9_.:-]+)(?:\s+(?<slot>\d+))?$/i', trim($operation), $matches)) {
                    throw new \InvalidArgumentException('SQLite VFS next84 lock operation is unsupported');
                }
                $normalized[] = [
                    'level' => strtolower($matches['level']),
                    'connection' => $matches['connection'],
                    'shared_slot' => isset($matches['slot']) && $matches['slot'] !== '' ? (int) $matches['slot'] : 0,
                ];
                continue;
            }

            if (!is_array($operation)) {
                throw new \InvalidArgumentException('SQLite VFS next84 lock operation must be a string or array');
            }

            $normalized[] = [
                'level' => strtolower(self::legacy84SourceString($operation['level'] ?? 'shared', 'lock level')),
                'connection' => self::legacy84SourceName($operation['connection'] ?? null),
                'shared_slot' => self::legacy84NonNegativeInt($operation['shared_slot'] ?? 0, 'shared_slot'),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string, array<string, mixed>> $sources
     * @return array<string, array<string, string>>
     */
    private static function legacy84AllHolders(array $sources): array
    {
        $holders = [];
        foreach ($sources as $name => $source) {
            $holders[$name] = $source['holders'];
        }

        return $holders;
    }

    private static function legacy84Status(array $events): string
    {
        foreach (array_reverse($events) as $event) {
            if (($event['kind'] ?? null) === 'lock') {
                return (string) ($event['result']['status'] ?? 'planned');
            }
            if (($event['status'] ?? null) === 'blocked') {
                return 'blocked';
            }
        }

        return 'ready';
    }

    private static function legacy84SourceName(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException('SQLite VFS next84 source name must not be empty');
        }

        return trim($value);
    }

    private static function legacy84SourceString(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite VFS next84 {$label} must not be empty");
        }

        return $value;
    }

    private static function legacy84NonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite VFS next84 {$label} must be a non-negative integer");
        }

        return $value;
    }

    /**
     * @param list<string> ...$sets
     * @return list<string>
     */
    private static function legacy84Dependencies(array ...$sets): array
    {
        return array_values(array_unique(array_merge(...$sets)));
    }
}
