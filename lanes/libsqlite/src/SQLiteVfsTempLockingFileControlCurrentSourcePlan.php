<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsTempLockingFileControlCurrentSourcePlan
{
    /**
     * @param list<string|array<string,mixed>> $operations
     * @param array{temp_dir?:string,connection_id?:string,temp_store?:string,directory_writable?:bool,current_source?:string,current?:array<string,mixed>} $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function currentSourceNext83(array $operations, array $options = []): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS temp locking file-control current-source next83 requires operations');
        }

        $trackGeneration = (bool) ($options['_track_temp_generation'] ?? false);
        $state = self::normalizeCurrent($options['current'] ?? null, self::sourceName((string) ($options['current_source'] ?? 'temp')));
        $events = [];

        foreach ($operations as $operation) {
            $op = self::normalizeOperation($operation);
            $before = self::snapshot($state);

            if ($op['kind'] === 'source') {
                $state['current_source'] = $op['source'] ?? $state['current_source'];
                $events[] = self::event('source', 'current-source-selected', $before, self::snapshot($state), [
                    'source' => $state['current_source'],
                ]);
                continue;
            }

            if ($op['kind'] === 'open') {
                $source = $op['source'] ?? $state['current_source'];
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
                $state['source_handles'][$source] = (string) $handle['id'];
                $state['handles'][(string) $handle['id']] = self::handleState($handle, $state, $source);
                if ($op['make_current']) {
                    $state['current_source'] = $source;
                }

                $events[] = self::event('open', (string) $handle['status'], $before, self::snapshot($state), [
                    'source' => $source,
                    'handle' => $handle['id'],
                    'path' => $handle['path'],
                    'file_control_key' => self::fileControlKey($source, $handle),
                    'current_source' => $state['current_source'],
                    'reused_controls' => isset($state['persistent_controls'][self::fileControlKey($source, $handle)]),
                    'lock_state' => $state['handles'][(string) $handle['id']]['lock_state'],
                ]);
                continue;
            }

            if ($op['kind'] === 'filecontrol') {
                $handleId = self::targetHandle($state, $op);
                if ($handleId === null || !isset($state['handles'][$handleId])) {
                    $events[] = self::event('filecontrol', 'missing-handle', $before, self::snapshot($state), [
                        'source' => $op['source'] ?? $state['current_source'],
                        'handle' => $op['handle'],
                        'file_control' => $op['control'],
                    ]);
                    continue;
                }

                $handle = &$state['handles'][$handleId];
                $control = (string) $op['control'];
                if ($trackGeneration && $control === 'data_version' && $op['value'] === null) {
                    $key = (string) $handle['file_control_key'];
                    $currentGeneration = ($handle['memory'] ?? false)
                        ? (int) ($handle['source_generation'] ?? 1)
                        : self::sourceGeneration($state, $key);
                    $openedGeneration = (int) ($handle['source_generation'] ?? $currentGeneration);
                    $source = (string) $handle['source'];
                    unset($handle);

                    $events[] = self::event('filecontrol', 'ok', $before, self::snapshot($state), [
                        'source' => $source,
                        'handle' => $handleId,
                        'file_control' => $control,
                        'value' => $currentGeneration,
                        'previous' => $openedGeneration,
                        'changed' => false,
                        'reason' => null,
                        'opened_generation' => $openedGeneration,
                        'source_generation' => $currentGeneration,
                        'stale_current_source' => $openedGeneration !== $currentGeneration,
                    ]);
                    continue;
                }

                $previous = $handle['controls'][$control] ?? null;
                $value = self::controlValue($control, $op['value']);
                $writeControl = self::writeControl($control);
                if ($trackGeneration && $writeControl && !self::writeLockHeld((string) $handle['lock_state'])) {
                    $source = (string) $handle['source'];
                    unset($handle);

                    $events[] = self::event('filecontrol', 'blocked', $before, self::snapshot($state), [
                        'source' => $source,
                        'handle' => $handleId,
                        'file_control' => $control,
                        'value' => $value,
                        'previous' => $previous,
                        'changed' => false,
                        'reason' => 'requires_reserved_or_exclusive_temp_lock',
                    ]);
                    continue;
                }

                $handle['controls'][$control] = $value;
                if (!($handle['memory'] ?? false)) {
                    $state['persistent_controls'][(string) $handle['file_control_key']] = $handle['controls'];
                    if ($trackGeneration && $writeControl && $previous !== $value) {
                        $key = (string) $handle['file_control_key'];
                        $state['persistent_generations'][$key] = self::sourceGeneration($state, $key) + 1;
                        $handle['source_generation'] = $state['persistent_generations'][$key];
                    }
                } elseif ($trackGeneration && $writeControl && $previous !== $value) {
                    $handle['source_generation'] = ((int) ($handle['source_generation'] ?? 1)) + 1;
                }
                $source = (string) $handle['source'];
                $generation = $trackGeneration ? (int) ($handle['source_generation'] ?? self::sourceGeneration($state, (string) $handle['file_control_key'])) : null;
                unset($handle);

                $events[] = self::event('filecontrol', 'ok', $before, self::snapshot($state), [
                    'source' => $source,
                    'handle' => $handleId,
                    'file_control' => $control,
                    'value' => $value,
                    'previous' => $previous,
                    'changed' => $previous !== $value,
                    'source_generation' => $generation,
                ]);
                continue;
            }

            if ($op['kind'] === 'lock') {
                $handleId = self::targetHandle($state, $op);
                if ($handleId === null || !isset($state['handles'][$handleId])) {
                    $events[] = self::event('lock', 'missing-handle', $before, self::snapshot($state), [
                        'source' => $op['source'] ?? $state['current_source'],
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
                    'source' => $state['handles'][$handleId]['source'],
                    'handle' => $handleId,
                    'lock_state' => $level,
                ]);
                continue;
            }

            if ($op['kind'] === 'unlock') {
                $handleId = self::targetHandle($state, $op);
                if ($handleId === null || !isset($state['handles'][$handleId])) {
                    $events[] = self::event('unlock', 'missing-handle', $before, self::snapshot($state), [
                        'source' => $op['source'] ?? $state['current_source'],
                        'handle' => $op['handle'],
                    ]);
                    continue;
                }

                $state['handles'][$handleId]['lock_state'] = 'unlocked';
                if (!$state['handles'][$handleId]['memory']) {
                    $state['persistent_locks'][(string) $state['handles'][$handleId]['file_control_key']] = 'unlocked';
                }

                $events[] = self::event('unlock', 'ok', $before, self::snapshot($state), [
                    'source' => $state['handles'][$handleId]['source'],
                    'handle' => $handleId,
                    'lock_state' => 'unlocked',
                ]);
                continue;
            }

            if ($op['kind'] === 'close') {
                $handleId = self::targetHandle($state, $op);
                if ($handleId === null || !isset($state['handles'][$handleId])) {
                    $events[] = self::event('close', 'missing-handle', $before, self::snapshot($state), [
                        'source' => $op['source'] ?? $state['current_source'],
                        'handle' => $op['handle'],
                        'deleted' => false,
                    ]);
                    continue;
                }

                $handle = $state['handles'][$handleId];
                unset($state['handles'][$handleId]);
                if (($state['source_handles'][(string) $handle['source']] ?? null) === $handleId) {
                    unset($state['source_handles'][(string) $handle['source']]);
                }
                $deleted = (bool) $handle['delete_on_close'] && !$handle['memory'];
                if ($deleted || $handle['memory']) {
                    unset($state['persistent_controls'][(string) $handle['file_control_key']], $state['persistent_locks'][(string) $handle['file_control_key']], $state['persistent_generations'][(string) $handle['file_control_key']]);
                } else {
                    $state['persistent_controls'][(string) $handle['file_control_key']] = $handle['controls'];
                    $state['persistent_locks'][(string) $handle['file_control_key']] = 'unlocked';
                }

                $events[] = self::event('close', 'closed', $before, self::snapshot($state), [
                    'source' => $handle['source'],
                    'handle' => $handleId,
                    'path' => $handle['path'],
                    'deleted' => $deleted,
                    'persisted_controls' => !$deleted && !$handle['memory'],
                    'lock_state' => 'unlocked',
                ]);
                continue;
            }

            throw new \InvalidArgumentException('Unsupported SQLite VFS temp locking file-control current-source operation');
        }

        return [
            'status' => (string) $events[array_key_last($events)]['status'],
            'current' => self::snapshot($state),
            'next' => self::next($state),
            'events' => $events,
            'dependencies' => [
                'vfs-tempfile-open-lifecycle',
                'vfs-xfilecontrol',
                $trackGeneration ? 'vfs-temp-lock-filecontrol-current-source-next102' : 'vfs-temp-locking-current-source-next83',
            ],
        ];
    }

    /**
     * @param list<string|array<string,mixed>> $operations
     * @param array{temp_dir?:string,connection_id?:string,temp_store?:string,directory_writable?:bool,current_source?:string,current?:array<string,mixed>} $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function currentSourceNext102(array $operations, array $options = []): array
    {
        $options['_track_temp_generation'] = true;

        return self::currentSourceNext83($operations, $options);
    }

    /**
     * @param array<string,mixed>|null $current
     * @return array<string,mixed>
     */
    private static function normalizeCurrent(mixed $current, string $currentSource): array
    {
        if (!is_array($current)) {
            return [
                'sequence' => 0,
                'current_source' => $currentSource,
                'last_open' => null,
                'source_handles' => [],
                'handles' => [],
                'persistent_controls' => [],
                'persistent_locks' => [],
                'persistent_generations' => [],
            ];
        }

        return [
            'sequence' => max(0, (int) ($current['sequence'] ?? 0)),
            'current_source' => self::sourceName((string) ($current['current_source'] ?? $currentSource)),
            'last_open' => isset($current['last_open']) ? (string) $current['last_open'] : null,
            'source_handles' => is_array($current['source_handles'] ?? null) ? $current['source_handles'] : [],
            'handles' => is_array($current['handles'] ?? null) ? $current['handles'] : [],
            'persistent_controls' => is_array($current['persistent_controls'] ?? null) ? $current['persistent_controls'] : [],
            'persistent_locks' => is_array($current['persistent_locks'] ?? null) ? $current['persistent_locks'] : [],
            'persistent_generations' => is_array($current['persistent_generations'] ?? null) ? $current['persistent_generations'] : [],
        ];
    }

    /**
     * @param string|array<string,mixed> $operation
     * @return array{kind:string,source:?string,handle:?string,suffix:string,delete_on_close:bool,exclusive:bool,readonly:bool,make_current:bool,control:?string,value:mixed}
     */
    private static function normalizeOperation(string|array $operation): array
    {
        if (is_array($operation)) {
            $kind = strtolower(str_replace(['_', '-'], '', (string) ($operation['op'] ?? $operation['kind'] ?? '')));

            return [
                'kind' => match ($kind) {
                    'filecontrol', 'xfilecontrol' => 'filecontrol',
                    'release' => 'unlock',
                    default => $kind,
                },
                'source' => isset($operation['source']) ? self::sourceName((string) $operation['source']) : null,
                'handle' => isset($operation['handle']) ? (string) $operation['handle'] : null,
                'suffix' => self::normalizeSuffix((string) ($operation['suffix'] ?? '')),
                'delete_on_close' => (bool) ($operation['delete_on_close'] ?? true),
                'exclusive' => (bool) ($operation['exclusive'] ?? true),
                'readonly' => (bool) ($operation['readonly'] ?? false),
                'make_current' => (bool) ($operation['make_current'] ?? true),
                'control' => isset($operation['control']) ? self::controlName((string) $operation['control']) : null,
                'value' => $operation['value'] ?? null,
            ];
        }

        $trimmed = trim($operation);
        if (preg_match('/^source\s*\(\s*([A-Za-z0-9_]+)\s*\)$/i', $trimmed, $matches) === 1) {
            return self::op('source', self::sourceName($matches[1]));
        }
        if (preg_match('/^(open|close|lock|unlock)\s*(?:\(([^)]*)\))?$/i', $trimmed, $matches) === 1) {
            $kind = strtolower($matches[1]);
            $argument = trim($matches[2] ?? '');
            $source = null;
            if ($argument !== '' && str_contains($argument, '.')) {
                [$maybeSource, $rest] = explode('.', $argument, 2);
                if (in_array(strtolower($maybeSource), ['main', 'temp', 'attached'], true)) {
                    $source = self::sourceName($maybeSource);
                    $argument = $rest;
                }
            }

            return [
                'kind' => $kind,
                'source' => $source,
                'handle' => ($kind === 'close' || $kind === 'unlock') && $argument !== '' && $source === null ? $argument : null,
                'suffix' => $kind === 'open' ? self::normalizeSuffix($argument) : '',
                'delete_on_close' => true,
                'exclusive' => true,
                'readonly' => false,
                'make_current' => true,
                'control' => null,
                'value' => $kind === 'lock' ? $argument : null,
            ];
        }
        if (preg_match('/^file_control\s*\(\s*(?:(?<source>[A-Za-z0-9_]+)\.)?(?<control>[A-Za-z_][A-Za-z0-9_-]*)\s*(?:,\s*(?<value>.*))?\)$/i', $trimmed, $matches) === 1) {
            return [
                'kind' => 'filecontrol',
                'source' => isset($matches['source']) && $matches['source'] !== '' ? self::sourceName($matches['source']) : null,
                'handle' => null,
                'suffix' => '',
                'delete_on_close' => true,
                'exclusive' => true,
                'readonly' => false,
                'make_current' => true,
                'control' => self::controlName($matches['control']),
                'value' => self::parseValue($matches['value'] ?? null),
            ];
        }

        throw new \InvalidArgumentException('SQLite VFS temp locking file-control current-source operation is unsupported');
    }

    /**
     * @return array{kind:string,source:?string,handle:?string,suffix:string,delete_on_close:bool,exclusive:bool,readonly:bool,make_current:bool,control:?string,value:mixed}
     */
    private static function op(string $kind, ?string $source): array
    {
        return [
            'kind' => $kind,
            'source' => $source,
            'handle' => null,
            'suffix' => '',
            'delete_on_close' => true,
            'exclusive' => true,
            'readonly' => false,
            'make_current' => true,
            'control' => null,
            'value' => null,
        ];
    }

    /**
     * @param array<string,mixed> $handle
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function handleState(array $handle, array $state, string $source): array
    {
        $key = self::fileControlKey($source, $handle);
        $controls = $state['persistent_controls'][$key] ?? [];
        if (!is_array($controls)) {
            $controls = [];
        }

        return $handle + [
            'source' => $source,
            'file_control_key' => $key,
            'controls' => $controls,
            'lock_state' => (string) ($state['persistent_locks'][$key] ?? 'unlocked'),
            'source_generation' => self::sourceGeneration($state, $key),
        ];
    }

    /**
     * @param array<string,mixed> $handle
     */
    private static function fileControlKey(string $source, array $handle): string
    {
        $path = (string) ($handle['path'] ?? '');
        if ($path === '') {
            return $source . ':memory:' . (string) ($handle['id'] ?? 'temp');
        }

        $suffix = (string) ($handle['suffix'] ?? '');
        if ($suffix !== '') {
            return $source . ':temp:' . $suffix;
        }

        return $source . ':' . $path;
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $op
     */
    private static function targetHandle(array $state, array $op): ?string
    {
        if (($op['handle'] ?? null) !== null && $op['handle'] !== '') {
            return (string) $op['handle'];
        }
        $source = $op['source'] ?? $state['current_source'];
        if (isset($state['source_handles'][$source])) {
            return (string) $state['source_handles'][$source];
        }

        return isset($state['last_open']) ? (string) $state['last_open'] : null;
    }

    private static function sourceName(string $source): string
    {
        $source = strtolower(trim($source));
        if (!in_array($source, ['main', 'temp', 'attached'], true)) {
            throw new \InvalidArgumentException('SQLite VFS current source must be main, temp, or attached');
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
            throw new \InvalidArgumentException('SQLite VFS temp lock state is unsupported');
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
        ksort($state['source_handles']);
        ksort($state['persistent_controls']);
        ksort($state['persistent_locks']);
        ksort($state['persistent_generations']);

        return $state;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function next(array $state): array
    {
        $bySource = ['main' => 0, 'temp' => 0, 'attached' => 0];
        $lockedBySource = ['main' => 0, 'temp' => 0, 'attached' => 0];
        $pendingDeletes = 0;
        foreach ($state['handles'] as $handle) {
            $source = (string) ($handle['source'] ?? 'temp');
            $bySource[$source] = ($bySource[$source] ?? 0) + 1;
            if (($handle['lock_state'] ?? 'unlocked') !== 'unlocked') {
                $lockedBySource[$source] = ($lockedBySource[$source] ?? 0) + 1;
            }
            if (($handle['delete_on_close'] ?? false) && !($handle['memory'] ?? false)) {
                $pendingDeletes++;
            }
        }

        return [
            'current_source' => $state['current_source'],
            'open_count' => count($state['handles']),
            'open_by_source' => $bySource,
            'locked_by_source' => $lockedBySource,
            'pending_delete_count' => $pendingDeletes,
            'persistent_control_count' => count($state['persistent_controls']),
            'persistent_lock_count' => count(array_filter($state['persistent_locks'], static fn (mixed $level): bool => $level !== 'unlocked')),
            'persistent_generation_count' => count($state['persistent_generations']),
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

        throw new \InvalidArgumentException("SQLite VFS file-control {$label} requires a non-negative integer");
    }

    private static function nameHint(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '' || str_contains($value, "\0")) {
            throw new \InvalidArgumentException('SQLite VFS file-control name_hint requires non-empty text without NUL bytes');
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function sourceGeneration(array $state, string $key): int
    {
        return max(1, (int) ($state['persistent_generations'][$key] ?? 1));
    }

    private static function writeControl(string $control): bool
    {
        return in_array($control, ['chunk_size', 'persist_wal', 'powersafe_overwrite', 'reserve_bytes', 'size_hint'], true);
    }

    private static function writeLockHeld(string $level): bool
    {
        return in_array($level, ['reserved', 'pending', 'exclusive'], true);
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
            throw new \InvalidArgumentException('SQLite VFS temp current-source suffix must be a plain filename suffix');
        }

        return $suffix;
    }
}
