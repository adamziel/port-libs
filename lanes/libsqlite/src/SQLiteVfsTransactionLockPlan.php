<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsTransactionLockPlan
{
    /**
     * @return array{status:string,phase:string,path:string,connection:string,begin:array<string, mixed>,locks:list<array<string, mixed>>,held:string|null,holders:array<string, string>,dependencies:list<string>,reason:string|null}
     */
    public static function begin(
        SQLiteVfsLockState $locks,
        string $path,
        string $connection,
        string $sql,
        ?SQLitePragmaLockingMode $lockingMode = null,
        ?string $schema = null,
        string $journalMode = 'delete',
        bool $readOnly = false
    ): array {
        $path = self::path($path);
        $connection = self::connection($connection);
        $begin = SQLiteTransactionBeginLockPlan::plan($sql, $lockingMode, $schema, $journalMode, $readOnly);
        $appliedLocks = [];
        $status = $begin['status'] === 'blocked' ? 'blocked' : 'begun';
        $reason = $begin['status'] === 'blocked' ? 'read-only handle cannot start a write transaction' : null;

        if ($begin['status'] === 'blocked') {
            return self::result('blocked', 'begin', $path, $connection, $begin, [], $locks->holders($path), $reason);
        }

        foreach ($begin['lock_sequence'] as $step) {
            $level = (string) $step['level'];
            if ($level === 'blocked') {
                $status = 'blocked';
                $reason = (string) $step['reason'];
                break;
            }
            if ($level === 'none') {
                continue;
            }

            $lock = $locks->acquire(SQLiteLockByteRangePlan::forLevel($path, $level, false, $connection));
            $appliedLocks[] = $lock + ['transaction_reason' => $step['reason'], 'transaction_timing' => $step['timing']];
            if ($lock['status'] !== 'acquired') {
                $status = 'blocked';
                $reason = $lock['reason'];
                break;
            }
        }

        return self::result($status, 'begin', $path, $connection, $begin, $appliedLocks, $locks->holders($path), $reason);
    }

    /**
     * @return array{status:string,phase:string,path:string,connection:string,begin:array<string, mixed>,locks:list<array<string, mixed>>,held:string|null,holders:array<string, string>,dependencies:list<string>,reason:string|null}
     */
    public static function firstRead(SQLiteVfsLockState $locks, string $path, string $connection, array $begin): array
    {
        $path = self::path($path);
        $connection = self::connection($connection);
        if (($begin['status'] ?? null) !== 'planned') {
            return self::result('blocked', 'first_read', $path, $connection, $begin, [], $locks->holders($path), 'begin transaction did not admit database access');
        }

        if (($begin['read_lock_deferred'] ?? false) !== true) {
            return self::result('already_locked', 'first_read', $path, $connection, $begin, [], $locks->holders($path), null);
        }

        $lock = $locks->acquire(SQLiteLockByteRangePlan::forLevel($path, 'shared', false, $connection));
        $status = $lock['status'] === 'acquired' ? 'read_lock_acquired' : 'blocked';

        return self::result($status, 'first_read', $path, $connection, $begin, [$lock], $locks->holders($path), $lock['reason']);
    }

    /**
     * @return array{status:string,phase:string,path:string,connection:string,begin:array<string, mixed>,locks:list<array<string, mixed>>,held:string|null,holders:array<string, string>,dependencies:list<string>,reason:string|null}
     */
    public static function promoteForCommit(SQLiteVfsLockState $locks, string $path, string $connection, array $begin): array
    {
        $path = self::path($path);
        $connection = self::connection($connection);
        if (($begin['status'] ?? null) !== 'planned') {
            return self::result('blocked', 'commit_promote', $path, $connection, $begin, [], $locks->holders($path), 'begin transaction did not admit commit');
        }

        $journalMode = strtolower((string) ($begin['journal_mode'] ?? 'delete'));
        $level = $journalMode === 'wal' ? 'reserved' : 'exclusive';
        $lock = $locks->acquire(SQLiteLockByteRangePlan::forLevel($path, $level, false, $connection));
        $status = $lock['status'] === 'acquired' ? 'commit_lock_acquired' : 'blocked';

        return self::result($status, 'commit_promote', $path, $connection, $begin, [$lock], $locks->holders($path), $lock['reason']);
    }

    /**
     * @return array{status:string,phase:string,path:string,connection:string,begin:array<string, mixed>,locks:list<array<string, mixed>>,held:string|null,holders:array<string, string>,dependencies:list<string>,reason:string|null}
     */
    public static function finish(SQLiteVfsLockState $locks, string $path, string $connection, array $begin, string $action = 'commit'): array
    {
        $path = self::path($path);
        $connection = self::connection($connection);
        $action = strtolower(trim($action));
        if (!in_array($action, ['commit', 'rollback'], true)) {
            throw new \InvalidArgumentException('SQLite VFS transaction finish action must be commit or rollback');
        }

        $release = $locks->release($path, $connection);

        return self::result($action === 'commit' ? 'committed' : 'rolled_back', $action, $path, $connection, $begin, [$release], $locks->holders($path), null);
    }

    private static function path(string $path): string
    {
        $path = trim($path);
        if ($path === '' || str_contains($path, "\0")) {
            throw new \InvalidArgumentException('SQLite VFS transaction locking requires a valid database path');
        }

        return $path;
    }

    private static function connection(string $connection): string
    {
        $connection = trim($connection);
        if ($connection === '') {
            throw new \InvalidArgumentException('SQLite VFS transaction locking requires a connection id');
        }

        return $connection;
    }

    /**
     * @param list<array<string, mixed>> $locks
     * @param array<string, string> $holders
     * @return array{status:string,phase:string,path:string,connection:string,begin:array<string, mixed>,locks:list<array<string, mixed>>,held:string|null,holders:array<string, string>,dependencies:list<string>,reason:string|null}
     */
    private static function result(
        string $status,
        string $phase,
        string $path,
        string $connection,
        array $begin,
        array $locks,
        array $holders,
        ?string $reason
    ): array {
        $dependencies = ['sqlite-vfs-transaction-lock-current'];
        foreach ($begin['dependencies'] ?? [] as $dependency) {
            $dependencies[] = (string) $dependency;
        }
        foreach ($locks as $lock) {
            foreach ($lock['dependencies'] ?? [] as $dependency) {
                $dependencies[] = (string) $dependency;
            }
        }

        return [
            'status' => $status,
            'phase' => $phase,
            'path' => $path,
            'connection' => $connection,
            'begin' => $begin,
            'locks' => $locks,
            'held' => $holders[$connection] ?? null,
            'holders' => $holders,
            'dependencies' => array_values(array_unique(array_filter($dependencies))),
            'reason' => $reason,
        ];
    }
}
