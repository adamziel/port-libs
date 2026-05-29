<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsCurrentSourceNext170173Plan
{
    /**
     * @param list<array<string, mixed>|string> $operations
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function run(array $operations, array $options = []): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS current-source next170-173 requires operations');
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
                    'vfs170173-' . $state['sequence'],
                    $path,
                    $owner,
                    self::controlState($op['controls'] ?? [])
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

            if ($op['kind'] === 'filecontrol') {
                $control = self::controlName((string) $op['name']);
                $value = self::controlValue($control, $op['value'] ?? true);
                $state['sources'][$source]['controls'][$control] = $value;
                $events[] = self::event('file_control', 'ok', $source, $before, self::summary($state), [
                    'name' => $control,
                    'value' => $value,
                    'control_count' => count($state['sources'][$source]['controls']),
                ]);
                continue;
            }

            if ($op['kind'] === 'pathname') {
                $suffix = self::pathSuffix((string) ($op['suffix'] ?? ''));
                $path = $state['sources'][$source]['path'] . $suffix;
                $sameOwner = self::owner($path) === $state['sources'][$source]['owner'];
                $events[] = self::event('pathname', $sameOwner ? 'ok' : 'blocked', $source, $before, self::summary($state), [
                    'path' => $path,
                    'owner' => self::owner($path),
                    'same_owner' => $sameOwner,
                ]);
                continue;
            }

            if ($op['kind'] === 'tempname') {
                $prefix = self::token((string) ($op['prefix'] ?? 'etilqs'), 'temporary prefix');
                $ordinal = self::positiveInt($op['ordinal'] ?? count($state['sources']) + 1, 'temporary ordinal');
                $path = rtrim(dirname($state['sources'][$source]['path']), '/') . '/' . $prefix . '-' . $ordinal . '.tmp';
                $state['sources'][$source]['generated_names'][] = $path;
                $events[] = self::event('temporary_name', 'ok', $source, $before, self::summary($state), [
                    'path' => $path,
                    'generated_count' => count($state['sources'][$source]['generated_names']),
                ]);
                continue;
            }

            if ($op['kind'] === 'close') {
                $state['sources'][$source]['closed'] = true;
                if ($state['current_source'] === $source) {
                    $state['current_source'] = self::firstOpenSource($state);
                }
                $events[] = self::event('close', 'closed', $source, $before, self::summary($state), [
                    'controls' => $state['sources'][$source]['controls'],
                    'generated_names' => $state['sources'][$source]['generated_names'],
                ]);
                continue;
            }

            throw new \InvalidArgumentException('SQLite VFS current-source next170-173 operation is unsupported');
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
                self::controlState($source['controls'] ?? []),
                is_array($source['generated_names'] ?? null) ? array_values($source['generated_names']) : [],
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
                'xfilecontrol' => 'filecontrol',
                'filecontrol' => 'filecontrol',
                'xpathname' => 'pathname',
                'xfullpathname' => 'pathname',
                'tempname' => 'tempname',
                default => $kind,
            }];
        }
        $trimmed = trim($operation);
        if (preg_match('/^source\s*\(\s*(?<source>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'source', 'source' => $matches['source']];
        }
        if (preg_match('/^pathname\s*\(\s*(?<suffix>-[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'pathname', 'suffix' => $matches['suffix']];
        }
        if (preg_match('/^tempname\s*\(\s*(?<prefix>[A-Za-z0-9_.:-]+)\s*,\s*(?<ordinal>[0-9]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'tempname', 'prefix' => $matches['prefix'], 'ordinal' => (int) $matches['ordinal']];
        }
        if (preg_match('/^close\s*\(\s*(?<source>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'close', 'source' => $matches['source']];
        }
        throw new \InvalidArgumentException('SQLite VFS current-source next170-173 operation is unsupported');
    }

    private static function sourceState(
        string $handle,
        string $path,
        string $owner,
        array $controls,
        array $generatedNames = [],
        bool $closed = false
    ): array {
        return [
            'handle' => $handle,
            'path' => $path,
            'owner' => $owner,
            'controls' => $controls,
            'generated_names' => array_map(static fn ($path): string => self::pathName((string) $path), $generatedNames),
            'closed' => $closed,
        ];
    }

    private static function sourceFor(array $state, mixed $source): string
    {
        if ($source !== null && $source !== '') {
            return self::sourceName((string) $source);
        }
        if (!is_string($state['current_source'])) {
            throw new \InvalidArgumentException('SQLite VFS current-source next170-173 has no selected source');
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

    private static function controlState(mixed $controls): array
    {
        $out = [];
        foreach (is_array($controls) ? $controls : [] as $name => $value) {
            $control = self::controlName((string) $name);
            $out[$control] = self::controlValue($control, $value);
        }
        ksort($out);
        return $out;
    }

    private static function controlName(string $name): string
    {
        $name = strtolower(str_replace('-', '_', trim($name)));
        $allowed = ['chunk_size', 'persist_wal', 'powersafe_overwrite', 'size_hint', 'tempfilename'];
        if (!in_array($name, $allowed, true)) {
            throw new \InvalidArgumentException('SQLite VFS current-source next170-173 file control is unsupported');
        }
        return $name;
    }

    private static function controlValue(string $control, mixed $value): int|bool|string
    {
        if (in_array($control, ['chunk_size', 'size_hint'], true)) {
            return self::positiveInt($value, $control);
        }
        if (in_array($control, ['persist_wal', 'powersafe_overwrite'], true)) {
            return (bool) $value;
        }
        return self::pathName((string) $value);
    }

    private static function pathSuffix(string $suffix): string
    {
        $suffix = trim($suffix);
        if (!in_array($suffix, ['-wal', '-shm', '-journal'], true)) {
            throw new \InvalidArgumentException('SQLite VFS current-source next170-173 path suffix is unsupported');
        }
        return $suffix;
    }

    private static function token(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:-]+$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite VFS current-source next170-173 {$label} is unsupported");
        }
        return $value;
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \InvalidArgumentException("SQLite VFS current-source next170-173 {$label} must be positive");
        }
        $int = (int) $value;
        if ($int <= 0) {
            throw new \InvalidArgumentException("SQLite VFS current-source next170-173 {$label} must be positive");
        }
        return $int;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \InvalidArgumentException("SQLite VFS current-source next170-173 {$label} must be non-negative");
        }
        $int = (int) $value;
        if ($int < 0) {
            throw new \InvalidArgumentException("SQLite VFS current-source next170-173 {$label} must be non-negative");
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
