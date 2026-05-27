<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsFileWriter
{
    public function __construct(
        private readonly string $rootDirectory,
        private readonly bool $readOnly = false,
        private readonly bool $immutable = false,
    ) {
        if ($rootDirectory === '') {
            throw new \InvalidArgumentException('SQLite VFS file writer requires a root directory');
        }
    }

    /**
     * @return array{status:string,root:string,applied:int,bytes_written:int,bytes_truncated:int,durable_syncs:int,directory_syncs:int,operations:list<array<string, mixed>>,dependencies:list<string>}
     */
    public function applyWalCheckpoint(SQLiteWal $wal, string $databaseBytes, string $databasePath, string $mode = 'passive', ?int $readerEndFrame = null): array
    {
        $plan = SQLiteWalFileWritePlan::checkpoint($wal, $databaseBytes, $databasePath, $mode, $readerEndFrame, $this->readOnly, $this->immutable);
        $result = $wal->durableCheckpointResult($databaseBytes, $mode, $readerEndFrame);
        $payloads = [
            $plan['database_path'] => $result['database_bytes'],
            $plan['wal_path'] => $result['wal_bytes'],
        ];

        return $this->applyOperations($plan['operations'], $payloads, $plan['dependencies']);
    }

    /**
     * @param list<array<string, mixed>> $operations
     * @param array<string, string> $payloads
     * @param list<string> $dependencies
     * @return array{status:string,root:string,applied:int,bytes_written:int,bytes_truncated:int,durable_syncs:int,directory_syncs:int,operations:list<array<string, mixed>>,dependencies:list<string>}
     */
    public function applyOperations(array $operations, array $payloads = [], array $dependencies = []): array
    {
        if ($this->readOnly || $this->immutable) {
            throw new \LogicException('SQLite VFS file writer requires a writable handle');
        }

        $applied = [];
        $bytesWritten = 0;
        $bytesTruncated = 0;
        $durableSyncs = 0;
        $directorySyncs = 0;

        foreach ($operations as $index => $operation) {
            $op = isset($operation['op']) ? (string) $operation['op'] : '';
            $path = isset($operation['path']) ? (string) $operation['path'] : '';
            if ($path === '') {
                throw new \InvalidArgumentException('SQLite VFS operation requires a path');
            }

            $localPath = $this->localPath($path);
            if ($op === 'write') {
                $offset = $this->nonNegativeInt($operation['offset'] ?? 0, 'SQLite VFS write offset');
                if (!array_key_exists($path, $payloads)) {
                    throw new \InvalidArgumentException("SQLite VFS write payload is missing for {$path}");
                }
                $data = $payloads[$path];
                $expected = $this->nonNegativeInt($operation['bytes'] ?? strlen($data), 'SQLite VFS write byte count');
                if ($expected !== strlen($data)) {
                    throw new \InvalidArgumentException("SQLite VFS write payload length mismatch for {$path}");
                }
                $this->writeAt($localPath, $offset, $data);
                $bytesWritten += strlen($data);
                $applied[] = $this->applied($index, $operation, $localPath, strlen($data));
            } elseif ($op === 'truncate') {
                $size = $this->nonNegativeInt($operation['bytes'] ?? 0, 'SQLite VFS truncate size');
                $this->truncate($localPath, $size);
                $bytesTruncated += $size;
                $applied[] = $this->applied($index, $operation, $localPath, $size);
            } elseif ($op === 'sync') {
                if (!is_file($localPath)) {
                    throw new \RuntimeException("SQLite VFS sync target does not exist: {$path}");
                }
                $handle = @fopen($localPath, 'c+b');
                if (!is_resource($handle)) {
                    throw new \RuntimeException("SQLite VFS sync target is not writable: {$path}");
                }
                fflush($handle);
                fclose($handle);
                $durableSyncs++;
                $applied[] = $this->applied($index, $operation, $localPath, 0);
            } elseif ($op === 'sync_directory') {
                if (!is_dir($localPath)) {
                    throw new \RuntimeException("SQLite VFS directory sync target does not exist: {$path}");
                }
                $directorySyncs++;
                $applied[] = $this->applied($index, $operation, $localPath, 0);
            } else {
                throw new \InvalidArgumentException("Unsupported SQLite VFS operation: {$op}");
            }
        }

        return [
            'status' => 'applied',
            'root' => $this->rootDirectory,
            'applied' => count($applied),
            'bytes_written' => $bytesWritten,
            'bytes_truncated' => $bytesTruncated,
            'durable_syncs' => $durableSyncs,
            'directory_syncs' => $directorySyncs,
            'operations' => $applied,
            'dependencies' => array_values(array_unique(array_merge($dependencies, ['vfs-file-handle-write-application']))),
        ];
    }

    private function localPath(string $path): string
    {
        if (str_contains($path, "\0")) {
            throw new \InvalidArgumentException('SQLite VFS path must not contain NUL bytes');
        }

        $root = rtrim($this->rootDirectory, DIRECTORY_SEPARATOR);
        $normalized = str_replace('\\', '/', $path);
        $relative = ltrim($normalized, '/');
        if ($relative === '' || str_contains($relative, '../') || str_starts_with($relative, '..')) {
            throw new \InvalidArgumentException("SQLite VFS path escapes writer root: {$path}");
        }

        return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    private function writeAt(string $path, int $offset, string $data): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("SQLite VFS could not create directory: {$directory}");
        }

        $handle = @fopen($path, 'c+b');
        if (!is_resource($handle)) {
            throw new \RuntimeException("SQLite VFS could not open file for writing: {$path}");
        }
        if (fseek($handle, $offset) !== 0) {
            fclose($handle);
            throw new \RuntimeException("SQLite VFS could not seek to offset {$offset}: {$path}");
        }
        $written = fwrite($handle, $data);
        fflush($handle);
        fclose($handle);
        if ($written !== strlen($data)) {
            throw new \RuntimeException("SQLite VFS short write: {$path}");
        }
    }

    private function truncate(string $path, int $size): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("SQLite VFS could not create directory: {$directory}");
        }

        $handle = @fopen($path, 'c+b');
        if (!is_resource($handle)) {
            throw new \RuntimeException("SQLite VFS could not open file for truncation: {$path}");
        }
        if (!ftruncate($handle, $size)) {
            fclose($handle);
            throw new \RuntimeException("SQLite VFS truncate failed: {$path}");
        }
        fflush($handle);
        fclose($handle);
    }

    private function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("{$label} must be a non-negative integer");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $operation
     * @return array<string, mixed>
     */
    private function applied(int $index, array $operation, string $localPath, int $bytes): array
    {
        return [
            'index' => $index,
            'op' => (string) $operation['op'],
            'path' => (string) $operation['path'],
            'local_path' => $localPath,
            'bytes' => $bytes,
            'durable' => (bool) ($operation['durable'] ?? false),
            'reason' => isset($operation['reason']) ? (string) $operation['reason'] : null,
        ];
    }
}
