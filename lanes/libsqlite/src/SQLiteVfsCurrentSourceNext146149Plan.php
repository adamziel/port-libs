<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsCurrentSourceNext146149Plan
{
    public static function run(array $operations, array $options = []): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS current-source next146-149 requires operations');
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
                ]);
                continue;
            }

            if ($op['kind'] === 'open') {
                $source = self::sourceName((string) $op['source']);
                if (isset($state['sources'][$source])) {
                    $state['current_source'] = $source;
                    $events[] = self::event('open', 'reused-current-source', $source, $before, self::snapshot($state), [
                        'handle' => $state['sources'][$source]['handle'],
                        'owner' => $state['sources'][$source]['owner'],
                    ]);
                    continue;
                }

                $state['sequence']++;
                $path = self::pathName((string) ($op['path'] ?? ''));
                $sourceState = [
                    'handle' => 'vfs146149-' . $state['sequence'],
                    'owner' => self::owner($path),
                    'path' => $path,
                    'readonly' => (bool) ($op['readonly'] ?? false),
                    'locks' => [],
                    'file_controls' => [],
                ];
                $state['sources'][$source] = $sourceState;
                $state['current_source'] = $source;
                $events[] = self::event('open', 'open', $source, $before, self::snapshot($state), $sourceState);
                continue;
            }

            $source = self::sourceFor($state, $op['source'] ?? null);
            if (!isset($state['sources'][$source])) {
                $events[] = self::event($op['kind'], 'missing-source', $source, $before, self::snapshot($state), []);
                continue;
            }

            if ($op['kind'] === 'filecontrol') {
                $control = self::controlName((string) $op['control']);
                $state['sources'][$source]['file_controls'][$control] = $op['value'];
                $events[] = self::event('file_control', 'ok', $source, $before, self::snapshot($state), [
                    'file_control' => $control,
                    'value' => $op['value'],
                    'handle' => $state['sources'][$source]['handle'],
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

            throw new \InvalidArgumentException('SQLite VFS current-source next146-149 operation is unsupported');
        }

        return [
            'status' => (string) ($events[array_key_last($events)]['status'] ?? 'ok'),
            'current' => $current,
            'next' => self::summary($state),
            'events' => $events,
            'dependencies' => [
                'vfs-current-source-hydration',
                'vfs-lock-uri-temp-filecontrol-current-source-next137',
                'vfs-current-source-next146-149',
            ],
        ];
    }

    private static function hydrate(mixed $current): array
    {
        $state = ['sequence' => 0, 'current_source' => null, 'sources' => []];
        if (!is_array($current)) {
            return $state;
        }

        $sources = is_array($current['sources'] ?? null) ? $current['sources'] : [];
        foreach ($sources as $name => $source) {
            if (!is_array($source)) {
                continue;
            }
            $sourceName = self::sourceName((string) $name);
            $path = self::pathName((string) ($source['path'] ?? ''));
            $handle = trim((string) ($source['handle'] ?? ''));
            if ($handle === '' || preg_match('/^[A-Za-z0-9_.:-]+$/', $handle) !== 1) {
                throw new \InvalidArgumentException('SQLite VFS hydrated handle name is unsupported');
            }
            $state['sources'][$sourceName] = [
                'handle' => $handle,
                'owner' => self::owner($path),
                'path' => $path,
                'readonly' => (bool) ($source['readonly'] ?? false),
                'locks' => is_array($source['locks'] ?? null) ? $source['locks'] : [],
                'file_controls' => is_array($source['file_controls'] ?? null) ? $source['file_controls'] : [],
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

        throw new \InvalidArgumentException('SQLite VFS current-source next146-149 operation is unsupported');
    }

    private static function sourceFor(array $state, mixed $source): string
    {
        if ($source !== null && $source !== '') {
            return self::sourceName((string) $source);
        }
        if (!is_string($state['current_source'])) {
            throw new \InvalidArgumentException('SQLite VFS current-source next146-149 has no selected source');
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

    private static function connectionName(mixed $connection): string
    {
        $connection = $connection === null || $connection === '' ? 'default' : trim((string) $connection);
        if ($connection === '' || preg_match('/^[A-Za-z0-9_.:-]+$/', $connection) !== 1) {
            throw new \InvalidArgumentException('SQLite VFS lock connection name is unsupported');
        }
        return $connection;
    }

    private static function summary(array $state): array
    {
        return [
            'current_source' => $state['current_source'],
            'source_count' => count($state['sources']),
            'sources' => $state['sources'],
        ];
    }

    private static function snapshot(array $state): array
    {
        ksort($state['sources']);
        return $state;
    }

    private static function event(string $op, string $status, string $source, array $current, array $next, array $extra): array
    {
        return ['op' => $op, 'status' => $status, 'source' => $source, 'current' => $current, 'next' => $next] + $extra;
    }
}
