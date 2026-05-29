<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsCurrentSourceNext150153Plan
{
    public static function run(array $operations, array $options = []): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS current-source next150-153 requires operations');
        }

        $state = self::hydrate($options['current'] ?? []);
        $current = self::summary($state);
        $events = [];

        foreach ($operations as $operation) {
            $op = self::operation($operation);
            $before = self::snapshot($state);

            if ($op['kind'] === 'source') {
                $source = self::sourceName((string) $op['source']);
                if (!isset($state['sources'][$source])) {
                    $events[] = self::event('source', 'missing-source', $source, $before, self::snapshot($state), []);
                    continue;
                }
                $state['current_source'] = $source;
                $events[] = self::event('source', 'ok', $source, $before, self::snapshot($state), [
                    'handle' => $state['sources'][$source]['handle'],
                    'generation' => $state['sources'][$source]['generation'],
                ]);
                continue;
            }

            if ($op['kind'] === 'open') {
                $source = self::sourceName((string) $op['source']);
                $path = self::pathName((string) ($op['path'] ?? ''));
                if (isset($state['sources'][$source])) {
                    $state['current_source'] = $source;
                    $events[] = self::event('open', 'reused-current-source', $source, $before, self::snapshot($state), [
                        'handle' => $state['sources'][$source]['handle'],
                        'owner' => $state['sources'][$source]['owner'],
                        'generation' => $state['sources'][$source]['generation'],
                    ]);
                    continue;
                }

                $state['sequence']++;
                $owner = self::owner($path);
                $state['owner_generations'][$owner] = (int) ($state['owner_generations'][$owner] ?? 0) + 1;
                $state['sources'][$source] = [
                    'handle' => 'vfs150153-' . $state['sequence'],
                    'owner' => $owner,
                    'path' => $path,
                    'readonly' => (bool) ($op['readonly'] ?? false),
                    'generation' => $state['owner_generations'][$owner],
                    'locks' => [],
                    'file_controls' => [],
                    'syncs' => [],
                    'closed' => false,
                ];
                $state['current_source'] = $source;
                $events[] = self::event('open', 'open', $source, $before, self::snapshot($state), $state['sources'][$source]);
                continue;
            }

            $source = self::sourceFor($state, $op['source'] ?? null);
            if (!isset($state['sources'][$source]) || $state['sources'][$source]['closed'] === true) {
                $events[] = self::event($op['kind'], 'missing-source', $source, $before, self::snapshot($state), []);
                continue;
            }

            if ($op['kind'] === 'filecontrol') {
                $control = self::controlName((string) $op['control']);
                $owner = $state['sources'][$source]['owner'];
                if ($control === 'data_version') {
                    $opened = (int) $state['sources'][$source]['generation'];
                    $currentGeneration = (int) ($state['owner_generations'][$owner] ?? $opened);
                    $events[] = self::event('file_control', 'ok', $source, $before, self::snapshot($state), [
                        'file_control' => $control,
                        'value' => $currentGeneration,
                        'opened_generation' => $opened,
                        'stale_current_source' => $opened !== $currentGeneration,
                    ]);
                    continue;
                }

                $state['sources'][$source]['file_controls'][$control] = $op['value'];
                if (in_array($control, ['persist_wal', 'checkpoint_fullfsync', 'powersafe_overwrite'], true)) {
                    $state['owner_generations'][$owner] = (int) ($state['owner_generations'][$owner] ?? 0) + 1;
                }
                $events[] = self::event('file_control', 'ok', $source, $before, self::snapshot($state), [
                    'file_control' => $control,
                    'value' => $op['value'],
                    'owner_generation' => $state['owner_generations'][$owner] ?? null,
                ]);
                continue;
            }

            if ($op['kind'] === 'lock') {
                $level = self::lockLevel((string) $op['level']);
                $connection = self::connectionName($op['connection'] ?? null);
                if ($state['sources'][$source]['readonly'] && in_array($level, ['reserved', 'pending', 'exclusive'], true)) {
                    $events[] = self::event('lock', 'blocked', $source, $before, self::snapshot($state), [
                        'level' => $level,
                        'connection' => $connection,
                        'reason' => 'readonly current-source cannot take writer lock',
                    ]);
                    continue;
                }
                $state['sources'][$source]['locks'][$level] = $connection;
                $events[] = self::event('lock', 'ok', $source, $before, self::snapshot($state), [
                    'level' => $level,
                    'connection' => $connection,
                    'owner' => $state['sources'][$source]['owner'],
                ]);
                continue;
            }

            if ($op['kind'] === 'sync') {
                $mode = self::syncMode((string) ($op['mode'] ?? 'normal'));
                $state['sources'][$source]['syncs'][] = $mode;
                $events[] = self::event('sync', 'ok', $source, $before, self::snapshot($state), [
                    'mode' => $mode,
                    'sync_count' => count($state['sources'][$source]['syncs']),
                ]);
                continue;
            }

            if ($op['kind'] === 'close') {
                $owner = $state['sources'][$source]['owner'];
                $state['sources'][$source]['locks'] = [];
                $state['sources'][$source]['closed'] = true;
                $state['owner_generations'][$owner] = (int) ($state['owner_generations'][$owner] ?? 0) + 1;
                if ($state['current_source'] === $source) {
                    $state['current_source'] = self::firstOpenSource($state);
                }
                $events[] = self::event('close', 'closed', $source, $before, self::snapshot($state), [
                    'owner' => $owner,
                    'owner_generation' => $state['owner_generations'][$owner],
                    'released_locks' => true,
                ]);
                continue;
            }

            throw new \InvalidArgumentException('SQLite VFS current-source next150-153 operation is unsupported');
        }

        return [
            'status' => (string) ($events[array_key_last($events)]['status'] ?? 'ok'),
            'current' => $current,
            'next' => self::summary($state),
            'events' => $events,
            'dependencies' => [
                'vfs-current-source-next146-149',
                'vfs-current-source-generation',
                'vfs-current-source-close-reopen-next150-153',
            ],
        ];
    }

    private static function hydrate(mixed $current): array
    {
        $state = ['sequence' => 0, 'current_source' => null, 'owner_generations' => [], 'sources' => []];
        if (!is_array($current)) {
            return $state;
        }

        foreach (is_array($current['owner_generations'] ?? null) ? $current['owner_generations'] : [] as $owner => $generation) {
            $state['owner_generations'][self::pathName((string) $owner)] = self::generation($generation);
        }

        foreach (is_array($current['sources'] ?? null) ? $current['sources'] : [] as $name => $source) {
            if (!is_array($source)) {
                continue;
            }
            $sourceName = self::sourceName((string) $name);
            $path = self::pathName((string) ($source['path'] ?? ''));
            $owner = self::owner($path);
            $generation = self::generation($source['generation'] ?? ($state['owner_generations'][$owner] ?? 1));
            $state['owner_generations'][$owner] = max((int) ($state['owner_generations'][$owner] ?? 0), $generation);
            $state['sources'][$sourceName] = [
                'handle' => self::handleName((string) ($source['handle'] ?? '')),
                'owner' => $owner,
                'path' => $path,
                'readonly' => (bool) ($source['readonly'] ?? false),
                'generation' => $generation,
                'locks' => is_array($source['locks'] ?? null) ? $source['locks'] : [],
                'file_controls' => is_array($source['file_controls'] ?? null) ? $source['file_controls'] : [],
                'syncs' => is_array($source['syncs'] ?? null) ? array_values($source['syncs']) : [],
                'closed' => (bool) ($source['closed'] ?? false),
            ];
        }

        if (isset($current['current_source'])) {
            $currentSource = self::sourceName((string) $current['current_source']);
            if (!isset($state['sources'][$currentSource])) {
                throw new \InvalidArgumentException('SQLite VFS hydrated current source has no handle');
            }
            $state['current_source'] = $currentSource;
        }

        $state['sequence'] = count($state['sources']);
        return $state;
    }

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
                'path' => $operation['path'] ?? null,
                'readonly' => $operation['readonly'] ?? null,
                'control' => $operation['control'] ?? null,
                'value' => $operation['value'] ?? null,
                'level' => $operation['level'] ?? null,
                'connection' => $operation['connection'] ?? null,
                'mode' => $operation['mode'] ?? null,
            ];
        }

        $trimmed = trim($operation);
        if (preg_match('/^source\s*\(\s*(?<source>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'source', 'source' => $matches['source']];
        }
        if (preg_match('/^open\s*\(\s*(?<source>[A-Za-z0-9_.:-]+)\s*,\s*(?<path>[^)]*)\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'open', 'source' => $matches['source'], 'path' => trim($matches['path'])];
        }
        if (preg_match('/^lock\s*\(\s*(?<level>shared|reserved|pending|exclusive)\s*,\s*(?<connection>[A-Za-z0-9_.:-]+)\s*\)$/i', $trimmed, $matches) === 1) {
            return ['kind' => 'lock', 'level' => $matches['level'], 'connection' => $matches['connection']];
        }
        if (preg_match('/^sync\s*\(\s*(?<mode>normal|full|dataonly)\s*\)$/i', $trimmed, $matches) === 1) {
            return ['kind' => 'sync', 'mode' => $matches['mode']];
        }
        if (preg_match('/^close\s*\(\s*(?<source>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'close', 'source' => $matches['source']];
        }

        throw new \InvalidArgumentException('SQLite VFS current-source next150-153 operation is unsupported');
    }

    private static function sourceFor(array $state, mixed $source): string
    {
        if ($source !== null && $source !== '') {
            return self::sourceName((string) $source);
        }
        if (!is_string($state['current_source'])) {
            throw new \InvalidArgumentException('SQLite VFS current-source next150-153 has no selected source');
        }
        return $state['current_source'];
    }

    private static function sourceName(string $source): string
    {
        $source = strtolower(trim($source));
        if ($source === '' || preg_match('/^[a-z0-9_.:-]+$/', $source) !== 1) {
            throw new \InvalidArgumentException('SQLite VFS current source name is unsupported');
        }
        return $source;
    }

    private static function pathName(string $path): string
    {
        $path = trim($path);
        if ($path === '' || str_contains($path, "\0")) {
            throw new \InvalidArgumentException('SQLite VFS source path is required');
        }
        return $path;
    }

    private static function owner(string $path): string
    {
        return preg_replace('/-(?:wal|shm|journal)$/', '', $path) ?? $path;
    }

    private static function handleName(string $handle): string
    {
        $handle = trim($handle);
        if ($handle === '' || preg_match('/^[A-Za-z0-9_.:-]+$/', $handle) !== 1) {
            throw new \InvalidArgumentException('SQLite VFS hydrated handle name is unsupported');
        }
        return $handle;
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
        if (!in_array($level, ['shared', 'reserved', 'pending', 'exclusive'], true)) {
            throw new \InvalidArgumentException('SQLite VFS lock level is unsupported');
        }
        return $level;
    }

    private static function syncMode(string $mode): string
    {
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['normal', 'full', 'dataonly'], true)) {
            throw new \InvalidArgumentException('SQLite VFS sync mode is unsupported');
        }
        return $mode;
    }

    private static function connectionName(mixed $connection): string
    {
        $connection = $connection === null || $connection === '' ? 'default' : trim((string) $connection);
        if ($connection === '' || preg_match('/^[A-Za-z0-9_.:-]+$/', $connection) !== 1) {
            throw new \InvalidArgumentException('SQLite VFS lock connection name is unsupported');
        }
        return $connection;
    }

    private static function generation(mixed $generation): int
    {
        $generation = filter_var($generation, FILTER_VALIDATE_INT);
        if (!is_int($generation) || $generation < 1) {
            throw new \InvalidArgumentException('SQLite VFS current-source generation must be positive');
        }
        return $generation;
    }

    private static function firstOpenSource(array $state): ?string
    {
        foreach ($state['sources'] as $source => $sourceState) {
            if ($sourceState['closed'] !== true) {
                return (string) $source;
            }
        }
        return null;
    }

    private static function summary(array $state): array
    {
        return [
            'current_source' => $state['current_source'],
            'source_count' => count($state['sources']),
            'open_source_count' => count(array_filter($state['sources'], static fn (array $source): bool => $source['closed'] !== true)),
            'owner_generations' => $state['owner_generations'],
            'sources' => $state['sources'],
        ];
    }

    private static function snapshot(array $state): array
    {
        ksort($state['sources']);
        ksort($state['owner_generations']);
        return $state;
    }

    private static function event(string $op, string $status, string $source, array $current, array $next, array $extra): array
    {
        return ['op' => $op, 'status' => $status, 'source' => $source, 'current' => $current, 'next' => $next] + $extra;
    }
}
