<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsOpenLockFileControlCurrentSource
{
    /**
     * @param list<string|array<string,mixed>> $operations
     * @param array<string,mixed> $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function planOpenLockFileControl(array $operations, array $options = []): array
    {
        return self::run($operations, $options, false, 'vfs-open-lock-filecontrol-current-source-next82');
    }

    /**
     * @param list<string|array<string,mixed>> $operations
     * @param array<string,mixed> $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function planUriOpenLock(array $operations, array $options = []): array
    {
        return self::run($operations, $options, true, 'vfs-open-uri-lock-current-source-next86');
    }

    /**
     * @param list<string|array<string,mixed>> $operations
     * @param array<string,mixed> $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function planLockingFileControlPersistence(array $operations, array $options = []): array
    {
        return self::run($operations, $options, true, 'vfs-filecontrol-locking-persistence-current-source-next90', true);
    }

    /**
     * @param list<string|array<string,mixed>> $operations
     * @param array<string,mixed> $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function planPersistWalLockFileControl(array $operations, array $options = []): array
    {
        return self::run($operations, $options, true, 'vfs-filecontrol-persistwal-lock-current-source-next94', true, true);
    }

    /**
     * @param list<string|array<string,mixed>> $operations
     * @param array<string,mixed> $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function planGeneratedSourceFileControls(array $operations, array $options = []): array
    {
        return self::run($operations, $options, true, 'vfs-open-lock-filecontrol-current-source-next99', true, true, true);
    }

    /**
     * @param list<string|array<string,mixed>> $operations
     * @param array<string,mixed> $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function planOpenDeviceCharacteristics(array $operations, array $options = []): array
    {
        return self::run($operations, $options, true, 'vfs-xopen-device-characteristics-current-source-next103', true, true, true, true);
    }

    /**
     * @param list<string|array<string,mixed>> $operations
     * @param array<string,mixed> $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function planUriFileControls(array $operations, array $options = []): array
    {
        return self::run($operations, $options, true, 'vfs-open-lock-filecontrol-uri-current-source-next105', true, true, true, true, true);
    }

    /**
     * @param list<string|array<string,mixed>> $operations
     * @param array<string,mixed> $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function planSqliteUriFileControls(array $operations, array $options = []): array
    {
        return self::run($operations, $options, true, 'vfs-open-lock-filecontrol-uri-current-source-next109', true, true, true, true, true, true);
    }

    /**
     * @param list<string|array<string,mixed>> $operations
     * @param array<string,mixed> $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    private static function run(
        array $operations,
        array $options,
        bool $uriAware,
        string $dependency,
        bool $lockRequiredForWriteControl = false,
        bool $persistWalRequiresWriteLock = false,
        bool $trackCurrentSourceGeneration = false,
        bool $trackDeviceCharacteristics = false,
        bool $trackUriFileControl = false,
        bool $sqliteUriHelperSemantics = false
    ): array {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS open lock file-control current-source next82 requires operations');
        }

        $state = self::normalizeCurrent($options['current'] ?? null);
        $events = [];

        foreach ($operations as $operation) {
            $op = self::normalizeOperation($operation);
            $before = self::snapshot($state);

            if ($op['kind'] === 'open') {
                $handle = self::openHandle($op, $options, $state, $uriAware, $trackCurrentSourceGeneration, $trackDeviceCharacteristics);
                $state['handles'][$handle['id']] = $handle;
                $state['last_open'] = $handle['id'];
                $events[] = self::event('open', $handle['status'], $before, self::snapshot($state), [
                    'handle' => $handle['id'],
                    'path' => $handle['path'],
                    'source_key' => $handle['source_key'],
                    'uri' => $handle['uri'],
                    'reused_controls' => $handle['reused_controls'],
                    'reused_lock' => $handle['reused_lock'],
                    'device_characteristics' => $handle['device_characteristics'] ?? null,
                    'device_flags' => $handle['device_flags'] ?? [],
                    'sector_size' => $handle['sector_size'] ?? null,
                    'xopen_flags' => $handle['xopen_flags'] ?? [],
                    'powersafe_overwrite' => $handle['controls']['powersafe_overwrite'] ?? null,
                ]);
                continue;
            }

            $handleId = self::targetHandle($state, $op['handle']);
            if ($handleId === null || !isset($state['handles'][$handleId])) {
                $events[] = self::event($op['kind'], 'missing-handle', $before, self::snapshot($state), [
                    'handle' => $op['handle'],
                ]);
                continue;
            }

            if ($op['kind'] === 'filecontrol') {
                $handle = &$state['handles'][$handleId];
                $control = self::controlName((string) $op['control']);
                $value = self::controlValue($control, $op['value']);
                $previous = $handle['controls'][$control] ?? null;
                if ($trackCurrentSourceGeneration && $control === 'data_version' && $op['value'] === null) {
                    $sourceKey = (string) $handle['source_key'];
                    $currentGeneration = self::sourceGeneration($state, $sourceKey);
                    $openedGeneration = (int) ($handle['source_generation'] ?? $currentGeneration);
                    unset($handle);

                    $events[] = self::event('filecontrol', 'ok', $before, self::snapshot($state), [
                        'handle' => $handleId,
                        'file_control' => $control,
                        'value' => $currentGeneration,
                        'previous' => $openedGeneration,
                        'changed' => false,
                        'reason' => null,
                        'lock_state' => (string) ($before['handles'][$handleId]['lock_state'] ?? 'unlocked'),
                        'source_generation' => $currentGeneration,
                        'opened_generation' => $openedGeneration,
                        'stale_current_source' => $openedGeneration !== $currentGeneration,
                    ]);
                    continue;
                }
                if ($trackDeviceCharacteristics && $control === 'device_characteristics' && $op['value'] === null) {
                    $characteristics = (int) ($handle['device_characteristics'] ?? 0);
                    $flags = is_array($handle['device_flags'] ?? null) ? array_values($handle['device_flags']) : [];
                    unset($handle);

                    $events[] = self::event('filecontrol', 'ok', $before, self::snapshot($state), [
                        'handle' => $handleId,
                        'file_control' => $control,
                        'value' => $characteristics,
                        'previous' => $characteristics,
                        'changed' => false,
                        'reason' => null,
                        'lock_state' => (string) ($before['handles'][$handleId]['lock_state'] ?? 'unlocked'),
                        'device_characteristics' => $characteristics,
                        'device_flags' => $flags,
                        'powersafe_overwrite' => in_array('powersafe_overwrite', $flags, true),
                    ]);
                    continue;
                }
                if ($trackUriFileControl && in_array($control, ['uri_parameter', 'uri_boolean', 'uri_int'], true)) {
                    $parameter = self::uriFileControlParameter($op['value']);
                    $default = $sqliteUriHelperSemantics ? self::uriFileControlDefault($op['value'], $control) : null;
                    $uriValues = self::uriParameterValues($handle, $parameter);
                    $value = match ($control) {
                        'uri_boolean' => $sqliteUriHelperSemantics ? self::sqliteUriBooleanValue($uriValues, $default) : self::uriBooleanValue($uriValues),
                        'uri_int' => $sqliteUriHelperSemantics ? self::sqliteUriIntValue($uriValues, $default) : self::uriIntValue($uriValues),
                        default => $uriValues[array_key_last($uriValues)] ?? null,
                    };
                    unset($handle);

                    $events[] = self::event('filecontrol', 'ok', $before, self::snapshot($state), [
                        'handle' => $handleId,
                        'file_control' => $control,
                        'parameter' => $parameter,
                        'value' => $value,
                        'values' => $uriValues,
                        'default' => $default,
                        'previous' => null,
                        'changed' => false,
                        'reason' => $uriValues === [] ? 'missing_uri_parameter' : null,
                        'lock_state' => (string) ($before['handles'][$handleId]['lock_state'] ?? 'unlocked'),
                        'source_generation' => $trackCurrentSourceGeneration ? self::sourceGeneration($state, (string) ($before['handles'][$handleId]['source_key'] ?? '')) : null,
                        'opened_generation' => (int) ($before['handles'][$handleId]['source_generation'] ?? 1),
                        'stale_current_source' => $trackCurrentSourceGeneration
                            ? (int) ($before['handles'][$handleId]['source_generation'] ?? 1) !== self::sourceGeneration($state, (string) ($before['handles'][$handleId]['source_key'] ?? ''))
                            : false,
                    ]);
                    continue;
                }
                $requiresWrite = self::writeControl($control, $persistWalRequiresWriteLock);
                $lockState = (string) $handle['lock_state'];
                $reason = null;
                $status = 'ok';
                if ($handle['readonly'] && $requiresWrite) {
                    $status = 'ignored';
                    $reason = 'readonly_handle';
                } elseif ($lockRequiredForWriteControl && $requiresWrite && !self::writeLockHeld($lockState)) {
                    $status = 'blocked';
                    $reason = 'requires_reserved_or_exclusive_lock';
                }
                if ($status === 'ok') {
                    $handle['controls'][$control] = $value;
                    if ($lockRequiredForWriteControl && $requiresWrite && $handle['persistent']) {
                        $handle['controls']['data_version'] = self::nextDataVersion($handle['controls']['data_version'] ?? null);
                    }
                    if ($trackDeviceCharacteristics && $control === 'powersafe_overwrite') {
                        $handle['device_characteristics'] = self::deviceCharacteristics(
                            self::deviceFlagsWithPowersafe(
                                is_array($handle['device_flags'] ?? null) ? $handle['device_flags'] : [],
                                (bool) $value
                            )
                        );
                        $handle['device_flags'] = self::deviceFlagNames((int) $handle['device_characteristics']);
                    }
                    if ($handle['persistent']) {
                        $state['persistent_controls'][$handle['source_key']] = self::persistentSubset($handle['controls']);
                        if ($trackCurrentSourceGeneration && $requiresWrite && $previous !== $value) {
                            $state['persistent_generations'][$handle['source_key']] = self::sourceGeneration($state, (string) $handle['source_key']) + 1;
                            $handle['source_generation'] = $state['persistent_generations'][$handle['source_key']];
                        }
                    }
                }
                unset($handle);

                $staleHandles = $trackCurrentSourceGeneration
                    ? self::staleHandles($state, (string) ($before['handles'][$handleId]['source_key'] ?? ''))
                    : [];
                $events[] = self::event('filecontrol', $status, $before, self::snapshot($state), [
                    'handle' => $handleId,
                    'file_control' => $control,
                    'value' => $value,
                    'previous' => $previous,
                    'changed' => $status === 'ok' && $previous !== $value,
                    'reason' => $reason,
                    'lock_state' => $lockState,
                    'source_generation' => $trackCurrentSourceGeneration ? self::sourceGeneration($state, (string) ($before['handles'][$handleId]['source_key'] ?? '')) : null,
                    'stale_handles' => $staleHandles,
                ]);
                continue;
            }

            if ($op['kind'] === 'lock') {
                $handle = &$state['handles'][$handleId];
                $level = self::lockLevel((string) $op['value']);
                if ($handle['nolock'] || $handle['immutable']) {
                    $events[] = self::event('lock', 'blocked', $before, self::snapshot($state), [
                        'handle' => $handleId,
                        'lock_state' => $handle['lock_state'],
                        'reason' => $handle['immutable']
                            ? 'immutable URI disables locking and change detection'
                            : 'nolock VFS disables POSIX byte-range locking',
                    ]);
                    unset($handle);
                    continue;
                }

                $handle['lock_state'] = $level;
                if ($handle['persistent']) {
                    $state['persistent_locks'][$handle['source_key']] = $level;
                }
                unset($handle);

                $events[] = self::event('lock', 'ok', $before, self::snapshot($state), [
                    'handle' => $handleId,
                    'lock_state' => $level,
                ]);
                continue;
            }

            if ($op['kind'] === 'close') {
                $handle = $state['handles'][$handleId];
                unset($state['handles'][$handleId]);
                $deleted = (bool) $handle['delete_on_close'] && $handle['persistent'];
                if ($deleted || !$handle['persistent']) {
                    unset($state['persistent_controls'][$handle['source_key']], $state['persistent_locks'][$handle['source_key']]);
                } else {
                    $state['persistent_controls'][$handle['source_key']] = self::persistentSubset($handle['controls']);
                    $state['persistent_locks'][$handle['source_key']] = 'unlocked';
                }

                $events[] = self::event('close', 'closed', $before, self::snapshot($state), [
                    'handle' => $handleId,
                    'path' => $handle['path'],
                    'deleted' => $deleted,
                    'persisted_controls' => !$deleted && $handle['persistent'],
                    'lock_state' => 'unlocked',
                ]);
                continue;
            }
        }

        $dependencies = [
            'vfs-open-file-control-application',
            'vfs-lock-state-application',
            $dependency,
        ];
        if ($trackDeviceCharacteristics) {
            $dependencies[] = 'vfs-xopen';
            $dependencies[] = 'vfs-xdevicecharacteristics';
        }
        if ($trackUriFileControl) {
            $dependencies[] = 'vfs-uri-file-control';
        }
        if ($sqliteUriHelperSemantics) {
            $dependencies[] = 'sqlite3-uri-helper-semantics';
        }

        return [
            'status' => (string) $events[array_key_last($events)]['status'],
            'current' => self::snapshot($state),
            'next' => self::next($state),
            'events' => $events,
            'dependencies' => array_values(array_unique($dependencies)),
        ];
    }

    /**
     * @param array<string,mixed>|null $current
     * @return array<string,mixed>
     */
    private static function normalizeCurrent(mixed $current): array
    {
        if (!is_array($current)) {
            return ['sequence' => 0, 'last_open' => null, 'handles' => [], 'persistent_controls' => [], 'persistent_locks' => [], 'persistent_generations' => []];
        }

        return [
            'sequence' => max(0, (int) ($current['sequence'] ?? 0)),
            'last_open' => isset($current['last_open']) ? (string) $current['last_open'] : null,
            'handles' => is_array($current['handles'] ?? null) ? $current['handles'] : [],
            'persistent_controls' => is_array($current['persistent_controls'] ?? null) ? $current['persistent_controls'] : [],
            'persistent_locks' => is_array($current['persistent_locks'] ?? null) ? $current['persistent_locks'] : [],
            'persistent_generations' => is_array($current['persistent_generations'] ?? null) ? $current['persistent_generations'] : [],
        ];
    }

    /**
     * @param array<string,mixed> $op
     * @param array<string,mixed> $options
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function openHandle(array $op, array $options, array &$state, bool $uriAware, bool $trackCurrentSourceGeneration = false, bool $trackDeviceCharacteristics = false): array
    {
        $state['sequence']++;
        $filename = (string) ($op['filename'] ?? '');
        if ($filename === '') {
            $filename = (string) ($options['filename'] ?? '/srv/app/data/application.sqlite');
        }
        $uri = self::openUri($filename, $uriAware);
        $path = (string) $uri['path'];
        $memory = $uri['mode'] === 'memory' || $path === ':memory:';
        $sourceKey = $memory ? 'memory:db-' . $state['sequence'] : $path;
        $controls = $memory ? [] : ($state['persistent_controls'][$sourceKey] ?? []);
        $lock = $memory ? 'unlocked' : (string) ($state['persistent_locks'][$sourceKey] ?? 'unlocked');
        $generation = $memory ? 1 : self::sourceGeneration($state, $sourceKey);

        if (!is_array($controls)) {
            $controls = [];
        }

        $deviceFlags = $trackDeviceCharacteristics ? self::openDeviceFlags($op, $options, $uri, $memory) : [];
        $deviceCharacteristics = $trackDeviceCharacteristics ? self::deviceCharacteristics($deviceFlags) : null;
        if ($trackDeviceCharacteristics && !array_key_exists('powersafe_overwrite', $controls)) {
            $controls['powersafe_overwrite'] = in_array('powersafe_overwrite', $deviceFlags, true);
        }

        return [
            'id' => 'db-' . $state['sequence'],
            'status' => $memory ? 'memory-open' : 'open',
            'path' => $memory ? '' : $path,
            'source_key' => $sourceKey,
            'readonly' => (bool) ($op['readonly'] ?? ($uri['mode'] === 'ro' || $uri['immutable'] === true)),
            'nolock' => (bool) ($op['nolock'] ?? $uri['nolock'] === true),
            'immutable' => $uri['immutable'] === true,
            'uri' => $uri,
            'delete_on_close' => (bool) ($op['delete_on_close'] ?? false),
            'persistent' => !$memory,
            'controls' => $controls,
            'lock_state' => $lock,
            'reused_controls' => $controls !== [],
            'reused_lock' => $lock !== 'unlocked',
            'source_generation' => $trackCurrentSourceGeneration ? $generation : null,
            'device_characteristics' => $deviceCharacteristics,
            'device_flags' => $trackDeviceCharacteristics ? self::deviceFlagNames((int) $deviceCharacteristics) : [],
            'sector_size' => $trackDeviceCharacteristics ? self::openSectorSize($op, $options, $memory) : null,
            'xopen_flags' => $trackDeviceCharacteristics ? self::xopenFlags($uri, (bool) ($op['readonly'] ?? ($uri['mode'] === 'ro' || $uri['immutable'] === true)), $memory) : [],
        ];
    }

    /**
     * @return array{is_uri:bool,path:string,mode:string|null,cache:string|null,immutable:bool|null,nolock:bool|null,vfs:string|null,authority:string|null}
     */
    private static function openUri(string $filename, bool $uriAware): array
    {
        if ($uriAware) {
            $uri = SQLiteFileUri::parse($filename);

            return [
                'is_uri' => $uri['is_uri'],
                'path' => $uri['path'] === '' ? self::canonicalPath($filename) : (string) $uri['path'],
                'mode' => $uri['mode'],
                'cache' => $uri['cache'],
                'immutable' => $uri['immutable'],
                'nolock' => $uri['nolock'],
                'vfs' => $uri['vfs'],
                'authority' => $uri['authority'],
                'known_parameters' => $uri['known_parameters'] ?? [],
                'unknown_parameters' => $uri['unknown_parameters'] ?? [],
                'all_query_parameters' => $uri['all_query_parameters'] ?? [],
            ];
        }

        $path = self::canonicalPath($filename);

        return [
            'is_uri' => str_starts_with($filename, 'file:'),
            'path' => $path,
            'mode' => str_contains(strtolower($filename), 'mode=memory') || $path === ':memory:' ? 'memory' : (str_contains(strtolower($filename), 'mode=ro') ? 'ro' : null),
            'cache' => null,
            'immutable' => null,
            'nolock' => str_contains(strtolower($filename), 'nolock=1') ? true : null,
            'vfs' => null,
            'authority' => null,
            'known_parameters' => [],
            'unknown_parameters' => [],
            'all_query_parameters' => [],
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
                    default => $kind,
                },
                'handle' => isset($operation['handle']) ? (string) $operation['handle'] : null,
                'filename' => $operation['filename'] ?? null,
                'readonly' => $operation['readonly'] ?? null,
                'nolock' => $operation['nolock'] ?? null,
                'device_flags' => $operation['device_flags'] ?? null,
                'sector_size' => $operation['sector_size'] ?? null,
                'delete_on_close' => $operation['delete_on_close'] ?? null,
                'control' => $operation['control'] ?? null,
                'value' => $operation['value'] ?? null,
            ];
        }

        $trimmed = trim($operation);
        if (preg_match('/^open\s*(?:\((?<filename>[^)]*)\))?$/i', $trimmed, $matches) === 1) {
            return ['kind' => 'open', 'handle' => null, 'filename' => trim($matches['filename'] ?? ''), 'value' => null];
        }
        if (preg_match('/^close\s*(?:\((?<handle>[^)]*)\))?$/i', $trimmed, $matches) === 1) {
            return ['kind' => 'close', 'handle' => ($matches['handle'] ?? '') !== '' ? trim($matches['handle']) : null, 'value' => null];
        }
        if (preg_match('/^lock\s*\(\s*(?<level>[^)]*)\)$/i', $trimmed, $matches) === 1) {
            return ['kind' => 'lock', 'handle' => null, 'value' => trim($matches['level'])];
        }
        if (preg_match('/^file_control\s*\(\s*(?<control>[A-Za-z_][A-Za-z0-9_-]*)\s*(?:,\s*(?<value>.*))?\)$/i', $trimmed, $matches) === 1) {
            return ['kind' => 'filecontrol', 'handle' => null, 'control' => $matches['control'], 'value' => self::parseValue($matches['value'] ?? null)];
        }

        throw new \InvalidArgumentException('SQLite VFS open lock file-control operation is unsupported');
    }

    private static function canonicalPath(string $filename): string
    {
        $filename = trim($filename);
        if ($filename === '') {
            return '/srv/app/data/application.sqlite';
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

    private static function lockLevel(string $level): string
    {
        $level = strtolower(trim($level));
        if (!in_array($level, ['unlocked', 'shared', 'reserved', 'pending', 'exclusive'], true)) {
            throw new \InvalidArgumentException('SQLite VFS lock state is unsupported');
        }

        return $level;
    }

    private static function writeControl(string $control, bool $persistWalRequiresWriteLock = false): bool
    {
        return in_array($control, ['chunk_size', 'size_hint', 'reserve_bytes', 'powersafe_overwrite'], true)
            || ($persistWalRequiresWriteLock && $control === 'persist_wal');
    }

    private static function writeLockHeld(string $lockState): bool
    {
        return in_array($lockState, ['reserved', 'pending', 'exclusive'], true);
    }

    private static function nextDataVersion(mixed $previous): int
    {
        $current = is_int($previous) ? $previous : ((is_string($previous) && preg_match('/^\d+$/', $previous) === 1) ? (int) $previous : 1);

        return max(1, $current) + 1;
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
     * @param array<string,mixed> $op
     * @param array<string,mixed> $options
     * @param array<string,mixed> $uri
     * @return list<string>
     */
    private static function openDeviceFlags(array $op, array $options, array $uri, bool $memory): array
    {
        $flags = [];
        $raw = $op['device_flags'] ?? $options['device_flags'] ?? ['powersafe_overwrite'];
        if (is_string($raw)) {
            $raw = preg_split('/[,\s|]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }
        if (!is_array($raw)) {
            throw new \InvalidArgumentException('SQLite VFS xOpen device flags must be a list');
        }

        foreach ($raw as $flag) {
            $name = strtolower(str_replace('-', '_', trim((string) $flag)));
            if ($name === '') {
                continue;
            }
            if (!array_key_exists($name, SQLiteVfsCapabilityPlan::deviceFlagMap())) {
                throw new \InvalidArgumentException("Unsupported SQLite VFS xOpen device flag: {$flag}");
            }
            $flags[$name] = true;
        }

        if ($memory) {
            unset($flags['powersafe_overwrite']);
        }
        if (($uri['nolock'] ?? null) === true) {
            unset($flags['undeletable_when_open']);
        }

        return array_keys($flags);
    }

    /**
     * @param list<string> $flags
     */
    private static function deviceCharacteristics(array $flags): int
    {
        $map = SQLiteVfsCapabilityPlan::deviceFlagMap();
        $value = 0;
        foreach ($flags as $flag) {
            $value |= $map[$flag] ?? 0;
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function deviceFlagNames(int $characteristics): array
    {
        $names = [];
        foreach (SQLiteVfsCapabilityPlan::deviceFlagMap() as $name => $bit) {
            if (($characteristics & $bit) !== 0) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @param list<string> $flags
     * @return list<string>
     */
    private static function deviceFlagsWithPowersafe(array $flags, bool $enabled): array
    {
        $set = array_fill_keys($flags, true);
        if ($enabled) {
            $set['powersafe_overwrite'] = true;
        } else {
            unset($set['powersafe_overwrite']);
        }

        return array_keys($set);
    }

    /**
     * @param array<string,mixed> $op
     * @param array<string,mixed> $options
     */
    private static function openSectorSize(array $op, array $options, bool $memory): int
    {
        if ($memory) {
            return 0;
        }
        $value = $op['sector_size'] ?? $options['sector_size'] ?? 512;
        if (!is_int($value) && !(is_string($value) && preg_match('/^\d+$/', $value) === 1)) {
            throw new \InvalidArgumentException('SQLite VFS xOpen sector size must be a positive integer');
        }
        $sectorSize = (int) $value;
        if ($sectorSize < 512 || ($sectorSize & ($sectorSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite VFS xOpen sector size must be a power of two at least 512');
        }

        return $sectorSize;
    }

    /**
     * @param array<string,mixed> $uri
     * @return list<string>
     */
    private static function xopenFlags(array $uri, bool $readonly, bool $memory): array
    {
        $flags = [$readonly ? 'readonly' : 'readwrite'];
        if ($memory) {
            $flags[] = 'memory';
        }
        if (($uri['mode'] ?? null) === 'rwc') {
            $flags[] = 'create';
        }
        if (($uri['cache'] ?? null) === 'shared') {
            $flags[] = 'sharedcache';
        }
        if (($uri['cache'] ?? null) === 'private') {
            $flags[] = 'privatecache';
        }
        if (($uri['immutable'] ?? null) === true) {
            $flags[] = 'immutable';
        }
        if (($uri['nolock'] ?? null) === true) {
            $flags[] = 'nolock';
        }

        return $flags;
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
        ksort($state['persistent_generations']);

        return $state;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function next(array $state): array
    {
        $openPaths = [];
        foreach ($state['handles'] as $handle) {
            if (($handle['path'] ?? '') !== '') {
                $openPaths[] = (string) $handle['path'];
            }
        }

        return [
            'open_count' => count($state['handles']),
            'open_paths' => $openPaths,
            'persistent_control_count' => count($state['persistent_controls']),
            'persistent_lock_count' => count(array_filter($state['persistent_locks'], static fn (mixed $level): bool => $level !== 'unlocked')),
            'persistent_generation_count' => count($state['persistent_generations']),
        ];
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function sourceGeneration(array $state, string $sourceKey): int
    {
        $generation = $state['persistent_generations'][$sourceKey] ?? null;
        if (is_int($generation) && $generation > 0) {
            return $generation;
        }

        return 1;
    }

    /**
     * @param array<string,mixed> $state
     * @return list<string>
     */
    private static function staleHandles(array $state, string $sourceKey): array
    {
        if ($sourceKey === '') {
            return [];
        }

        $generation = self::sourceGeneration($state, $sourceKey);
        $stale = [];
        foreach ($state['handles'] as $id => $handle) {
            if (($handle['source_key'] ?? null) !== $sourceKey) {
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

    private static function uriFileControlParameter(mixed $value): string
    {
        if (is_array($value)) {
            $value = $value['parameter'] ?? $value['name'] ?? null;
        }
        if (!is_string($value) || trim($value) === '' || str_contains($value, "\0")) {
            throw new \InvalidArgumentException('SQLite VFS URI file-control requires a non-empty parameter name');
        }

        return trim($value);
    }

    private static function uriFileControlDefault(mixed $value, string $control): mixed
    {
        if (!is_array($value) || !array_key_exists('default', $value)) {
            return $control === 'uri_boolean' ? false : ($control === 'uri_int' ? 0 : null);
        }

        return match ($control) {
            'uri_boolean' => self::boolean($value['default']),
            'uri_int' => is_int($value['default']) || (is_string($value['default']) && preg_match('/^-?\d+$/', trim($value['default'])) === 1)
                ? (int) $value['default']
                : throw new \InvalidArgumentException('SQLite VFS URI integer default expects an integer'),
            default => $value['default'],
        };
    }

    /**
     * @param array<string,mixed> $handle
     * @return list<string>
     */
    private static function uriParameterValues(array $handle, string $parameter): array
    {
        $parameters = $handle['uri']['all_query_parameters'] ?? [];
        if (!is_array($parameters) || !isset($parameters[$parameter]) || !is_array($parameters[$parameter])) {
            return [];
        }

        return array_values(array_map(static fn (mixed $value): string => (string) $value, $parameters[$parameter]));
    }

    /**
     * @param list<string> $values
     */
    private static function uriBooleanValue(array $values): ?bool
    {
        if ($values === []) {
            return null;
        }
        $value = $values[array_key_last($values)];

        return match ($value) {
            '0' => false,
            '1' => true,
            default => throw new \InvalidArgumentException("SQLite VFS URI boolean parameter expects 0 or 1: {$value}"),
        };
    }

    /**
     * @param list<string> $values
     */
    private static function sqliteUriBooleanValue(array $values, mixed $default): bool
    {
        if ($values === []) {
            return (bool) $default;
        }
        $value = strtolower($values[array_key_last($values)]);
        if (in_array($value, ['yes', 'true', 'on'], true) || preg_match('/^[+-]?[1-9][0-9]*/', $value) === 1) {
            return true;
        }
        if (in_array($value, ['no', 'false', 'off'], true) || preg_match('/^[+-]?0(?:\D|$)/', $value) === 1) {
            return false;
        }

        return (bool) $default;
    }

    /**
     * @param list<string> $values
     */
    private static function uriIntValue(array $values): ?int
    {
        if ($values === []) {
            return null;
        }
        $value = $values[array_key_last($values)];
        if (preg_match('/^-?\d+$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite VFS URI integer parameter expects an integer: {$value}");
        }

        return (int) $value;
    }

    /**
     * @param list<string> $values
     */
    private static function sqliteUriIntValue(array $values, mixed $default): int
    {
        if ($values === []) {
            return (int) $default;
        }
        $value = trim($values[array_key_last($values)]);
        if (preg_match('/^[+-]?\d+$/', $value) !== 1) {
            return 0;
        }

        return (int) $value;
    }
}
