<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsFileLock
{
    /**
     * @var array<string, resource>
     */
    private array $handles = [];

    /**
     * @var array<string, array<string, string>>
     */
    private array $holdersByPath = [];

    public function __construct(private readonly string $rootDirectory)
    {
        if ($rootDirectory === '') {
            throw new \InvalidArgumentException('SQLite VFS file lock requires a root directory');
        }
    }

    public function __destruct()
    {
        foreach (array_keys($this->handles) as $key) {
            $this->releaseKey($key);
        }
    }

    /**
     * @param array{level:string,can_lock:bool,nolock:bool,path:string,connection:string|null,ranges:list<array{name:string,offset:int,length:int,mode:string>>,dependencies:list<string>,reason:string|null} $plan
     * @return array{status:string,applied:bool,path:string,connection:string|null,requested:string,held:string|null,holders:array<string, string>,blocking:list<array{connection:string,level:string}>,lock_file:string|null,local_lock_file:string|null,lock_type:string|null,ranges:list<array{name:string,offset:int,length:int,mode:string>>,dependencies:list<string>,reason:string|null}
     */
    public function acquire(array $plan): array
    {
        $path = self::path((string) ($plan['path'] ?? ''));
        $connection = self::connection($plan['connection'] ?? null);
        $requested = self::level((string) ($plan['level'] ?? ''));
        if ($requested === 'none') {
            return $this->release($path, $connection);
        }

        $holders = $this->holdersByPath[$path] ?? [];
        $held = $connection === null ? null : ($holders[$connection] ?? null);
        $dependencies = self::dependencies($plan['dependencies'] ?? []);

        if ($connection === null) {
            throw new \InvalidArgumentException('SQLite VFS file lock requires a connection id');
        }

        if (!(bool) ($plan['can_lock'] ?? false)) {
            return $this->result('blocked', false, $path, $connection, $requested, $held, $holders, [], null, null, null, $plan, $dependencies, (string) ($plan['reason'] ?? 'vfs_lock_plan_cannot_lock'));
        }

        if ((bool) ($plan['nolock'] ?? false)) {
            return $this->result('blocked', false, $path, $connection, $requested, $held, $holders, [], null, null, null, $plan, $dependencies, 'nolock VFS disables process-backed file locking');
        }

        $blocking = self::blockingHolders($holders, $connection, $requested);
        if ($blocking !== []) {
            return $this->result('blocked', false, $path, $connection, $requested, $held, $holders, $blocking, null, null, null, $plan, $dependencies, self::blockingReason($requested));
        }

        $lockFile = $path . '-lock';
        $localLockFile = $this->localPath($lockFile);
        $key = self::key($path, $connection);
        $lockType = $requested === 'exclusive' ? 'exclusive' : $requested;
        $operation = $lockType === 'exclusive' ? LOCK_EX : LOCK_SH;

        $this->releaseKey($key);
        $handle = $this->openLockFile($localLockFile);
        if (!flock($handle, $operation | LOCK_NB)) {
            fclose($handle);

            return $this->result('blocked', false, $path, $connection, $requested, $held, $holders, [], $lockFile, $localLockFile, $lockType, $plan, $dependencies, 'process_file_lock_busy');
        }

        $this->handles[$key] = $handle;
        $holders[$connection] = self::stronger($held, $requested);
        $this->holdersByPath[$path] = $holders;

        return $this->result('acquired', true, $path, $connection, $requested, $holders[$connection], $holders, [], $lockFile, $localLockFile, $lockType, $plan, $dependencies, null);
    }

    /**
     * @return array{status:string,applied:bool,path:string,connection:string|null,requested:string,held:string|null,holders:array<string, string>,blocking:list<array{connection:string,level:string}>,lock_file:string|null,local_lock_file:string|null,lock_type:string|null,ranges:list<array{name:string,offset:int,length:int,mode:string>>,dependencies:list<string>,reason:string|null}
     */
    public function release(string $path, ?string $connection = null): array
    {
        $path = self::path($path);
        $connection = $connection === null ? null : self::connection($connection);
        $holders = $this->holdersByPath[$path] ?? [];
        $held = $connection === null ? null : ($holders[$connection] ?? null);

        if ($connection === null) {
            foreach (array_keys($holders) as $holder) {
                $this->releaseKey(self::key($path, $holder));
            }
            unset($this->holdersByPath[$path]);
            $holders = [];
        } else {
            $this->releaseKey(self::key($path, $connection));
            unset($holders[$connection]);
            if ($holders === []) {
                unset($this->holdersByPath[$path]);
            } else {
                $this->holdersByPath[$path] = $holders;
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
            'lock_file' => $path . '-lock',
            'local_lock_file' => $this->localPath($path . '-lock'),
            'lock_type' => null,
            'ranges' => [],
            'dependencies' => ['sqlite-lock-byte-range', 'vfs-process-file-lock'],
            'reason' => null,
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function snapshot(): array
    {
        return $this->holdersByPath;
    }

    private function localPath(string $path): string
    {
        if (str_contains($path, "\0")) {
            throw new \InvalidArgumentException('SQLite VFS file lock path must not contain NUL bytes');
        }

        $normalized = str_replace('\\', '/', $path);
        $relative = ltrim($normalized, '/');
        if ($relative === '' || str_contains($relative, '../') || str_starts_with($relative, '..')) {
            throw new \InvalidArgumentException("SQLite VFS file lock path escapes root: {$path}");
        }

        return rtrim($this->rootDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    /**
     * @return resource
     */
    private function openLockFile(string $localLockFile)
    {
        $directory = dirname($localLockFile);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("SQLite VFS could not create lock directory: {$directory}");
        }

        $handle = @fopen($localLockFile, 'c+b');
        if (!is_resource($handle)) {
            throw new \RuntimeException("SQLite VFS could not open lock file: {$localLockFile}");
        }

        return $handle;
    }

    private function releaseKey(string $key): void
    {
        if (!isset($this->handles[$key])) {
            return;
        }

        flock($this->handles[$key], LOCK_UN);
        fclose($this->handles[$key]);
        unset($this->handles[$key]);
    }

    private static function key(string $path, string $connection): string
    {
        return $path . "\0" . $connection;
    }

    private static function path(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            throw new \InvalidArgumentException('SQLite VFS file lock requires a database path');
        }
        if (str_contains($path, "\0")) {
            throw new \InvalidArgumentException('SQLite VFS file lock path must not contain NUL bytes');
        }

        return $path;
    }

    private static function connection(mixed $connection): string
    {
        if (!is_string($connection)) {
            throw new \InvalidArgumentException('SQLite VFS file lock requires a connection id');
        }

        $connection = trim($connection);
        if ($connection === '') {
            throw new \InvalidArgumentException('SQLite VFS file lock requires a connection id');
        }

        return $connection;
    }

    private static function level(string $level): string
    {
        $level = strtolower(trim($level));
        if (!in_array($level, ['none', 'shared', 'reserved', 'pending', 'exclusive'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite VFS file lock level: {$level}");
        }

        return $level;
    }

    /**
     * @param array<string, string> $holders
     * @return list<array{connection:string,level:string}>
     */
    private static function blockingHolders(array $holders, string $connection, string $requested): array
    {
        $blocking = [];
        foreach ($holders as $holder => $level) {
            if ($holder !== $connection && self::conflicts($requested, $level)) {
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
        if ($requested === 'reserved' || $requested === 'pending') {
            return in_array($held, ['reserved', 'pending', 'exclusive'], true);
        }
        if ($requested === 'exclusive') {
            return $held !== 'none';
        }

        return false;
    }

    private static function blockingReason(string $requested): string
    {
        return $requested === 'shared'
            ? 'pending_or_exclusive_process_lock_blocks_new_reader'
            : ($requested === 'exclusive' ? 'exclusive_process_lock_waits_for_all_other_holders' : 'writer_process_lock_conflicts_with_existing_writer');
    }

    private static function stronger(?string $current, string $requested): string
    {
        $rank = ['none' => 0, 'shared' => 1, 'reserved' => 2, 'pending' => 3, 'exclusive' => 4];
        if ($current === null) {
            return $requested;
        }

        return $rank[$requested] > $rank[$current] ? $requested : $current;
    }

    /**
     * @param mixed $dependencies
     * @return list<string>
     */
    private static function dependencies(mixed $dependencies): array
    {
        if (!is_array($dependencies)) {
            return ['vfs-process-file-lock'];
        }

        return array_values(array_unique(array_merge(array_map('strval', $dependencies), ['vfs-process-file-lock'])));
    }

    /**
     * @param array<string, mixed> $plan
     * @param list<string> $dependencies
     * @param list<array{connection:string,level:string}> $blocking
     * @return array{status:string,applied:bool,path:string,connection:string|null,requested:string,held:string|null,holders:array<string, string>,blocking:list<array{connection:string,level:string}>,lock_file:string|null,local_lock_file:string|null,lock_type:string|null,ranges:list<array{name:string,offset:int,length:int,mode:string>>,dependencies:list<string>,reason:string|null}
     */
    private function result(
        string $status,
        bool $applied,
        string $path,
        ?string $connection,
        string $requested,
        ?string $held,
        array $holders,
        array $blocking,
        ?string $lockFile,
        ?string $localLockFile,
        ?string $lockType,
        array $plan,
        array $dependencies,
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
            'lock_file' => $lockFile,
            'local_lock_file' => $localLockFile,
            'lock_type' => $lockType,
            'ranges' => is_array($plan['ranges'] ?? null) ? $plan['ranges'] : [],
            'dependencies' => $dependencies,
            'reason' => $reason,
        ];
    }
}
