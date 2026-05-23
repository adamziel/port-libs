<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class FolderScanSchedulerResult
{
    /**
     * @var array<string, FolderScanCheckpointSnapshot>
     */
    private array $snapshots;

    /**
     * @var array<string, \Throwable>
     */
    private array $errors;

    /**
     * @param array<string, FolderScanCheckpointSnapshot> $snapshots
     * @param array<string, \Throwable> $errors
     */
    public function __construct(array $snapshots = [], array $errors = [])
    {
        foreach ($snapshots as $folderId => $snapshot) {
            if (!is_string($folderId) || $folderId === '') {
                throw new \InvalidArgumentException('Folder scan scheduler snapshot keys must be folder IDs');
            }
            if (!$snapshot instanceof FolderScanCheckpointSnapshot) {
                throw new \InvalidArgumentException('Folder scan scheduler snapshots must be checkpoint snapshots');
            }
        }
        foreach ($errors as $folderId => $error) {
            if (!is_string($folderId) || $folderId === '') {
                throw new \InvalidArgumentException('Folder scan scheduler error keys must be folder IDs');
            }
            if (!$error instanceof \Throwable) {
                throw new \InvalidArgumentException('Folder scan scheduler errors must be Throwables');
            }
        }

        ksort($snapshots, SORT_STRING);
        ksort($errors, SORT_STRING);
        $this->snapshots = $snapshots;
        $this->errors = $errors;
    }

    public function successful(): bool
    {
        return $this->errors === [];
    }

    public function snapshot(string $folderId): ?FolderScanCheckpointSnapshot
    {
        return $this->snapshots[$folderId] ?? null;
    }

    public function error(string $folderId): ?\Throwable
    {
        return $this->errors[$folderId] ?? null;
    }

    /**
     * @return array<string, FolderScanCheckpointSnapshot>
     */
    public function snapshots(): array
    {
        return $this->snapshots;
    }

    /**
     * @return array<string, \Throwable>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<string, string>
     */
    public function errorMessages(): array
    {
        $messages = [];
        foreach ($this->errors as $folderId => $error) {
            $messages[$folderId] = $error->getMessage();
        }

        return $messages;
    }

    /**
     * @return array{successful:bool, folderCount:int, completedCount:int, errorCount:int, folders:array<string, array<string, mixed>>, errors:array<string, string>}
     */
    public function toRestStatus(): array
    {
        $folders = [];
        foreach ($this->snapshots as $folderId => $snapshot) {
            $folders[$folderId] = $snapshot->toRestStatus();
        }
        foreach ($this->errors as $folderId => $error) {
            $folders[$folderId] = [
                'folder' => $folderId,
                'state' => 'error',
                'complete' => false,
                'errorType' => get_debug_type($error),
                'error' => $error->getMessage(),
            ];
        }
        ksort($folders, SORT_STRING);

        return [
            'successful' => $this->successful(),
            'folderCount' => count($folders),
            'completedCount' => count($this->snapshots),
            'errorCount' => count($this->errors),
            'folders' => $folders,
            'errors' => $this->errorMessages(),
        ];
    }
}
