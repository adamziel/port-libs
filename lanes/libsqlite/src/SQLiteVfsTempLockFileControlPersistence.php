<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsTempLockFileControlPersistence
{
    /**
     * @param list<string|array<string,mixed>> $operations
     * @param array{temp_dir?:string,connection_id?:string,temp_store?:string,directory_writable?:bool,current?:array<string,mixed>} $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function tempLockFileControlPersistenceSequence(array $operations, array $options = []): array
    {
        $state = self::normalizeCurrent($options['current'] ?? null);
        $events = [];

        foreach ($operations as $operation) {
            $op = self::normalizeOperation($operation);
            $before = self::snapshot($state);

            if ($op['kind'] === 'open') {
                $lifecycle = SQLiteVfsTempFileOpenLifecycle::tempFileOpenLifecycleSequence(
                    [[
                        'op' => 'open',
                        'suffix' => $op['suffix'],
                        'delete_on_close' => $op['delete_on_close'],
                        'exclusive' => $op['exclusive'],
                        'readonly' => $op['readonly'],
                    ]],
                    $options + ['current' => ['sequence' => $state['sequence'], 'handles' => []]],
                );
                $handle = $lifecycle['current']['handles'][$lifecycle['current']['last_open']];
                $state['sequence'] = (int) $lifecycle['current']['sequence'];
                $state['last_open'] = (string) $handle['id'];
                $state['handles'][$handle['id']] = self::handleState($handle, $state);

                $events[] = self::event('open', (string) $handle['status'], $before, self::snapshot($state), [
                    'handle' => $handle['id'],
                    'path' => $handle['path'],
                    'file_control_key' => self::fileControlKey($handle),
                    'reused_controls' => isset($state['persistent_controls'][self::fileControlKey($handle)]),
                    'lock_state' => $state['handles'][$handle['id']]['lock_state'],
                ]);
                continue;
            }

            if ($op['kind'] === 'filecontrol') {
                $handleId = self::targetHandle($state, $op['handle']);
                if ($handleId === null || !isset($state['handles'][$handleId])) {
                    $events[] = self::event('filecontrol', 'missing-handle', $before, self::snapshot($state), [
                        'handle' => $op['handle'],
                        'file_control' => $op['control'],
                    ]);
                    continue;
                }

                $handle = &$state['handles'][$handleId];
                $control = $op['control'];
                $previous = $handle['controls'][$control] ?? null;
                $value = self::controlValue($control, $op['value']);
                $handle['controls'][$control] = $value;
                $state['persistent_controls'][(string) $handle['file_control_key']] = $handle['controls'];
                unset($handle);

                $events[] = self::event('filecontrol', 'ok', $before, self::snapshot($state), [
                    'handle' => $handleId,
                    'file_control' => $control,
                    'value' => $value,
                    'previous' => $previous,
                    'changed' => $previous !== $value,
                ]);
                continue;
            }

            if ($op['kind'] === 'lock') {
                $handleId = self::targetHandle($state, $op['handle']);
                if ($handleId === null || !isset($state['handles'][$handleId])) {
                    $events[] = self::event('lock', 'missing-handle', $before, self::snapshot($state), [
                        'handle' => $op['handle'],
                    ]);
                    continue;
                }

                $level = self::lockLevel((string) $op['value']);
                $state['handles'][$handleId]['lock_state'] = $level;
                if (!$state['handles'][$handleId]['memory']) {
                    $state['persistent_locks'][(string) $state['handles'][$handleId]['file_control_key']] = $level;
                }

                $events[] = self::event('lock', 'ok', $before, self::snapshot($state), [
                    'handle' => $handleId,
                    'lock_state' => $level,
                ]);
                continue;
            }

            if ($op['kind'] === 'close') {
                $handleId = self::targetHandle($state, $op['handle']);
                if ($handleId === null || !isset($state['handles'][$handleId])) {
                    $events[] = self::event('close', 'missing-handle', $before, self::snapshot($state), [
                        'handle' => $op['handle'],
                        'deleted' => false,
                    ]);
                    continue;
                }

                $handle = $state['handles'][$handleId];
                unset($state['handles'][$handleId]);
                $deleted = (bool) $handle['delete_on_close'] && !$handle['memory'];
                if ($deleted || $handle['memory']) {
                    unset($state['persistent_controls'][(string) $handle['file_control_key']], $state['persistent_locks'][(string) $handle['file_control_key']]);
                } else {
                    $state['persistent_controls'][(string) $handle['file_control_key']] = $handle['controls'];
                    $state['persistent_locks'][(string) $handle['file_control_key']] = 'unlocked';
                }

                $events[] = self::event('close', 'closed', $before, self::snapshot($state), [
                    'handle' => $handleId,
                    'path' => $handle['path'],
                    'deleted' => $deleted,
                    'persisted_controls' => !$deleted && !$handle['memory'],
                    'lock_state' => 'unlocked',
                ]);
                continue;
            }

            throw new \InvalidArgumentException('Unsupported SQLite temp lock file-control persistence operation');
        }

        return [
            'status' => $events === [] ? 'idle' : (string) $events[array_key_last($events)]['status'],
            'current' => self::snapshot($state),
            'next' => self::next($state),
            'events' => $events,
            'dependencies' => [
                'vfs-tempfile-open-lifecycle',
                'vfs-xfilecontrol',
                'vfs-temp-lock-filecontrol-persistence',
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
                'last_open' => null,
                'handles' => [],
                'persistent_controls' => [],
                'persistent_locks' => [],
            ];
        }

        return [
            'sequence' => max(0, (int) ($current['sequence'] ?? 0)),
            'last_open' => isset($current['last_open']) ? (string) $current['last_open'] : null,
            'handles' => is_array($current['handles'] ?? null) ? $current['handles'] : [],
            'persistent_controls' => is_array($current['persistent_controls'] ?? null) ? $current['persistent_controls'] : [],
            'persistent_locks' => is_array($current['persistent_locks'] ?? null) ? $current['persistent_locks'] : [],
        ];
    }

    /**
     * @param string|array<string,mixed> $operation
     * @return array{kind:string,handle:?string,suffix:string,delete_on_close:bool,exclusive:bool,readonly:bool,control:?string,value:mixed}
     */
    private static function normalizeOperation(string|array $operation): array
    {
        if (is_array($operation)) {
            $kind = strtolower(str_replace(['_', '-'], '', (string) ($operation['op'] ?? $operation['kind'] ?? '')));

            return [
                'kind' => match ($kind) {
                    'filecontrol', 'xfilecontrol' => 'filecontrol',
                    default => $kind,
                },
                'handle' => isset($operation['handle']) ? (string) $operation['handle'] : null,
                'suffix' => self::normalizeSuffix((string) ($operation['suffix'] ?? '')),
                'delete_on_close' => (bool) ($operation['delete_on_close'] ?? true),
                'exclusive' => (bool) ($operation['exclusive'] ?? true),
                'readonly' => (bool) ($operation['readonly'] ?? false),
                'control' => isset($operation['control']) ? self::controlName((string) $operation['control']) : null,
                'value' => $operation['value'] ?? null,
            ];
        }

        $trimmed = trim($operation);
        if (preg_match('/^(open|close|lock)\s*(?:\(([^)]*)\))?$/i', $trimmed, $matches) === 1) {
            $kind = strtolower($matches[1]);
            $argument = trim($matches[2] ?? '');

            return [
                'kind' => $kind,
                'handle' => $kind === 'close' ? ($argument === '' ? null : $argument) : null,
                'suffix' => $kind === 'open' ? self::normalizeSuffix($argument) : '',
                'delete_on_close' => true,
                'exclusive' => true,
                'readonly' => false,
                'control' => null,
                'value' => $kind === 'lock' ? $argument : null,
            ];
        }

        if (preg_match('/^file_control\s*\(\s*(?<control>[A-Za-z_][A-Za-z0-9_-]*)\s*(?:,\s*(?<value>.*))?\)$/i', $trimmed, $matches) === 1) {
            return [
                'kind' => 'filecontrol',
                'handle' => null,
                'suffix' => '',
                'delete_on_close' => true,
                'exclusive' => true,
                'readonly' => false,
                'control' => self::controlName($matches['control']),
                'value' => self::parseValue($matches['value'] ?? null),
            ];
        }

        throw new \InvalidArgumentException('SQLite temp lock file-control operation is unsupported');
    }

    /**
     * @param array<string,mixed> $handle
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function handleState(array $handle, array $state): array
    {
        $key = self::fileControlKey($handle);
        $controls = $state['persistent_controls'][$key] ?? [];
        if (!is_array($controls)) {
            $controls = [];
        }

        return $handle + [
            'file_control_key' => $key,
            'controls' => $controls,
            'lock_state' => (string) ($state['persistent_locks'][$key] ?? 'unlocked'),
        ];
    }

    /**
     * @param array<string,mixed> $handle
     */
    private static function fileControlKey(array $handle): string
    {
        if (($handle['path'] ?? '') !== '') {
            return (string) $handle['path'];
        }

        return 'memory:' . (string) ($handle['id'] ?? 'temp');
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function targetHandle(array $state, ?string $handle): ?string
    {
        if ($handle !== null && $handle !== '') {
            return $handle;
        }

        return isset($state['last_open']) ? (string) $state['last_open'] : null;
    }

    private static function controlName(string $control): string
    {
        $control = strtolower(str_replace('-', '_', trim($control)));
        if ($control === '') {
            throw new \InvalidArgumentException('SQLite temp file-control name is required');
        }

        return $control;
    }

    private static function controlValue(string $control, mixed $value): mixed
    {
        return match ($control) {
            'persist_wal', 'powersafe_overwrite' => self::boolean($value),
            'chunk_size', 'mmap_size', 'size_hint', 'lock_timeout', 'reserve_bytes' => self::nonNegativeInt($value, $control),
            'name_hint' => self::nameHint($value),
            default => $value,
        };
    }

    private static function lockLevel(string $level): string
    {
        $level = strtolower(trim($level));
        if (!in_array($level, ['unlocked', 'shared', 'reserved', 'pending', 'exclusive'], true)) {
            throw new \InvalidArgumentException('SQLite temp lock state is unsupported');
        }

        return $level;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function snapshot(array $state): array
    {
        ksort($state['handles']);
        ksort($state['persistent_controls']);
        ksort($state['persistent_locks']);

        return $state;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function next(array $state): array
    {
        $openPaths = [];
        $pendingDeletes = 0;
        foreach ($state['handles'] as $handle) {
            if (($handle['path'] ?? '') !== '') {
                $openPaths[] = (string) $handle['path'];
            }
            if (($handle['delete_on_close'] ?? false) && !($handle['memory'] ?? false)) {
                $pendingDeletes++;
            }
        }

        return [
            'open_count' => count($state['handles']),
            'open_paths' => $openPaths,
            'pending_delete_count' => $pendingDeletes,
            'persistent_control_count' => count($state['persistent_controls']),
            'persistent_lock_count' => count(array_filter($state['persistent_locks'], static fn (mixed $level): bool => $level !== 'unlocked')),
            'requires_directory_write' => $pendingDeletes > 0,
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
        if (preg_match('/^[-+]?\d+$/', $trimmed) === 1) {
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
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'on', 'yes'], true);
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (is_int($value) || (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1)) {
            $int = (int) $value;
            if ($int >= 0) {
                return $int;
            }
        }

        throw new \InvalidArgumentException("SQLite temp file-control {$label} requires a non-negative integer");
    }

    private static function nameHint(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '' || str_contains($value, "\0")) {
            throw new \InvalidArgumentException('SQLite temp file-control name_hint requires non-empty text without NUL bytes');
        }

        return $value;
    }

    private static function normalizeSuffix(string $suffix): string
    {
        $suffix = trim($suffix);
        if ($suffix === '') {
            return '';
        }
        if ($suffix[0] !== '.') {
            $suffix = '.' . $suffix;
        }
        if (str_contains($suffix, '/') || str_contains($suffix, '\\') || str_contains($suffix, "\0") || preg_match('/^\.[A-Za-z0-9_.-]+$/', $suffix) !== 1) {
            throw new \InvalidArgumentException('SQLite temp lock file-control suffix must be a plain filename suffix');
        }

        return $suffix;
    }
}
