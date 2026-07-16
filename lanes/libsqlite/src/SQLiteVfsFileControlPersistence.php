<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsFileControlPersistence
{
    /**
     * @var list<string>
     */
    private const PERSISTENT_KEYS = [
        'persist_wal',
        'chunk_size',
        'mmap_size',
        'powersafe_overwrite',
        'size_limit',
        'reserve_bytes',
        'data_version',
    ];

    public function __construct(private readonly string $rootDirectory)
    {
        if ($rootDirectory === '') {
            throw new \InvalidArgumentException('SQLite VFS file-control persistence requires a root directory');
        }
    }

    /**
     * @param array<string|int, mixed> $controls
     * @return array{status:string,path:string,connection:string,current:array<string, mixed>,lock:array<string, mixed>,file_control:array<string, mixed>,persisted:array<string, mixed>,release:array<string, mixed>,next:array<string, mixed>,sidecar:string,dependencies:list<string>}
     */
    public function persistentFileControlApply(
        string $filename,
        bool $fileExists,
        bool $directoryWritable,
        array $controls,
        string $connection = 'app-import',
        int $sectorSize = 512,
        array $deviceFlags = ['powersafe_overwrite'],
        string $syncMode = 'normal',
        bool $persistWal = false,
        ?int $chunkSize = null,
        ?int $mmapSize = null
    ): array {
        if ($controls === []) {
            throw new \InvalidArgumentException('SQLite VFS file-control persistence requires controls');
        }

        $capability = SQLiteVfsCapabilityPlan::forFilename(
            $filename,
            $fileExists,
            $directoryWritable,
            $sectorSize,
            $deviceFlags,
            $syncMode,
            $persistWal,
            $chunkSize,
            $mmapSize
        );
        if (($capability['open']['can_open'] ?? false) !== true) {
            throw new \RuntimeException('SQLite VFS file-control persistence requires an openable current handle');
        }

        $path = (string) $capability['path'];
        $storedBefore = $this->readPersisted($path);
        $currentCapability = self::withPersistedControls($capability, $storedBefore);
        $current = SQLiteVfsFileControlState::fromCapabilityPlan($currentCapability)->snapshot();

        $locks = new SQLiteVfsLockState();
        $lock = $locks->acquire(SQLiteLockByteRangePlan::forOpenPlan($currentCapability['open'], 'reserved', $connection));
        if ($lock['status'] !== 'acquired') {
            return [
                'status' => 'blocked',
                'path' => $path,
                'connection' => $connection,
                'current' => $current,
                'lock' => $lock,
                'file_control' => [
                    'status' => 'blocked',
                    'applied' => 0,
                    'changed' => 0,
                    'results' => [],
                    'controls' => $current['controls'],
                    'dependencies' => $current['dependencies'],
                ],
                'persisted' => $storedBefore,
                'release' => [
                    'status' => 'skipped',
                    'applied' => false,
                    'path' => $path,
                    'connection' => $connection,
                    'requested' => 'none',
                    'held' => null,
                    'holders' => [],
                    'blocking' => [],
                    'ranges' => [],
                    'dependencies' => ['sqlite-lock-byte-range', 'vfs-file-control-persistence-persistent-file-control-apply'],
                    'reason' => 'current_handle_lock_not_acquired',
                ],
                'next' => $current,
                'sidecar' => $this->sidecarPath($path),
                'dependencies' => self::dependencies($current['dependencies'], $lock['dependencies'], ['vfs-file-control-persistence-persistent-file-control-apply']),
            ];
        }

        $state = SQLiteVfsFileControlState::fromCapabilityPlan($currentCapability);
        $batch = $state->applyMany($controls);
        $persisted = $this->writePersisted($path, $batch['controls']);
        $release = $locks->release($path, $connection);
        $nextCapability = self::withPersistedControls($capability, $persisted);
        $next = SQLiteVfsFileControlState::fromCapabilityPlan($nextCapability)->snapshot();

        return [
            'status' => 'persisted',
            'path' => $path,
            'connection' => $connection,
            'current' => $current,
            'lock' => $lock,
            'file_control' => $batch,
            'persisted' => $persisted,
            'release' => $release,
            'next' => $next,
            'sidecar' => $this->sidecarPath($path),
            'dependencies' => self::dependencies($batch['dependencies'], $lock['dependencies'], $release['dependencies'], ['vfs-file-control-persistence-persistent-file-control-apply']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function readPersisted(string $path): array
    {
        $sidecar = $this->sidecarPath($path);
        if (!is_file($sidecar)) {
            return [];
        }

        $json = file_get_contents($sidecar);
        if (!is_string($json)) {
            throw new \RuntimeException("SQLite VFS file-control sidecar is unreadable: {$sidecar}");
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException("SQLite VFS file-control sidecar is malformed: {$sidecar}");
        }

        return self::persistentSubset($decoded);
    }

    /**
     * @param array<string, mixed> $controls
     * @return array<string, mixed>
     */
    public function writePersisted(string $path, array $controls): array
    {
        $subset = self::persistentSubset($controls);
        $sidecar = $this->sidecarPath($path);
        $directory = dirname($sidecar);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("SQLite VFS could not create file-control sidecar directory: {$directory}");
        }

        $json = json_encode($subset, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || file_put_contents($sidecar, $json . "\n") === false) {
            throw new \RuntimeException("SQLite VFS could not write file-control sidecar: {$sidecar}");
        }

        return $subset;
    }

    public function sidecarPath(string $path): string
    {
        $path = trim($path);
        if ($path === '' || str_contains($path, "\0")) {
            throw new \InvalidArgumentException('SQLite VFS file-control sidecar requires a database path');
        }

        return rtrim($this->rootDirectory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . '.sqlite-vfs-file-control'
            . DIRECTORY_SEPARATOR
            . hash('sha256', $path)
            . '.json';
    }

    /**
     * @param array<string, mixed> $capability
     * @param array<string, mixed> $persisted
     * @return array<string, mixed>
     */
    private static function withPersistedControls(array $capability, array $persisted): array
    {
        $capability['file_controls'] = array_merge($capability['file_controls'], $persisted);

        return $capability;
    }

    /**
     * @param array<string, mixed> $controls
     * @return array<string, mixed>
     */
    private static function persistentSubset(array $controls): array
    {
        $subset = [];
        foreach (self::PERSISTENT_KEYS as $key) {
            if (array_key_exists($key, $controls)) {
                $subset[$key] = $controls[$key];
            }
        }

        return $subset;
    }

    /**
     * @param list<string> ...$dependencyLists
     * @return list<string>
     */
    private static function dependencies(array ...$dependencyLists): array
    {
        $merged = [];
        foreach ($dependencyLists as $dependencies) {
            foreach ($dependencies as $dependency) {
                if (is_string($dependency)) {
                    $merged[] = $dependency;
                }
            }
        }

        return array_values(array_unique($merged));
    }
}
