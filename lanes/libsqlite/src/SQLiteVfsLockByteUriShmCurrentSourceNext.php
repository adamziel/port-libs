<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsLockByteUriShmCurrentSourceNext
{
    /**
     * @param list<string|array<string,mixed>> $operations
     * @param array<string,mixed> $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function plan(array $operations, array $options = [], ?string $filename = null): array
    {
        if ($filename !== null) {
            return self::legacyUriShmLockPlan($operations, $options, $filename);
        }

        return self::run($operations, $options, false, 'vfs-lock-byte-uri-shm-current-source-next97');
    }

    /**
     * @param list<string|array<string,mixed>> $operations
     * @param array<string,mixed> $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function planShmLockByteFileControl(array $operations, array $options = []): array
    {
        return self::run($operations, $options, true, 'vfs-shm-lockbyte-filecontrol-current-source-next112');
    }

    /**
     * @param list<string|array<string,mixed>> $operations
     * @param array<string,mixed> $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function planShmLockByteUriFileControl(array $operations, array $options = []): array
    {
        return self::run($operations, $options, true, 'vfs-shm-lockbyte-uri-filecontrol-current-source-next117');
    }

    /**
     * @param list<string|array<string,mixed>> $operations
     * @param array<string,mixed> $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function planOpenShmFileControlUri(array $operations, array $options = []): array
    {
        return self::run($operations, $options, true, 'vfs-open-shm-filecontrol-uri-current-source-next128', true);
    }

    /**
     * @param list<string|array<string,mixed>> $operations
     * @param array<string,mixed> $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function planLockingUriFileControl(array $operations, array $options = []): array
    {
        return self::run($operations, $options, true, 'vfs-locking-uri-filecontrol-current-source-next135', true, true);
    }

    /**
     * @param list<string|array<string,mixed>> $operations
     * @param array<string,mixed> $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function planUriFileControlShm(array $operations, array $options = []): array
    {
        return self::run($operations, $options, true, 'vfs-uri-filecontrol-shm-current-source-next136', true, true, true);
    }

    /**
     * @param list<string|array<string,mixed>> $operations
     * @param array<string,mixed> $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function planTempUriFileControlRegression(array $operations, array $options = []): array
    {
        return self::run($operations, $options, true, 'vfs-temp-uri-filecontrol-regression-current-source-next139', true, false, false, true);
    }

    /**
     * @param list<string|array<string,mixed>> $operations
     * @param array<string,mixed> $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function planUriShmFileControlRegression(array $operations, array $options = []): array
    {
        return self::run($operations, $options, true, 'vfs-uri-shm-filecontrol-regression-current-source-next141', true);
    }

    /**
     * @param list<string|array<string,mixed>> $operations
     * @param array<string,mixed> $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    private static function run(array $operations, array $options, bool $trackFileControls, string $dependency, bool $trackUriFileControls = false, bool $requireFreshWriteHandle = false, bool $releaseShmLocksOnClose = false, bool $allowTempSource = false): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS lock-byte URI SHM current-source next97 requires operations');
        }

        $state = self::normalizeCurrent($options['current'] ?? null);
        $defaultFilename = self::stringValue($options['filename'] ?? 'file:/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=shared', 'filename');
        $events = [];

        foreach ($operations as $operation) {
            $op = self::operation($operation);
            $before = self::snapshot($state);

            if ($op['kind'] === 'open') {
                $handle = self::openHandle($state, $op, $defaultFilename, $allowTempSource);
                $state['handles'][$handle['id']] = $handle;
                $state['source_handles'][$handle['source']] = $handle['id'];
                $state['current_source'] = $handle['source'];
                $state['owners'][$handle['owner']] = self::owner($state, $handle['owner']);
                $events[] = self::event('open', $handle['status'], $before, self::snapshot($state), [
                    'handle' => $handle['id'],
                    'source' => $handle['source'],
                    'path' => $handle['path'],
                    'owner' => $handle['owner'],
                    'uri' => $handle['uri'],
                    'sidecar_open_first' => $handle['source'] !== 'main' && !$handle['owner_had_main_open'],
                    'readonly' => $handle['readonly'],
                    'nolock' => $handle['nolock'],
                    'temporary' => $handle['temporary'],
                ]);
                continue;
            }

            if ($op['kind'] === 'source') {
                $source = self::sourceName((string) $op['source']);
                if ($source === 'temp' && !$allowTempSource) {
                    throw new \InvalidArgumentException('SQLite VFS current source must be main, wal, or shm');
                }
                if (!isset($state['source_handles'][$source])) {
                    $events[] = self::event('source', 'missing-handle', $before, self::snapshot($state), ['source' => $source]);
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
                $events[] = self::event('filecontrol', ...self::applyFileControl($state, $op, $before, $trackFileControls, $trackUriFileControls, $requireFreshWriteHandle));
                continue;
            }

            if ($op['kind'] === 'lock') {
                $events[] = self::event('lock', ...self::applyByteLock($state, $op, $before));
                continue;
            }

            if ($op['kind'] === 'shm') {
                $events[] = self::event('shm', ...self::applyShmLock($state, $op, $before));
                continue;
            }

            if ($op['kind'] === 'yield') {
                $connection = self::connection((string) $op['connection']);
                $owner = self::ownerFor($state, $op['source'] ?? null);
                self::releaseConnection($state, $owner, $connection);
                $state['owners'][$owner] = self::owner($state, $owner);
                $events[] = self::event('yield', 'released', $before, self::snapshot($state), [
                    'owner' => $owner,
                    'connection' => $connection,
                ]);
                continue;
            }

            if ($op['kind'] === 'close') {
                $source = self::sourceFor($state, $op['source'] ?? null);
                if ($source === 'temp' && !$allowTempSource) {
                    throw new \InvalidArgumentException('SQLite VFS current source must be main, wal, or shm');
                }
                $handleId = $state['source_handles'][$source] ?? null;
                if (!is_string($handleId) || !isset($state['handles'][$handleId])) {
                    $events[] = self::event('close', 'missing-handle', $before, self::snapshot($state), ['source' => $source]);
                    continue;
                }
                $handle = $state['handles'][$handleId];
                $releasedShmLocks = $releaseShmLocksOnClose ? self::releaseSourceShmLocks($state, (string) $handle['owner'], $source) : [];
                unset($state['handles'][$handleId], $state['source_handles'][$source]);
                $releasedConnection = null;
                if ($source === 'shm' && isset($op['connection']) && $op['connection'] !== null) {
                    $releasedConnection = self::connection((string) $op['connection']);
                    foreach (array_keys(self::emptyShmLocks()) as $lock) {
                        unset($state['shm_locks'][$handle['owner']][$lock][$releasedConnection]);
                    }
                }
                $state['owners'][$handle['owner']] = self::owner($state, $handle['owner']);
                if ($state['current_source'] === $source) {
                    $state['current_source'] = self::firstOpenSource($state);
                }
                $events[] = self::event('close', 'closed', $before, self::snapshot($state), [
                    'source' => $source,
                    'handle' => $handleId,
                    'owner' => $handle['owner'],
                    'released_connection' => $releasedConnection,
                    'released_shm_locks' => $releaseShmLocksOnClose ? $releasedShmLocks : $releasedConnection !== null,
                ]);
                continue;
            }

            throw new \InvalidArgumentException('SQLite VFS lock-byte URI SHM current-source next97 operation is unsupported');
        }

        return [
            'status' => (string) $events[array_key_last($events)]['status'],
            'current' => $events[0]['current'],
            'next' => self::next($state),
            'events' => $events,
            'dependencies' => array_values(array_unique(array_merge([
                'sqlite-file-uri',
                'sqlite-lock-byte-range-current-next',
                'sqlite-wal-shm-locks',
                $dependency,
            ], $trackFileControls ? ['vfs-current-source-file-control-data-version'] : [], $trackUriFileControls ? ['vfs-current-source-uri-file-control'] : [], $requireFreshWriteHandle ? ['vfs-current-source-stale-write-refresh'] : [], $releaseShmLocksOnClose ? ['vfs-current-source-shm-close-release'] : []))),
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $op
     * @param array<string,mixed> $before
     * @return array{0:string,1:array<string,mixed>,2:array<string,mixed>,3:array<string,mixed>}
     */
    private static function applyFileControl(array &$state, array $op, array $before, bool $trackFileControls, bool $trackUriFileControls, bool $requireFreshWriteHandle): array
    {
        $source = self::sourceFor($state, $op['source'] ?? null);
        $handle = self::handle($state, $source);
        $owner = (string) $handle['owner'];
        $control = self::controlName((string) ($op['control'] ?? ''));
        $generation = self::ownerGeneration($state, $owner);
        $openedGeneration = (int) ($handle['source_generation'] ?? $generation);
        if ($trackFileControls && $trackUriFileControls && in_array($control, ['uri_parameter', 'uri_boolean', 'uri_int'], true)) {
            $parameter = self::uriFileControlParameter($op['value'] ?? null);
            $default = self::uriFileControlDefault($op['value'] ?? null, $control);
            $uriValues = self::uriParameterValues($handle, $parameter);
            $value = match ($control) {
                'uri_boolean' => self::sqliteUriBooleanValue($uriValues, $default),
                'uri_int' => self::sqliteUriIntValue($uriValues, $default),
                default => $uriValues[array_key_last($uriValues)] ?? $default,
            };

            return ['ok', $before, self::snapshot($state), [
                'source' => $source,
                'handle' => $handle['id'],
                'owner' => $owner,
                'file_control' => $control,
                'parameter' => $parameter,
                'value' => $value,
                'values' => $uriValues,
                'default' => $default,
                'previous' => null,
                'changed' => false,
                'reason' => $uriValues === [] ? 'missing_uri_parameter' : null,
                'routed_to' => 'current-source-uri',
                'source_generation' => $generation,
                'opened_generation' => $openedGeneration,
                'stale_current_source' => $openedGeneration !== $generation,
                'stale_handles' => self::staleHandles($state, $owner),
            ]];
        }

        $value = self::controlValue($control, $op['value'] ?? null);
        $controls = is_array($state['persistent_controls'][$owner] ?? null) ? $state['persistent_controls'][$owner] : [];
        $previous = $controls[$control] ?? null;

        if (!$trackFileControls) {
            return ['unsupported', $before, self::snapshot($state), [
                'source' => $source,
                'handle' => $handle['id'],
                'owner' => $owner,
                'file_control' => $control,
                'value' => $value,
                'reason' => 'filecontrol requires planShmLockByteFileControl',
            ]];
        }

        if ($control === 'data_version' && ($op['value'] ?? null) === null) {
            return ['ok', $before, self::snapshot($state), [
                'source' => $source,
                'handle' => $handle['id'],
                'owner' => $owner,
                'file_control' => $control,
                'value' => $generation,
                'previous' => $openedGeneration,
                'changed' => false,
                'routed_to' => 'database',
                'source_generation' => $generation,
                'opened_generation' => $openedGeneration,
                'stale_current_source' => $openedGeneration !== $generation,
                'stale_handles' => self::staleHandles($state, $owner),
            ]];
        }

        if ($control === 'data_version' && self::isRefreshValue($op['value'] ?? null)) {
            if (isset($state['handles'][(string) $handle['id']])) {
                $state['handles'][(string) $handle['id']]['source_generation'] = $generation;
            }
            $state['owners'][$owner] = self::owner($state, $owner);

            return ['ok', $before, self::snapshot($state), [
                'source' => $source,
                'handle' => $handle['id'],
                'owner' => $owner,
                'file_control' => $control,
                'value' => $generation,
                'previous' => $openedGeneration,
                'changed' => $openedGeneration !== $generation,
                'reason' => null,
                'routed_to' => 'database',
                'source_generation' => $generation,
                'opened_generation' => $generation,
                'refreshed_current_source' => true,
                'stale_current_source' => false,
                'stale_handles' => self::staleHandles($state, $owner),
            ]];
        }

        if (($handle['temporary'] ?? false) === true) {
            $localControls = is_array($handle['controls'] ?? null) ? $handle['controls'] : [];
            $previous = $localControls[$control] ?? null;
            $localGeneration = max(1, (int) ($handle['local_generation'] ?? $openedGeneration));
            if ($previous !== $value) {
                $localGeneration++;
            }
            $localControls[$control] = $value;
            $localControls['data_version'] = $localGeneration;
            if (isset($state['handles'][(string) $handle['id']])) {
                $state['handles'][(string) $handle['id']]['controls'] = self::persistentControls($localControls);
                $state['handles'][(string) $handle['id']]['local_generation'] = $localGeneration;
                $state['handles'][(string) $handle['id']]['source_generation'] = $localGeneration;
            }

            return ['ok', $before, self::snapshot($state), [
                'source' => $source,
                'handle' => $handle['id'],
                'owner' => $owner,
                'file_control' => $control,
                'value' => $value,
                'previous' => $previous,
                'changed' => $previous !== $value,
                'reason' => null,
                'routed_to' => 'temporary-handle',
                'source_generation' => $localGeneration,
                'opened_generation' => $openedGeneration,
                'stale_handles' => [],
            ]];
        }

        $requiresWrite = self::writeControl($control);
        $status = 'ok';
        $reason = null;
        if ((bool) ($handle['readonly'] ?? false) && $requiresWrite) {
            $status = 'ignored';
            $reason = 'readonly_handle';
        } elseif ($requiresWrite && !self::ownerHasWriteByteLock($state, $owner)) {
            $status = 'blocked';
            $reason = 'requires_reserved_pending_or_exclusive_byte_lock';
        } elseif ($requireFreshWriteHandle && $requiresWrite && $openedGeneration !== $generation) {
            $status = 'blocked';
            $reason = 'stale_current_source_requires_data_version_refresh';
        }

        if ($status === 'ok') {
            $controls[$control] = $value;
            if ($requiresWrite && $previous !== $value) {
                $generation++;
                $state['persistent_generations'][$owner] = $generation;
                $controls['data_version'] = $generation;
                if (isset($state['handles'][(string) $handle['id']])) {
                    $state['handles'][(string) $handle['id']]['source_generation'] = $generation;
                }
            }
            $state['persistent_controls'][$owner] = self::persistentControls($controls);
        }
        $state['owners'][$owner] = self::owner($state, $owner);

        return [$status, $before, self::snapshot($state), [
            'source' => $source,
            'handle' => $handle['id'],
            'owner' => $owner,
            'file_control' => $control,
            'value' => $value,
            'previous' => $previous,
            'changed' => $status === 'ok' && $previous !== $value,
            'reason' => $reason,
            'routed_to' => 'database',
            'source_generation' => self::ownerGeneration($state, $owner),
            'opened_generation' => $openedGeneration,
            'stale_current_source' => $openedGeneration !== self::ownerGeneration($state, $owner),
            'stale_handles' => self::staleHandles($state, $owner),
        ]];
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $op
     * @param array<string,mixed> $before
     * @return array{0:string,1:array<string,mixed>,2:array<string,mixed>,3:array<string,mixed>}
     */
    private static function applyByteLock(array &$state, array $op, array $before): array
    {
        $source = self::sourceFor($state, $op['source'] ?? null);
        $handle = self::handle($state, $source);
        $owner = (string) $handle['owner'];
        $connection = self::connection((string) $op['connection']);
        $level = self::level((string) $op['level']);
        $currentLevel = (string) ($state['lock_holders'][$owner][$connection] ?? 'none');
        $currentSlot = (int) ($state['shared_slots'][$owner][$connection] ?? 0);
        $slot = isset($op['shared_slot']) ? self::slot($op['shared_slot']) : $currentSlot;
        $plan = SQLiteLockByteRangePlan::transition((string) $handle['path'], $currentLevel, $level, (bool) $handle['nolock'], $level === 'none' ? null : $connection, $currentSlot, $slot);
        $blocking = self::byteBlockers($state['lock_holders'][$owner] ?? [], $connection, $level);
        $status = (string) $plan['status'];
        $reason = $plan['reason'] ?? null;
        if ($status === 'planned' && $blocking !== []) {
            $status = 'blocked';
            $reason = 'owner_byte_lock_conflict';
        }

        if ($status === 'planned') {
            if ($level === 'none') {
                unset($state['lock_holders'][$owner][$connection], $state['shared_slots'][$owner][$connection]);
            } else {
                $state['lock_holders'][$owner][$connection] = $level;
                if (in_array($level, ['shared', 'reserved'], true)) {
                    $state['shared_slots'][$owner][$connection] = $slot;
                }
            }
            ksort($state['lock_holders'][$owner]);
            ksort($state['shared_slots'][$owner]);
        }

        $state['owners'][$owner] = self::owner($state, $owner);

        return [$status, $before, self::snapshot($state), [
            'source' => $source,
            'handle' => $handle['id'],
            'owner' => $owner,
            'path' => $handle['path'],
            'connection' => $connection,
            'level' => $level,
            'plan' => $plan,
            'blocking' => $blocking,
            'reason' => $reason,
        ]];
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $op
     * @param array<string,mixed> $before
     * @return array{0:string,1:array<string,mixed>,2:array<string,mixed>,3:array<string,mixed>}
     */
    private static function applyShmLock(array &$state, array $op, array $before): array
    {
        $source = self::sourceFor($state, $op['source'] ?? null);
        $handle = self::handle($state, $source);
        $owner = (string) $handle['owner'];
        $connection = self::connection((string) $op['connection']);
        $lock = self::shmLock((string) $op['lock']);
        $mode = self::shmMode((string) ($op['mode'] ?? 'shared'));

        if ($mode === 'unlock') {
            unset($state['shm_locks'][$owner][$lock][$connection]);
            unset($state['shm_lock_sources'][$owner][$lock][$connection]);
            $state['owners'][$owner] = self::owner($state, $owner);

            return ['released', $before, self::snapshot($state), [
                'source' => $source,
                'handle' => $handle['id'],
                'owner' => $owner,
                'connection' => $connection,
                'lock' => $lock,
                'mode' => $mode,
                'blocking' => [],
                'reason' => null,
            ]];
        }

        $blocking = self::shmBlockers($state['shm_locks'][$owner][$lock] ?? [], $connection, $mode);
        $status = $blocking === [] ? 'acquired' : 'blocked';
        if ($status === 'acquired') {
            $state['shm_locks'][$owner][$lock][$connection] = $mode;
            $state['shm_lock_sources'][$owner][$lock][$connection] = $source;
            ksort($state['shm_locks'][$owner][$lock]);
            ksort($state['shm_lock_sources'][$owner][$lock]);
        }
        $state['owners'][$owner] = self::owner($state, $owner);

        return [$status, $before, self::snapshot($state), [
            'source' => $source,
            'handle' => $handle['id'],
            'owner' => $owner,
            'connection' => $connection,
            'lock' => $lock,
            'mode' => $mode,
            'blocking' => $blocking,
            'reason' => $blocking === [] ? null : 'owner_shm_lock_conflict',
        ]];
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
                'owners' => [],
                'lock_holders' => [],
                'shared_slots' => [],
                'shm_locks' => [],
                'shm_lock_sources' => [],
                'persistent_controls' => [],
                'persistent_generations' => [],
            ];
        }

        return [
            'sequence' => max(0, (int) ($current['sequence'] ?? 0)),
            'current_source' => isset($current['current_source']) ? self::sourceName((string) $current['current_source']) : null,
            'handles' => is_array($current['handles'] ?? null) ? $current['handles'] : [],
            'source_handles' => is_array($current['source_handles'] ?? null) ? $current['source_handles'] : [],
            'owners' => is_array($current['owners'] ?? null) ? $current['owners'] : [],
            'lock_holders' => is_array($current['lock_holders'] ?? null) ? $current['lock_holders'] : [],
            'shared_slots' => is_array($current['shared_slots'] ?? null) ? $current['shared_slots'] : [],
            'shm_locks' => is_array($current['shm_locks'] ?? null) ? $current['shm_locks'] : [],
            'shm_lock_sources' => is_array($current['shm_lock_sources'] ?? null) ? $current['shm_lock_sources'] : [],
            'persistent_controls' => is_array($current['persistent_controls'] ?? null) ? $current['persistent_controls'] : [],
            'persistent_generations' => is_array($current['persistent_generations'] ?? null) ? $current['persistent_generations'] : [],
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $op
     * @return array<string,mixed>
     */
    private static function openHandle(array &$state, array $op, string $defaultFilename, bool $allowTempSource): array
    {
        $state['sequence']++;
        $filename = self::stringValue($op['filename'] ?? $defaultFilename, 'filename');
        $uri = SQLiteFileUri::parse($filename);
        $path = self::openPath($filename, $uri);
        $memory = $path === ':memory:' || $uri['mode'] === 'memory';
        $source = isset($op['source']) && $op['source'] !== null ? self::sourceName((string) $op['source']) : self::sourceFromPath($path);
        if ($source === 'temp' && !$allowTempSource) {
            throw new \InvalidArgumentException('SQLite VFS current source must be main, wal, or shm');
        }
        $temporary = $source === 'temp' || (bool) ($op['temporary'] ?? false);
        $owner = $memory
            ? 'memory:vfs97-' . $state['sequence']
            : ($temporary ? 'temp:vfs97-' . $state['sequence'] . ':' . self::ownerPath($path) : self::ownerPath($path));
        $sourcePath = $memory ? $owner : self::sourcePath($owner, $source);
        $ownerHadMainOpen = isset($state['source_handles']['main'])
            && isset($state['handles'][(string) $state['source_handles']['main']])
            && ($state['handles'][(string) $state['source_handles']['main']]['owner'] ?? null) === $owner;
        $readonly = (bool) ($op['readonly'] ?? ($uri['mode'] === 'ro' || $uri['immutable'] === true));
        $nolock = (bool) ($uri['nolock'] ?? false);
        self::ensureOwner($state, $owner);
        $controls = is_array($state['persistent_controls'][$owner] ?? null) ? $state['persistent_controls'][$owner] : [];
        $generation = self::ownerGeneration($state, $owner);

        return [
            'id' => 'vfs97-' . $state['sequence'],
            'status' => $source . '-open',
            'source' => $source,
            'path' => $sourcePath,
            'owner' => $owner,
            'readonly' => $readonly,
            'nolock' => $nolock,
            'temporary' => $temporary,
            'local_generation' => $generation,
            'controls' => $controls,
            'source_generation' => $generation,
            'owner_had_main_open' => $ownerHadMainOpen,
            'uri' => [
                'is_uri' => $uri['is_uri'],
                'path' => $path,
                'mode' => $uri['mode'],
                'cache' => $uri['cache'],
                'immutable' => $uri['immutable'],
                'nolock' => $uri['nolock'],
                'vfs' => $uri['vfs'],
                'authority' => $uri['authority'],
                'known_parameters' => $uri['known_parameters'] ?? [],
                'unknown_parameters' => $uri['unknown_parameters'] ?? [],
                'all_query_parameters' => $uri['all_query_parameters'] ?? [],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function ensureOwner(array &$state, string $owner): void
    {
        $state['lock_holders'][$owner] ??= [];
        $state['shared_slots'][$owner] ??= [];
        $state['shm_locks'][$owner] ??= self::emptyShmLocks();
        $state['shm_lock_sources'][$owner] ??= self::emptyShmLocks();
        $state['owners'][$owner] = self::owner($state, $owner);
    }

    /**
     * @return array<string,array<string,string>>
     */
    private static function emptyShmLocks(): array
    {
        return ['read0' => [], 'read1' => [], 'read2' => [], 'read3' => [], 'read4' => [], 'write' => [], 'checkpoint' => [], 'recover' => []];
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function owner(array $state, string $owner): array
    {
        $open = [];
        foreach (['main', 'wal', 'shm', 'temp'] as $source) {
            $handleId = $state['source_handles'][$source] ?? null;
            $open[$source] = is_string($handleId) && isset($state['handles'][$handleId]) && ($state['handles'][$handleId]['owner'] ?? null) === $owner;
        }

        return [
            'owner' => $owner,
            'open' => $open,
            'holders' => self::sortedMap($state['lock_holders'][$owner] ?? []),
            'shared_slots' => self::sortedMap($state['shared_slots'][$owner] ?? []),
            'shm_locks' => self::sortedShm($state['shm_locks'][$owner] ?? self::emptyShmLocks()),
            'lock_count' => count($state['lock_holders'][$owner] ?? []),
            'shm_lock_count' => array_sum(array_map('count', $state['shm_locks'][$owner] ?? self::emptyShmLocks())),
            'controls' => self::sortedMap(is_array($state['persistent_controls'][$owner] ?? null) ? $state['persistent_controls'][$owner] : []),
            'generation' => self::ownerGeneration($state, $owner),
        ];
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function releaseConnection(array &$state, string $owner, string $connection): void
    {
        unset($state['lock_holders'][$owner][$connection], $state['shared_slots'][$owner][$connection]);
        foreach (array_keys(self::emptyShmLocks()) as $lock) {
            unset($state['shm_locks'][$owner][$lock][$connection]);
            unset($state['shm_lock_sources'][$owner][$lock][$connection]);
        }
    }

    /**
     * @param array<string,mixed> $state
     * @return list<string>
     */
    private static function releaseSourceShmLocks(array &$state, string $owner, string $source): array
    {
        if ($source !== 'shm') {
            return [];
        }

        $released = [];
        foreach (array_keys(self::emptyShmLocks()) as $lock) {
            $sources = is_array($state['shm_lock_sources'][$owner][$lock] ?? null) ? $state['shm_lock_sources'][$owner][$lock] : [];
            foreach ($sources as $connection => $lockSource) {
                if ($lockSource !== $source) {
                    continue;
                }
                unset($state['shm_locks'][$owner][$lock][$connection], $state['shm_lock_sources'][$owner][$lock][$connection]);
                $released[] = $lock . ':' . $connection;
            }
        }
        sort($released);

        return $released;
    }

    /**
     * @param array<string,string> $holders
     * @return list<string>
     */
    private static function byteBlockers(array $holders, string $connection, string $level): array
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
     * @param string|array<string,mixed> $operation
     * @return array<string,mixed>
     */
    private static function operation(string|array $operation): array
    {
        if (is_array($operation)) {
            $kind = strtolower(str_replace(['_', '-'], '', (string) ($operation['op'] ?? $operation['kind'] ?? '')));

            return $operation + ['kind' => match ($kind) {
                'xopen' => 'open',
                'mainlock', 'byte', 'bytelock', 'lock' => 'lock',
                'shmlock', 'shm' => 'shm',
                'release' => 'yield',
                default => $kind,
            }];
        }

        $trimmed = trim($operation);
        if (preg_match('/^open\s*(?:\((?<arg>[^)]*)\))?$/i', $trimmed, $matches) === 1) {
            $arg = trim($matches['arg'] ?? '');
            if (in_array(strtolower($arg), ['main', 'wal', 'shm', 'temp'], true)) {
                return ['kind' => 'open', 'source' => strtolower($arg), 'filename' => null];
            }

            return ['kind' => 'open', 'source' => null, 'filename' => $arg !== '' ? $arg : null];
        }
        if (preg_match('/^source\s*\(\s*(?<source>main|wal|shm|temp)\s*\)$/i', $trimmed, $matches) === 1) {
            return ['kind' => 'source', 'source' => strtolower($matches['source'])];
        }
        if (preg_match('/^file_control\s*\(\s*(?<control>[A-Za-z_][A-Za-z0-9_-]*)\s*(?:,\s*(?<value>.*?))?\)(?:\s+on\s+(?<source>main|wal|shm))?$/i', $trimmed, $matches) === 1) {
            return [
                'kind' => 'filecontrol',
                'source' => isset($matches['source']) && $matches['source'] !== '' ? strtolower($matches['source']) : null,
                'control' => $matches['control'],
                'value' => self::parseValue($matches['value'] ?? null),
            ];
        }
        if (preg_match('/^close\s*(?:\((?<source>main|wal|shm|temp)\))?$/i', $trimmed, $matches) === 1) {
            return ['kind' => 'close', 'source' => strtolower($matches['source'] ?? '') ?: null];
        }
        if (preg_match('/^lock\s+(?<level>none|shared|reserved|pending|exclusive)\s+(?<connection>[A-Za-z0-9_.:-]+)(?:\s+(?<slot>\d+))?(?:\s+on\s+(?<source>main|wal|shm|temp))?$/i', $trimmed, $matches) === 1) {
            return [
                'kind' => 'lock',
                'level' => strtolower($matches['level']),
                'connection' => $matches['connection'],
                'shared_slot' => isset($matches['slot']) && $matches['slot'] !== '' ? (int) $matches['slot'] : null,
                'source' => isset($matches['source']) && $matches['source'] !== '' ? strtolower($matches['source']) : null,
            ];
        }
        if (preg_match('/^shm\s+(?<lock>read[0-4]|write|checkpoint|recover)\s+(?<mode>shared|exclusive|unlock)\s+(?<connection>[A-Za-z0-9_.:-]+)(?:\s+on\s+(?<source>main|wal|shm))?$/i', $trimmed, $matches) === 1) {
            return [
                'kind' => 'shm',
                'lock' => strtolower($matches['lock']),
                'mode' => strtolower($matches['mode']),
                'connection' => $matches['connection'],
                'source' => isset($matches['source']) && $matches['source'] !== '' ? strtolower($matches['source']) : null,
            ];
        }
        if (preg_match('/^yield\s+(?<connection>[A-Za-z0-9_.:-]+)(?:\s+on\s+(?<source>main|wal|shm|temp))?$/i', $trimmed, $matches) === 1) {
            return [
                'kind' => 'yield',
                'connection' => $matches['connection'],
                'source' => isset($matches['source']) && $matches['source'] !== '' ? strtolower($matches['source']) : null,
            ];
        }

        throw new \InvalidArgumentException('SQLite VFS lock-byte URI SHM current-source next97 operation string is unsupported');
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
            'temp' => $owner,
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

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function handle(array $state, string $source): array
    {
        $id = $state['source_handles'][$source] ?? null;
        if (!is_string($id) || !isset($state['handles'][$id])) {
            throw new \InvalidArgumentException("SQLite VFS current-source {$source} handle is not open");
        }

        return $state['handles'][$id];
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function ownerFor(array $state, mixed $source): string
    {
        $handle = self::handle($state, self::sourceFor($state, $source));

        return (string) $handle['owner'];
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function firstOpenSource(array $state): ?string
    {
        foreach (['main', 'wal', 'shm', 'temp'] as $source) {
            if (isset($state['source_handles'][$source])) {
                return $source;
            }
        }

        return null;
    }

    private static function sourceName(string $source): string
    {
        $source = strtolower(trim($source));
        if (!in_array($source, ['main', 'wal', 'shm', 'temp'], true)) {
            throw new \InvalidArgumentException('SQLite VFS current source must be main, wal, shm, or temp');
        }

        return $source;
    }

    private static function connection(string $connection): string
    {
        $connection = trim($connection);
        if ($connection === '') {
            throw new \InvalidArgumentException('SQLite VFS lock-byte URI SHM current-source next97 connection is required');
        }

        return $connection;
    }

    private static function level(string $level): string
    {
        $level = strtolower(trim($level));
        if (!in_array($level, ['none', 'shared', 'reserved', 'pending', 'exclusive'], true)) {
            throw new \InvalidArgumentException('SQLite VFS lock-byte URI SHM current-source next97 byte lock level is unsupported');
        }

        return $level;
    }

    private static function shmLock(string $lock): string
    {
        $lock = strtolower(trim($lock));
        if (!in_array($lock, array_keys(self::emptyShmLocks()), true)) {
            throw new \InvalidArgumentException('SQLite VFS lock-byte URI SHM current-source next97 SHM lock is unsupported');
        }

        return $lock;
    }

    private static function shmMode(string $mode): string
    {
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['shared', 'exclusive', 'unlock'], true)) {
            throw new \InvalidArgumentException('SQLite VFS lock-byte URI SHM current-source next97 SHM mode is unsupported');
        }

        return $mode;
    }

    private static function slot(mixed $slot): int
    {
        if (!is_int($slot) || $slot < 0 || $slot >= SQLiteLockByteRangePlan::SHARED_SIZE) {
            throw new \InvalidArgumentException('SQLite VFS lock-byte URI SHM current-source next97 shared slot is out of range');
        }

        return $slot;
    }

    private static function stringValue(mixed $value, string $label): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException("SQLite VFS lock-byte URI SHM current-source next97 {$label} is required");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function snapshot(array $state): array
    {
        ksort($state['handles']);
        ksort($state['source_handles']);
        ksort($state['owners']);
        ksort($state['lock_holders']);
        ksort($state['shared_slots']);
        ksort($state['shm_locks']);
        ksort($state['shm_lock_sources']);
        ksort($state['persistent_controls']);
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
        foreach ($state['handles'] as $handle) {
            $source = (string) ($handle['source'] ?? 'main');
            $openBySource[$source] = ($openBySource[$source] ?? 0) + 1;
        }

        return [
            'current_source' => $state['current_source'],
            'open_by_source' => $openBySource,
            'owner_count' => count($state['owners']),
            'owners' => $state['owners'],
            'handles' => $state['handles'],
            'shm_lock_sources' => $state['shm_lock_sources'],
            'lock_holder_count' => array_sum(array_map('count', $state['lock_holders'])),
            'shm_lock_count' => array_sum(array_map(static fn (array $locks): int => array_sum(array_map('count', $locks)), $state['shm_locks'])),
            'persistent_control_count' => count($state['persistent_controls']),
            'persistent_generation_count' => count($state['persistent_generations']),
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

    private static function ownerGeneration(array $state, string $owner): int
    {
        $generation = $state['persistent_generations'][$owner] ?? null;
        if (is_int($generation) && $generation > 0) {
            return $generation;
        }

        return 1;
    }

    /**
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

    private static function ownerHasWriteByteLock(array $state, string $owner): bool
    {
        foreach (($state['lock_holders'][$owner] ?? []) as $level) {
            if (in_array($level, ['reserved', 'pending', 'exclusive'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $controls
     * @return array<string,mixed>
     */
    private static function persistentControls(array $controls): array
    {
        $subset = [];
        foreach (['persist_wal', 'chunk_size', 'mmap_size', 'powersafe_overwrite', 'reserve_bytes', 'lock_timeout', 'data_version'] as $key) {
            if (array_key_exists($key, $controls)) {
                $subset[$key] = $controls[$key];
            }
        }
        ksort($subset);

        return $subset;
    }

    private static function controlName(string $control): string
    {
        $control = strtolower(str_replace('-', '_', trim($control)));
        if ($control === '') {
            throw new \InvalidArgumentException('SQLite VFS current-source file-control name is required');
        }

        return $control;
    }

    private static function controlValue(string $control, mixed $value): mixed
    {
        return match ($control) {
            'persist_wal', 'powersafe_overwrite' => self::boolean($value),
            'chunk_size', 'mmap_size', 'size_hint', 'reserve_bytes', 'lock_timeout' => self::nonNegativeInt($value, $control),
            default => $value,
        };
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

    private static function writeControl(string $control): bool
    {
        return in_array($control, ['chunk_size', 'size_hint', 'reserve_bytes', 'powersafe_overwrite', 'persist_wal'], true);
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

    private static function isRefreshValue(mixed $value): bool
    {
        return is_string($value) && in_array(strtolower(trim($value)), ['refresh', 'current', 'reopen'], true);
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

    /**
     * @param array<string,mixed> $values
     * @return array<string,mixed>
     */
    private static function sortedMap(array $values): array
    {
        ksort($values);

        return $values;
    }

    /**
     * @param array<string,array<string,string>> $locks
     * @return array<string,array<string,string>>
     */
    private static function sortedShm(array $locks): array
    {
        $normalized = self::emptyShmLocks();
        foreach ($locks as $name => $holders) {
            if (isset($normalized[$name]) && is_array($holders)) {
                ksort($holders);
                $normalized[$name] = $holders;
            }
        }

        return $normalized;
    }

/**
     * @param array<string,mixed> $current
     * @param list<array<string,mixed>|string> $operations
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    private static function legacyUriShmLockPlan(array $current, array $operations, string $filename): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS lock-byte URI SHM current-source next93 requires operations');
        }

        $open = self::legacyUriShmLockOpen($filename);
        $state = self::legacyUriShmLockNormalizeCurrent($current);
        $sourceKey = $open['source_key'];
        $state['selected_source'] = $sourceKey;
        $state['sources'][$sourceKey] ??= self::legacyUriShmLockSource($open, []);

        $events = [];
        foreach ($operations as $operation) {
            $op = self::legacyUriShmLockOperation($operation);
            $before = self::legacyUriShmLockSummary($state);
            $source = &$state['sources'][$sourceKey];

            if ($op['kind'] === 'main') {
                $event = self::legacyUriShmLockMainLock($source, $open, $op);
                unset($source);
                $events[] = self::legacyUriShmLockEvent('main', $event['status'], $before, self::legacyUriShmLockSummary($state), $event);
                continue;
            }

            if ($op['kind'] === 'shm') {
                $event = self::legacyUriShmLockShmLock($source, $open, $op);
                unset($source);
                $events[] = self::legacyUriShmLockEvent('shm', $event['status'], $before, self::legacyUriShmLockSummary($state), $event);
                continue;
            }

            if ($op['kind'] === 'release') {
                $connection = self::legacyUriShmLockConnection((string) $op['connection']);
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
                $events[] = self::legacyUriShmLockEvent('release', 'released', $before, self::legacyUriShmLockSummary($state), $event);
                continue;
            }

            unset($source);
            throw new \InvalidArgumentException('SQLite VFS lock-byte URI SHM current-source next93 operation is unsupported');
        }

        return [
            'status' => (string) $events[array_key_last($events)]['status'],
            'current' => self::legacyUriShmLockSummary(self::legacyUriShmLockNormalizeCurrent($current)),
            'next' => self::legacyUriShmLockSummary($state),
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
    private static function legacyUriShmLockOpen(string $filename): array
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
    private static function legacyUriShmLockMainLock(array &$source, array $open, array $op): array
    {
        $connection = self::legacyUriShmLockConnection((string) $op['connection']);
        $level = self::legacyUriShmLockLevel((string) $op['level']);
        $currentLevel = (string) ($source['main_holders'][$connection] ?? 'none');
        $currentSlot = (int) ($source['shared_slots'][$connection] ?? 0);
        $nextSlot = isset($op['shared_slot']) ? self::legacyUriShmLockSlot($op['shared_slot']) : $currentSlot;

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
        $blocking = $blockedReason === null ? self::legacyUriShmLockMainBlockers($source['main_holders'], $connection, $level) : [];
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
    private static function legacyUriShmLockShmLock(array &$source, array $open, array $op): array
    {
        $connection = self::legacyUriShmLockConnection((string) $op['connection']);
        $lock = self::legacyUriShmLockShmName((string) $op['lock']);
        $mode = self::legacyUriShmLockShmMode((string) ($op['mode'] ?? 'shared'));

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

        $blocking = $blockedReason === null ? self::legacyUriShmLockShmBlockers($source['shm_locks'][$lock], $connection, $mode) : [];
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
    private static function legacyUriShmLockMainBlockers(array $holders, string $connection, string $level): array
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
    private static function legacyUriShmLockShmBlockers(array $holders, string $connection, string $mode): array
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
    private static function legacyUriShmLockNormalizeCurrent(array $current): array
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
            $state['sources'][$sourceKey] = self::legacyUriShmLockSource([
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
    private static function legacyUriShmLockSource(array $open, array $source): array
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
            'main_holders' => self::legacyUriShmLockStringMap($source['main_holders'] ?? []),
            'shared_slots' => self::legacyUriShmLockIntMap($source['shared_slots'] ?? []),
            'shm_locks' => self::legacyUriShmLockShmLocks($source['shm_locks'] ?? []),
            'constants' => SQLiteLockByteRangePlan::constants(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function legacyUriShmLockSummary(array $state): array
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
    private static function legacyUriShmLockEvent(string $kind, string $status, array $current, array $next, array $detail): array
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
    private static function legacyUriShmLockOperation(array|string $operation): array
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
    private static function legacyUriShmLockStringMap(mixed $values): array
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
    private static function legacyUriShmLockIntMap(mixed $values): array
    {
        $out = [];
        if (is_array($values)) {
            foreach ($values as $key => $value) {
                if (is_string($key) && is_int($value)) {
                    $out[$key] = self::legacyUriShmLockSlot($value);
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
    private static function legacyUriShmLockShmLocks(mixed $locks): array
    {
        $normalized = ['read0' => [], 'read1' => [], 'read2' => [], 'read3' => [], 'read4' => [], 'write' => [], 'checkpoint' => [], 'recover' => []];
        if (!is_array($locks)) {
            return $normalized;
        }
        foreach ($locks as $name => $holders) {
            $normalized[self::legacyUriShmLockShmName((string) $name)] = self::legacyUriShmLockStringMap($holders);
        }

        return $normalized;
    }

    private static function legacyUriShmLockConnection(string $connection): string
    {
        $connection = trim($connection);
        if ($connection === '') {
            throw new \InvalidArgumentException('SQLite VFS lock-byte URI SHM current-source next93 connection is required');
        }

        return $connection;
    }

    private static function legacyUriShmLockLevel(string $level): string
    {
        $level = strtolower(trim($level));
        if (!in_array($level, ['none', 'shared', 'reserved', 'pending', 'exclusive'], true)) {
            throw new \InvalidArgumentException('SQLite VFS lock-byte URI SHM current-source next93 lock level is unsupported');
        }

        return $level;
    }

    private static function legacyUriShmLockShmName(string $lock): string
    {
        $lock = strtolower(trim($lock));
        if (!in_array($lock, ['read0', 'read1', 'read2', 'read3', 'read4', 'write', 'checkpoint', 'recover'], true)) {
            throw new \InvalidArgumentException('SQLite VFS lock-byte URI SHM current-source next93 SHM lock is unsupported');
        }

        return $lock;
    }

    private static function legacyUriShmLockShmMode(string $mode): string
    {
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['shared', 'exclusive', 'unlock'], true)) {
            throw new \InvalidArgumentException('SQLite VFS lock-byte URI SHM current-source next93 SHM mode is unsupported');
        }

        return $mode;
    }

    private static function legacyUriShmLockSlot(mixed $slot): int
    {
        if (!is_int($slot) || $slot < 0 || $slot >= SQLiteLockByteRangePlan::SHARED_SIZE) {
            throw new \InvalidArgumentException('SQLite VFS lock-byte URI SHM current-source next93 shared slot is out of range');
        }

        return $slot;
    }
}
