<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsShmLockFileControlCurrentSource
{
    /**
     * @param list<array<string,mixed>|string> $operations
     * @param array<string,mixed> $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function planShmLockFileControl(array $operations, array $options = []): array
    {
        return self::run($operations, $options, false, 'vfs-shm-lock-filecontrol-current-source-next85', 'shm-lock-file-control');
    }

    /**
     * @param list<array<string,mixed>|string> $operations
     * @param array<string,mixed> $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function planUriShmLockFileControl(array $operations, array $options = []): array
    {
        return self::run($operations, $options, true, 'vfs-uri-shm-lock-filecontrol-current-source-next88', 'uri-shm-lock-file-control');
    }

    /**
     * @param list<array<string,mixed>|string> $operations
     * @param array<string,mixed> $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    private static function run(array $operations, array $options, bool $uriAware, string $dependency, string $label): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException("SQLite SHM lock file-control current-source {} requires operations");
        }

        $state = self::normalizeCurrent($options['current'] ?? null);
        $open = self::openSource((string) ($options['filename'] ?? '/srv/www/wp-content/database/.ht.sqlite'), $uriAware, $state);
        $path = $open['source_key'];
        if ($path === '') {
            throw new \InvalidArgumentException("SQLite SHM lock file-control current-source {} requires a database path");
        }

        if (!isset($state['sources'][$path])) {
            $state['sources'][$path] = self::newSource($path, $options, $open);
        }

        $events = [];
        foreach ($operations as $operation) {
            $op = self::normalizeOperation($operation);
            $before = self::snapshot($state);
            $source = &$state['sources'][$path];

            if ($op['kind'] === 'open') {
                $state['sequence']++;
                $handleId = 'shm-' . $state['sequence'];
                $state['handles'][$handleId] = [
                    'id' => $handleId,
                    'path' => $open['path'],
                    'source_key' => $path,
                    'uri' => $open['uri'],
                    'source_generation' => $source['generation'],
                    'lock' => null,
                    'readonly' => (bool) ($op['readonly'] ?? $open['readonly']),
                    'nolock' => (bool) ($op['nolock'] ?? $open['nolock']),
                    'immutable' => (bool) $open['immutable'],
                    'persistent' => (bool) $open['persistent'],
                    'controls' => $source['controls'],
                    'shm_locks' => $source['locks'],
                ];
                $state['last_handle'] = $handleId;
                unset($source);

                $events[] = self::event('open', 'open', $before, self::snapshot($state), [
                    'handle' => $handleId,
                    'path' => $open['path'],
                    'source_key' => $path,
                    'uri' => $open['uri'],
                    'source_generation' => $state['handles'][$handleId]['source_generation'],
                    'reused_controls' => $state['handles'][$handleId]['controls'] !== [],
                ]);
                continue;
            }

            $handleId = self::targetHandle($state, $op['handle']);
            if ($handleId === null || !isset($state['handles'][$handleId])) {
                unset($source);
                $events[] = self::event($op['kind'], 'missing-handle', $before, self::snapshot($state), [
                    'handle' => $op['handle'],
                ]);
                continue;
            }

            $handle = &$state['handles'][$handleId];
            if ($handle['source_key'] !== $path) {
                unset($handle, $source);
                $events[] = self::event($op['kind'], 'wrong-source', $before, self::snapshot($state), [
                    'handle' => $handleId,
                ]);
                continue;
            }

            if ($op['kind'] === 'shmlock') {
                $lock = self::lockName((string) $op['lock']);
                $exclusive = (bool) ($op['exclusive'] ?? false);
                if ($handle['nolock'] || $handle['immutable'] || !$handle['persistent']) {
                    $reason = !$handle['persistent']
                        ? 'memory_uri_has_private_shm'
                        : ($handle['immutable'] ? 'immutable_uri_disables_shm_locking' : 'nolock_uri_disables_shm_locking');
                    unset($handle, $source);
                    $events[] = self::event('shmlock', 'blocked', $before, self::snapshot($state), [
                        'handle' => $handleId,
                        'lock' => $lock,
                        'exclusive' => $exclusive,
                        'blocking' => [],
                        'reason' => $reason,
                    ]);
                    continue;
                }

                $conflicts = self::lockConflicts($source['locks'], $lock, $handleId, $exclusive);
                if ($conflicts !== []) {
                    unset($handle, $source);
                    $events[] = self::event('shmlock', 'blocked', $before, self::snapshot($state), [
                        'handle' => $handleId,
                        'lock' => $lock,
                        'exclusive' => $exclusive,
                        'blocking' => $conflicts,
                        'reason' => 'shm_lock_conflict',
                    ]);
                    continue;
                }

                $source['locks'][$lock][$handleId] = $exclusive ? 'exclusive' : 'shared';
                $handle['lock'] = $lock;
                $handle['shm_locks'] = $source['locks'];
                unset($handle, $source);
                $events[] = self::event('shmlock', 'acquired', $before, self::snapshot($state), [
                    'handle' => $handleId,
                    'lock' => $lock,
                    'exclusive' => $exclusive,
                ]);
                continue;
            }

            if ($op['kind'] === 'filecontrol') {
                $control = self::controlName((string) $op['control']);
                $value = self::controlValue($control, $op['value']);
                $requiresWrite = self::writeControl($control);
                $stale = $handle['source_generation'] !== $source['generation'];
                $allowedByLock = self::controlAllowedByLock($source['locks'], $handleId, $requiresWrite);
                $status = 'ok';
                $reason = null;
                if ($stale) {
                    $status = 'blocked';
                    $reason = 'stale_current_source';
                } elseif ($handle['readonly'] && $requiresWrite) {
                    $status = 'ignored';
                    $reason = 'readonly_handle';
                } elseif (!$allowedByLock) {
                    $status = 'blocked';
                    $reason = $requiresWrite ? 'requires_exclusive_shm_lock' : 'requires_shm_read_lock';
                }

                $previous = $handle['controls'][$control] ?? null;
                if ($status === 'ok') {
                    $handle['controls'][$control] = $value;
                    $source['controls'][$control] = $value;
                    if ($requiresWrite) {
                        $source['generation']++;
                        $handle['source_generation'] = $source['generation'];
                    }
                }
                $handle['shm_locks'] = $source['locks'];
                unset($handle, $source);

                $events[] = self::event('filecontrol', $status, $before, self::snapshot($state), [
                    'handle' => $handleId,
                    'file_control' => $control,
                    'value' => $value,
                    'previous' => $previous,
                    'changed' => $status === 'ok' && $previous !== $value,
                    'reason' => $reason,
                ]);
                continue;
            }

            if ($op['kind'] === 'release') {
                foreach ($source['locks'] as $lock => $holders) {
                    unset($source['locks'][$lock][$handleId]);
                }
                $handle['lock'] = null;
                $handle['shm_locks'] = $source['locks'];
                unset($handle, $source);
                $events[] = self::event('release', 'released', $before, self::snapshot($state), [
                    'handle' => $handleId,
                ]);
                continue;
            }

            if ($op['kind'] === 'close') {
                foreach ($source['locks'] as $lock => $holders) {
                    unset($source['locks'][$lock][$handleId]);
                }
                unset($state['handles'][$handleId], $source);
                $events[] = self::event('close', 'closed', $before, self::snapshot($state), [
                    'handle' => $handleId,
                ]);
                continue;
            }

            unset($handle, $source);
            throw new \InvalidArgumentException('SQLite SHM lock file-control current-source operation is unsupported');
        }

        return [
            'status' => (string) $events[array_key_last($events)]['status'],
            'current' => $events[0]['current'],
            'next' => self::summary($state, $path),
            'events' => $events,
            'dependencies' => [
                'sqlite-shm-locks',
                'vfs-xfilecontrol-current-source',
                $dependency,
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
            return ['sequence' => 0, 'last_handle' => null, 'handles' => [], 'sources' => []];
        }

        return [
            'sequence' => max(0, (int) ($current['sequence'] ?? 0)),
            'last_handle' => isset($current['last_handle']) ? (string) $current['last_handle'] : null,
            'handles' => is_array($current['handles'] ?? null) ? $current['handles'] : [],
            'sources' => is_array($current['sources'] ?? null) ? $current['sources'] : [],
        ];
    }

    /**
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    private static function newSource(string $path, array $options, array $open): array
    {
        return [
            'path' => $path,
            'filename' => $open['path'],
            'uri' => $open['uri'],
            'persistent' => $open['persistent'],
            'generation' => max(1, (int) ($options['generation'] ?? 1)),
            'controls' => is_array($options['file_controls'] ?? null) ? $options['file_controls'] : [],
            'locks' => [
                'read' => [],
                'write' => [],
                'checkpoint' => [],
                'recover' => [],
            ],
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
            return [
                'kind' => match ($kind) {
                    'filecontrol', 'xfilecontrol' => 'filecontrol',
                    'shmlock', 'lock' => 'shmlock',
                    default => $kind,
                },
                'handle' => isset($operation['handle']) ? (string) $operation['handle'] : null,
                'lock' => $operation['lock'] ?? $operation['value'] ?? null,
                'control' => $operation['control'] ?? null,
                'value' => $operation['value'] ?? null,
                'readonly' => $operation['readonly'] ?? null,
                'nolock' => $operation['nolock'] ?? null,
                'exclusive' => $operation['exclusive'] ?? null,
            ];
        }

        $trimmed = trim($operation);
        if (preg_match('/^open(?:\((?<mode>readonly)\))?$/i', $trimmed, $matches) === 1) {
            return [
                'kind' => 'open',
                'handle' => null,
                'readonly' => isset($matches['mode']) && $matches['mode'] !== '' ? true : null,
            ];
        }
        if (preg_match('/^close(?:\((?<handle>[^)]*)\))?$/i', $trimmed, $matches) === 1) {
            return ['kind' => 'close', 'handle' => ($matches['handle'] ?? '') !== '' ? trim($matches['handle']) : null];
        }
        if (preg_match('/^release(?:\((?<handle>[^)]*)\))?$/i', $trimmed, $matches) === 1) {
            return ['kind' => 'release', 'handle' => ($matches['handle'] ?? '') !== '' ? trim($matches['handle']) : null];
        }
        if (preg_match('/^shm_lock\((?<lock>[A-Za-z_]+)(?:,\s*(?<mode>exclusive|shared))?\)$/i', $trimmed, $matches) === 1) {
            return ['kind' => 'shmlock', 'handle' => null, 'lock' => $matches['lock'], 'exclusive' => strtolower($matches['mode'] ?? '') === 'exclusive'];
        }
        if (preg_match('/^file_control\((?<control>[A-Za-z_][A-Za-z0-9_-]*)(?:,\s*(?<value>.*))?\)$/i', $trimmed, $matches) === 1) {
            return ['kind' => 'filecontrol', 'handle' => null, 'control' => $matches['control'], 'value' => self::parseValue($matches['value'] ?? null)];
        }

        throw new \InvalidArgumentException('SQLite SHM lock file-control operation is unsupported');
    }

    private static function canonicalPath(string $filename): string
    {
        $filename = trim($filename);
        if (str_starts_with(strtolower($filename), 'file:')) {
            $filename = substr($filename, 5);
            $query = strpos($filename, '?');
            return $query === false ? $filename : substr($filename, 0, $query);
        }

        return $filename;
    }

    /**
     * @param array<string,mixed> $state
     * @return array{path:string,source_key:string,readonly:bool,nolock:bool,immutable:bool,persistent:bool,uri:array<string,mixed>}
     */
    private static function openSource(string $filename, bool $uriAware, array $state): array
    {
        if ($uriAware) {
            $uri = SQLiteFileUri::parse($filename);
            $path = $uri['path'] === '' ? self::canonicalPath($filename) : (string) $uri['path'];
            $memory = $uri['mode'] === 'memory' || $path === ':memory:';

            return [
                'path' => $memory ? '' : $path,
                'source_key' => $memory ? 'memory:shm-' . (((int) ($state['sequence'] ?? 0)) + 1) : $path,
                'readonly' => $uri['mode'] === 'ro' || $uri['immutable'] === true,
                'nolock' => $uri['nolock'] === true,
                'immutable' => $uri['immutable'] === true,
                'persistent' => !$memory,
                'uri' => [
                    'is_uri' => $uri['is_uri'],
                    'path' => $path,
                    'mode' => $uri['mode'],
                    'cache' => $uri['cache'],
                    'immutable' => $uri['immutable'],
                    'nolock' => $uri['nolock'],
                    'vfs' => $uri['vfs'],
                    'authority' => $uri['authority'],
                ],
            ];
        }

        $path = self::canonicalPath($filename);

        return [
            'path' => $path,
            'source_key' => $path,
            'readonly' => false,
            'nolock' => false,
            'immutable' => false,
            'persistent' => true,
            'uri' => [
                'is_uri' => str_starts_with(strtolower($filename), 'file:'),
                'path' => $path,
                'mode' => null,
                'cache' => null,
                'immutable' => null,
                'nolock' => null,
                'vfs' => null,
                'authority' => null,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function targetHandle(array $state, ?string $handle): ?string
    {
        if ($handle !== null && $handle !== '') {
            return $handle;
        }

        return isset($state['last_handle']) ? (string) $state['last_handle'] : null;
    }

    private static function lockName(string $lock): string
    {
        $lock = strtolower(str_replace(['_', '-'], '', trim($lock)));
        $lock = match ($lock) {
            'read', 'reader' => 'read',
            'write', 'writer' => 'write',
            'checkpoint', 'ckpt' => 'checkpoint',
            'recover', 'recovery' => 'recover',
            default => $lock,
        };
        if (!in_array($lock, ['read', 'write', 'checkpoint', 'recover'], true)) {
            throw new \InvalidArgumentException('SQLite SHM lock name is unsupported');
        }

        return $lock;
    }

    /**
     * @param array<string,array<string,string>> $locks
     * @return list<string>
     */
    private static function lockConflicts(array $locks, string $lock, string $handleId, bool $exclusive): array
    {
        $conflicts = [];
        foreach ($locks as $name => $holders) {
            foreach ($holders as $holder => $mode) {
                if ($holder === $handleId) {
                    continue;
                }
                if ($exclusive || $mode === 'exclusive' || $name !== 'read' || $lock !== 'read') {
                    $conflicts[] = $holder . ':' . $name . ':' . $mode;
                }
            }
        }

        return $conflicts;
    }

    /**
     * @param array<string,array<string,string>> $locks
     */
    private static function controlAllowedByLock(array $locks, string $handleId, bool $requiresWrite): bool
    {
        if ($requiresWrite) {
            return ($locks['write'][$handleId] ?? null) === 'exclusive'
                || ($locks['checkpoint'][$handleId] ?? null) === 'exclusive'
                || ($locks['recover'][$handleId] ?? null) === 'exclusive';
        }

        return isset($locks['read'][$handleId])
            || isset($locks['write'][$handleId])
            || isset($locks['checkpoint'][$handleId])
            || isset($locks['recover'][$handleId]);
    }

    private static function controlName(string $control): string
    {
        $control = strtolower(str_replace('-', '_', trim($control)));
        if ($control === '') {
            throw new \InvalidArgumentException('SQLite SHM file-control name is required');
        }

        return $control;
    }

    private static function controlValue(string $control, mixed $value): mixed
    {
        return match ($control) {
            'persist_wal', 'powersafe_overwrite' => self::boolean($value),
            'chunk_size', 'mmap_size', 'reserve_bytes', 'data_version' => self::nonNegativeInt($value, $control),
            'name_hint' => self::nameHint($value),
            default => $value,
        };
    }

    private static function writeControl(string $control): bool
    {
        return in_array($control, ['persist_wal', 'chunk_size', 'reserve_bytes', 'powersafe_overwrite', 'data_version'], true);
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function snapshot(array $state): array
    {
        ksort($state['handles']);
        ksort($state['sources']);

        return $state;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function summary(array $state, string $path): array
    {
        $source = $state['sources'][$path];

        return [
            'path' => $path,
            'generation' => $source['generation'],
            'controls' => $source['controls'],
            'locks' => $source['locks'],
            'open_count' => count($state['handles']),
            'locked_count' => array_sum(array_map('count', $source['locks'])),
        ];
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private static function event(string $op, string $status, array $current, array $next, array $extra): array
    {
        return ['op' => $op, 'status' => $status, 'current' => $current, 'next' => $next] + $extra;
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

        throw new \InvalidArgumentException("SQLite SHM file-control {$label} requires a non-negative integer");
    }

    private static function nameHint(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '' || str_contains($value, "\0")) {
            throw new \InvalidArgumentException('SQLite SHM file-control name_hint requires non-empty text without NUL bytes');
        }

        return $value;
    }
}
