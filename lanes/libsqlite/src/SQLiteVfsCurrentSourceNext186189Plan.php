<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsCurrentSourceNext186189Plan
{
    /**
     * @param list<array<string, mixed>|string> $operations
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function run(array $operations, array $options = []): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS current-source next186-189 requires operations');
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
                    'vfs186189-' . $state['sequence'],
                    $path,
                    $owner,
                    self::sectorSize($op['sector_size'] ?? 4096),
                    self::characteristics($op['characteristics'] ?? [])
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

            if ($op['kind'] === 'lock') {
                $level = self::lockLevel((string) ($op['level'] ?? 'shared'));
                $currentLevel = $state['sources'][$source]['lock'];
                $status = self::lockRank($level) >= self::lockRank($currentLevel) ? 'locked' : 'downgraded';
                $state['sources'][$source]['lock'] = $level;
                $events[] = self::event('lock', $status, $source, $before, self::summary($state), [
                    'lock' => $level,
                    'reserved' => self::lockRank($level) >= self::lockRank('reserved'),
                ]);
                continue;
            }

            if ($op['kind'] === 'unlock') {
                $level = self::lockLevel((string) ($op['level'] ?? 'none'));
                $state['sources'][$source]['lock'] = $level;
                $events[] = self::event('unlock', 'unlocked', $source, $before, self::summary($state), [
                    'lock' => $level,
                    'reserved' => self::lockRank($level) >= self::lockRank('reserved'),
                ]);
                continue;
            }

            if ($op['kind'] === 'checkreservedlock') {
                $reserved = self::lockRank($state['sources'][$source]['lock']) >= self::lockRank('reserved');
                $events[] = self::event('check_reserved_lock', $reserved ? 'reserved' : 'clear', $source, $before, self::summary($state), [
                    'reserved' => $reserved,
                    'lock' => $state['sources'][$source]['lock'],
                ]);
                continue;
            }

            if ($op['kind'] === 'filecontrol') {
                $name = self::controlName((string) ($op['name'] ?? $op['control'] ?? ''));
                $value = self::controlValue($op['value'] ?? true);
                $state['sources'][$source]['file_controls'][$name] = $value;
                ksort($state['sources'][$source]['file_controls']);
                $events[] = self::event('file_control', 'recorded', $source, $before, self::summary($state), [
                    'name' => $name,
                    'value' => $value,
                    'control_count' => count($state['sources'][$source]['file_controls']),
                ]);
                continue;
            }

            if ($op['kind'] === 'sector') {
                $events[] = self::event('sector_size', 'reported', $source, $before, self::summary($state), [
                    'sector_size' => $state['sources'][$source]['sector_size'],
                ]);
                continue;
            }

            if ($op['kind'] === 'characteristics') {
                $events[] = self::event('device_characteristics', 'reported', $source, $before, self::summary($state), [
                    'characteristics' => $state['sources'][$source]['characteristics'],
                ]);
                continue;
            }

            if ($op['kind'] === 'close') {
                $state['sources'][$source]['closed'] = true;
                if ($state['current_source'] === $source) {
                    $state['current_source'] = self::firstOpenSource($state);
                }
                $events[] = self::event('close', 'closed', $source, $before, self::summary($state), [
                    'lock' => $state['sources'][$source]['lock'],
                    'file_controls' => $state['sources'][$source]['file_controls'],
                ]);
                continue;
            }

            throw new \InvalidArgumentException('SQLite VFS current-source next186-189 operation is unsupported');
        }

        return [
            'status' => (string) ($events[array_key_last($events)]['status'] ?? 'ok'),
            'current' => $current,
            'next' => self::summary($state),
            'events' => $events,
            'dependencies' => [
                'vfs-current-source-close-reopen-next150-153',
                'vfs-current-source-mmap-shm-next158-161',
                'vfs-current-source-sync-truncate-size-reserve-next178-181',
                'vfs-current-source-temp-dir-readonly-next182-185',
                'vfs-current-source-lock-filecontrol-next186-189',
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
                self::sectorSize($source['sector_size'] ?? 4096),
                self::characteristics($source['characteristics'] ?? []),
                self::lockLevel((string) ($source['lock'] ?? 'none')),
                self::fileControls($source['file_controls'] ?? []),
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
                'xlock' => 'lock',
                'xunlock' => 'unlock',
                'xcheckreservedlock' => 'checkreservedlock',
                'xfilecontrol' => 'filecontrol',
                'xsectorsize' => 'sector',
                'xdevicecharacteristics' => 'characteristics',
                default => $kind,
            }];
        }
        $trimmed = trim($operation);
        if (preg_match('/^source\s*\(\s*(?<source>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'source', 'source' => $matches['source']];
        }
        if (preg_match('/^lock\s*\(\s*(?<level>none|shared|reserved|pending|exclusive)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'lock', 'level' => $matches['level']];
        }
        if (preg_match('/^unlock\s*\(\s*(?<level>none|shared|reserved|pending|exclusive)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'unlock', 'level' => $matches['level']];
        }
        if ($trimmed === 'checkreservedlock()') {
            return ['kind' => 'checkreservedlock'];
        }
        if (preg_match('/^filecontrol\s*\(\s*(?<name>[A-Za-z0-9_.:-]+)\s*,\s*(?<value>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'filecontrol', 'name' => $matches['name'], 'value' => $matches['value']];
        }
        if ($trimmed === 'sector()') {
            return ['kind' => 'sector'];
        }
        if ($trimmed === 'characteristics()') {
            return ['kind' => 'characteristics'];
        }
        if (preg_match('/^close\s*\(\s*(?<source>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'close', 'source' => $matches['source']];
        }
        throw new \InvalidArgumentException('SQLite VFS current-source next186-189 operation is unsupported');
    }

    private static function sourceState(
        string $handle,
        string $path,
        string $owner,
        int $sectorSize,
        array $characteristics,
        string $lock = 'none',
        array $fileControls = [],
        bool $closed = false
    ): array {
        return [
            'handle' => $handle,
            'path' => $path,
            'owner' => $owner,
            'lock' => $lock,
            'file_controls' => $fileControls,
            'sector_size' => $sectorSize,
            'characteristics' => $characteristics,
            'closed' => $closed,
        ];
    }

    private static function sourceFor(array $state, mixed $source): string
    {
        if ($source !== null && $source !== '') {
            return self::sourceName((string) $source);
        }
        if (!is_string($state['current_source'])) {
            throw new \InvalidArgumentException('SQLite VFS current-source next186-189 has no selected source');
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

    private static function lockLevel(string $level): string
    {
        $level = strtolower(trim($level));
        if (!isset(self::lockRanks()[$level])) {
            throw new \InvalidArgumentException('SQLite VFS current-source next186-189 lock level is unsupported');
        }
        return $level;
    }

    private static function lockRank(string $level): int
    {
        return self::lockRanks()[$level];
    }

    /** @return array<string, int> */
    private static function lockRanks(): array
    {
        return ['none' => 0, 'shared' => 1, 'reserved' => 2, 'pending' => 3, 'exclusive' => 4];
    }

    private static function sectorSize(mixed $value): int
    {
        $size = self::positiveInt($value, 'sector size');
        if ($size < 512 || $size > 65536) {
            throw new \InvalidArgumentException('SQLite VFS current-source next186-189 sector size is unsupported');
        }
        return $size;
    }

    private static function characteristics(mixed $values): array
    {
        $allowed = ['atomic', 'safe_append', 'sequential', 'undeletable_when_open', 'powersafe_overwrite'];
        $out = [];
        foreach (is_array($values) ? $values : [$values] as $value) {
            $name = strtolower(trim((string) $value));
            if ($name === '') {
                continue;
            }
            if (!in_array($name, $allowed, true)) {
                throw new \InvalidArgumentException('SQLite VFS current-source next186-189 device characteristic is unsupported');
            }
            $out[] = $name;
        }
        $out = array_values(array_unique($out));
        sort($out);
        return $out;
    }

    private static function fileControls(mixed $controls): array
    {
        $out = [];
        foreach (is_array($controls) ? $controls : [] as $name => $value) {
            $out[self::controlName((string) $name)] = self::controlValue($value);
        }
        ksort($out);
        return $out;
    }

    private static function controlName(string $name): string
    {
        $name = strtolower(trim($name));
        if ($name === '' || preg_match('/^[a-z0-9_.:-]+$/', $name) !== 1) {
            throw new \InvalidArgumentException('SQLite VFS current-source next186-189 file-control name is unsupported');
        }
        return $name;
    }

    private static function controlValue(mixed $value): bool|int|string
    {
        if (is_bool($value) || is_int($value)) {
            return $value;
        }
        $value = trim((string) $value);
        if ($value === '') {
            throw new \InvalidArgumentException('SQLite VFS current-source next186-189 file-control value is unsupported');
        }
        if (ctype_digit($value)) {
            return (int) $value;
        }
        if (!in_array(strtolower($value), ['true', 'false'], true) && preg_match('/^[A-Za-z0-9_.:-]+$/', $value) !== 1) {
            throw new \InvalidArgumentException('SQLite VFS current-source next186-189 file-control value is unsupported');
        }
        return match (strtolower($value)) {
            'true' => true,
            'false' => false,
            default => $value,
        };
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

    private static function token(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:-]+$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite VFS current-source next186-189 {$label} is unsupported");
        }
        return $value;
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \InvalidArgumentException("SQLite VFS current-source next186-189 {$label} must be positive");
        }
        $int = (int) $value;
        if ($int <= 0) {
            throw new \InvalidArgumentException("SQLite VFS current-source next186-189 {$label} must be positive");
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
