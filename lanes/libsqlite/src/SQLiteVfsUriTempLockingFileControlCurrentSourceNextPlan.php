<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsUriTempLockingFileControlCurrentSourceNextPlan
{
    /**
     * @param list<string|array<string,mixed>> $operations
     * @param array<string,mixed> $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function run(array $operations, array $options = []): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS URI temp locking file-control current-source next130 requires operations');
        }

        $state = [
            'sequence' => 0,
            'current_source' => null,
            'handles' => [],
            'source_handles' => [],
            'persistent_controls' => self::arrayMap($options['persistent_controls'] ?? []),
            'persistent_locks' => self::arrayMap($options['persistent_locks'] ?? []),
            'deleted_temp_owners' => [],
        ];
        $events = [];

        foreach ($operations as $operation) {
            $op = self::operation($operation);
            $before = self::snapshot($state);
            if ($op['kind'] === 'open') {
                $handle = self::openHandle($state, $op, $options);
                $state['handles'][$handle['id']] = $handle;
                $state['source_handles'][$handle['source']] = $handle['id'];
                $state['current_source'] = $handle['source'];
                $events[] = self::event('open', $handle['status'], $handle['source'], $before, self::snapshot($state), [
                    'handle' => $handle['id'],
                    'owner' => $handle['owner'],
                    'path' => $handle['path'],
                    'temporary' => $handle['temporary'],
                    'delete_on_close' => $handle['delete_on_close'],
                    'readonly' => $handle['readonly'],
                    'nolock' => $handle['nolock'],
                    'reused_controls' => $handle['reused_controls'],
                    'reused_locks' => $handle['reused_locks'],
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
                $handleId = $state['source_handles'][$source] ?? null;
                if (!is_string($handleId) || !isset($state['handles'][$handleId])) {
                    $events[] = self::event('file_control', 'missing-handle', $source, $before, self::snapshot($state), []);
                    continue;
                }
                $result = self::applyFileControl($state, $handleId, self::controlName((string) $op['control']), $op['value']);
                $events[] = self::event('file_control', $result['status'], $source, $before, self::snapshot($state), $result + [
                    'handle' => $handleId,
                ]);
                continue;
            }

            if ($op['kind'] === 'lock') {
                $source = self::sourceFor($state, $op['source']);
                $handleId = $state['source_handles'][$source] ?? null;
                if (!is_string($handleId) || !isset($state['handles'][$handleId])) {
                    $events[] = self::event('lock', 'missing-handle', $source, $before, self::snapshot($state), []);
                    continue;
                }
                $result = self::applyLock($state, $handleId, self::lockLevel((string) $op['level']), self::connectionName($op['connection'] ?? null));
                $events[] = self::event('lock', $result['status'], $source, $before, self::snapshot($state), $result + [
                    'handle' => $handleId,
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
                $deleted = false;
                if ($handle['delete_on_close']) {
                    unset($state['persistent_controls'][$handle['owner']], $state['persistent_locks'][$handle['owner']]);
                    $state['deleted_temp_owners'][] = $handle['owner'];
                    $deleted = true;
                }
                if ($state['current_source'] === $source) {
                    $state['current_source'] = array_key_first($state['source_handles']);
                }
                $events[] = self::event('close', 'closed', $source, $before, self::snapshot($state), [
                    'handle' => $handleId,
                    'owner' => $handle['owner'],
                    'released_locks' => true,
                    'deleted_temp' => $deleted,
                ]);
            }
        }

        return [
            'status' => (string) ($events[array_key_last($events)]['status'] ?? 'ok'),
            'current' => [
                'sequence' => 0,
                'current_source' => null,
                'handles' => [],
                'source_handles' => [],
                'persistent_controls' => self::arrayMap($options['persistent_controls'] ?? []),
                'persistent_locks' => self::arrayMap($options['persistent_locks'] ?? []),
                'deleted_temp_owners' => [],
            ],
            'next' => self::summary($state),
            'events' => $events,
            'dependencies' => [
                'sqlite-file-uri-parser',
                'vfs-temp-delete-on-close',
                'vfs-file-control-current-source',
                'vfs-lock-byte-state',
                'vfs-uri-temp-locking-filecontrol-current-source-next130',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $op
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    private static function openHandle(array &$state, array $op, array $options): array
    {
        $state['sequence']++;
        $source = self::sourceName((string) ($op['source'] ?? 'main'));
        $filename = trim((string) ($op['filename'] ?? ($options['filename'] ?? '')));
        $temporary = (bool) ($op['temporary'] ?? false) || $source === 'temp' || $filename === '' || str_contains(strtolower($filename), 'mode=memory');
        $uri = $filename !== '' && str_starts_with(strtolower($filename), 'file:') ? SQLiteFileUri::parse($filename) : null;
        $path = is_array($uri) ? (string) $uri['path'] : ($filename === '' ? '' : $filename);
        if ($temporary && $path === '') {
            $path = 'temp:' . $source . ':' . $state['sequence'];
        }
        $owner = $temporary ? 'temp:' . $source . ':' . $state['sequence'] : self::stripSidecarSuffix($path);
        $controls = !$temporary && is_array($state['persistent_controls'][$owner] ?? null) ? $state['persistent_controls'][$owner] : [];
        $locks = !$temporary && is_array($state['persistent_locks'][$owner] ?? null) ? $state['persistent_locks'][$owner] : [];

        return [
            'id' => 'vfs130-' . $state['sequence'],
            'status' => $temporary ? 'temp-open' : 'open',
            'source' => $source,
            'owner' => $owner,
            'path' => $path,
            'temporary' => $temporary,
            'delete_on_close' => (bool) ($op['delete_on_close'] ?? $temporary),
            'readonly' => (bool) ($op['readonly'] ?? (is_array($uri) && ($uri['mode'] ?? null) === 'ro')),
            'nolock' => (bool) ($op['nolock'] ?? (is_array($uri) && ($uri['nolock'] ?? null) === true)),
            'locking_mode' => 'normal',
            'controls' => $controls,
            'locks' => $locks,
            'reused_controls' => $controls !== [],
            'reused_locks' => $locks !== [],
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function applyFileControl(array &$state, string $handleId, string $control, mixed $value): array
    {
        $handle = &$state['handles'][$handleId];
        $previous = $control === 'locking_mode' ? $handle['locking_mode'] : ($handle['controls'][$control] ?? null);
        $normalized = self::controlValue($control, $value);
        $status = 'ok';
        $reason = null;
        $persistent = false;

        if ($handle['readonly'] && in_array($control, ['persist_wal', 'reserve_bytes', 'size_hint', 'chunk_size'], true)) {
            $status = 'ignored';
            $reason = 'readonly handle ignores mutating file-control';
        } elseif ($handle['temporary'] && $control === 'persist_wal') {
            $status = 'ignored';
            $reason = 'temporary database handles do not persist WAL state';
        } elseif ($control === 'locking_mode') {
            $handle['locking_mode'] = $normalized;
            if ($normalized === 'exclusive') {
                $handle['locks']['exclusive'] = 'locking_mode';
            }
        } else {
            $handle['controls'][$control] = $normalized;
            $persistent = !$handle['temporary'] && in_array($control, ['persist_wal', 'reserve_bytes', 'chunk_size'], true);
            if ($persistent) {
                $state['persistent_controls'][$handle['owner']] = self::persistentSubset($handle['controls']);
            }
        }

        $owner = $handle['owner'];
        $temporary = $handle['temporary'];
        unset($handle);

        return [
            'file_control' => $control,
            'value' => $normalized,
            'previous' => $previous,
            'changed' => $status === 'ok' && $previous !== $normalized,
            'persistent' => $persistent,
            'temporary' => $temporary,
            'owner' => $owner,
            'reason' => $reason,
            'status' => $status,
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function applyLock(array &$state, string $handleId, string $level, string $connection): array
    {
        $handle = &$state['handles'][$handleId];
        if ($handle['nolock']) {
            unset($handle);
            return ['level' => $level, 'connection' => $connection, 'status' => 'blocked', 'reason' => 'nolock VFS disables temp byte-range locking'];
        }
        if ($handle['readonly'] && in_array($level, ['reserved', 'pending', 'exclusive'], true)) {
            unset($handle);
            return ['level' => $level, 'connection' => $connection, 'status' => 'blocked', 'reason' => 'readonly handle cannot take writer locks'];
        }

        $locks = $handle['locks'];
        $blocking = [];
        foreach ($locks as $held => $owner) {
            if ($owner === $connection) {
                continue;
            }
            if ($level === 'exclusive' && $held === 'exclusive' && $owner === 'locking_mode') {
                continue;
            }
            if ($level === 'shared' && $held === 'exclusive') {
                $blocking[] = $owner . ':exclusive';
            } elseif ($level !== 'shared') {
                $blocking[] = $owner . ':' . $held;
            }
        }
        if ($blocking !== []) {
            unset($handle);
            return ['level' => $level, 'connection' => $connection, 'status' => 'busy', 'blocking' => $blocking, 'reason' => 'temp file lock is held by another connection'];
        }

        if ($level === 'unlock') {
            $locks = array_filter($locks, static fn (string $owner): bool => $owner !== $connection);
        } else {
            $locks[$level] = $connection;
        }
        $handle['locks'] = $locks;
        if (!$handle['temporary']) {
            $state['persistent_locks'][$handle['owner']] = $locks;
        }
        $temporary = $handle['temporary'];
        unset($handle);

        return ['level' => $level, 'connection' => $connection, 'status' => 'ok', 'blocking' => [], 'temporary' => $temporary];
    }

    /**
     * @param string|array<string,mixed> $operation
     * @return array<string,mixed>
     */
    private static function operation(string|array $operation): array
    {
        if (is_array($operation)) {
            $kind = strtolower(str_replace(['_', '-'], '', (string) ($operation['op'] ?? $operation['kind'] ?? '')));
            return [
                'kind' => match ($kind) {
                    'filecontrol', 'xfilecontrol' => 'filecontrol',
                    default => $kind,
                },
                'source' => $operation['source'] ?? null,
                'filename' => $operation['filename'] ?? null,
                'temporary' => $operation['temporary'] ?? null,
                'delete_on_close' => $operation['delete_on_close'] ?? null,
                'readonly' => $operation['readonly'] ?? null,
                'nolock' => $operation['nolock'] ?? null,
                'control' => $operation['control'] ?? null,
                'value' => $operation['value'] ?? null,
                'level' => $operation['level'] ?? null,
                'connection' => $operation['connection'] ?? null,
            ];
        }

        $trimmed = trim($operation);
        if (preg_match('/^open\s*\(\s*(?<source>[A-Za-z0-9_.:-]+)(?:\s*,\s*(?<filename>[^)]*))?\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'open', 'source' => $matches['source'], 'filename' => trim($matches['filename'] ?? '')];
        }
        if (preg_match('/^source\s*\(\s*(?<source>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'source', 'source' => $matches['source']];
        }
        if (preg_match('/^close\s*\(\s*(?<source>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'close', 'source' => $matches['source']];
        }
        if (preg_match('/^file_control\s*\(\s*(?<control>[A-Za-z_][A-Za-z0-9_-]*)\s*(?:,\s*(?<value>.*))?\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'filecontrol', 'source' => null, 'control' => $matches['control'], 'value' => self::parseValue($matches['value'] ?? null)];
        }
        if (preg_match('/^lock\s*\(\s*(?<level>shared|reserved|pending|exclusive|unlock)\s*(?:,\s*(?<connection>[A-Za-z0-9_.:-]+))?\s*\)$/i', $trimmed, $matches) === 1) {
            return ['kind' => 'lock', 'source' => null, 'level' => $matches['level'], 'connection' => $matches['connection'] ?? null];
        }

        throw new \InvalidArgumentException('SQLite VFS URI temp locking file-control operation is unsupported');
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function sourceFor(array $state, mixed $source): string
    {
        if ($source !== null && $source !== '') {
            return self::sourceName((string) $source);
        }

        return is_string($state['current_source']) ? $state['current_source'] : 'main';
    }

    private static function sourceName(string $source): string
    {
        $source = strtolower(trim($source));
        if ($source === '' || preg_match('/^[a-z0-9_.:-]+$/', $source) !== 1) {
            throw new \InvalidArgumentException('SQLite VFS current source name is unsupported');
        }

        return $source;
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
            'persist_wal' => self::boolean($value),
            'reserve_bytes', 'chunk_size', 'size_hint' => self::nonNegativeInt($value, $control),
            'locking_mode' => self::lockingMode($value),
            default => $value,
        };
    }

    private static function lockLevel(string $level): string
    {
        $level = strtolower(trim($level));
        if (!in_array($level, ['shared', 'reserved', 'pending', 'exclusive', 'unlock'], true)) {
            throw new \InvalidArgumentException('SQLite VFS temp lock level is unsupported');
        }

        return $level;
    }

    private static function connectionName(mixed $connection): string
    {
        $connection = $connection === null || $connection === '' ? 'default' : trim((string) $connection);
        if ($connection === '' || preg_match('/^[A-Za-z0-9_.:-]+$/', $connection) !== 1) {
            throw new \InvalidArgumentException('SQLite VFS temp lock connection name is unsupported');
        }

        return $connection;
    }

    /**
     * @param array<string,mixed> $controls
     * @return array<string,mixed>
     */
    private static function persistentSubset(array $controls): array
    {
        $subset = [];
        foreach (['persist_wal', 'reserve_bytes', 'chunk_size'] as $key) {
            if (array_key_exists($key, $controls)) {
                $subset[$key] = $controls[$key];
            }
        }

        return $subset;
    }

    private static function stripSidecarSuffix(string $path): string
    {
        return preg_replace('/-(?:wal|shm|journal)$/', '', $path) ?? $path;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function summary(array $state): array
    {
        return [
            'current_source' => $state['current_source'],
            'open_count' => count($state['handles']),
            'temp_open_count' => count(array_filter($state['handles'], static fn (array $handle): bool => (bool) $handle['temporary'])),
            'persistent_control_count' => count($state['persistent_controls']),
            'persistent_lock_count' => count($state['persistent_locks']),
            'deleted_temp_owners' => array_values(array_unique($state['deleted_temp_owners'])),
        ];
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
        ksort($state['persistent_locks']);
        sort($state['deleted_temp_owners']);

        return $state;
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

    private static function lockingMode(mixed $value): string
    {
        $mode = strtolower(trim((string) $value));
        if (!in_array($mode, ['normal', 'exclusive'], true)) {
            throw new \InvalidArgumentException('SQLite VFS locking_mode file-control expects normal or exclusive');
        }

        return $mode;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (is_int($value) || (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1)) {
            $int = (int) $value;
            if ($int >= 0) {
                return $int;
            }
        }

        throw new \InvalidArgumentException("SQLite VFS {$label} file-control expects a non-negative integer");
    }

    /**
     * @return array<string,mixed>
     */
    private static function arrayMap(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
