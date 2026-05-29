<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsCurrentSourceNext166169Plan
{
    /**
     * @param list<array<string, mixed>|string> $operations
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function run(array $operations, array $options = []): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS current-source next166-169 requires operations');
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
                    'vfs166169-' . $state['sequence'],
                    $path,
                    $owner,
                    self::systemCalls($op['system_calls'] ?? $op['syscalls'] ?? [])
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

            if ($op['kind'] === 'currenttime') {
                $julian = self::julianDay($op['unix'] ?? $op['timestamp'] ?? null);
                $state['sources'][$source]['last_time_julian'] = $julian;
                $events[] = self::event('current_time', 'ok', $source, $before, self::summary($state), [
                    'julian_day' => $julian,
                    'unix' => self::unixFromJulian($julian),
                ]);
                continue;
            }

            if ($op['kind'] === 'currenttimeint64') {
                $unix = self::nonNegativeInt($op['unix'] ?? $op['timestamp'] ?? null, 'current time unix timestamp');
                $int64 = ($unix + 21086676 * 10000) * 1000;
                $state['sources'][$source]['last_time_int64'] = $int64;
                $events[] = self::event('current_time_int64', 'ok', $source, $before, self::summary($state), [
                    'unix' => $unix,
                    'sqlite_time_int64' => $int64,
                    'monotonic_for_source' => $int64 >= (int) ($before['sources'][$source]['last_time_int64'] ?? 0),
                ]);
                continue;
            }

            if ($op['kind'] === 'lasterror') {
                $code = self::errorCode($op['code'] ?? 'SQLITE_IOERR');
                $message = self::message((string) ($op['message'] ?? $code));
                $state['sources'][$source]['last_error'] = ['code' => $code, 'message' => $message];
                $events[] = self::event('last_error', 'recorded', $source, $before, self::summary($state), [
                    'code' => $code,
                    'message' => $message,
                ]);
                continue;
            }

            if ($op['kind'] === 'setsystemcall') {
                $name = self::systemCallName((string) $op['name']);
                $enabled = (bool) ($op['enabled'] ?? true);
                $state['sources'][$source]['system_calls'][$name] = $enabled;
                $events[] = self::event('set_system_call', 'ok', $source, $before, self::summary($state), [
                    'name' => $name,
                    'enabled' => $enabled,
                    'enabled_count' => self::enabledSystemCallCount($state['sources'][$source]['system_calls']),
                ]);
                continue;
            }

            if ($op['kind'] === 'getsystemcall') {
                $name = self::systemCallName((string) $op['name']);
                $events[] = self::event('get_system_call', 'ok', $source, $before, self::summary($state), [
                    'name' => $name,
                    'enabled' => (bool) ($state['sources'][$source]['system_calls'][$name] ?? false),
                ]);
                continue;
            }

            if ($op['kind'] === 'nextsystemcall') {
                $after = isset($op['after']) && $op['after'] !== '' ? self::systemCallName((string) $op['after']) : null;
                $next = self::nextSystemCall($state['sources'][$source]['system_calls'], $after);
                $events[] = self::event('next_system_call', $next === null ? 'end' : 'ok', $source, $before, self::summary($state), [
                    'after' => $after,
                    'name' => $next,
                ]);
                continue;
            }

            if ($op['kind'] === 'close') {
                $state['sources'][$source]['closed'] = true;
                if ($state['current_source'] === $source) {
                    $state['current_source'] = self::firstOpenSource($state);
                }
                $events[] = self::event('close', 'closed', $source, $before, self::summary($state), [
                    'last_error' => $state['sources'][$source]['last_error'],
                    'enabled_system_calls' => self::enabledSystemCallCount($state['sources'][$source]['system_calls']),
                ]);
                continue;
            }

            throw new \InvalidArgumentException('SQLite VFS current-source next166-169 operation is unsupported');
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
                self::systemCalls($source['system_calls'] ?? []),
                $source['last_error'] ?? null,
                $source['last_time_julian'] ?? null,
                $source['last_time_int64'] ?? null,
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
                'xcurrenttime' => 'currenttime',
                'xcurrenttimeint64' => 'currenttimeint64',
                'xgetlasterror' => 'lasterror',
                'xsetsystemcall' => 'setsystemcall',
                'xgetsystemcall' => 'getsystemcall',
                'xnextsystemcall' => 'nextsystemcall',
                default => $kind,
            }];
        }
        $trimmed = trim($operation);
        if (preg_match('/^source\s*\(\s*(?<source>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'source', 'source' => $matches['source']];
        }
        if (preg_match('/^currenttime\s*\(\s*(?<unix>[0-9]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'currenttime', 'unix' => (int) $matches['unix']];
        }
        if (preg_match('/^currenttimeint64\s*\(\s*(?<unix>[0-9]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'currenttimeint64', 'unix' => (int) $matches['unix']];
        }
        if (preg_match('/^nextsystemcall\s*\(\s*(?<after>[A-Za-z0-9_.:-]*)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'nextsystemcall', 'after' => $matches['after']];
        }
        if (preg_match('/^close\s*\(\s*(?<source>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'close', 'source' => $matches['source']];
        }
        throw new \InvalidArgumentException('SQLite VFS current-source next166-169 operation is unsupported');
    }

    private static function sourceState(
        string $handle,
        string $path,
        string $owner,
        array $systemCalls,
        mixed $lastError = null,
        mixed $lastTimeJulian = null,
        mixed $lastTimeInt64 = null,
        bool $closed = false
    ): array {
        return [
            'handle' => $handle,
            'path' => $path,
            'owner' => $owner,
            'system_calls' => $systemCalls,
            'last_error' => is_array($lastError) ? [
                'code' => self::errorCode($lastError['code'] ?? 'SQLITE_IOERR'),
                'message' => self::message((string) ($lastError['message'] ?? $lastError['code'] ?? 'SQLITE_IOERR')),
            ] : null,
            'last_time_julian' => $lastTimeJulian === null ? null : (float) $lastTimeJulian,
            'last_time_int64' => $lastTimeInt64 === null ? null : self::positiveInt($lastTimeInt64, 'current time int64'),
            'closed' => $closed,
        ];
    }

    private static function sourceFor(array $state, mixed $source): string
    {
        if ($source !== null && $source !== '') {
            return self::sourceName((string) $source);
        }
        if (!is_string($state['current_source'])) {
            throw new \InvalidArgumentException('SQLite VFS current-source next166-169 has no selected source');
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

    private static function julianDay(mixed $unix): float
    {
        return self::nonNegativeInt($unix, 'current time unix timestamp') / 86400 + 2440587.5;
    }

    private static function unixFromJulian(float $julian): int
    {
        return (int) round(($julian - 2440587.5) * 86400);
    }

    private static function systemCalls(mixed $calls): array
    {
        $out = [];
        foreach (is_array($calls) ? $calls : [] as $name => $enabled) {
            if (is_int($name)) {
                $name = (string) $enabled;
                $enabled = true;
            }
            $out[self::systemCallName((string) $name)] = (bool) $enabled;
        }
        ksort($out);
        return $out;
    }

    private static function systemCallName(string $name): string
    {
        $name = strtolower(str_replace('-', '_', trim($name)));
        if ($name === '' || preg_match('/^[a-z0-9_]+$/', $name) !== 1) {
            throw new \InvalidArgumentException('SQLite VFS current-source next166-169 system call name is unsupported');
        }
        return $name;
    }

    private static function nextSystemCall(array $calls, ?string $after): ?string
    {
        $names = array_keys(array_filter($calls));
        sort($names);
        foreach ($names as $name) {
            if ($after === null || strcmp($name, $after) > 0) {
                return $name;
            }
        }
        return null;
    }

    private static function enabledSystemCallCount(array $calls): int
    {
        return count(array_filter($calls));
    }

    private static function errorCode(mixed $code): string
    {
        $code = strtoupper(str_replace(' ', '_', trim((string) $code)));
        if ($code === '' || preg_match('/^SQLITE_[A-Z0-9_]+$/', $code) !== 1) {
            throw new \InvalidArgumentException('SQLite VFS current-source next166-169 error code is unsupported');
        }
        return $code;
    }

    private static function message(string $message): string
    {
        $message = trim($message);
        if ($message === '' || str_contains($message, "\0")) {
            throw new \InvalidArgumentException('SQLite VFS current-source next166-169 error message is unsupported');
        }
        return $message;
    }

    private static function token(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:-]+$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite VFS current-source next166-169 {$label} is unsupported");
        }
        return $value;
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \InvalidArgumentException("SQLite VFS current-source next166-169 {$label} must be positive");
        }
        $int = (int) $value;
        if ($int <= 0) {
            throw new \InvalidArgumentException("SQLite VFS current-source next166-169 {$label} must be positive");
        }
        return $int;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \InvalidArgumentException("SQLite VFS current-source next166-169 {$label} must be non-negative");
        }
        $int = (int) $value;
        if ($int < 0) {
            throw new \InvalidArgumentException("SQLite VFS current-source next166-169 {$label} must be non-negative");
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
