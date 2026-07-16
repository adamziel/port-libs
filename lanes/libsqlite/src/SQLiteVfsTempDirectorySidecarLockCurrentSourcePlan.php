<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsTempDirectorySidecarLockCurrentSourcePlan
{
    /**
     * @param list<array<string,mixed>> $operations
     * @param array{temp_dir?:string,connection_id?:string,current?:array<string,mixed>} $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function planTempDirectorySidecarLock(array $operations, array $options = []): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS temp-directory sidecar lock requires operations');
        }

        $state = self::normalizeCurrent($options['current'] ?? null, (string) ($options['temp_dir'] ?? sys_get_temp_dir()));
        $connectionId = self::segment((string) ($options['connection_id'] ?? 'conn'));
        $events = [];

        foreach ($operations as $operation) {
            $op = self::operation($operation);
            $before = self::snapshot($state);

            if ($op['op'] === 'temp_directory') {
                $old = $state['temp_dir'];
                $state['temp_dir'] = self::directory((string) $op['path']);
                $state['temp_generation']++;
                $events[] = self::event('temp_directory', 'ok', $before, self::snapshot($state), [
                    'old_directory' => $old,
                    'new_directory' => $state['temp_dir'],
                    'temp_generation' => $state['temp_generation'],
                    'changed' => $old !== $state['temp_dir'],
                ]);
                continue;
            }

            if ($op['op'] === 'open') {
                $state['sequence']++;
                $source = self::source((string) $op['source']);
                $suffix = self::suffix((string) $op['suffix']);
                $handle = self::handle($state, $connectionId, $source, $suffix, (bool) $op['delete_on_close']);
                $state['handles'][$handle['id']] = $handle;
                $state['source_handles'][$source] = $handle['id'];
                $state['last_open'] = $handle['id'];
                $state['sidecar_locks'][$handle['sidecar_key']] ??= 'unlocked';

                $events[] = self::event('open', 'temp-open', $before, self::snapshot($state), [
                    'source' => $source,
                    'handle' => $handle['id'],
                    'path' => $handle['path'],
                    'sidecar_path' => $handle['sidecar_path'],
                    'sidecar_key' => $handle['sidecar_key'],
                    'directory_generation' => $handle['directory_generation'],
                    'reused_sidecar_lock' => $before['sidecar_locks'][$handle['sidecar_key']] ?? null,
                ]);
                continue;
            }

            if ($op['op'] === 'lock') {
                $handleId = self::targetHandle($state, $op);
                if ($handleId === null || !isset($state['handles'][$handleId])) {
                    $events[] = self::event('lock', 'missing-handle', $before, self::snapshot($state), [
                        'handle' => $op['handle'] ?? null,
                        'lock_state' => self::lockState((string) $op['value']),
                    ]);
                    continue;
                }

                $lock = self::lockState((string) $op['value']);
                $key = (string) $state['handles'][$handleId]['sidecar_key'];
                $state['handles'][$handleId]['lock_state'] = $lock;
                $state['sidecar_locks'][$key] = $lock;
                $events[] = self::event('lock', 'ok', $before, self::snapshot($state), [
                    'handle' => $handleId,
                    'sidecar_key' => $key,
                    'lock_state' => $lock,
                ]);
                continue;
            }

            if ($op['op'] === 'filecontrol') {
                $handleId = self::targetHandle($state, $op);
                if ($handleId === null || !isset($state['handles'][$handleId])) {
                    $events[] = self::event('filecontrol', 'missing-handle', $before, self::snapshot($state), [
                        'handle' => $op['handle'] ?? null,
                        'file_control' => $op['control'],
                    ]);
                    continue;
                }

                $handle = &$state['handles'][$handleId];
                $key = (string) $handle['sidecar_key'];
                $control = self::control((string) $op['control']);
                $previous = $handle['controls'][$control] ?? null;
                $value = self::controlValue($control, $op['value'] ?? null);
                $handle['controls'][$control] = $value;
                $state['sidecar_controls'][$key] = $handle['controls'];
                unset($handle);

                $events[] = self::event('filecontrol', 'ok', $before, self::snapshot($state), [
                    'handle' => $handleId,
                    'sidecar_key' => $key,
                    'file_control' => $control,
                    'value' => $value,
                    'previous' => $previous,
                    'changed' => $previous !== $value,
                ]);
                continue;
            }

            if ($op['op'] === 'close') {
                $handleId = self::targetHandle($state, $op);
                if ($handleId === null || !isset($state['handles'][$handleId])) {
                    $events[] = self::event('close', 'missing-handle', $before, self::snapshot($state), [
                        'handle' => $op['handle'] ?? null,
                    ]);
                    continue;
                }

                $handle = $state['handles'][$handleId];
                unset($state['handles'][$handleId]);
                if (($state['source_handles'][(string) $handle['source']] ?? null) === $handleId) {
                    unset($state['source_handles'][(string) $handle['source']]);
                }
                $state['sidecar_locks'][(string) $handle['sidecar_key']] = 'unlocked';
                if ((bool) $handle['delete_on_close']) {
                    unset($state['sidecar_controls'][(string) $handle['sidecar_key']]);
                }

                $events[] = self::event('close', 'closed', $before, self::snapshot($state), [
                    'handle' => $handleId,
                    'sidecar_key' => $handle['sidecar_key'],
                    'deleted' => (bool) $handle['delete_on_close'],
                    'lock_state' => 'unlocked',
                ]);
                continue;
            }

            throw new \InvalidArgumentException('Unsupported SQLite VFS temp-directory sidecar lock operation');
        }

        return [
            'status' => (string) $events[array_key_last($events)]['status'],
            'current' => self::snapshot($state),
            'next' => self::next($state),
            'events' => $events,
            'dependencies' => [
                'vfs-sidecar-paths',
                'vfs-tempfile-open-lifecycle',
                'vfs-temp-directory-sidecar-lock',
            ],
        ];
    }

    /**
     * @param array<string,mixed>|null $current
     * @return array<string,mixed>
     */
    private static function normalizeCurrent(mixed $current, string $tempDir): array
    {
        if (!is_array($current)) {
            return [
                'temp_dir' => self::directory($tempDir),
                'temp_generation' => 1,
                'sequence' => 0,
                'last_open' => null,
                'source_handles' => [],
                'handles' => [],
                'sidecar_locks' => [],
                'sidecar_controls' => [],
            ];
        }

        return [
            'temp_dir' => self::directory((string) ($current['temp_dir'] ?? $tempDir)),
            'temp_generation' => max(1, (int) ($current['temp_generation'] ?? 1)),
            'sequence' => max(0, (int) ($current['sequence'] ?? 0)),
            'last_open' => isset($current['last_open']) ? (string) $current['last_open'] : null,
            'source_handles' => is_array($current['source_handles'] ?? null) ? $current['source_handles'] : [],
            'handles' => is_array($current['handles'] ?? null) ? $current['handles'] : [],
            'sidecar_locks' => is_array($current['sidecar_locks'] ?? null) ? $current['sidecar_locks'] : [],
            'sidecar_controls' => is_array($current['sidecar_controls'] ?? null) ? $current['sidecar_controls'] : [],
        ];
    }

    /**
     * @param array<string,mixed> $operation
     * @return array<string,mixed>
     */
    private static function operation(array $operation): array
    {
        $op = strtolower(str_replace(['_', '-'], '', (string) ($operation['op'] ?? '')));

        return [
            'op' => match ($op) {
                'tempdirectory', 'tempdir' => 'temp_directory',
                'xfilecontrol', 'filecontrol' => 'filecontrol',
                default => $op,
            },
            'path' => $operation['path'] ?? $operation['value'] ?? null,
            'source' => $operation['source'] ?? 'temp',
            'suffix' => $operation['suffix'] ?? 'stmt-journal',
            'delete_on_close' => $operation['delete_on_close'] ?? false,
            'handle' => $operation['handle'] ?? null,
            'control' => $operation['control'] ?? null,
            'value' => $operation['value'] ?? null,
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function handle(array $state, string $connectionId, string $source, string $suffix, bool $deleteOnClose): array
    {
        $basename = 'sqlite-' . $connectionId . '-' . str_pad((string) $state['sequence'], 6, '0', STR_PAD_LEFT) . $suffix;
        $path = $state['temp_dir'] . '/' . $basename;
        $sidecarPath = $state['temp_dir'] . '/.sqlite-' . $connectionId . '-' . $source . $suffix . '.lock';
        $key = $state['temp_dir'] . '|' . $source . '|' . $suffix;
        $controls = $state['sidecar_controls'][$key] ?? [];

        return [
            'id' => 'temp-' . $connectionId . '-' . $state['sequence'],
            'source' => $source,
            'path' => $path,
            'suffix' => $suffix,
            'directory' => $state['temp_dir'],
            'directory_generation' => $state['temp_generation'],
            'sidecar_path' => $sidecarPath,
            'sidecar_key' => $key,
            'delete_on_close' => $deleteOnClose,
            'lock_state' => (string) ($state['sidecar_locks'][$key] ?? 'unlocked'),
            'controls' => is_array($controls) ? $controls : [],
        ];
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
        $source = self::source((string) ($op['source'] ?? 'temp'));
        if (isset($state['source_handles'][$source])) {
            return (string) $state['source_handles'][$source];
        }

        return isset($state['last_open']) ? (string) $state['last_open'] : null;
    }

    private static function directory(string $directory): string
    {
        $directory = rtrim(str_replace('\\', '/', trim($directory)), '/');
        if ($directory === '' || str_contains($directory, "\0")) {
            throw new \InvalidArgumentException('SQLite temp directory sidecar lock path is invalid');
        }

        return $directory;
    }

    private static function segment(string $segment): string
    {
        $segment = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($segment)) ?? '');
        $segment = trim($segment, '-');

        return $segment === '' ? 'conn' : $segment;
    }

    private static function source(string $source): string
    {
        $source = strtolower(trim($source));
        if (!in_array($source, ['main', 'temp', 'attached'], true)) {
            throw new \InvalidArgumentException('SQLite VFS temp sidecar source is unsupported');
        }

        return $source;
    }

    private static function suffix(string $suffix): string
    {
        $suffix = trim($suffix);
        if ($suffix === '') {
            return '.tmp';
        }
        if (str_contains($suffix, '/') || str_contains($suffix, '\\') || str_contains($suffix, "\0")) {
            throw new \InvalidArgumentException('SQLite VFS temp sidecar suffix is invalid');
        }

        return str_starts_with($suffix, '.') ? $suffix : '.' . $suffix;
    }

    private static function lockState(string $lock): string
    {
        $lock = strtolower(trim($lock));
        if (!in_array($lock, ['unlocked', 'shared', 'reserved', 'pending', 'exclusive'], true)) {
            throw new \InvalidArgumentException('SQLite VFS temp sidecar lock state is unsupported');
        }

        return $lock;
    }

    private static function control(string $control): string
    {
        $control = strtolower(str_replace('-', '_', trim($control)));
        if ($control === '') {
            throw new \InvalidArgumentException('SQLite VFS temp sidecar file-control is required');
        }

        return $control;
    }

    private static function controlValue(string $control, mixed $value): mixed
    {
        return match ($control) {
            'chunk_size', 'size_hint', 'mmap_size', 'lock_timeout' => max(0, (int) $value),
            'persist_wal', 'powersafe_overwrite' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool) $value,
            default => $value,
        };
    }

    /**
     * @param array<string,mixed> $before
     * @param array<string,mixed> $after
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private static function event(string $op, string $status, array $before, array $after, array $extra): array
    {
        return ['op' => $op, 'status' => $status, 'current' => $before, 'next' => $after] + $extra;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function snapshot(array $state): array
    {
        ksort($state['source_handles']);
        ksort($state['handles']);
        ksort($state['sidecar_locks']);
        ksort($state['sidecar_controls']);

        return $state;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function next(array $state): array
    {
        $openByDirectory = [];
        $lockedSidecars = 0;
        foreach ($state['handles'] as $handle) {
            $directory = (string) $handle['directory'];
            $openByDirectory[$directory] = ($openByDirectory[$directory] ?? 0) + 1;
            if (($handle['lock_state'] ?? 'unlocked') !== 'unlocked') {
                $lockedSidecars++;
            }
        }
        ksort($openByDirectory);

        return [
            'temp_dir' => $state['temp_dir'],
            'temp_generation' => $state['temp_generation'],
            'open_count' => count($state['handles']),
            'open_by_directory' => $openByDirectory,
            'sidecar_lock_count' => count($state['sidecar_locks']),
            'locked_sidecar_count' => $lockedSidecars,
            'sidecar_control_count' => count($state['sidecar_controls']),
            'current_sidecar_keys' => array_keys($state['sidecar_locks']),
        ];
    }
}
