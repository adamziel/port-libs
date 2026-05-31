<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsShmLockMatrixPlan
{
    /**
     * @param list<array{connection:string,mode:string,action:string,offset:int,count:int}> $operations
     * @return array{script:string,scenario:string,events:list<array{connection:string,mode:string,action:string,offset:int,count:int,result:string,blocking:list<string>,slots:list<string>}>,final_slots:list<string>,busy_count:int,ok_count:int,dependencies:list<string>}
     */
    public static function run(string $scenario, array $operations, int $slotCount = 8, int $sharedLimit = 255): array
    {
        if ($scenario === '') {
            throw new \InvalidArgumentException('SQLite SHM lock matrix scenario is required');
        }
        if ($slotCount < 1 || $sharedLimit < 1) {
            throw new \InvalidArgumentException('SQLite SHM lock matrix limits must be positive');
        }

        $state = [];
        $events = [];
        $busy = 0;
        $ok = 0;

        foreach ($operations as $operation) {
            $connection = self::connection($operation['connection'] ?? null);
            $mode = self::mode($operation['mode'] ?? null);
            $action = self::action($operation['action'] ?? null);
            $offset = self::offset($operation['offset'] ?? null, $slotCount);
            $count = self::count($operation['count'] ?? null, $offset, $slotCount);
            $blocking = [];
            $result = 'SQLITE_OK';

            if ($action === 'lock') {
                $blocking = self::blocking($state, $connection, $mode, $offset, $count, $sharedLimit);
                if ($blocking !== []) {
                    $result = 'SQLITE_BUSY';
                    ++$busy;
                } else {
                    self::applyLock($state, $connection, $mode, $offset, $count);
                    ++$ok;
                }
            } else {
                self::applyUnlock($state, $connection, $offset, $count);
                ++$ok;
            }

            $events[] = [
                'connection' => $connection,
                'mode' => $mode,
                'action' => $action,
                'offset' => $offset,
                'count' => $count,
                'result' => $result,
                'blocking' => $blocking,
                'slots' => self::slots($state, $slotCount),
            ];
        }

        return [
            'script' => 'shmlock.test',
            'scenario' => $scenario,
            'events' => $events,
            'final_slots' => self::slots($state, $slotCount),
            'busy_count' => $busy,
            'ok_count' => $ok,
            'dependencies' => ['sqlite-upstream-shmlock-test', 'sqlite-vfs-shm-byte-range-locks', 'sqlite-wal-index-locking'],
        ];
    }

    /**
     * @return list<array{connection:string,mode:string,action:string,offset:int,count:int}>
     */
    public static function upstreamScriptedOperations(): array
    {
        return [
            ['connection' => 'db', 'mode' => 'shared', 'action' => 'lock', 'offset' => 7, 'count' => 1],
            ['connection' => 'db2', 'mode' => 'exclusive', 'action' => 'lock', 'offset' => 7, 'count' => 1],
            ['connection' => 'db', 'mode' => 'shared', 'action' => 'unlock', 'offset' => 7, 'count' => 1],
            ['connection' => 'db2', 'mode' => 'exclusive', 'action' => 'lock', 'offset' => 7, 'count' => 1],
            ['connection' => 'db', 'mode' => 'shared', 'action' => 'lock', 'offset' => 7, 'count' => 1],
            ['connection' => 'db', 'mode' => 'exclusive', 'action' => 'lock', 'offset' => 7, 'count' => 1],
            ['connection' => 'db2', 'mode' => 'exclusive', 'action' => 'unlock', 'offset' => 7, 'count' => 1],
            ['connection' => 'db', 'mode' => 'exclusive', 'action' => 'lock', 'offset' => 0, 'count' => 8],
            ['connection' => 'db', 'mode' => 'exclusive', 'action' => 'unlock', 'offset' => 0, 'count' => 8],
            ['connection' => 'db2', 'mode' => 'exclusive', 'action' => 'lock', 'offset' => 0, 'count' => 8],
            ['connection' => 'db2', 'mode' => 'exclusive', 'action' => 'unlock', 'offset' => 0, 'count' => 8],
            ['connection' => 'db', 'mode' => 'shared', 'action' => 'lock', 'offset' => 0, 'count' => 1],
            ['connection' => 'db2', 'mode' => 'shared', 'action' => 'lock', 'offset' => 0, 'count' => 1],
            ['connection' => 'db3', 'mode' => 'shared', 'action' => 'lock', 'offset' => 0, 'count' => 1],
            ['connection' => 'db3', 'mode' => 'shared', 'action' => 'unlock', 'offset' => 0, 'count' => 1],
            ['connection' => 'db3', 'mode' => 'exclusive', 'action' => 'lock', 'offset' => 0, 'count' => 1],
            ['connection' => 'db2', 'mode' => 'shared', 'action' => 'unlock', 'offset' => 0, 'count' => 1],
            ['connection' => 'db3', 'mode' => 'exclusive', 'action' => 'lock', 'offset' => 0, 'count' => 1],
            ['connection' => 'db', 'mode' => 'shared', 'action' => 'unlock', 'offset' => 0, 'count' => 1],
            ['connection' => 'db3', 'mode' => 'exclusive', 'action' => 'lock', 'offset' => 0, 'count' => 1],
            ['connection' => 'db3', 'mode' => 'exclusive', 'action' => 'unlock', 'offset' => 0, 'count' => 1],
            ['connection' => 'db', 'mode' => 'shared', 'action' => 'lock', 'offset' => 3, 'count' => 1],
            ['connection' => 'db2', 'mode' => 'exclusive', 'action' => 'lock', 'offset' => 2, 'count' => 2],
            ['connection' => 'db', 'mode' => 'shared', 'action' => 'lock', 'offset' => 2, 'count' => 1],
            ['connection' => 'db2', 'mode' => 'exclusive', 'action' => 'lock', 'offset' => 0, 'count' => 5],
            ['connection' => 'db2', 'mode' => 'exclusive', 'action' => 'lock', 'offset' => 0, 'count' => 4],
            ['connection' => 'db2', 'mode' => 'exclusive', 'action' => 'lock', 'offset' => 0, 'count' => 3],
            ['connection' => 'db', 'mode' => 'shared', 'action' => 'unlock', 'offset' => 3, 'count' => 1],
            ['connection' => 'db2', 'mode' => 'exclusive', 'action' => 'lock', 'offset' => 2, 'count' => 2],
            ['connection' => 'db', 'mode' => 'shared', 'action' => 'unlock', 'offset' => 2, 'count' => 1],
            ['connection' => 'db2', 'mode' => 'exclusive', 'action' => 'lock', 'offset' => 2, 'count' => 2],
            ['connection' => 'db2', 'mode' => 'exclusive', 'action' => 'unlock', 'offset' => 2, 'count' => 2],
        ];
    }

    /**
     * @return list<array{connection:string,mode:string,action:string,offset:int,count:int}>
     */
    public static function deterministicRandomOperations(int $steps, int $seed = 1): array
    {
        if ($steps < 1) {
            throw new \InvalidArgumentException('SQLite SHM lock random operation count must be positive');
        }

        $rng = $seed;
        $held = ['db0' => array_fill(0, 8, 'none'), 'db1' => array_fill(0, 8, 'none')];
        $operations = [];

        for ($i = 0; $i < $steps; $i++) {
            $connection = ($i % 2) === 0 ? 'db0' : 'db1';
            $rng = self::nextRandom($rng);
            $slot = $rng % 8;
            $rng = self::nextRandom($rng);
            $unlock = ($rng % 3) === 0;

            if ($unlock && $held[$connection][$slot] !== 'none') {
                $operations[] = ['connection' => $connection, 'mode' => $held[$connection][$slot], 'action' => 'unlock', 'offset' => $slot, 'count' => 1];
                $held[$connection][$slot] = 'none';
                continue;
            }

            if ($held[$connection][$slot] !== 'none') {
                continue;
            }

            $rng = self::nextRandom($rng);
            $mode = ($rng % 2) === 0 ? 'shared' : 'exclusive';
            $count = 1;
            if ($mode === 'exclusive') {
                $limit = 1;
                while (($slot + $limit) < 8 && $held[$connection][$slot + $limit] === 'none') {
                    ++$limit;
                }
                $rng = self::nextRandom($rng);
                $count = 1 + ($rng % $limit);
            }
            $operations[] = ['connection' => $connection, 'mode' => $mode, 'action' => 'lock', 'offset' => $slot, 'count' => $count];
            for ($j = $slot; $j < $slot + $count; $j++) {
                $held[$connection][$j] = $mode;
            }
        }

        return $operations;
    }

    private static function nextRandom(int $value): int
    {
        return (int) (($value * 1103515245 + 12345) & 0x7fffffff);
    }

    /**
     * @param array<string,array<int,array{mode:string,connections:list<string>}>> $state
     * @return list<string>
     */
    private static function blocking(array $state, string $connection, string $mode, int $offset, int $count, int $sharedLimit): array
    {
        $blocking = [];
        for ($slot = $offset; $slot < $offset + $count; $slot++) {
            $entry = $state[$slot] ?? ['mode' => 'none', 'connections' => []];
            if ($entry['mode'] === 'none') {
                continue;
            }
            $others = array_values(array_filter($entry['connections'], static fn (string $holder): bool => $holder !== $connection));
            if ($others === []) {
                continue;
            }
            if ($mode === 'exclusive' || $entry['mode'] === 'exclusive') {
                foreach ($others as $holder) {
                    $blocking[] = $holder . ':' . $slot . ':' . $entry['mode'];
                }
                continue;
            }
            if (count($entry['connections']) >= $sharedLimit && !in_array($connection, $entry['connections'], true)) {
                $blocking[] = 'shared-limit:' . $slot;
            }
        }

        return $blocking;
    }

    /**
     * @param array<int,array{mode:string,connections:list<string>}> $state
     */
    private static function applyLock(array &$state, string $connection, string $mode, int $offset, int $count): void
    {
        for ($slot = $offset; $slot < $offset + $count; $slot++) {
            if ($mode === 'exclusive') {
                $state[$slot] = ['mode' => 'exclusive', 'connections' => [$connection]];
                continue;
            }
            $entry = $state[$slot] ?? ['mode' => 'shared', 'connections' => []];
            $entry['mode'] = 'shared';
            if (!in_array($connection, $entry['connections'], true)) {
                $entry['connections'][] = $connection;
                sort($entry['connections'], SORT_STRING);
            }
            $state[$slot] = $entry;
        }
    }

    /**
     * @param array<int,array{mode:string,connections:list<string>}> $state
     */
    private static function applyUnlock(array &$state, string $connection, int $offset, int $count): void
    {
        for ($slot = $offset; $slot < $offset + $count; $slot++) {
            if (!isset($state[$slot])) {
                continue;
            }
            $state[$slot]['connections'] = array_values(array_filter(
                $state[$slot]['connections'],
                static fn (string $holder): bool => $holder !== $connection
            ));
            if ($state[$slot]['connections'] === []) {
                unset($state[$slot]);
            } elseif ($state[$slot]['mode'] === 'exclusive') {
                $state[$slot]['mode'] = 'shared';
            }
        }
    }

    /**
     * @param array<int,array{mode:string,connections:list<string>}> $state
     * @return list<string>
     */
    private static function slots(array $state, int $slotCount): array
    {
        $slots = [];
        for ($slot = 0; $slot < $slotCount; $slot++) {
            if (!isset($state[$slot])) {
                $slots[] = 'none';
                continue;
            }
            $slots[] = $state[$slot]['mode'] . ':' . implode(',', $state[$slot]['connections']);
        }

        return $slots;
    }

    private static function connection(mixed $connection): string
    {
        if (!is_string($connection) || !preg_match('/\A[a-z][a-z0-9_-]*\z/', $connection)) {
            throw new \InvalidArgumentException('SQLite SHM lock connection name is invalid');
        }

        return $connection;
    }

    private static function mode(mixed $mode): string
    {
        if ($mode !== 'shared' && $mode !== 'exclusive') {
            throw new \InvalidArgumentException('SQLite SHM lock mode must be shared or exclusive');
        }

        return $mode;
    }

    private static function action(mixed $action): string
    {
        if ($action !== 'lock' && $action !== 'unlock') {
            throw new \InvalidArgumentException('SQLite SHM lock action must be lock or unlock');
        }

        return $action;
    }

    private static function offset(mixed $offset, int $slotCount): int
    {
        if (!is_int($offset) || $offset < 0 || $offset >= $slotCount) {
            throw new \InvalidArgumentException('SQLite SHM lock offset is out of range');
        }

        return $offset;
    }

    private static function count(mixed $count, int $offset, int $slotCount): int
    {
        if (!is_int($count) || $count < 1 || ($offset + $count) > $slotCount) {
            throw new \InvalidArgumentException('SQLite SHM lock count is out of range');
        }

        return $count;
    }
}
