<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsShmOpenFileControlCurrentSourcePlan
{
    /**
     * @param list<string|array<string,mixed>> $operations
     * @param array<string,mixed> $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function planShmOpenFileControl(array $operations, array $options = []): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS SHM file-control open current-source next91 requires operations');
        }

        $state = self::normalizeCurrent($options['current'] ?? null);
        $events = [];

        foreach ($operations as $operation) {
            $op = self::normalizeOperation($operation);
            $before = self::snapshot($state);

            if ($op['kind'] === 'open') {
                $handle = self::openHandle($state, $op, $options);
                $state['handles'][$handle['id']] = $handle;
                $state['source_handles'][$handle['source']] = $handle['id'];
                $state['current_source'] = $handle['source'];
                $state['owners'][$handle['owner']] = self::ownerSnapshot($state, $handle['owner']);
                $events[] = self::event('open', $handle['status'], $before, self::snapshot($state), [
                    'handle' => $handle['id'],
                    'source' => $handle['source'],
                    'path' => $handle['path'],
                    'owner' => $handle['owner'],
                    'uri' => $handle['uri'],
                    'reused_controls' => $handle['reused_controls'],
                    'sidecar_open_first' => $handle['source'] !== 'main' && !$handle['owner_had_main_open'],
                ]);
                continue;
            }

            if ($op['kind'] === 'source') {
                $source = self::sourceName((string) $op['source']);
                if (!isset($state['source_handles'][$source])) {
                    $events[] = self::event('source', 'missing-handle', $before, self::snapshot($state), [
                        'source' => $source,
                    ]);
                    continue;
                }
                $state['current_source'] = $source;
                $events[] = self::event('source', 'ok', $before, self::snapshot($state), [
                    'source' => $source,
                    'handle' => $state['source_handles'][$source],
                ]);
                continue;
            }

            if ($op['kind'] === 'filecontrol') {
                $source = self::sourceFor($state, $op['source']);
                $handleId = self::handleForSource($state, $source);
                if ($handleId === null) {
                    $events[] = self::event('filecontrol', 'missing-handle', $before, self::snapshot($state), [
                        'source' => $source,
                        'routed_to' => 'owner-database',
                    ]);
                    continue;
                }

                $handle = &$state['handles'][$handleId];
                $control = self::controlName((string) $op['control']);
                $value = self::controlValue($control, $op['value']);
                $previous = $state['persistent_controls'][$handle['owner']][$control] ?? null;
                $status = 'ok';
                $reason = null;
                if (!$handle['persistent']) {
                    $status = 'ignored';
                    $reason = 'memory_source_has_no_persistent_owner';
                } elseif ($handle['readonly'] && self::writeControl($control)) {
                    $status = 'ignored';
                    $reason = 'readonly_owner_handle';
                } else {
                    $state['persistent_controls'][$handle['owner']][$control] = $value;
                    $state['owners'][$handle['owner']] = self::ownerSnapshot($state, $handle['owner']);
                    foreach ($state['handles'] as &$candidate) {
                        if (($candidate['owner'] ?? null) === $handle['owner']) {
                            $candidate['controls'] = $state['persistent_controls'][$handle['owner']];
                        }
                    }
                    unset($candidate);
                }
                unset($handle);

                $events[] = self::event('filecontrol', $status, $before, self::snapshot($state), [
                    'source' => $source,
                    'handle' => $handleId,
                    'file_control' => $control,
                    'value' => $value,
                    'previous' => $previous,
                    'changed' => $status === 'ok' && $previous !== $value,
                    'reason' => $reason,
                    'routed_to' => 'owner-database',
                ]);
                continue;
            }

            if ($op['kind'] === 'close') {
                $source = self::sourceFor($state, $op['source']);
                $handleId = self::handleForSource($state, $source);
                if ($handleId === null) {
                    $events[] = self::event('close', 'missing-handle', $before, self::snapshot($state), [
                        'source' => $source,
                    ]);
                    continue;
                }

                $handle = $state['handles'][$handleId];
                unset($state['handles'][$handleId], $state['source_handles'][$source]);
                $state['owners'][$handle['owner']] = self::ownerSnapshot($state, $handle['owner']);
                if ($state['current_source'] === $source) {
                    $state['current_source'] = self::firstOpenSource($state);
                }

                $events[] = self::event('close', 'closed', $before, self::snapshot($state), [
                    'source' => $source,
                    'handle' => $handleId,
                    'owner' => $handle['owner'],
                    'persistent_controls_retained' => isset($state['persistent_controls'][$handle['owner']]),
                ]);
                continue;
            }

            throw new \InvalidArgumentException('SQLite VFS SHM file-control open current-source operation is unsupported');
        }

        return [
            'status' => (string) $events[array_key_last($events)]['status'],
            'current' => $events[0]['current'],
            'next' => self::next($state),
            'events' => $events,
            'dependencies' => [
                'sqlite-file-uri',
                'vfs-shm-sidecar-owner-routing',
                'vfs-xfilecontrol-current-source',
                'vfs-shm-filecontrol-open-current-source-next91',
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
                'owners' => [],
            ];
        }

        return [
            'sequence' => max(0, (int) ($current['sequence'] ?? 0)),
            'current_source' => isset($current['current_source']) ? self::sourceName((string) $current['current_source']) : null,
            'handles' => is_array($current['handles'] ?? null) ? $current['handles'] : [],
            'source_handles' => is_array($current['source_handles'] ?? null) ? $current['source_handles'] : [],
            'persistent_controls' => is_array($current['persistent_controls'] ?? null) ? $current['persistent_controls'] : [],
            'owners' => is_array($current['owners'] ?? null) ? $current['owners'] : [],
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
        $filename = self::stringValue($op['filename'] ?? $options['filename'] ?? '/srv/www/wp-content/database/.ht.sqlite');
        $uri = SQLiteFileUri::parse($filename);
        $path = self::openPath($filename, $uri);
        $memory = $path === ':memory:' || $uri['mode'] === 'memory';
        $source = self::sourceName((string) ($op['source'] ?? self::sourceFromPath($path)));
        $owner = $memory ? 'memory:vfs91-' . $state['sequence'] : self::ownerPath($path);
        $sourcePath = $memory ? '' : self::sourcePath($owner, $source);
        $ownerHadMainOpen = isset($state['source_handles']['main'])
            && isset($state['handles'][(string) $state['source_handles']['main']])
            && ($state['handles'][(string) $state['source_handles']['main']]['owner'] ?? null) === $owner;
        $controls = $memory ? [] : (is_array($state['persistent_controls'][$owner] ?? null) ? $state['persistent_controls'][$owner] : []);

        return [
            'id' => 'vfs91-' . $state['sequence'],
            'status' => $source . '-open',
            'source' => $source,
            'path' => $sourcePath,
            'owner' => $owner,
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
            'readonly' => (bool) ($op['readonly'] ?? ($uri['mode'] === 'ro' || $uri['immutable'] === true)),
            'persistent' => !$memory,
            'controls' => $controls,
            'reused_controls' => $controls !== [],
            'owner_had_main_open' => $ownerHadMainOpen,
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
                    'xopen' => 'open',
                    'xfilecontrol', 'filecontrol' => 'filecontrol',
                    default => $kind,
                },
                'source' => $operation['source'] ?? null,
                'filename' => $operation['filename'] ?? null,
                'readonly' => $operation['readonly'] ?? null,
                'control' => $operation['control'] ?? null,
                'value' => $operation['value'] ?? null,
            ];
        }

        $trimmed = trim($operation);
        if (preg_match('/^open\s*(?:\((?<arg>[^)]*)\))?$/i', $trimmed, $matches) === 1) {
            $arg = trim($matches['arg'] ?? '');
            if (in_array(strtolower($arg), ['main', 'wal', 'shm'], true)) {
                return ['kind' => 'open', 'source' => strtolower($arg), 'filename' => null];
            }

            return ['kind' => 'open', 'source' => null, 'filename' => $arg !== '' ? $arg : null];
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

        throw new \InvalidArgumentException('SQLite VFS SHM file-control open current-source operation is unsupported');
    }

    private static function openPath(string $filename, array $uri): string
    {
        if (($uri['path'] ?? '') !== '') {
            return (string) $uri['path'];
        }
        if ($filename === ':memory:' || str_starts_with(strtolower($filename), 'file::memory:')) {
            return ':memory:';
        }
        if (str_starts_with(strtolower($filename), 'file:')) {
            $withoutScheme = substr($filename, 5);
            $query = strpos($withoutScheme, '?');

            return $query === false ? $withoutScheme : substr($withoutScheme, 0, $query);
        }

        return $filename;
    }

    private static function sourceFromPath(string $path): string
    {
        if (str_ends_with($path, '-shm')) {
            return 'shm';
        }
        if (str_ends_with($path, '-wal')) {
            return 'wal';
        }

        return 'main';
    }

    private static function ownerPath(string $path): string
    {
        return preg_replace('/-(?:wal|shm)$/', '', $path) ?? $path;
    }

    private static function sourcePath(string $owner, string $source): string
    {
        return match ($source) {
            'wal' => $owner . '-wal',
            'shm' => $owner . '-shm',
            default => $owner,
        };
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

    /**
     * @param array<string,mixed> $state
     */
    private static function handleForSource(array $state, string $source): ?string
    {
        if (isset($state['source_handles'][$source])) {
            return (string) $state['source_handles'][$source];
        }

        return null;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function ownerSnapshot(array $state, string $owner): array
    {
        $open = [];
        foreach (['main', 'wal', 'shm'] as $source) {
            $handleId = $state['source_handles'][$source] ?? null;
            $open[$source] = is_string($handleId)
                && isset($state['handles'][$handleId])
                && ($state['handles'][$handleId]['owner'] ?? null) === $owner;
        }

        return [
            'owner' => $owner,
            'open' => $open,
            'controls' => is_array($state['persistent_controls'][$owner] ?? null) ? $state['persistent_controls'][$owner] : [],
        ];
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function firstOpenSource(array $state): ?string
    {
        foreach (['main', 'wal', 'shm'] as $source) {
            if (isset($state['source_handles'][$source])) {
                return $source;
            }
        }

        return null;
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
            'chunk_size', 'mmap_size', 'reserve_bytes', 'data_version', 'size_hint' => self::nonNegativeInt($value, $control),
            'name_hint' => self::nameHint($value),
            default => $value,
        };
    }

    private static function writeControl(string $control): bool
    {
        return in_array($control, ['chunk_size', 'reserve_bytes', 'powersafe_overwrite', 'data_version', 'size_hint'], true);
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
        ksort($state['owners']);

        return $state;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function next(array $state): array
    {
        $openBySource = ['main' => 0, 'wal' => 0, 'shm' => 0];
        foreach ($state['handles'] as $handle) {
            $source = (string) ($handle['source'] ?? 'main');
            $openBySource[$source] = ($openBySource[$source] ?? 0) + 1;
        }

        return [
            'current_source' => $state['current_source'],
            'open_by_source' => $openBySource,
            'owner_count' => count($state['owners']),
            'persistent_control_count' => count($state['persistent_controls']),
            'persistent_controls' => $state['persistent_controls'],
            'owners' => $state['owners'],
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

        throw new \InvalidArgumentException("SQLite VFS file-control {$label} requires a non-negative integer");
    }

    private static function nameHint(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '' || str_contains($value, "\0")) {
            throw new \InvalidArgumentException('SQLite VFS file-control name_hint requires non-empty text without NUL bytes');
        }

        return $value;
    }

    private static function stringValue(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException('SQLite VFS open current-source filename must not be empty');
        }

        return $value;
    }
}
