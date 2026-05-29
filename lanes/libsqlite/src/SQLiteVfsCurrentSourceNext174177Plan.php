<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsCurrentSourceNext174177Plan
{
    /**
     * @param list<array<string, mixed>|string> $operations
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function run(array $operations, array $options = []): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS current-source next174-177 requires operations');
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
                    'vfs174177-' . $state['sequence'],
                    $path,
                    $owner,
                    self::pathSet($op['existing_paths'] ?? [$path])
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
                    'owner' => $state['sources'][$source]['owner'],
                ]);
                continue;
            }

            $source = self::sourceFor($state, $op['source'] ?? null);
            if (!isset($state['sources'][$source]) || $state['sources'][$source]['closed'] === true) {
                $events[] = self::event($op['kind'], 'missing-source', $source, $before, self::summary($state), []);
                continue;
            }

            if ($op['kind'] === 'access') {
                $path = self::sourcePath($state['sources'][$source], $op['path'] ?? null);
                $exists = in_array($path, $state['sources'][$source]['existing_paths'], true);
                $events[] = self::event('access', $exists ? 'exists' : 'missing', $source, $before, self::summary($state), [
                    'path' => $path,
                    'same_owner' => self::owner($path) === $state['sources'][$source]['owner'],
                    'exists' => $exists,
                ]);
                continue;
            }

            if ($op['kind'] === 'delete') {
                $path = self::sourcePath($state['sources'][$source], $op['path'] ?? null);
                $sameOwner = self::owner($path) === $state['sources'][$source]['owner'];
                $prior = $state['sources'][$source]['existing_paths'];
                if ($sameOwner) {
                    $state['sources'][$source]['existing_paths'] = array_values(array_diff($prior, [$path]));
                    $state['sources'][$source]['deleted_paths'][] = $path;
                }
                $events[] = self::event('delete', $sameOwner ? 'deleted' : 'blocked', $source, $before, self::summary($state), [
                    'path' => $path,
                    'same_owner' => $sameOwner,
                    'existed_before' => in_array($path, $prior, true),
                ]);
                continue;
            }

            if ($op['kind'] === 'randomness') {
                $bytes = self::positiveInt($op['bytes'] ?? 16, 'randomness bytes');
                $seed = self::token((string) ($op['seed'] ?? $source), 'randomness seed');
                $hex = substr(hash('sha256', $state['sources'][$source]['handle'] . ':' . $seed . ':' . $bytes), 0, $bytes * 2);
                $state['sources'][$source]['randomness_hex'] = $hex;
                $events[] = self::event('randomness', 'ok', $source, $before, self::summary($state), [
                    'bytes' => $bytes,
                    'hex' => $hex,
                ]);
                continue;
            }

            if ($op['kind'] === 'sleep') {
                $microseconds = self::nonNegativeInt($op['microseconds'] ?? $op['usec'] ?? 0, 'sleep microseconds');
                $state['sources'][$source]['slept_microseconds'] += $microseconds;
                $events[] = self::event('sleep', 'ok', $source, $before, self::summary($state), [
                    'microseconds' => $microseconds,
                    'total_microseconds' => $state['sources'][$source]['slept_microseconds'],
                ]);
                continue;
            }

            if ($op['kind'] === 'close') {
                $state['sources'][$source]['closed'] = true;
                if ($state['current_source'] === $source) {
                    $state['current_source'] = self::firstOpenSource($state);
                }
                $events[] = self::event('close', 'closed', $source, $before, self::summary($state), [
                    'deleted_paths' => $state['sources'][$source]['deleted_paths'],
                ]);
                continue;
            }

            throw new \InvalidArgumentException('SQLite VFS current-source next174-177 operation is unsupported');
        }

        return [
            'status' => (string) ($events[array_key_last($events)]['status'] ?? 'ok'),
            'current' => $current,
            'next' => self::summary($state),
            'events' => $events,
            'dependencies' => [
                'vfs-current-source-close-reopen-next150-153',
                'vfs-current-source-io-methods-next154-157',
                'vfs-current-source-mmap-shm-next158-161',
                'vfs-current-source-environment-next162-165',
                'vfs-current-source-time-error-syscall-next166-169',
                'vfs-current-source-path-control-names-next170-173',
                'vfs-current-source-access-delete-random-sleep-next174-177',
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
                self::pathSet($source['existing_paths'] ?? [$path]),
                self::pathSet($source['deleted_paths'] ?? []),
                (string) ($source['randomness_hex'] ?? ''),
                self::nonNegativeInt($source['slept_microseconds'] ?? 0, 'slept microseconds'),
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
                'xaccess' => 'access',
                'xdelete' => 'delete',
                'xrandomness' => 'randomness',
                'xsleep' => 'sleep',
                default => $kind,
            }];
        }
        $trimmed = trim($operation);
        if (preg_match('/^source\s*\(\s*(?<source>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'source', 'source' => $matches['source']];
        }
        if (preg_match('/^access\s*\(\s*(?<suffix>-(?:wal|shm|journal))\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'access', 'path' => $matches['suffix']];
        }
        if (preg_match('/^delete\s*\(\s*(?<suffix>-(?:wal|shm|journal))\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'delete', 'path' => $matches['suffix']];
        }
        if (preg_match('/^randomness\s*\(\s*(?<bytes>[0-9]+)\s*,\s*(?<seed>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'randomness', 'bytes' => (int) $matches['bytes'], 'seed' => $matches['seed']];
        }
        if (preg_match('/^sleep\s*\(\s*(?<usec>[0-9]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'sleep', 'microseconds' => (int) $matches['usec']];
        }
        if (preg_match('/^close\s*\(\s*(?<source>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'close', 'source' => $matches['source']];
        }
        throw new \InvalidArgumentException('SQLite VFS current-source next174-177 operation is unsupported');
    }

    private static function sourceState(
        string $handle,
        string $path,
        string $owner,
        array $existingPaths,
        array $deletedPaths = [],
        string $randomnessHex = '',
        int $sleptMicroseconds = 0,
        bool $closed = false
    ): array {
        return [
            'handle' => $handle,
            'path' => $path,
            'owner' => $owner,
            'existing_paths' => $existingPaths,
            'deleted_paths' => $deletedPaths,
            'randomness_hex' => $randomnessHex,
            'slept_microseconds' => $sleptMicroseconds,
            'closed' => $closed,
        ];
    }

    private static function sourceFor(array $state, mixed $source): string
    {
        if ($source !== null && $source !== '') {
            return self::sourceName((string) $source);
        }
        if (!is_string($state['current_source'])) {
            throw new \InvalidArgumentException('SQLite VFS current-source next174-177 has no selected source');
        }
        return $state['current_source'];
    }

    private static function sourcePath(array $source, mixed $path): string
    {
        if ($path === null || $path === '') {
            return $source['path'];
        }
        $path = self::pathName((string) $path);
        if (str_starts_with($path, '-')) {
            return $source['owner'] . $path;
        }
        return $path;
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
            throw new \InvalidArgumentException("SQLite VFS current-source next174-177 {$label} is unsupported");
        }
        return $value;
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \InvalidArgumentException("SQLite VFS current-source next174-177 {$label} must be positive");
        }
        $int = (int) $value;
        if ($int <= 0) {
            throw new \InvalidArgumentException("SQLite VFS current-source next174-177 {$label} must be positive");
        }
        return $int;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \InvalidArgumentException("SQLite VFS current-source next174-177 {$label} must be non-negative");
        }
        $int = (int) $value;
        if ($int < 0) {
            throw new \InvalidArgumentException("SQLite VFS current-source next174-177 {$label} must be non-negative");
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
