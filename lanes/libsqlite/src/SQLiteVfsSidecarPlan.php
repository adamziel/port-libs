<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsSidecarPlan
{
    /**
     * @return array{status:string,path:string,directory:string,basename:string,wal_path:string,shm_path:string,journal_path:string,super_journal_glob:string,temp_directory:string,read_only:bool,immutable:bool,nolock:bool,wal_readable:bool,wal_writable:bool,shm_readable:bool,shm_writable:bool,journal_readable:bool,journal_writable:bool,uses_shared_memory:bool,requires_directory_write:bool,dependencies:list<string>,open:array<string, mixed>}
     */
    public static function forFilename(string $filename, bool $fileExists, bool $directoryWritable): array
    {
        $open = SQLiteOpenPlan::forFilename($filename, $fileExists, $directoryWritable);
        if ($open['memory']) {
            return self::result($open, ':memory:', '', ':memory:', false, false);
        }

        $path = (string) $open['path'];
        if ($path === '') {
            throw new \InvalidArgumentException('SQLite VFS sidecar planning requires a database path');
        }

        return self::result($open, $path, dirname($path), basename($path), $fileExists, $directoryWritable);
    }

    /**
     * @param array<string, mixed> $open
     * @return array{status:string,path:string,directory:string,basename:string,wal_path:string,shm_path:string,journal_path:string,super_journal_glob:string,temp_directory:string,read_only:bool,immutable:bool,nolock:bool,wal_readable:bool,wal_writable:bool,shm_readable:bool,shm_writable:bool,journal_readable:bool,journal_writable:bool,uses_shared_memory:bool,requires_directory_write:bool,dependencies:list<string>,open:array<string, mixed>}
     */
    private static function result(
        array $open,
        string $path,
        string $directory,
        string $basename,
        bool $fileExists,
        bool $directoryWritable
    ): array {
        $readOnly = (bool) $open['read_only'];
        $immutable = (bool) $open['immutable'];
        $nolock = (bool) $open['nolock'];
        $memory = (bool) $open['memory'];
        $walReadable = !$memory && $fileExists;
        $sidecarWritable = !$memory && !$readOnly && $directoryWritable;
        $usesSharedMemory = !$memory && !$immutable && !$nolock;

        return [
            'status' => $memory ? 'memory' : (string) $open['status'],
            'path' => $path,
            'directory' => $directory,
            'basename' => $basename,
            'wal_path' => $memory ? '' : $path . '-wal',
            'shm_path' => $memory ? '' : $path . '-shm',
            'journal_path' => $memory ? '' : $path . '-journal',
            'super_journal_glob' => $memory ? '' : $path . '-mj*',
            'temp_directory' => $memory ? '' : self::tempDirectory($directory, $directoryWritable),
            'read_only' => $readOnly,
            'immutable' => $immutable,
            'nolock' => $nolock,
            'wal_readable' => $walReadable,
            'wal_writable' => $sidecarWritable,
            'shm_readable' => $walReadable && $usesSharedMemory,
            'shm_writable' => $sidecarWritable && $usesSharedMemory,
            'journal_readable' => !$memory && $fileExists && !$immutable,
            'journal_writable' => $sidecarWritable,
            'uses_shared_memory' => $usesSharedMemory,
            'requires_directory_write' => !$memory && (!$readOnly || (bool) $open['can_create']),
            'dependencies' => self::dependencies($open),
            'open' => $open,
        ];
    }

    private static function tempDirectory(string $directory, bool $directoryWritable): string
    {
        if ($directoryWritable && $directory !== '') {
            return $directory;
        }

        return sys_get_temp_dir();
    }

    /**
     * @param array<string, mixed> $open
     * @return list<string>
     */
    private static function dependencies(array $open): array
    {
        $dependencies = $open['dependencies'];
        $dependencies[] = 'vfs-sidecar-paths';
        if (!$open['memory']) {
            $dependencies[] = 'wal-sidecar-open';
            $dependencies[] = 'rollback-journal-sidecar-open';
        }
        if (!$open['memory'] && !$open['immutable'] && !$open['nolock']) {
            $dependencies[] = 'shared-memory-sidecar-open';
        }

        return array_values(array_unique($dependencies));
    }
}
