<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsFileHandle
{
    public function __construct(
        private readonly string $rootDirectory,
        private readonly string $path,
        private readonly bool $readOnly = false,
        private readonly bool $immutable = false,
    ) {
        if ($rootDirectory === '') {
            throw new \InvalidArgumentException('SQLite VFS file handle requires a root directory');
        }
        if ($path === '') {
            throw new \InvalidArgumentException('SQLite VFS file handle requires a path');
        }
    }

    /**
     * @return array{status:string,path:string,local_path:string,exists:bool,size:int,read_only:bool,immutable:bool,dependencies:list<string>}
     */
    public function stat(): array
    {
        $localPath = $this->localPath();

        return [
            'status' => 'ready',
            'path' => $this->path,
            'local_path' => $localPath,
            'exists' => is_file($localPath),
            'size' => is_file($localPath) ? (filesize($localPath) ?: 0) : 0,
            'read_only' => $this->readOnly,
            'immutable' => $this->immutable,
            'dependencies' => ['vfs-file-handle-primitive', 'vfs-xfilesize'],
        ];
    }

    /**
     * @return array{status:string,path:string,local_path:string,offset:int,requested:int,bytes_read:int,short_read:int,data:string,zero_filled_data:string,dependencies:list<string>}
     */
    public function readAt(int $offset, int $length): array
    {
        $this->assertNonNegative($offset, 'SQLite VFS read offset');
        $this->assertNonNegative($length, 'SQLite VFS read length');

        $localPath = $this->localPath();
        if (!is_file($localPath)) {
            throw new \RuntimeException("SQLite VFS read target does not exist: {$this->path}");
        }

        $handle = @fopen($localPath, 'rb');
        if (!is_resource($handle)) {
            throw new \RuntimeException("SQLite VFS read target is not readable: {$this->path}");
        }
        if (fseek($handle, $offset) !== 0) {
            fclose($handle);
            throw new \RuntimeException("SQLite VFS could not seek to offset {$offset}: {$this->path}");
        }
        $data = $length === 0 ? '' : fread($handle, $length);
        fclose($handle);
        if (!is_string($data)) {
            throw new \RuntimeException("SQLite VFS read failed: {$this->path}");
        }

        $bytesRead = strlen($data);
        $short = max(0, $length - $bytesRead);

        return [
            'status' => $short > 0 ? 'short_read' : 'ok',
            'path' => $this->path,
            'local_path' => $localPath,
            'offset' => $offset,
            'requested' => $length,
            'bytes_read' => $bytesRead,
            'short_read' => $short,
            'data' => $data,
            'zero_filled_data' => $data . str_repeat("\0", $short),
            'dependencies' => ['vfs-file-handle-primitive', 'vfs-xread'],
        ];
    }

    /**
     * @return array{status:string,path:string,local_path:string,offset:int,bytes_written:int,size:int,dependencies:list<string>}
     */
    public function writeAt(int $offset, string $data): array
    {
        $this->assertWritable('SQLite VFS write requires a writable handle');
        $this->assertNonNegative($offset, 'SQLite VFS write offset');

        $localPath = $this->localPath();
        $directory = dirname($localPath);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("SQLite VFS could not create directory: {$directory}");
        }

        $handle = @fopen($localPath, 'c+b');
        if (!is_resource($handle)) {
            throw new \RuntimeException("SQLite VFS write target is not writable: {$this->path}");
        }
        if (fseek($handle, $offset) !== 0) {
            fclose($handle);
            throw new \RuntimeException("SQLite VFS could not seek to offset {$offset}: {$this->path}");
        }
        $written = fwrite($handle, $data);
        fflush($handle);
        fclose($handle);
        if ($written !== strlen($data)) {
            throw new \RuntimeException("SQLite VFS short write: {$this->path}");
        }

        return [
            'status' => 'ok',
            'path' => $this->path,
            'local_path' => $localPath,
            'offset' => $offset,
            'bytes_written' => strlen($data),
            'size' => is_file($localPath) ? (filesize($localPath) ?: 0) : 0,
            'dependencies' => ['vfs-file-handle-primitive', 'vfs-xwrite'],
        ];
    }

    /**
     * @return array{status:string,path:string,local_path:string,size:int,dependencies:list<string>}
     */
    public function truncateTo(int $size): array
    {
        $this->assertWritable('SQLite VFS truncate requires a writable handle');
        $this->assertNonNegative($size, 'SQLite VFS truncate size');

        $localPath = $this->localPath();
        $directory = dirname($localPath);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("SQLite VFS could not create directory: {$directory}");
        }

        $handle = @fopen($localPath, 'c+b');
        if (!is_resource($handle)) {
            throw new \RuntimeException("SQLite VFS truncate target is not writable: {$this->path}");
        }
        if (!ftruncate($handle, $size)) {
            fclose($handle);
            throw new \RuntimeException("SQLite VFS truncate failed: {$this->path}");
        }
        fflush($handle);
        fclose($handle);

        return [
            'status' => 'ok',
            'path' => $this->path,
            'local_path' => $localPath,
            'size' => $size,
            'dependencies' => ['vfs-file-handle-primitive', 'vfs-xtruncate'],
        ];
    }

    /**
     * @return array{status:string,path:string,local_path:string,deleted:bool,dependencies:list<string>}
     */
    public function delete(): array
    {
        $this->assertWritable('SQLite VFS delete requires a writable handle');

        $localPath = $this->localPath();
        $deleted = false;
        if (is_file($localPath)) {
            if (!unlink($localPath)) {
                throw new \RuntimeException("SQLite VFS could not delete file: {$this->path}");
            }
            $deleted = true;
        }

        return [
            'status' => 'ok',
            'path' => $this->path,
            'local_path' => $localPath,
            'deleted' => $deleted,
            'dependencies' => ['vfs-file-handle-primitive', 'vfs-xdelete'],
        ];
    }

    private function localPath(): string
    {
        if (str_contains($this->path, "\0")) {
            throw new \InvalidArgumentException('SQLite VFS path must not contain NUL bytes');
        }

        $root = rtrim($this->rootDirectory, DIRECTORY_SEPARATOR);
        $normalized = str_replace('\\', '/', $this->path);
        $relative = ltrim($normalized, '/');
        if ($relative === '' || str_contains($relative, '../') || str_starts_with($relative, '..')) {
            throw new \InvalidArgumentException("SQLite VFS path escapes handle root: {$this->path}");
        }

        return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    private function assertWritable(string $message): void
    {
        if ($this->readOnly || $this->immutable) {
            throw new \LogicException($message);
        }
    }

    private function assertNonNegative(int $value, string $label): void
    {
        if ($value < 0) {
            throw new \InvalidArgumentException("{$label} must be a non-negative integer");
        }
    }
}
