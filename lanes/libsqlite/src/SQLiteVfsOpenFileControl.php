<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsOpenFileControl
{
    private readonly SQLiteVfsFileControlState $state;
    private readonly SQLiteVfsFileHandle $handle;
    private readonly array $capability;

    /**
     * @param array<string, mixed> $capability
     */
    public function __construct(
        private readonly string $rootDirectory,
        array $capability
    ) {
        if ($rootDirectory === '') {
            throw new \InvalidArgumentException('SQLite VFS open file-control requires a root directory');
        }
        if (!is_array($capability['open'] ?? null)) {
            throw new \InvalidArgumentException('SQLite VFS open file-control requires an open capability plan');
        }

        $open = $capability['open'];
        if (($open['can_open'] ?? false) !== true) {
            throw new \RuntimeException('SQLite VFS open file-control requires an openable file handle');
        }

        $path = (string) ($capability['path'] ?? '');
        if ($path === '') {
            throw new \InvalidArgumentException('SQLite VFS open file-control requires a path');
        }

        $this->capability = $capability;
        $this->state = SQLiteVfsFileControlState::fromCapabilityPlan($capability);
        $this->handle = new SQLiteVfsFileHandle(
            $rootDirectory,
            $path,
            (bool) ($capability['read_only'] ?? false),
            (bool) ($capability['immutable'] ?? false)
        );
    }

    public static function forFilename(
        string $rootDirectory,
        string $filename,
        bool $fileExists,
        bool $directoryWritable,
        int $sectorSize = 512,
        array $deviceFlags = ['powersafe_overwrite'],
        string $syncMode = 'normal',
        bool $persistWal = false,
        ?int $chunkSize = null,
        ?int $mmapSize = null
    ): self {
        return new self(
            $rootDirectory,
            SQLiteVfsCapabilityPlan::forFilename(
                $filename,
                $fileExists,
                $directoryWritable,
                $sectorSize,
                $deviceFlags,
                $syncMode,
                $persistWal,
                $chunkSize,
                $mmapSize
            )
        );
    }

    /**
     * @return array{status:string,root:string,capability:array<string, mixed>,state:array<string, mixed>,stat:array<string, mixed>,dependencies:list<string>}
     */
    public function snapshot(): array
    {
        $state = $this->state->snapshot();

        return [
            'status' => 'ready',
            'root' => $this->rootDirectory,
            'capability' => $this->capability,
            'state' => $state,
            'stat' => $this->handle->stat(),
            'dependencies' => $this->dependencies($state['dependencies'], ['vfs-open-file-control-application']),
        ];
    }

    /**
     * @param array<string|int, mixed> $controls
     * @return array{status:string,root:string,file_control:array<string, mixed>,preallocations:list<array<string, mixed>>,stat:array<string, mixed>,bytes_preallocated:int,dependencies:list<string>}
     */
    public function applyMany(array $controls): array
    {
        if ($controls === []) {
            throw new \InvalidArgumentException('SQLite VFS open file-control application requires at least one control');
        }

        $batch = $this->state->applyMany($controls);
        $preallocations = [];
        foreach ($batch['results'] as $result) {
            if (($result['op'] ?? null) !== 'size_hint' || ($result['status'] ?? null) !== 'ok') {
                continue;
            }
            $preallocations[] = $this->applySizeHint((int) $result['value']);
        }

        $bytesPreallocated = 0;
        foreach ($preallocations as $preallocation) {
            $bytesPreallocated += (int) $preallocation['bytes_added'];
        }

        return [
            'status' => 'applied',
            'root' => $this->rootDirectory,
            'file_control' => $batch,
            'preallocations' => $preallocations,
            'stat' => $this->handle->stat(),
            'bytes_preallocated' => $bytesPreallocated,
            'dependencies' => $this->dependencies($batch['dependencies'], ['vfs-open-file-control-application', 'vfs-size-hint-preallocation']),
        ];
    }

    /**
     * @return array{status:string,path:string,requested_size:int,target_size:int,previous_size:int,bytes_added:int,chunk_size:int|null,operation:array<string, mixed>|null,reason:string|null,dependencies:list<string>}
     */
    private function applySizeHint(int $requestedSize): array
    {
        $snapshot = $this->state->snapshot();
        $controls = $snapshot['controls'];
        $stat = $this->handle->stat();
        $previousSize = (int) $stat['size'];
        $chunkSize = isset($controls['chunk_size']) && is_int($controls['chunk_size']) && $controls['chunk_size'] > 0
            ? $controls['chunk_size']
            : null;
        $targetSize = $this->roundedSize($requestedSize, $chunkSize);

        if ($targetSize <= $previousSize) {
            return [
                'status' => 'skipped',
                'path' => $snapshot['path'],
                'requested_size' => $requestedSize,
                'target_size' => $targetSize,
                'previous_size' => $previousSize,
                'bytes_added' => 0,
                'chunk_size' => $chunkSize,
                'operation' => null,
                'reason' => 'size_hint_does_not_extend_file',
                'dependencies' => $this->dependencies($snapshot['dependencies'], ['vfs-size-hint-preallocation']),
            ];
        }

        $operation = $this->handle->truncateTo($targetSize);

        return [
            'status' => 'preallocated',
            'path' => $snapshot['path'],
            'requested_size' => $requestedSize,
            'target_size' => $targetSize,
            'previous_size' => $previousSize,
            'bytes_added' => $targetSize - $previousSize,
            'chunk_size' => $chunkSize,
            'operation' => $operation,
            'reason' => $chunkSize === null ? 'apply_size_hint_to_open_file' : 'apply_chunked_size_hint_to_open_file',
            'dependencies' => $this->dependencies($snapshot['dependencies'], ['vfs-size-hint-preallocation', 'vfs-xtruncate']),
        ];
    }

    private function roundedSize(int $requestedSize, ?int $chunkSize): int
    {
        if ($requestedSize < 0) {
            throw new \InvalidArgumentException('SQLite VFS size hint must be a non-negative integer');
        }
        if ($chunkSize === null || $requestedSize === 0) {
            return $requestedSize;
        }

        return (int) (ceil($requestedSize / $chunkSize) * $chunkSize);
    }

    /**
     * @param list<string> $left
     * @param list<string> $right
     * @return list<string>
     */
    private function dependencies(array $left, array $right): array
    {
        return array_values(array_unique(array_merge($left, $right, ['vfs-file-handle-primitive'])));
    }
}
