<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsCurrentSourceNext182185Plan
{
    /**
     * @param list<array<string, mixed>|string> $operations
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function run(array $operations, array $options = []): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS current-source next182-185 requires operations');
        }

        $state = self::hydrate($options['current'] ?? []);
        $current = self::summary($state);
        $events = [];

        foreach ($operations as $operation) {
            $op = self::operation($operation);
            $before = self::summary($state);

            if ($op['kind'] === 'open') {
                $source = self::sourceName((string) $op['source']);
                $path = self::pathName((string) $op['path']);
                $owner = self::owner($path);
                $state['sequence']++;
                $state['owner_generations'][$owner] = (int) ($state['owner_generations'][$owner] ?? 0) + 1;
                $state['sources'][$source] = self::sourceState(
                    'vfs182185-' . $state['sequence'],
                    $path,
                    $owner,
                    self::pathName((string) ($op['directory'] ?? dirname($path))),
                    (bool) ($op['readonly'] ?? false),
                    self::pathSet($op['known_dirs'] ?? [dirname($path)])
                );
                $state['current_source'] = $source;
                $events[] = self::event('open', 'open', $source, $before, self::summary($state), $state['sources'][$source]);
                continue;
            }

            if ($op['kind'] === 'source') {
                $source = self::sourceName((string) $op['source']);
                if (!isset($state['sources'][$source]) || $state['sources'][$source]['closed'] === true) {
                    $events[] = self::event('source', 'missing-source', $source, $before, self::summary($state), []);
                    continue;
                }
                $state['current_source'] = $source;
                $events[] = self::event('source', 'ok', $source, $before, self::summary($state), [
                    'path' => $state['sources'][$source]['path'],
                    'directory' => $state['sources'][$source]['directory'],
                ]);
                continue;
            }

            $source = self::sourceFor($state, $op['source'] ?? null);
            if (!isset($state['sources'][$source]) || $state['sources'][$source]['closed'] === true) {
                $events[] = self::event($op['kind'], 'missing-source', $source, $before, self::summary($state), []);
                continue;
            }

            if ($op['kind'] === 'tempname') {
                $suffix = self::token((string) ($op['suffix'] ?? 'tmp'), 'temp suffix');
                $name = $state['sources'][$source]['directory'] . '/etilqs-' . $state['sources'][$source]['handle'] . '-' . $suffix;
                $state['sources'][$source]['temp_paths'][] = $name;
                $events[] = self::event('temp_name', 'ok', $source, $before, self::summary($state), [
                    'path' => $name,
                    'same_directory' => dirname($name) === $state['sources'][$source]['directory'],
                    'temp_count' => count($state['sources'][$source]['temp_paths']),
                ]);
                continue;
            }

            if ($op['kind'] === 'mkdir') {
                $path = self::pathName((string) ($op['path'] ?? $state['sources'][$source]['directory']));
                $sameDirectory = $path === $state['sources'][$source]['directory'];
                if ($sameDirectory) {
                    $state['sources'][$source]['known_dirs'] = self::pathSet(array_merge($state['sources'][$source]['known_dirs'], [$path]));
                }
                $events[] = self::event('mkdir', $sameDirectory ? 'ok' : 'blocked', $source, $before, self::summary($state), [
                    'path' => $path,
                    'same_directory' => $sameDirectory,
                ]);
                continue;
            }

            if ($op['kind'] === 'syncdir') {
                $path = self::pathName((string) ($op['path'] ?? $state['sources'][$source]['directory']));
                $known = in_array($path, $state['sources'][$source]['known_dirs'], true);
                if ($known) {
                    $state['sources'][$source]['directory_syncs']++;
                }
                $events[] = self::event('sync_directory', $known ? 'synced' : 'missing-directory', $source, $before, self::summary($state), [
                    'path' => $path,
                    'sync_count' => $state['sources'][$source]['directory_syncs'],
                ]);
                continue;
            }

            if ($op['kind'] === 'readonly') {
                $readonly = (bool) ($op['value'] ?? true);
                $state['sources'][$source]['readonly'] = $readonly;
                $events[] = self::event('readonly', $readonly ? 'readonly' : 'writable', $source, $before, self::summary($state), [
                    'readonly' => $readonly,
                ]);
                continue;
            }

            if ($op['kind'] === 'unlink') {
                $path = self::pathName((string) ($op['path'] ?? $state['sources'][$source]['path']));
                $sameOwner = self::owner($path) === $state['sources'][$source]['owner'];
                if ($state['sources'][$source]['readonly'] === true || !$sameOwner) {
                    $events[] = self::event('unlink', 'blocked', $source, $before, self::summary($state), [
                        'path' => $path,
                        'readonly' => $state['sources'][$source]['readonly'],
                        'same_owner' => $sameOwner,
                    ]);
                    continue;
                }
                $state['sources'][$source]['unlinked_paths'][] = $path;
                $events[] = self::event('unlink', 'unlinked', $source, $before, self::summary($state), [
                    'path' => $path,
                    'unlink_count' => count($state['sources'][$source]['unlinked_paths']),
                ]);
                continue;
            }

            if ($op['kind'] === 'close') {
                $state['sources'][$source]['closed'] = true;
                if ($state['current_source'] === $source) {
                    $state['current_source'] = self::firstOpenSource($state);
                }
                $events[] = self::event('close', 'closed', $source, $before, self::summary($state), [
                    'temp_paths' => $state['sources'][$source]['temp_paths'],
                    'directory_syncs' => $state['sources'][$source]['directory_syncs'],
                ]);
                continue;
            }

            throw new \InvalidArgumentException('SQLite VFS current-source next182-185 operation is unsupported');
        }

        return [
            'status' => (string) ($events[array_key_last($events)]['status'] ?? 'ok'),
            'current' => $current,
            'next' => self::summary($state),
            'events' => $events,
            'dependencies' => [
                'vfs-current-source-close-reopen-next150-153',
                'vfs-current-source-mmap-shm-next158-161',
                'vfs-current-source-access-delete-random-sleep-next174-177',
                'vfs-current-source-sync-truncate-size-reserve-next178-181',
                'vfs-current-source-temp-dir-readonly-next182-185',
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
            $state['owner_generations'][self::pathName((string) $owner)] = self::positiveInt($generation, 'owner generation');
        }
        foreach (is_array($current['sources'] ?? null) ? $current['sources'] : [] as $name => $source) {
            if (!is_array($source)) {
                continue;
            }
            $path = self::pathName((string) ($source['path'] ?? ''));
            $sourceName = self::sourceName((string) $name);
            $state['sources'][$sourceName] = self::sourceState(
                self::token((string) ($source['handle'] ?? $sourceName), 'handle'),
                $path,
                self::pathName((string) ($source['owner'] ?? self::owner($path))),
                self::pathName((string) ($source['directory'] ?? dirname($path))),
                (bool) ($source['readonly'] ?? false),
                self::pathSet($source['known_dirs'] ?? [dirname($path)]),
                self::pathSet($source['temp_paths'] ?? []),
                self::pathSet($source['unlinked_paths'] ?? []),
                self::nonNegativeInt($source['directory_syncs'] ?? 0, 'directory sync count'),
                (bool) ($source['closed'] ?? false)
            );
        }
        if (isset($current['current_source'])) {
            $state['current_source'] = self::sourceName((string) $current['current_source']);
        }
        $state['sequence'] = count($state['sources']);
        return $state;
    }

    private static function operation(string|array $operation): array
    {
        if (is_array($operation)) {
            $kind = strtolower(str_replace(['_', '-'], '', (string) ($operation['op'] ?? $operation['kind'] ?? '')));
            return $operation + ['kind' => match ($kind) {
                'tempname', 'xtempname', 'gettemppath' => 'tempname',
                'xmkdir' => 'mkdir',
                'syncdirectory', 'xsyncdirectory' => 'syncdir',
                'setreadonly' => 'readonly',
                'xdelete', 'delete' => 'unlink',
                default => $kind,
            }];
        }
        $trimmed = trim($operation);
        if (preg_match('/^source\s*\(\s*(?<source>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'source', 'source' => $matches['source']];
        }
        if (preg_match('/^tempname\s*\(\s*(?<suffix>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'tempname', 'suffix' => $matches['suffix']];
        }
        if (preg_match('/^mkdir\s*\(\s*(?<path>[^)]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'mkdir', 'path' => $matches['path']];
        }
        if (preg_match('/^syncdir\s*\(\s*(?<path>[^)]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'syncdir', 'path' => $matches['path']];
        }
        if (preg_match('/^readonly\s*\(\s*(?<value>true|false|1|0)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'readonly', 'value' => in_array($matches['value'], ['true', '1'], true)];
        }
        if (preg_match('/^unlink\s*\(\s*(?<path>[^)]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'unlink', 'path' => $matches['path']];
        }
        if (preg_match('/^close\s*\(\s*(?<source>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'close', 'source' => $matches['source']];
        }
        throw new \InvalidArgumentException('SQLite VFS current-source next182-185 operation is unsupported');
    }

    private static function sourceState(
        string $handle,
        string $path,
        string $owner,
        string $directory,
        bool $readonly,
        array $knownDirs,
        array $tempPaths = [],
        array $unlinkedPaths = [],
        int $directorySyncs = 0,
        bool $closed = false
    ): array {
        return [
            'handle' => $handle,
            'path' => $path,
            'owner' => $owner,
            'directory' => $directory,
            'readonly' => $readonly,
            'known_dirs' => $knownDirs,
            'temp_paths' => $tempPaths,
            'unlinked_paths' => $unlinkedPaths,
            'directory_syncs' => $directorySyncs,
            'closed' => $closed,
        ];
    }

    private static function sourceFor(array $state, mixed $source): string
    {
        if ($source !== null && $source !== '') {
            return self::sourceName((string) $source);
        }
        if (!is_string($state['current_source'])) {
            throw new \InvalidArgumentException('SQLite VFS current-source next182-185 has no selected source');
        }
        return $state['current_source'];
    }

    private static function firstOpenSource(array $state): ?string
    {
        foreach ($state['sources'] as $name => $source) {
            if ($source['closed'] !== true) {
                return (string) $name;
            }
        }
        return null;
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

    private static function pathSet(mixed $paths): array
    {
        $out = [];
        foreach (is_array($paths) ? $paths : [] as $path) {
            $out[] = self::pathName((string) $path);
        }
        $out = array_values(array_unique($out));
        sort($out);
        return $out;
    }

    private static function token(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:-]+$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite VFS current-source next182-185 {$label} is unsupported");
        }
        return $value;
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \InvalidArgumentException("SQLite VFS current-source next182-185 {$label} must be positive");
        }
        $int = (int) $value;
        if ($int <= 0) {
            throw new \InvalidArgumentException("SQLite VFS current-source next182-185 {$label} must be positive");
        }
        return $int;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \InvalidArgumentException("SQLite VFS current-source next182-185 {$label} must be non-negative");
        }
        $int = (int) $value;
        if ($int < 0) {
            throw new \InvalidArgumentException("SQLite VFS current-source next182-185 {$label} must be non-negative");
        }
        return $int;
    }

    private static function summary(array $state): array
    {
        $open = 0;
        foreach ($state['sources'] as $source) {
            $open += $source['closed'] === true ? 0 : 1;
        }
        return [
            'current_source' => $state['current_source'],
            'source_count' => count($state['sources']),
            'open_source_count' => $open,
            'owner_generations' => $state['owner_generations'],
            'sources' => $state['sources'],
        ];
    }

    private static function event(string $operation, string $status, string $source, array $before, array $next, array $extra): array
    {
        return array_merge([
            'operation' => $operation,
            'status' => $status,
            'source' => $source,
            'before' => $before,
            'next' => $next,
        ], $extra);
    }
}
