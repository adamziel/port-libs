<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsLockUriTempFileControlCurrentSourceNextPlan
{
    /**
     * @param list<string|array<string,mixed>> $operations
     * @param array<string,mixed> $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function run(array $operations, array $options = []): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS lock URI temp file-control current-source next137 requires operations');
        }

        $state = [
            'sequence' => 0,
            'generation' => 0,
            'current_source' => null,
            'temp_directory' => self::directory($options['temp_directory'] ?? sys_get_temp_dir(), 'temp_directory'),
            'handles' => [],
            'source_handles' => [],
            'owner_locks' => [],
            'deleted_temp_owners' => [],
        ];
        $events = [];

        foreach ($operations as $operation) {
            $op = self::operation($operation);
            $before = self::snapshot($state);

            if ($op['kind'] === 'open') {
                $handle = self::openHandle($state, $op);
                $state['handles'][$handle['id']] = $handle;
                $state['source_handles'][$handle['source']] = $handle['id'];
                $state['current_source'] = $handle['source'];
                $events[] = self::event('open', $handle['status'], $handle['source'], $before, self::snapshot($state), [
                    'handle' => $handle['id'],
                    'owner' => $handle['owner'],
                    'path' => $handle['path'],
                    'temporary' => $handle['temporary'],
                    'delete_on_close' => $handle['delete_on_close'],
                    'temp_directory' => $handle['temp_directory'],
                    'uri_parameters' => $handle['uri_parameters'],
                    'readonly' => $handle['readonly'],
                    'nolock' => $handle['nolock'],
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
                $source = self::sourceFor($state, $op['source'] ?? null);
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
                $source = self::sourceFor($state, $op['source'] ?? null);
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
                $source = self::sourceFor($state, $op['source'] ?? null);
                $handleId = $state['source_handles'][$source] ?? null;
                if (!is_string($handleId) || !isset($state['handles'][$handleId])) {
                    $events[] = self::event('close', 'missing-handle', $source, $before, self::snapshot($state), []);
                    continue;
                }
                $handle = $state['handles'][$handleId];
                unset($state['handles'][$handleId], $state['source_handles'][$source], $state['owner_locks'][$handle['owner']]);
                $deleted = (bool) $handle['delete_on_close'];
                if ($deleted) {
                    $state['deleted_temp_owners'][] = $handle['owner'];
                    $state['generation']++;
                }
                if ($state['current_source'] === $source) {
                    $state['current_source'] = array_key_first($state['source_handles']);
                }
                $events[] = self::event('close', 'closed', $source, $before, self::snapshot($state), [
                    'handle' => $handleId,
                    'owner' => $handle['owner'],
                    'deleted_temp' => $deleted,
                    'released_locks' => true,
                ]);
                continue;
            }

            throw new \InvalidArgumentException('SQLite VFS lock URI temp file-control operation is unsupported');
        }

        return [
            'status' => (string) ($events[array_key_last($events)]['status'] ?? 'ok'),
            'current' => [
                'sequence' => 0,
                'generation' => 0,
                'current_source' => null,
                'temp_directory' => self::directory($options['temp_directory'] ?? sys_get_temp_dir(), 'temp_directory'),
                'handles' => [],
                'source_handles' => [],
                'owner_locks' => [],
                'deleted_temp_owners' => [],
            ],
            'next' => self::summary($state),
            'events' => $events,
            'dependencies' => [
                'sqlite-file-uri-parser',
                'vfs-lock-byte-state',
                'vfs-temp-directory-current-source',
                'vfs-uri-file-control-current-source',
                'vfs-lock-uri-temp-filecontrol-current-source-next137',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $op
     * @return array<string,mixed>
     */
    private static function openHandle(array &$state, array $op): array
    {
        $state['sequence']++;
        $source = self::sourceName((string) ($op['source'] ?? 'main'));
        $filename = trim((string) ($op['filename'] ?? ''));
        $uri = $filename !== '' && str_starts_with(strtolower($filename), 'file:') ? SQLiteFileUri::parse($filename) : null;
        $parameters = is_array($uri) ? self::uriParameters($uri) : [];
        $mode = is_array($uri) ? (string) ($uri['mode'] ?? '') : '';
        $temporary = (bool) ($op['temporary'] ?? false) || $source === 'temp' || $filename === '' || $mode === 'memory';
        $tempDirectory = self::directory($parameters['tempdir'] ?? $state['temp_directory'], 'tempdir');
        $path = is_array($uri) ? (string) $uri['path'] : $filename;
        if ($temporary && ($path === '' || $mode === 'memory')) {
            $path = rtrim($tempDirectory, '/') . '/sqlite-temp-' . $state['sequence'] . '.db';
        }
        $owner = $temporary ? 'temp:' . $source . ':' . $state['sequence'] : self::stripSidecarSuffix($path);

        return [
            'id' => 'vfs137-' . $state['sequence'],
            'status' => $temporary ? 'temp-open' : 'open',
            'source' => $source,
            'owner' => $owner,
            'path' => $path,
            'temporary' => $temporary,
            'delete_on_close' => (bool) ($op['delete_on_close'] ?? $temporary),
            'temp_directory' => $temporary ? $tempDirectory : null,
            'readonly' => (bool) ($op['readonly'] ?? ($mode === 'ro')),
            'nolock' => (bool) ($op['nolock'] ?? (is_array($uri) && ($uri['nolock'] ?? null) === true)),
            'uri_parameters' => $parameters,
            'opened_generation' => $state['generation'],
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function applyFileControl(array &$state, string $handleId, string $control, mixed $value): array
    {
        $handle = $state['handles'][$handleId];
        if ($control === 'temp_directory') {
            $previous = $state['temp_directory'];
            $directory = self::directory($value, 'temp_directory');
            $state['temp_directory'] = $directory;
            $state['generation']++;

            return [
                'file_control' => $control,
                'value' => $directory,
                'previous' => $previous,
                'changed' => $previous !== $directory,
                'routed_to' => 'connection-temp-directory',
                'affects_existing_handle' => false,
                'stale_current_source' => ((int) $handle['opened_generation']) !== $state['generation'],
                'status' => 'ok',
            ];
        }

        if (in_array($control, ['uri_parameter', 'uri_boolean', 'uri_int'], true)) {
            $spec = is_array($value) ? $value : ['parameter' => $value];
            $parameter = self::parameterName((string) ($spec['parameter'] ?? ''));
            $default = $spec['default'] ?? null;
            $raw = $handle['uri_parameters'][$parameter] ?? null;
            $result = match ($control) {
                'uri_boolean' => $raw === null ? self::boolean($default) : self::boolean($raw),
                'uri_int' => $raw === null ? self::nonNegativeInt($default ?? 0, $parameter) : self::nonNegativeInt($raw, $parameter),
                default => $raw ?? $default,
            };

            return [
                'file_control' => $control,
                'parameter' => $parameter,
                'value' => $result,
                'default' => $default,
                'changed' => false,
                'routed_to' => 'current-source-uri',
                'stale_current_source' => ((int) $handle['opened_generation']) !== $state['generation'],
                'reason' => $raw === null ? 'missing_uri_parameter' : null,
                'status' => 'ok',
            ];
        }

        return [
            'file_control' => $control,
            'value' => $value,
            'changed' => false,
            'routed_to' => 'unsupported',
            'reason' => 'file-control is outside next137 temp/URI scope',
            'status' => 'unsupported',
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function applyLock(array &$state, string $handleId, string $level, string $connection): array
    {
        $handle = $state['handles'][$handleId];
        if ($handle['nolock']) {
            return ['level' => $level, 'connection' => $connection, 'status' => 'blocked', 'reason' => 'URI nolock disables byte-range locking'];
        }
        if ($handle['readonly'] && in_array($level, ['reserved', 'pending', 'exclusive'], true)) {
            return ['level' => $level, 'connection' => $connection, 'status' => 'blocked', 'reason' => 'readonly URI handle cannot take writer locks'];
        }

        $locks = is_array($state['owner_locks'][$handle['owner']] ?? null) ? $state['owner_locks'][$handle['owner']] : [];
        $blocking = [];
        foreach ($locks as $held => $owner) {
            if ($owner === $connection) {
                continue;
            }
            if ($level === 'shared' && $held === 'exclusive') {
                $blocking[] = $owner . ':exclusive';
            } elseif ($level !== 'shared') {
                $blocking[] = $owner . ':' . $held;
            }
        }
        if ($blocking !== []) {
            return ['level' => $level, 'connection' => $connection, 'status' => 'busy', 'blocking' => $blocking, 'reason' => 'current-source owner lock is held'];
        }

        if ($level === 'unlock') {
            $locks = array_filter($locks, static fn (string $owner): bool => $owner !== $connection);
        } else {
            $locks[$level] = $connection;
        }
        $state['owner_locks'][$handle['owner']] = $locks;

        return ['level' => $level, 'connection' => $connection, 'status' => 'ok', 'blocking' => [], 'owner' => $handle['owner']];
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
            return ['kind' => 'filecontrol', 'control' => $matches['control'], 'value' => self::parseValue($matches['value'] ?? null)];
        }
        if (preg_match('/^lock\s*\(\s*(?<level>shared|reserved|pending|exclusive|unlock)\s*(?:,\s*(?<connection>[A-Za-z0-9_.:-]+))?\s*\)$/i', $trimmed, $matches) === 1) {
            return ['kind' => 'lock', 'level' => $matches['level'], 'connection' => $matches['connection'] ?? null];
        }

        throw new \InvalidArgumentException('SQLite VFS lock URI temp file-control operation is unsupported');
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

    private static function lockLevel(string $level): string
    {
        $level = strtolower(trim($level));
        if (!in_array($level, ['shared', 'reserved', 'pending', 'exclusive', 'unlock'], true)) {
            throw new \InvalidArgumentException('SQLite VFS lock level is unsupported');
        }

        return $level;
    }

    private static function connectionName(mixed $connection): string
    {
        $connection = $connection === null || $connection === '' ? 'default' : trim((string) $connection);
        if ($connection === '' || preg_match('/^[A-Za-z0-9_.:-]+$/', $connection) !== 1) {
            throw new \InvalidArgumentException('SQLite VFS lock connection name is unsupported');
        }

        return $connection;
    }

    private static function directory(mixed $value, string $label): string
    {
        if (!is_string($value) || trim($value) === '' || str_contains($value, "\0")) {
            throw new \InvalidArgumentException("SQLite VFS {$label} must be a non-empty path");
        }

        return rtrim($value, '/');
    }

    private static function parameterName(string $parameter): string
    {
        $parameter = strtolower(trim($parameter));
        if ($parameter === '' || preg_match('/^[a-z0-9_.-]+$/', $parameter) !== 1) {
            throw new \InvalidArgumentException('SQLite VFS URI file-control parameter is unsupported');
        }

        return $parameter;
    }

    /**
     * @param array<string,mixed> $uri
     * @return array<string,mixed>
     */
    private static function uriParameters(array $uri): array
    {
        $parameters = is_array($uri['unknown_parameters'] ?? null) ? $uri['unknown_parameters'] : [];
        foreach (['mode', 'cache', 'vfs', 'nolock', 'immutable', 'psow'] as $key) {
            if (array_key_exists($key, $uri)) {
                $parameters[$key] = $uri[$key];
            }
        }
        ksort($parameters);

        return $parameters;
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

        throw new \InvalidArgumentException("SQLite VFS {$label} expects a non-negative integer");
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
            'generation' => $state['generation'],
            'current_source' => $state['current_source'],
            'temp_directory' => $state['temp_directory'],
            'open_count' => count($state['handles']),
            'temp_open_count' => count(array_filter($state['handles'], static fn (array $handle): bool => (bool) $handle['temporary'])),
            'lock_owner_count' => count($state['owner_locks']),
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
        ksort($state['owner_locks']);
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
}
