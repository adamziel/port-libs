<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsLockState
{
    /**
     * @var array<string, array<string, string>>
     */
    private array $locksByPath = [];

    private const RANK = [
        'none' => 0,
        'shared' => 1,
        'reserved' => 2,
        'pending' => 3,
        'exclusive' => 4,
    ];

    /**
     * @param array{level:string,can_lock:bool,nolock:bool,path:string,connection:string|null,ranges:list<array{name:string,offset:int,length:int,mode:string>>,dependencies:list<string>,reason:string|null} $plan
     * @return array{status:string,applied:bool,path:string,connection:string|null,requested:string,held:string|null,holders:array<string, string>,blocking:list<array{connection:string,level:string}>,ranges:list<array{name:string,offset:int,length:int,mode:string>>,dependencies:list<string>,reason:string|null}
     */
    public function acquire(array $plan): array
    {
        $path = self::planPath($plan);
        $connection = $plan['connection'] === null ? null : self::connection((string) $plan['connection']);
        $requested = self::level((string) $plan['level']);
        $holders = $this->locksByPath[$path] ?? [];
        $current = $connection === null ? null : ($holders[$connection] ?? null);

        if ($requested === 'none') {
            return $this->release($path, $connection);
        }

        if (!(bool) $plan['can_lock']) {
            return self::result('blocked', false, $path, $connection, $requested, $current, $holders, [], $plan, (string) $plan['reason']);
        }

        if ($connection === null) {
            throw new \InvalidArgumentException('SQLite VFS lock application requires a connection id');
        }

        $blocking = self::blockingHolders($holders, $connection, $requested);
        if ($blocking !== []) {
            return self::result('blocked', false, $path, $connection, $requested, $current, $holders, $blocking, $plan, self::blockingReason($requested, $blocking));
        }

        $holders[$connection] = self::stronger($current, $requested);
        $this->locksByPath[$path] = $holders;

        return self::result('acquired', true, $path, $connection, $requested, $holders[$connection], $holders, [], $plan, null);
    }

    /**
     * @return array{status:string,applied:bool,path:string,connection:string|null,requested:string,held:string|null,holders:array<string, string>,blocking:list<array{connection:string,level:string}>,ranges:list<array{name:string,offset:int,length:int,mode:string>>,dependencies:list<string>,reason:string|null}
     */
    public function release(string $path, ?string $connection = null): array
    {
        $path = self::path($path);
        $connection = $connection === null ? null : self::connection($connection);
        $holders = $this->locksByPath[$path] ?? [];
        $held = $connection === null ? null : ($holders[$connection] ?? null);

        if ($connection === null) {
            unset($this->locksByPath[$path]);
            $holders = [];
        } else {
            unset($holders[$connection]);
            if ($holders === []) {
                unset($this->locksByPath[$path]);
            } else {
                $this->locksByPath[$path] = $holders;
            }
        }

        return [
            'status' => 'released',
            'applied' => true,
            'path' => $path,
            'connection' => $connection,
            'requested' => 'none',
            'held' => $held,
            'holders' => $holders,
            'blocking' => [],
            'ranges' => [],
            'dependencies' => ['sqlite-lock-byte-range', 'vfs-lock-state-application'],
            'reason' => null,
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function snapshot(): array
    {
        return $this->locksByPath;
    }

    /**
     * @return array<string, string>
     */
    public function holders(string $path): array
    {
        return $this->locksByPath[self::path($path)] ?? [];
    }

    /**
     * @param array<string, string> $holders
     * @return list<array{connection:string,level:string}>
     */
    private static function blockingHolders(array $holders, string $connection, string $requested): array
    {
        $blocking = [];
        foreach ($holders as $holder => $level) {
            if ($holder === $connection) {
                continue;
            }
            if (self::conflicts($requested, $level)) {
                $blocking[] = ['connection' => $holder, 'level' => $level];
            }
        }

        return $blocking;
    }

    private static function conflicts(string $requested, string $held): bool
    {
        if ($requested === 'shared') {
            return in_array($held, ['pending', 'exclusive'], true);
        }
        if ($requested === 'reserved') {
            return in_array($held, ['reserved', 'pending', 'exclusive'], true);
        }
        if ($requested === 'pending') {
            return in_array($held, ['reserved', 'pending', 'exclusive'], true);
        }
        if ($requested === 'exclusive') {
            return $held !== 'none';
        }

        return false;
    }

    private static function blockingReason(string $requested, array $blocking): string
    {
        if ($requested === 'shared') {
            return 'pending_or_exclusive_lock_blocks_new_reader';
        }
        if ($requested === 'exclusive') {
            return 'exclusive_lock_waits_for_all_other_holders';
        }

        return 'writer_lock_conflicts_with_existing_writer';
    }

    private static function stronger(?string $current, string $requested): string
    {
        if ($current === null) {
            return $requested;
        }

        return self::RANK[$requested] > self::RANK[$current] ? $requested : $current;
    }

    private static function level(string $level): string
    {
        $level = strtolower(trim($level));
        if (!isset(self::RANK[$level])) {
            throw new \InvalidArgumentException("Unsupported SQLite VFS lock level: {$level}");
        }

        return $level;
    }

    private static function planPath(array $plan): string
    {
        return self::path(isset($plan['path']) ? (string) $plan['path'] : '');
    }

    private static function path(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            throw new \InvalidArgumentException('SQLite VFS lock state requires a database path');
        }
        if (str_contains($path, "\0")) {
            throw new \InvalidArgumentException('SQLite VFS lock path must not contain NUL bytes');
        }

        return $path;
    }

    private static function connection(string $connection): string
    {
        $connection = trim($connection);
        if ($connection === '') {
            throw new \InvalidArgumentException('SQLite VFS lock state requires a connection id');
        }

        return $connection;
    }

    /**
     * @param array<string, string> $holders
     * @param list<array{connection:string,level:string}> $blocking
     * @param array{ranges:list<array{name:string,offset:int,length:int,mode:string>>,dependencies:list<string>,reason:string|null} $plan
     * @return array{status:string,applied:bool,path:string,connection:string|null,requested:string,held:string|null,holders:array<string, string>,blocking:list<array{connection:string,level:string}>,ranges:list<array{name:string,offset:int,length:int,mode:string>>,dependencies:list<string>,reason:string|null}
     */
    private static function result(
        string $status,
        bool $applied,
        string $path,
        ?string $connection,
        string $requested,
        ?string $held,
        array $holders,
        array $blocking,
        array $plan,
        ?string $reason
    ): array {
        return [
            'status' => $status,
            'applied' => $applied,
            'path' => $path,
            'connection' => $connection,
            'requested' => $requested,
            'held' => $held,
            'holders' => $holders,
            'blocking' => $blocking,
            'ranges' => $plan['ranges'],
            'dependencies' => array_values(array_unique(array_merge($plan['dependencies'], ['vfs-lock-state-application']))),
            'reason' => $reason,
        ];
    }
}
