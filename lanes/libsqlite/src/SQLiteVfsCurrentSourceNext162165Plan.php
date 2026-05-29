<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsCurrentSourceNext162165Plan
{
    /**
     * @param list<array<string, mixed>|string> $operations
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function run(array $operations, array $options = []): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS current-source next162-165 requires operations');
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
                    'vfs162165-' . $state['sequence'],
                    $path,
                    $owner,
                    (bool) ($op['temporary'] ?? false),
                    self::positiveInt($op['sector_size'] ?? 4096, 'sector size'),
                    self::deviceFlags($op['device'] ?? [])
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
                $path = self::pathName((string) ($op['path'] ?? $state['sources'][$source]['path']));
                $mode = self::accessMode((string) ($op['mode'] ?? 'exists'));
                $allowed = self::sameOwner($state['sources'][$source]['owner'], $path);
                $events[] = self::event('access', $allowed ? 'ok' : 'blocked', $source, $before, self::summary($state), [
                    'path' => $path,
                    'mode' => $mode,
                    'reason' => $allowed ? 'current-source owner accepted' : 'access path belongs to a different current-source owner',
                ]);
                continue;
            }

            if ($op['kind'] === 'fullpathname') {
                $path = self::pathName((string) ($op['path'] ?? $state['sources'][$source]['path']));
                $full = self::fullPathName($state['sources'][$source]['owner'], $path);
                $events[] = self::event('full_pathname', 'ok', $source, $before, self::summary($state), [
                    'path' => $path,
                    'full_pathname' => $full,
                    'stable' => $full === $state['sources'][$source]['path'],
                ]);
                continue;
            }

            if ($op['kind'] === 'sector') {
                $sector = self::positiveInt($op['bytes'] ?? $op['sector_size'] ?? null, 'sector size');
                $state['sources'][$source]['sector_size'] = $sector;
                $events[] = self::event('sector_size', 'ok', $source, $before, self::summary($state), [
                    'sector_size' => $sector,
                    'direct_overflow_risk' => $sector > 4096,
                ]);
                continue;
            }

            if ($op['kind'] === 'device') {
                $flags = self::deviceFlags($op['flags'] ?? $op['device'] ?? []);
                $state['sources'][$source]['device'] = $flags;
                $events[] = self::event('device_characteristics', 'ok', $source, $before, self::summary($state), [
                    'device' => $flags,
                    'safe_append' => in_array('safe_append', $flags, true),
                    'powersafe_overwrite' => in_array('powersafe_overwrite', $flags, true),
                ]);
                continue;
            }

            if ($op['kind'] === 'randomness') {
                $bytes = self::positiveInt($op['bytes'] ?? 16, 'randomness bytes');
                $seed = self::token((string) ($op['seed'] ?? $source), 'randomness seed');
                $state['sources'][$source]['randomness'][] = hash('sha256', $source . '|' . $seed . '|' . $bytes);
                $events[] = self::event('randomness', 'ok', $source, $before, self::summary($state), [
                    'bytes' => $bytes,
                    'digest' => $state['sources'][$source]['randomness'][array_key_last($state['sources'][$source]['randomness'])],
                    'request_count' => count($state['sources'][$source]['randomness']),
                ]);
                continue;
            }

            if ($op['kind'] === 'sleep') {
                $micros = self::nonNegativeInt($op['micros'] ?? $op['microseconds'] ?? null, 'sleep microseconds');
                $state['sources'][$source]['sleep_microseconds'] += $micros;
                $events[] = self::event('sleep', 'ok', $source, $before, self::summary($state), [
                    'microseconds' => $micros,
                    'total_sleep_microseconds' => $state['sources'][$source]['sleep_microseconds'],
                ]);
                continue;
            }

            if ($op['kind'] === 'delete') {
                $path = self::pathName((string) ($op['path'] ?? $state['sources'][$source]['path']));
                $syncDir = (bool) ($op['sync_dir'] ?? false);
                if (!self::sameOwner($state['sources'][$source]['owner'], $path)) {
                    $events[] = self::event('delete', 'blocked', $source, $before, self::summary($state), [
                        'path' => $path,
                        'reason' => 'delete path belongs to a different current-source owner',
                    ]);
                    continue;
                }
                if ($state['sources'][$source]['temporary'] !== true && $syncDir !== true) {
                    $events[] = self::event('delete', 'blocked', $source, $before, self::summary($state), [
                        'path' => $path,
                        'reason' => 'persistent current-source delete requires directory sync',
                    ]);
                    continue;
                }
                $state['sources'][$source]['deleted_paths'][] = $path;
                $events[] = self::event('delete', 'ok', $source, $before, self::summary($state), [
                    'path' => $path,
                    'sync_dir' => $syncDir,
                    'delete_count' => count($state['sources'][$source]['deleted_paths']),
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
                    'randomness_requests' => count($state['sources'][$source]['randomness']),
                ]);
                continue;
            }

            throw new \InvalidArgumentException('SQLite VFS current-source next162-165 operation is unsupported');
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
                (bool) ($source['temporary'] ?? false),
                self::positiveInt($source['sector_size'] ?? 4096, 'sector size'),
                self::deviceFlags($source['device'] ?? []),
                is_array($source['deleted_paths'] ?? null) ? array_values($source['deleted_paths']) : [],
                is_array($source['randomness'] ?? null) ? array_values($source['randomness']) : [],
                self::nonNegativeInt($source['sleep_microseconds'] ?? 0, 'sleep microseconds'),
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
                'xfullpathname' => 'fullpathname',
                'xsectorsize' => 'sector',
                'xdevicecharacteristics' => 'device',
                'xrandomness' => 'randomness',
                'xsleep' => 'sleep',
                default => $kind,
            }];
        }
        $trimmed = trim($operation);
        if (preg_match('/^source\s*\(\s*(?<source>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'source', 'source' => $matches['source']];
        }
        if (preg_match('/^access\s*\(\s*(?<path>[^,]+)\s*,\s*(?<mode>[A-Za-z_]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'access', 'path' => trim($matches['path']), 'mode' => $matches['mode']];
        }
        if (preg_match('/^fullpathname\s*\(\s*(?<path>[^)]*)\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'fullpathname', 'path' => trim($matches['path'])];
        }
        if (preg_match('/^sleep\s*\(\s*(?<micros>[0-9]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'sleep', 'micros' => (int) $matches['micros']];
        }
        if (preg_match('/^close\s*\(\s*(?<source>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'close', 'source' => $matches['source']];
        }
        throw new \InvalidArgumentException('SQLite VFS current-source next162-165 operation is unsupported');
    }

    private static function sourceState(
        string $handle,
        string $path,
        string $owner,
        bool $temporary,
        int $sectorSize,
        array $device,
        array $deletedPaths = [],
        array $randomness = [],
        int $sleepMicroseconds = 0,
        bool $closed = false
    ): array {
        return [
            'handle' => $handle,
            'path' => $path,
            'owner' => $owner,
            'temporary' => $temporary,
            'sector_size' => $sectorSize,
            'device' => $device,
            'deleted_paths' => $deletedPaths,
            'randomness' => $randomness,
            'sleep_microseconds' => $sleepMicroseconds,
            'closed' => $closed,
        ];
    }

    private static function sourceFor(array $state, mixed $source): string
    {
        if ($source !== null && $source !== '') {
            return self::sourceName((string) $source);
        }
        if (!is_string($state['current_source'])) {
            throw new \InvalidArgumentException('SQLite VFS current-source next162-165 has no selected source');
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

    private static function sameOwner(string $owner, string $path): bool
    {
        return self::owner($path) === $owner;
    }

    private static function fullPathName(string $owner, string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }
        $directory = dirname($owner);
        return ($directory === '.' ? '' : $directory . '/') . $path;
    }

    private static function accessMode(string $mode): string
    {
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['exists', 'readwrite', 'readonly'], true)) {
            throw new \InvalidArgumentException('SQLite VFS current-source next162-165 access mode is unsupported');
        }
        return $mode;
    }

    private static function deviceFlags(mixed $flags): array
    {
        $items = is_array($flags) ? $flags : preg_split('/[,\s]+/', (string) $flags, -1, PREG_SPLIT_NO_EMPTY);
        $out = [];
        foreach ($items ?: [] as $flag) {
            $flag = strtolower(str_replace('-', '_', trim((string) $flag)));
            if ($flag === '') {
                continue;
            }
            if (!in_array($flag, ['atomic', 'safe_append', 'sequential', 'powersafe_overwrite', 'undeletable_when_open'], true)) {
                throw new \InvalidArgumentException('SQLite VFS current-source next162-165 device flag is unsupported');
            }
            $out[$flag] = true;
        }
        return array_keys($out);
    }

    private static function token(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:-]+$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite VFS current-source next162-165 {$label} is unsupported");
        }
        return $value;
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \InvalidArgumentException("SQLite VFS current-source next162-165 {$label} must be positive");
        }
        $int = (int) $value;
        if ($int <= 0) {
            throw new \InvalidArgumentException("SQLite VFS current-source next162-165 {$label} must be positive");
        }
        return $int;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \InvalidArgumentException("SQLite VFS current-source next162-165 {$label} must be non-negative");
        }
        $int = (int) $value;
        if ($int < 0) {
            throw new \InvalidArgumentException("SQLite VFS current-source next162-165 {$label} must be non-negative");
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
