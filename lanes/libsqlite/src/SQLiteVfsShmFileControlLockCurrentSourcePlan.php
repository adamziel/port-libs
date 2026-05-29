<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsShmFileControlLockCurrentSourcePlan
{
    /**
     * @param list<string|array<string,mixed>> $operations
     * @param array<string,mixed> $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function currentSourceNext87(array $operations, array $options = []): array
    {
        return self::runCurrentSourceNext($operations, $options, 'vfs-shm-filecontrol-lock-current-source-next87');
    }

    /**
     * @param list<string|array<string,mixed>> $operations
     * @param array<string,mixed> $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function currentSourceNext92(array $operations, array $options = []): array
    {
        return self::runCurrentSourceNext($operations, $options, 'vfs-uri-shm-filecontrol-current-source-next92');
    }

    /**
     * @param list<string|array<string,mixed>> $operations
     * @param array<string,mixed> $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function currentSourceNext104(array $operations, array $options = []): array
    {
        return self::runCurrentSourceNext($operations, $options, 'vfs-uri-shm-filecontrol-current-source-next104', true);
    }

    /**
     * @param list<string|array<string,mixed>> $operations
     * @param array<string,mixed> $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function currentSourceNext126(array $operations, array $options = []): array
    {
        return self::runCurrentSourceNext($operations, $options, 'vfs-uri-shm-filecontrol-lock-current-source-next126', true, true);
    }

    /**
     * @param list<string|array<string,mixed>> $operations
     * @param array<string,mixed> $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function currentSourceNext131(array $operations, array $options = []): array
    {
        return self::runCurrentSourceNext($operations, $options, 'vfs-shm-uri-filecontrol-lock-current-source-next131', true, true, true);
    }

    /**
     * @param list<string|array<string,mixed>> $operations
     * @param array<string,mixed> $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function currentSourceNext138(array $operations, array $options = []): array
    {
        return self::runCurrentSourceNext($operations, $options, 'vfs-shm-bad-source-regression-current-source-next138', true, true, true);
    }

    /**
     * @param list<string|array<string,mixed>> $operations
     * @param array<string,mixed> $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    private static function runCurrentSourceNext(array $operations, array $options, string $dependencyMarker, bool $trackGeneration = false, bool $trackShmOwners = false, bool $trackShmRanges = false): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS SHM file-control lock current-source requires operations');
        }

        $state = self::normalizeCurrent($options['current'] ?? null);
        $events = [];

        foreach ($operations as $operation) {
            $op = self::normalizeOperation($operation);
            $before = self::snapshot($state);

            if ($op['kind'] === 'open') {
                $handle = self::openHandle($state, $op, $options, $trackGeneration);
                $state['handles'][$handle['id']] = $handle;
                $state['source_handles'][$handle['source']] = $handle['id'];
                $state['current_source'] = $handle['source'];
                $events[] = self::event('open', $handle['status'], $handle['source'], $before, self::snapshot($state), [
                    'handle' => $handle['id'],
                    'path' => $handle['path'],
                    'owner' => $handle['owner'],
                    'reused_controls' => $handle['reused_controls'],
                    'reused_shm_locks' => $handle['reused_shm_locks'],
                    'source_generation' => $handle['source_generation'] ?? null,
                ]);
                continue;
            }

            if ($op['kind'] === 'source') {
                $source = self::sourceName((string) $op['source']);
                if (!isset($state['source_handles'][$source])) {
                    $events[] = self::event('source', 'missing-handle', $source, $before, self::snapshot($state), []);
                    continue;
                }
                $state['current_source'] = $source;
                $events[] = self::event('source', 'ok', $source, $before, self::snapshot($state), [
                    'handle' => $state['source_handles'][$source],
                ]);
                continue;
            }

            if ($op['kind'] === 'filecontrol') {
                $source = self::sourceFor($state, $op['source']);
                $handleId = self::databaseHandleFor($state, $source);
                if ($handleId === null) {
                    $events[] = self::event('filecontrol', 'missing-handle', $source, $before, self::snapshot($state), []);
                    continue;
                }

                $control = self::controlName((string) $op['control']);
                $value = self::controlValue($control, $op['value']);
                $handle = &$state['handles'][$handleId];
                $previous = $handle['controls'][$control] ?? null;
                if ($trackGeneration && $control === 'data_version' && $op['value'] === null) {
                    $owner = (string) $handle['owner'];
                    $currentGeneration = self::ownerGeneration($state, $owner);
                    $sourceHandleId = (string) ($state['source_handles'][$source] ?? $handleId);
                    $sourceHandle = is_array($state['handles'][$sourceHandleId] ?? null) ? $state['handles'][$sourceHandleId] : $handle;
                    $openedGeneration = (int) ($sourceHandle['source_generation'] ?? $currentGeneration);
                    unset($handle);

                    $events[] = self::event('filecontrol', 'ok', $source, $before, self::snapshot($state), [
                        'handle' => $handleId,
                        'file_control' => $control,
                        'value' => $currentGeneration,
                        'previous' => $openedGeneration,
                        'changed' => false,
                        'routed_to' => 'database',
                        'source_generation' => $currentGeneration,
                        'opened_generation' => $openedGeneration,
                        'stale_current_source' => $openedGeneration !== $currentGeneration,
                        'stale_handles' => self::staleHandles($state, $owner),
                    ]);
                    continue;
                }
                $status = ($handle['readonly'] && self::writeControl($control)) ? 'ignored' : 'ok';
                if ($status === 'ok') {
                    $handle['controls'][$control] = $value;
                    if ($trackGeneration && self::writeControl($control) && $previous !== $value) {
                        $state['persistent_generations'][$handle['owner']] = self::ownerGeneration($state, (string) $handle['owner']) + 1;
                        $sourceHandleId = (string) ($state['source_handles'][$source] ?? $handleId);
                        if ($sourceHandleId === $handleId) {
                            $handle['source_generation'] = $state['persistent_generations'][$handle['owner']];
                        } elseif (isset($state['handles'][$sourceHandleId])) {
                            $state['handles'][$sourceHandleId]['source_generation'] = $state['persistent_generations'][$handle['owner']];
                        }
                        $handle['controls']['data_version'] = $state['persistent_generations'][$handle['owner']];
                    }
                    $state['persistent_controls'][$handle['owner']] = self::persistentSubset($handle['controls']);
                }
                $owner = (string) $handle['owner'];
                unset($handle);

                $events[] = self::event('filecontrol', $status, $source, $before, self::snapshot($state), [
                    'handle' => $handleId,
                    'file_control' => $control,
                    'value' => $value,
                    'previous' => $previous,
                    'changed' => $status === 'ok' && $previous !== $value,
                    'routed_to' => 'database',
                    'source_generation' => $trackGeneration ? self::ownerGeneration($state, $owner) : null,
                    'stale_handles' => $trackGeneration ? self::staleHandles($state, $owner) : [],
                ]);
                continue;
            }

            if ($op['kind'] === 'shmlock') {
                $source = self::sourceFor($state, $op['source'] ?? 'shm');
                $handleId = self::shmHandleFor($state, $source);
                if ($handleId === null) {
                    $events[] = self::event('shm_lock', 'missing-handle', $source, $before, self::snapshot($state), []);
                    continue;
                }

                $status = self::applyShmLock(
                    $state,
                    $handleId,
                    self::lockName((string) $op['lock']),
                    self::lockSpan($op['span'] ?? 1),
                    (string) $op['mode'],
                    self::connectionName($op['connection'] ?? null),
                    $trackShmOwners,
                    $trackShmRanges
                );
                $events[] = self::event('shm_lock', $status['status'], $source, $before, self::snapshot($state), $status + [
                    'handle' => $handleId,
                    'routed_to' => 'shm',
                ]);
                continue;
            }

            if ($op['kind'] === 'close') {
                $source = self::sourceFor($state, $op['source']);
                $handleId = $state['source_handles'][$source] ?? null;
                if (!is_string($handleId) || !isset($state['handles'][$handleId])) {
                    $events[] = self::event('close', 'missing-handle', $source, $before, self::snapshot($state), []);
                    continue;
                }

                $handle = $state['handles'][$handleId];
                unset($state['handles'][$handleId], $state['source_handles'][$source]);
                if ($handle['source'] === 'shm') {
                    unset($state['persistent_shm_locks'][$handle['owner']]);
                    unset($state['persistent_shm_lock_owners'][$handle['owner']]);
                }
                if ($state['current_source'] === $source) {
                    $state['current_source'] = isset($state['source_handles']['main']) ? 'main' : null;
                }

                $events[] = self::event('close', 'closed', $source, $before, self::snapshot($state), [
                    'handle' => $handleId,
                    'released_shm_locks' => $handle['source'] === 'shm',
                ]);
                continue;
            }
        }

        return [
            'status' => (string) $events[array_key_last($events)]['status'],
            'current' => self::snapshot($state),
            'next' => self::next($state),
            'events' => $events,
            'dependencies' => [
                'vfs-shm-current-source-routing',
                'vfs-file-control-application',
                'vfs-shm-lock-state',
                $dependencyMarker,
            ],
        ];
    }

    /**
     * @param array<string,mixed>|null $current
     * @return array<string,mixed>
     */
    private static function normalizeCurrent(mixed $current): array
    {
        if (!is_array($current)) {
            return [
                'sequence' => 0,
                'current_source' => null,
                'handles' => [],
                'source_handles' => [],
                'persistent_controls' => [],
                'persistent_shm_locks' => [],
                'persistent_shm_lock_owners' => [],
                'persistent_generations' => [],
            ];
        }

        return [
            'sequence' => max(0, (int) ($current['sequence'] ?? 0)),
            'current_source' => isset($current['current_source']) ? self::sourceName((string) $current['current_source']) : null,
            'handles' => is_array($current['handles'] ?? null) ? self::normalizeCurrentHandles($current['handles']) : [],
            'source_handles' => is_array($current['source_handles'] ?? null) ? self::normalizeSourceHandles($current['source_handles']) : [],
            'persistent_controls' => is_array($current['persistent_controls'] ?? null) ? $current['persistent_controls'] : [],
            'persistent_shm_locks' => is_array($current['persistent_shm_locks'] ?? null) ? self::normalizePersistentShmLocks($current['persistent_shm_locks']) : [],
            'persistent_shm_lock_owners' => is_array($current['persistent_shm_lock_owners'] ?? null) ? self::normalizePersistentShmLockOwners($current['persistent_shm_lock_owners']) : [],
            'persistent_generations' => is_array($current['persistent_generations'] ?? null) ? $current['persistent_generations'] : [],
        ];
    }

    /**
     * @param array<string,mixed> $handles
     * @return array<string,mixed>
     */
    private static function normalizeCurrentHandles(array $handles): array
    {
        foreach ($handles as $id => $handle) {
            if (!is_array($handle)) {
                throw new \InvalidArgumentException('SQLite VFS current handle state must be an array');
            }
            $handle['source'] = self::sourceName((string) ($handle['source'] ?? 'main'));
            if (is_array($handle['shm_locks'] ?? null)) {
                $handle['shm_locks'] = self::normalizeShmLockModes($handle['shm_locks']);
            }
            if (is_array($handle['shm_lock_owners'] ?? null)) {
                $handle['shm_lock_owners'] = self::normalizeShmLockOwners($handle['shm_lock_owners']);
            }
            $handles[(string) $id] = $handle;
        }

        return $handles;
    }

    /**
     * @param array<string,mixed> $sourceHandles
     * @return array<string,string>
     */
    private static function normalizeSourceHandles(array $sourceHandles): array
    {
        $normalized = [];
        foreach ($sourceHandles as $source => $handleId) {
            $normalized[self::sourceName((string) $source)] = (string) $handleId;
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $persistent
     * @return array<string,array<string,string>>
     */
    private static function normalizePersistentShmLocks(array $persistent): array
    {
        $normalized = [];
        foreach ($persistent as $owner => $locks) {
            if (!is_array($locks)) {
                throw new \InvalidArgumentException('SQLite VFS persistent SHM lock state must be an array');
            }
            $normalized[(string) $owner] = self::normalizeShmLockModes($locks);
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $persistent
     * @return array<string,array<string,list<string>>>
     */
    private static function normalizePersistentShmLockOwners(array $persistent): array
    {
        $normalized = [];
        foreach ($persistent as $owner => $locks) {
            if (!is_array($locks)) {
                throw new \InvalidArgumentException('SQLite VFS persistent SHM lock owner state must be an array');
            }
            $normalized[(string) $owner] = self::normalizeShmLockOwners($locks);
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $locks
     * @return array<string,string>
     */
    private static function normalizeShmLockModes(array $locks): array
    {
        $normalized = [];
        foreach ($locks as $lock => $mode) {
            $lockName = self::lockName((string) $lock);
            $mode = strtolower(trim((string) $mode));
            if (!in_array($mode, ['shared', 'exclusive'], true)) {
                throw new \InvalidArgumentException('SQLite SHM hydrated lock mode is unsupported');
            }
            $normalized[$lockName] = $mode;
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $ownersByLock
     * @return array<string,list<string>>
     */
    private static function normalizeShmLockOwners(array $ownersByLock): array
    {
        $normalized = [];
        foreach ($ownersByLock as $lock => $owners) {
            $normalized[self::lockName((string) $lock)] = self::lockOwners($owners);
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $op
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    private static function openHandle(array &$state, array $op, array $options, bool $trackGeneration = false): array
    {
        $state['sequence']++;
        $source = self::sourceName((string) ($op['source'] ?? 'main'));
        $filename = trim((string) ($op['filename'] ?? ''));
        if ($filename === '') {
            $filename = (string) ($options['filename'] ?? '/srv/www/wp-content/database/.ht.sqlite');
        }
        $owner = self::ownerPath($filename);
        $uri = str_starts_with(strtolower(trim($filename)), 'file:') ? SQLiteFileUri::parse(trim($filename)) : null;
        $path = match ($source) {
            'wal' => $owner . '-wal',
            'shm' => $owner . '-shm',
            default => $owner,
        };
        $controls = is_array($state['persistent_controls'][$owner] ?? null) ? $state['persistent_controls'][$owner] : [];
        $shmLocks = is_array($state['persistent_shm_locks'][$owner] ?? null) ? $state['persistent_shm_locks'][$owner] : [];
        $shmLockOwners = is_array($state['persistent_shm_lock_owners'][$owner] ?? null) ? $state['persistent_shm_lock_owners'][$owner] : [];

        return [
            'id' => 'vfs87-' . $state['sequence'],
            'status' => $source . '-open',
            'source' => $source,
            'owner' => $owner,
            'path' => $path,
            'readonly' => (bool) ($op['readonly'] ?? (is_array($uri) && ($uri['mode'] ?? null) === 'ro')),
            'nolock' => (bool) ($op['nolock'] ?? (is_array($uri) && ($uri['nolock'] ?? null) === true)),
            'controls' => $source === 'main' ? $controls : [],
            'shm_locks' => $source === 'shm' ? $shmLocks : [],
            'shm_lock_owners' => $source === 'shm' ? $shmLockOwners : [],
            'reused_controls' => $source === 'main' && $controls !== [],
            'reused_shm_locks' => $source === 'shm' && $shmLocks !== [],
            'source_generation' => $trackGeneration ? self::ownerGeneration($state, $owner) : null,
        ];
    }

    /**
     * @param string|array<string,mixed> $operation
     * @return array<string,mixed>
     */
    private static function normalizeOperation(string|array $operation): array
    {
        if (is_array($operation)) {
            $kind = strtolower(str_replace(['_', '-'], '', (string) ($operation['op'] ?? $operation['kind'] ?? '')));
            if (!in_array($kind, ['open', 'source', 'close', 'filecontrol', 'xfilecontrol', 'shmlock', 'xshmlock'], true)) {
                throw new \InvalidArgumentException('SQLite VFS SHM file-control lock operation is unsupported');
            }

            return [
                'kind' => match ($kind) {
                    'filecontrol', 'xfilecontrol' => 'filecontrol',
                    'shmlock', 'xshmlock' => 'shmlock',
                    default => $kind,
                },
                'source' => $operation['source'] ?? null,
                'filename' => $operation['filename'] ?? null,
                'readonly' => $operation['readonly'] ?? null,
                'nolock' => $operation['nolock'] ?? null,
                'control' => $operation['control'] ?? null,
                'value' => $operation['value'] ?? null,
                'lock' => $operation['lock'] ?? null,
                'span' => $operation['span'] ?? $operation['n'] ?? $operation['count'] ?? 1,
                'mode' => $operation['mode'] ?? 'exclusive',
                'connection' => $operation['connection'] ?? null,
            ];
        }

        $trimmed = trim($operation);
        if (preg_match('/^open\s*(?:\((?<source>main|wal|shm)(?:\s*,\s*(?<filename>[^)]*))?\))?$/i', $trimmed, $matches) === 1) {
            return ['kind' => 'open', 'source' => strtolower($matches['source'] ?? 'main'), 'filename' => trim($matches['filename'] ?? '')];
        }
        if (preg_match('/^source\s*\(\s*(?<source>main|wal|shm)\s*\)$/i', $trimmed, $matches) === 1) {
            return ['kind' => 'source', 'source' => strtolower($matches['source'])];
        }
        if (preg_match('/^close\s*(?:\((?<source>main|wal|shm)\))?$/i', $trimmed, $matches) === 1) {
            return ['kind' => 'close', 'source' => strtolower($matches['source'] ?? '') ?: null];
        }
        if (preg_match('/^file_control\s*\(\s*(?<control>[A-Za-z_][A-Za-z0-9_-]*)\s*(?:,\s*(?<value>.*))?\)$/i', $trimmed, $matches) === 1) {
            return ['kind' => 'filecontrol', 'source' => null, 'control' => $matches['control'], 'value' => self::parseValue($matches['value'] ?? null)];
        }
        if (preg_match('/^shm_lock\s*\(\s*(?<lock>[A-Za-z0-9_ -]+)\s*(?:,\s*(?<mode>shared|exclusive|unlock))?\s*\)$/i', $trimmed, $matches) === 1) {
            return ['kind' => 'shmlock', 'source' => 'shm', 'lock' => $matches['lock'], 'span' => 1, 'mode' => strtolower($matches['mode'] ?? 'exclusive'), 'connection' => null];
        }
        if (preg_match('/^shm_lock_range\s*\(\s*(?<lock>[A-Za-z0-9_ -]+)\s*,\s*(?<span>\d+)\s*(?:,\s*(?<mode>shared|exclusive|unlock))?\s*\)$/i', $trimmed, $matches) === 1) {
            return ['kind' => 'shmlock', 'source' => 'shm', 'lock' => $matches['lock'], 'span' => (int) $matches['span'], 'mode' => strtolower($matches['mode'] ?? 'exclusive'), 'connection' => null];
        }

        throw new \InvalidArgumentException('SQLite VFS SHM file-control lock operation is unsupported');
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function sourceFor(array $state, mixed $source): string
    {
        if ($source !== null && $source !== '') {
            return self::sourceName((string) $source);
        }
        if (is_string($state['current_source'] ?? null)) {
            return $state['current_source'];
        }

        return 'main';
    }

    private static function sourceName(string $source): string
    {
        $source = strtolower(trim($source));
        if (!in_array($source, ['main', 'wal', 'shm'], true)) {
            throw new \InvalidArgumentException('SQLite VFS current source must be main, wal, or shm');
        }

        return $source;
    }

    private static function ownerPath(string $filename): string
    {
        $filename = trim($filename);
        if ($filename === '') {
            return '/srv/www/wp-content/database/.ht.sqlite';
        }
        if (str_starts_with(strtolower($filename), 'file:')) {
            $uri = SQLiteFileUri::parse($filename);
            return self::stripSidecarSuffix((string) $uri['path']);
        }

        return self::stripSidecarSuffix($filename);
    }

    private static function stripSidecarSuffix(string $path): string
    {
        return preg_replace('/-(?:wal|shm)$/', '', $path) ?? $path;
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function databaseHandleFor(array $state, string $source): ?string
    {
        if ($source === 'main' && isset($state['source_handles']['main'])) {
            return (string) $state['source_handles']['main'];
        }
        if (isset($state['source_handles']['main'])) {
            return (string) $state['source_handles']['main'];
        }

        return null;
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function shmHandleFor(array $state, string $source): ?string
    {
        if ($source !== 'shm') {
            return null;
        }
        if (isset($state['source_handles']['shm'])) {
            return (string) $state['source_handles']['shm'];
        }

        return null;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function applyShmLock(array &$state, string $handleId, string $lock, int $span, string $mode, string $connection, bool $trackOwners, bool $trackRanges = false): array
    {
        $handle = &$state['handles'][$handleId];
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['shared', 'exclusive', 'unlock'], true)) {
            throw new \InvalidArgumentException('SQLite SHM lock mode is unsupported');
        }
        $locksToApply = self::lockRange($lock, $span);
        if ($handle['nolock']) {
            unset($handle);
            return ['lock' => $lock, 'span' => $span, 'locks' => $locksToApply, 'mode' => $mode, 'status' => 'blocked', 'reason' => 'nolock VFS disables SHM byte-range locking'];
        }
        if ($handle['readonly'] && $mode === 'exclusive') {
            unset($handle);
            return ['lock' => $lock, 'span' => $span, 'locks' => $locksToApply, 'mode' => $mode, 'status' => 'blocked', 'reason' => 'readonly SHM handle cannot take exclusive locks'];
        }

        $locks = $handle['shm_locks'];
        $owners = is_array($handle['shm_lock_owners'] ?? null) ? $handle['shm_lock_owners'] : [];
        $previousByLock = [];
        $ownersByLock = [];
        $conflictsByLock = [];
        foreach ($locksToApply as $lockName) {
            $previousByLock[$lockName] = $locks[$lockName] ?? null;
            $ownersByLock[$lockName] = self::lockOwners($owners[$lockName] ?? null);
            $conflicts = $trackOwners ? self::conflictingShmLockOwners($previousByLock[$lockName], $ownersByLock[$lockName], $mode, $connection) : [];
            if ($conflicts !== []) {
                $conflictsByLock[$lockName] = $conflicts;
            }
            if (!$trackOwners && $mode === 'exclusive' && $previousByLock[$lockName] === 'shared') {
                $conflictsByLock[$lockName] = ['shared'];
            }
        }
        if ($conflictsByLock !== []) {
            unset($handle);
            return [
                'lock' => $lock,
                'span' => $span,
                'locks' => $locksToApply,
                'mode' => $mode,
                'connection' => $connection,
                'status' => 'busy',
                'previous' => $previousByLock[$lock] ?? null,
                'previous_locks' => $previousByLock,
                'owners' => $ownersByLock[$lock] ?? [],
                'owner_locks' => $ownersByLock,
                'blocking_connections' => self::uniqueStrings(array_merge(...array_values($conflictsByLock))),
                'blocking_locks' => $conflictsByLock,
                'reason' => 'SHM lock is held by another connection',
            ];
        }

        $previous = $previousByLock[$lock] ?? null;
        foreach ($locksToApply as $lockName) {
            $previousOwners = $ownersByLock[$lockName] ?? [];
            if ($mode === 'unlock') {
                if ($trackOwners) {
                    $owners[$lockName] = array_values(array_filter($previousOwners, static fn (string $owner): bool => $owner !== $connection));
                    if ($owners[$lockName] === []) {
                        unset($owners[$lockName], $locks[$lockName]);
                    } elseif (count($owners[$lockName]) === 1) {
                        $locks[$lockName] = ($previousByLock[$lockName] ?? null) === 'exclusive' ? 'exclusive' : 'shared';
                    } else {
                        $locks[$lockName] = 'shared';
                    }
                } else {
                    unset($locks[$lockName]);
                }
            } else {
                if ($mode === 'exclusive' && ($previousByLock[$lockName] ?? null) === 'shared') {
                    unset($handle);
                    return [
                        'lock' => $lock,
                        'span' => $span,
                        'locks' => $locksToApply,
                        'mode' => $mode,
                        'connection' => $connection,
                        'status' => 'busy',
                        'previous' => 'shared',
                        'previous_locks' => $previousByLock,
                        'reason' => 'shared SHM lock must be released before exclusive lock',
                    ];
                }
                $locks[$lockName] = $mode;
                if ($trackOwners) {
                    $owners[$lockName] = $mode === 'shared'
                        ? self::uniqueStrings(array_merge($previousOwners, [$connection]))
                        : [$connection];
                }
            }
        }
        $handle['shm_locks'] = $locks;
        $handle['shm_lock_owners'] = $owners;
        $state['persistent_shm_locks'][$handle['owner']] = $locks;
        if ($trackOwners) {
            $state['persistent_shm_lock_owners'][$handle['owner']] = $owners;
        }
        unset($handle);

        return [
            'lock' => $lock,
            'span' => $span,
            'locks' => $locksToApply,
            'mode' => $mode,
            'connection' => $connection,
            'status' => 'ok',
            'previous' => $previous,
            'previous_locks' => $trackRanges ? $previousByLock : [],
            'owners' => $owners[$lock] ?? [],
            'owner_locks' => $trackRanges ? array_intersect_key($owners, array_flip($locksToApply)) : [],
            'changed' => $previous !== ($mode === 'unlock' ? null : $mode),
            'changed_locks' => $trackRanges ? self::changedLocks($previousByLock, $locks, $locksToApply, $mode) : [],
        ];
    }

    private static function lockSpan(mixed $span): int
    {
        if (is_int($span) || (is_string($span) && preg_match('/^\d+$/', trim($span)) === 1)) {
            $int = (int) $span;
            if ($int >= 1 && $int <= 8) {
                return $int;
            }
        }

        throw new \InvalidArgumentException('SQLite SHM lock range span must be between 1 and 8');
    }

    /**
     * @return list<string>
     */
    private static function lockRange(string $lock, int $span): array
    {
        $ordered = ['checkpoint', 'recover', 'write', 'read0', 'read1', 'read2', 'read3', 'read4'];
        $start = array_search($lock, $ordered, true);
        if (!is_int($start) || $start + $span > count($ordered)) {
            throw new \InvalidArgumentException('SQLite SHM lock range exceeds supported lock bytes');
        }

        return array_slice($ordered, $start, $span);
    }

    /**
     * @param array<string,mixed> $previous
     * @param array<string,mixed> $locks
     * @param list<string> $locksToApply
     * @return list<string>
     */
    private static function changedLocks(array $previous, array $locks, array $locksToApply, string $mode): array
    {
        $changed = [];
        foreach ($locksToApply as $lock) {
            $next = $mode === 'unlock' ? null : ($locks[$lock] ?? null);
            if (($previous[$lock] ?? null) !== $next) {
                $changed[] = $lock;
            }
        }

        return $changed;
    }

    /**
     * @param mixed $owners
     * @return list<string>
     */
    private static function lockOwners(mixed $owners): array
    {
        if (!is_array($owners)) {
            return [];
        }

        return self::uniqueStrings(array_map('strval', $owners));
    }

    /**
     * @param list<string> $owners
     * @return list<string>
     */
    private static function conflictingShmLockOwners(mixed $previous, array $owners, string $mode, string $connection): array
    {
        if ($mode === 'unlock' || $owners === []) {
            return [];
        }
        if ($previous === 'exclusive') {
            return array_values(array_filter($owners, static fn (string $owner): bool => $owner !== $connection));
        }
        if ($previous === 'shared' && $mode === 'exclusive') {
            return array_values(array_filter($owners, static fn (string $owner): bool => $owner !== $connection));
        }

        return [];
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private static function uniqueStrings(array $values): array
    {
        $unique = [];
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '' && !in_array($value, $unique, true)) {
                $unique[] = $value;
            }
        }

        return $unique;
    }

    private static function lockName(string $lock): string
    {
        $lock = strtolower(str_replace(['-', ' '], '_', trim($lock)));
        $valid = ['checkpoint', 'recover', 'write', 'read0', 'read1', 'read2', 'read3', 'read4'];
        if (!in_array($lock, $valid, true)) {
            throw new \InvalidArgumentException('SQLite SHM lock name is unsupported');
        }

        return $lock;
    }

    private static function controlName(string $control): string
    {
        $control = strtolower(str_replace('-', '_', trim($control)));
        if ($control === '') {
            throw new \InvalidArgumentException('SQLite VFS file-control name is required');
        }

        return $control;
    }

    private static function controlValue(string $control, mixed $value): mixed
    {
        return match ($control) {
            'persist_wal', 'powersafe_overwrite' => self::boolean($value),
            'chunk_size', 'mmap_size', 'size_hint', 'reserve_bytes', 'lock_timeout' => self::nonNegativeInt($value, $control),
            'name_hint' => self::nameHint($value),
            default => $value,
        };
    }

    private static function writeControl(string $control): bool
    {
        return in_array($control, ['chunk_size', 'size_hint', 'reserve_bytes', 'powersafe_overwrite', 'persist_wal'], true);
    }

    /**
     * @param array<string,mixed> $controls
     * @return array<string,mixed>
     */
    private static function persistentSubset(array $controls): array
    {
        $subset = [];
        foreach (['persist_wal', 'chunk_size', 'mmap_size', 'powersafe_overwrite', 'reserve_bytes', 'lock_timeout', 'data_version'] as $key) {
            if (array_key_exists($key, $controls)) {
                $subset[$key] = $controls[$key];
            }
        }

        return $subset;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function snapshot(array $state): array
    {
        ksort($state['handles']);
        ksort($state['source_handles']);
        ksort($state['persistent_controls']);
        ksort($state['persistent_shm_locks']);
        ksort($state['persistent_shm_lock_owners']);
        ksort($state['persistent_generations']);

        return $state;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function next(array $state): array
    {
        $openBySource = ['main' => 0, 'wal' => 0, 'shm' => 0];
        $shmLockCount = 0;
        foreach ($state['handles'] as $handle) {
            $source = (string) ($handle['source'] ?? 'main');
            $openBySource[$source] = ($openBySource[$source] ?? 0) + 1;
            if ($source === 'shm' && is_array($handle['shm_locks'] ?? null)) {
                $shmLockCount += count($handle['shm_locks']);
            }
        }

        return [
            'current_source' => $state['current_source'],
            'open_by_source' => $openBySource,
            'persistent_control_count' => count($state['persistent_controls']),
            'shm_lock_count' => $shmLockCount,
            'persistent_shm_owner_count' => count(array_filter($state['persistent_shm_locks'], static fn (mixed $locks): bool => is_array($locks) && $locks !== [])),
            'persistent_shm_connection_count' => self::persistentShmConnectionCount($state),
            'persistent_generation_count' => count($state['persistent_generations']),
        ];
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function persistentShmConnectionCount(array $state): int
    {
        $connections = [];
        foreach ($state['persistent_shm_lock_owners'] as $locks) {
            if (!is_array($locks)) {
                continue;
            }
            foreach ($locks as $owners) {
                foreach (self::lockOwners($owners) as $owner) {
                    $connections[$owner] = true;
                }
            }
        }

        return count($connections);
    }

    private static function connectionName(mixed $connection): string
    {
        if ($connection === null || $connection === '') {
            return 'default';
        }
        $name = trim((string) $connection);
        if ($name === '' || preg_match('/^[A-Za-z0-9_.:-]+$/', $name) !== 1) {
            throw new \InvalidArgumentException('SQLite SHM lock connection name is unsupported');
        }

        return $name;
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function ownerGeneration(array $state, string $owner): int
    {
        $generation = $state['persistent_generations'][$owner] ?? null;
        if (is_int($generation) && $generation > 0) {
            return $generation;
        }

        return 1;
    }

    /**
     * @param array<string,mixed> $state
     * @return list<string>
     */
    private static function staleHandles(array $state, string $owner): array
    {
        $generation = self::ownerGeneration($state, $owner);
        $stale = [];
        foreach ($state['handles'] as $id => $handle) {
            if (($handle['owner'] ?? null) !== $owner) {
                continue;
            }
            if ((int) ($handle['source_generation'] ?? $generation) !== $generation) {
                $stale[] = (string) $id;
            }
        }

        return $stale;
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private static function event(string $op, string $status, string $source, array $current, array $next, array $extra): array
    {
        return ['op' => $op, 'status' => $status, 'source' => $source, 'current' => $current, 'next' => $next] + $extra;
    }

    private static function parseValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }
        if (preg_match('/^\d+$/', $trimmed) === 1) {
            return (int) $trimmed;
        }
        if ((str_starts_with($trimmed, "'") && str_ends_with($trimmed, "'")) || (str_starts_with($trimmed, '"') && str_ends_with($trimmed, '"'))) {
            $quote = $trimmed[0];
            return str_replace($quote . $quote, $quote, substr($trimmed, 1, -1));
        }

        return $trimmed;
    }

    private static function boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value !== 0;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'on', 'yes'], true);
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (is_int($value) || (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1)) {
            $int = (int) $value;
            if ($int >= 0) {
                return $int;
            }
        }

        throw new \InvalidArgumentException("SQLite VFS file-control {$label} requires a non-negative integer");
    }

    private static function nameHint(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '' || str_contains($value, "\0")) {
            throw new \InvalidArgumentException('SQLite VFS file-control name_hint requires non-empty text without NUL bytes');
        }

        return $value;
    }
}
