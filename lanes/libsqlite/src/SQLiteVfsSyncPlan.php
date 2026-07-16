<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsSyncPlan
{
    public const SQLITE_SYNC_NORMAL = 0x02;
    public const SQLITE_SYNC_FULL = 0x03;
    public const SQLITE_SYNC_DATAONLY = 0x10;

    /**
     * @return array{status:string,path:string,target:string,mode:string,flags:int,flag_names:list<string>,durable:bool,data_only:bool,directory:bool,allowed:bool,reason:string|null,dependencies:list<string>}
     */
    public static function forPath(
        string $path,
        string $target = 'database',
        string $mode = 'normal',
        bool $dataOnly = false,
        bool $directory = false,
        bool $readOnly = false,
        bool $immutable = false,
        bool $memory = false
    ): array {
        $path = self::path($path, $directory);
        $target = self::target($target);
        $mode = self::mode($mode);
        $flags = $mode === 'full' ? self::SQLITE_SYNC_FULL : self::SQLITE_SYNC_NORMAL;
        if ($dataOnly) {
            $flags |= self::SQLITE_SYNC_DATAONLY;
        }

        $reason = null;
        if ($memory) {
            $reason = 'memory_database';
        } elseif ($mode === 'off') {
            $reason = 'sync_off';
        } elseif ($readOnly || $immutable) {
            $reason = 'readonly_handle';
        }

        return [
            'status' => $reason === null ? 'planned' : 'skipped',
            'path' => $path,
            'target' => $target,
            'mode' => $mode,
            'flags' => $mode === 'off' ? 0 : $flags,
            'flag_names' => $mode === 'off' ? [] : self::flagNames($flags),
            'durable' => $reason === null,
            'data_only' => $mode !== 'off' && $dataOnly,
            'directory' => $directory,
            'allowed' => $reason === null,
            'reason' => $reason,
            'dependencies' => ['vfs-xsync-flags', 'vfs-file-handle-sync'],
        ];
    }

    /**
     * @return list<array{status:string,path:string,target:string,mode:string,flags:int,flag_names:list<string>,durable:bool,data_only:bool,directory:bool,allowed:bool,reason:string|null,dependencies:list<string>}>
     */
    public static function rollbackCommitSequence(
        string $databasePath,
        string $mode = 'full',
        bool $persistJournal = false,
        bool $powersafeOverwrite = false
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite VFS sync sequence requires a database path');
        }
        $journalPath = $databasePath . '-journal';
        $directory = dirname($databasePath);

        $sequence = [
            self::forPath($journalPath, 'rollback_journal', $mode, false, false),
            self::forPath($databasePath, 'database', $mode, true, false),
        ];

        if ($persistJournal) {
            $sequence[] = self::forPath($journalPath, 'rollback_journal_header', $mode, true, false);
        }
        if (!$powersafeOverwrite) {
            $sequence[] = self::forPath($directory, 'directory', 'normal', false, true);
        }

        return $sequence;
    }

    /**
     * @return list<string>
     */
    private static function flagNames(int $flags): array
    {
        $names = [($flags & self::SQLITE_SYNC_FULL) === self::SQLITE_SYNC_FULL ? 'full' : 'normal'];
        if (($flags & self::SQLITE_SYNC_DATAONLY) !== 0) {
            $names[] = 'dataonly';
        }

        return $names;
    }

    private static function mode(string $mode): string
    {
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['off', 'normal', 'full'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite VFS sync mode: {$mode}");
        }

        return $mode;
    }

    private static function target(string $target): string
    {
        $target = strtolower(trim(str_replace('-', '_', $target)));
        if (!in_array($target, ['database', 'wal', 'rollback_journal', 'rollback_journal_header', 'directory', 'temp'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite VFS sync target: {$target}");
        }

        return $target;
    }

    private static function path(string $path, bool $directory): string
    {
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '..')) {
            throw new \InvalidArgumentException('SQLite VFS sync path must be a safe absolute lane path');
        }
        if ($path[0] !== '/') {
            throw new \InvalidArgumentException('SQLite VFS sync path must be absolute');
        }
        if ($directory && preg_match('/\.[A-Za-z0-9]+$/', basename($path)) === 1) {
            throw new \InvalidArgumentException('SQLite VFS directory sync path must name a directory');
        }

        return $path;
    }
}
