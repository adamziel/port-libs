<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsUriOpenLockByteCurrentSourceNext84
{
    /**
     * @param list<array<string, mixed>> $sources
     * @return array{status:string,count:int,current:array<string, mixed>,next:array<string, mixed>,events:list<array<string, mixed>>,dependencies:list<string>}
     */
    public static function plan(array $sources): array
    {
        if ($sources === []) {
            throw new \InvalidArgumentException('SQLite VFS URI open lock-byte current-source next84 requires sources');
        }

        $locks = new SQLiteVfsLockState();
        $current = ['sources' => [], 'holders' => [], 'selected_source' => null];
        $events = [];
        $dependencies = ['vfs-uri-open-lock-byte-current-source-next84'];

        foreach ($sources as $ordinal => $source) {
            $name = self::sourceName($source['name'] ?? ('source' . $ordinal));
            $filename = self::sourceString($source['filename'] ?? null, 'filename');
            $open = SQLiteOpenPlan::forFilename(
                $filename,
                (bool) ($source['file_exists'] ?? true),
                (bool) ($source['directory_writable'] ?? true),
                (bool) ($source['lock_available'] ?? true),
                isset($source['busy_timeout']) ? SQLiteBusyHandler::timeout(self::nonNegativeInt($source['busy_timeout'], 'busy_timeout')) : null
            );
            $operations = self::operations($source['operations'] ?? []);
            $sourceCurrent = self::sourceSnapshot($name, $open, $locks);
            $dependencies = self::dependencies($dependencies, $open['dependencies']);

            if (($open['can_open'] ?? false) !== true) {
                $next = $current;
                $next['sources'][$name] = $sourceCurrent;
                $events[] = [
                    'ordinal' => $ordinal,
                    'kind' => 'open',
                    'source' => $name,
                    'status' => 'blocked',
                    'current_source' => null,
                    'open' => $open,
                    'next_source' => $sourceCurrent,
                    'reason' => $open['reason'],
                    'dependencies' => self::dependencies(['vfs-uri-open-source'], $open['dependencies']),
                ];
                $current = $next;
                continue;
            }

            $next = $current;
            $next['sources'][$name] = $sourceCurrent;
            $next['selected_source'] = $name;
            $events[] = [
                'ordinal' => $ordinal,
                'kind' => 'open',
                'source' => $name,
                'status' => 'ready',
                'current_source' => $current['sources'][$name] ?? null,
                'open' => $open,
                'next_source' => $sourceCurrent,
                'reason' => null,
                'dependencies' => self::dependencies(['vfs-uri-open-source'], $open['dependencies']),
            ];
            $current = $next;

            foreach ($operations as $operation) {
                $before = self::sourceSnapshot($name, $open, $locks);
                $lockPlan = SQLiteLockByteRangePlan::forOpenPlan(
                    $open,
                    $operation['level'],
                    $operation['connection'],
                    $operation['shared_slot']
                );
                $result = $locks->acquire($lockPlan);
                $after = self::sourceSnapshot($name, $open, $locks);
                $current['sources'][$name] = $after;
                $current['holders'] = self::allHolders($current['sources']);
                $current['selected_source'] = $name;
                $dependencies = self::dependencies($dependencies, $lockPlan['dependencies'], $result['dependencies']);

                $events[] = [
                    'ordinal' => count($events),
                    'kind' => 'lock',
                    'source' => $name,
                    'level' => $operation['level'],
                    'connection' => $operation['connection'],
                    'current_source' => $before,
                    'plan' => $lockPlan,
                    'result' => $result,
                    'next_source' => $after,
                    'dependencies' => self::dependencies(['vfs-uri-open-lock-byte-current-source-next84'], $lockPlan['dependencies'], $result['dependencies']),
                ];
            }
        }

        return [
            'status' => self::status($events),
            'count' => count($events),
            'current' => ['sources' => [], 'holders' => [], 'selected_source' => null],
            'next' => $current,
            'events' => $events,
            'dependencies' => array_values(array_unique($dependencies)),
        ];
    }

    /**
     * @param array<string, mixed> $open
     * @return array{name:string,path:string,input:string,is_uri:bool,mode:string,cache:string|null,vfs:string|null,nolock:bool,immutable:bool,can_open:bool,holders:array<string, string>,constants:array<string, int>,dependencies:list<string>}
     */
    private static function sourceSnapshot(string $name, array $open, SQLiteVfsLockState $locks): array
    {
        $path = (string) $open['path'];

        return [
            'name' => $name,
            'path' => $path,
            'input' => (string) ($open['uri']['input'] ?? $path),
            'is_uri' => (bool) ($open['uri']['is_uri'] ?? false),
            'mode' => (string) $open['mode'],
            'cache' => $open['cache'],
            'vfs' => $open['vfs'],
            'nolock' => (bool) $open['nolock'],
            'immutable' => (bool) $open['immutable'],
            'can_open' => (bool) $open['can_open'],
            'holders' => $locks->holders($path),
            'constants' => SQLiteLockByteRangePlan::constants(),
            'dependencies' => self::dependencies(['vfs-current-source-open'], $open['dependencies']),
        ];
    }

    /**
     * @param mixed $operations
     * @return list<array{level:string,connection:string,shared_slot:int}>
     */
    private static function operations(mixed $operations): array
    {
        if (!is_array($operations)) {
            throw new \InvalidArgumentException('SQLite VFS next84 source operations must be a list');
        }

        $normalized = [];
        foreach ($operations as $operation) {
            if (is_string($operation)) {
                if (!preg_match('/^(?<level>shared|reserved|pending|exclusive)\s+(?<connection>[A-Za-z0-9_.:-]+)(?:\s+(?<slot>\d+))?$/i', trim($operation), $matches)) {
                    throw new \InvalidArgumentException('SQLite VFS next84 lock operation is unsupported');
                }
                $normalized[] = [
                    'level' => strtolower($matches['level']),
                    'connection' => $matches['connection'],
                    'shared_slot' => isset($matches['slot']) && $matches['slot'] !== '' ? (int) $matches['slot'] : 0,
                ];
                continue;
            }

            if (!is_array($operation)) {
                throw new \InvalidArgumentException('SQLite VFS next84 lock operation must be a string or array');
            }

            $normalized[] = [
                'level' => strtolower(self::sourceString($operation['level'] ?? 'shared', 'lock level')),
                'connection' => self::sourceName($operation['connection'] ?? null),
                'shared_slot' => self::nonNegativeInt($operation['shared_slot'] ?? 0, 'shared_slot'),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string, array<string, mixed>> $sources
     * @return array<string, array<string, string>>
     */
    private static function allHolders(array $sources): array
    {
        $holders = [];
        foreach ($sources as $name => $source) {
            $holders[$name] = $source['holders'];
        }

        return $holders;
    }

    private static function status(array $events): string
    {
        foreach (array_reverse($events) as $event) {
            if (($event['kind'] ?? null) === 'lock') {
                return (string) ($event['result']['status'] ?? 'planned');
            }
            if (($event['status'] ?? null) === 'blocked') {
                return 'blocked';
            }
        }

        return 'ready';
    }

    private static function sourceName(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException('SQLite VFS next84 source name must not be empty');
        }

        return trim($value);
    }

    private static function sourceString(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite VFS next84 {$label} must not be empty");
        }

        return $value;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite VFS next84 {$label} must be a non-negative integer");
        }

        return $value;
    }

    /**
     * @param list<string> ...$sets
     * @return list<string>
     */
    private static function dependencies(array ...$sets): array
    {
        return array_values(array_unique(array_merge(...$sets)));
    }
}
