<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteLockByteRangePlan
{
    public const PENDING_BYTE = 0x40000000;
    public const RESERVED_BYTE = self::PENDING_BYTE + 1;
    public const SHARED_FIRST = self::PENDING_BYTE + 2;
    public const SHARED_SIZE = 510;

    /**
     * @return array{level:string,can_lock:bool,nolock:bool,path:string,connection:string|null,ranges:list<array{name:string,offset:int,length:int,mode:string>>,dependencies:list<string>,reason:string|null}
     */
    public static function forLevel(
        string $path,
        string $level,
        bool $nolock = false,
        ?string $connection = null,
        int $sharedSlot = 0
    ): array {
        $path = trim($path);
        if ($path === '') {
            throw new \InvalidArgumentException('SQLite lock byte-range plan requires a database path');
        }

        $level = self::level($level);
        $connection = $connection === null ? null : self::connection($connection);
        if ($level !== 'none' && $connection === null) {
            throw new \InvalidArgumentException('SQLite lock byte-range plan requires a connection id');
        }

        if ($nolock) {
            return [
                'level' => $level,
                'can_lock' => $level === 'none',
                'nolock' => true,
                'path' => $path,
                'connection' => $connection,
                'ranges' => [],
                'dependencies' => ['sqlite-lock-byte-range', 'nolock-open'],
                'reason' => $level === 'none' ? null : 'nolock VFS disables POSIX byte-range locking',
            ];
        }

        return [
            'level' => $level,
            'can_lock' => true,
            'nolock' => false,
            'path' => $path,
            'connection' => $connection,
            'ranges' => self::ranges($level, $sharedSlot),
            'dependencies' => ['sqlite-lock-byte-range', 'vfs-file-lock'],
            'reason' => null,
        ];
    }

    /**
     * @param array<string, mixed> $open
     * @return array{level:string,can_lock:bool,nolock:bool,path:string,connection:string|null,ranges:list<array{name:string,offset:int,length:int,mode:string>>,dependencies:list<string>,reason:string|null}
     */
    public static function forOpenPlan(array $open, string $level, string $connection, int $sharedSlot = 0): array
    {
        $path = isset($open['path']) ? (string) $open['path'] : '';
        $nolock = (bool) ($open['nolock'] ?? false);
        $plan = self::forLevel($path, $level, $nolock, $connection, $sharedSlot);
        $plan['dependencies'] = array_values(array_unique(array_merge(
            array_values(array_filter($open['dependencies'] ?? [], 'is_string')),
            $plan['dependencies']
        )));

        if (($open['can_open'] ?? true) === false) {
            $plan['can_lock'] = false;
            $plan['reason'] = 'open admission failed before byte-range locking';
        }

        return $plan;
    }

    /**
     * @return array{pending:int,reserved:int,shared_first:int,shared_size:int,shared_last:int}
     */
    public static function constants(): array
    {
        return [
            'pending' => self::PENDING_BYTE,
            'reserved' => self::RESERVED_BYTE,
            'shared_first' => self::SHARED_FIRST,
            'shared_size' => self::SHARED_SIZE,
            'shared_last' => self::SHARED_FIRST + self::SHARED_SIZE - 1,
        ];
    }

    /**
     * @return list<array{name:string,offset:int,length:int,mode:string}>
     */
    private static function ranges(string $level, int $sharedSlot): array
    {
        if ($sharedSlot < 0 || $sharedSlot >= self::SHARED_SIZE) {
            throw new \InvalidArgumentException('SQLite shared lock slot must be between 0 and 509');
        }

        return match ($level) {
            'none' => [],
            'shared' => [
                ['name' => 'shared', 'offset' => self::SHARED_FIRST + $sharedSlot, 'length' => 1, 'mode' => 'shared'],
            ],
            'reserved' => [
                ['name' => 'shared', 'offset' => self::SHARED_FIRST + $sharedSlot, 'length' => 1, 'mode' => 'shared'],
                ['name' => 'reserved', 'offset' => self::RESERVED_BYTE, 'length' => 1, 'mode' => 'exclusive'],
            ],
            'pending' => [
                ['name' => 'pending', 'offset' => self::PENDING_BYTE, 'length' => 1, 'mode' => 'exclusive'],
                ['name' => 'reserved', 'offset' => self::RESERVED_BYTE, 'length' => 1, 'mode' => 'exclusive'],
            ],
            'exclusive' => [
                ['name' => 'pending', 'offset' => self::PENDING_BYTE, 'length' => 1, 'mode' => 'exclusive'],
                ['name' => 'reserved', 'offset' => self::RESERVED_BYTE, 'length' => 1, 'mode' => 'exclusive'],
                ['name' => 'shared', 'offset' => self::SHARED_FIRST, 'length' => self::SHARED_SIZE, 'mode' => 'exclusive'],
            ],
        };
    }

    private static function level(string $level): string
    {
        $level = strtolower(trim($level));
        if (!in_array($level, ['none', 'shared', 'reserved', 'pending', 'exclusive'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite lock byte-range level: {$level}");
        }

        return $level;
    }

    private static function connection(string $connection): string
    {
        $connection = trim($connection);
        if ($connection === '') {
            throw new \InvalidArgumentException('SQLite lock byte-range connection id must not be empty');
        }

        return $connection;
    }
}
