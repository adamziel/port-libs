<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class RemoteDownloadProgressTracker
{
    /**
     * @var array<string, array<string, true>>
     */
    private array $folderDevices = [];

    /**
     * @var array<string, DeviceDownloadState>
     */
    private array $deviceDownloads = [];

    /**
     * @var list<array{device:string, folder:string, state:array<string, int>}>
     */
    private array $events = [];

    /**
     * @param array<string, list<string>> $sharedFolders
     */
    public function __construct(array $sharedFolders = [])
    {
        foreach ($sharedFolders as $folder => $deviceIds) {
            $this->shareFolderWith($folder, $deviceIds);
        }
    }

    /**
     * @param list<string> $deviceIds
     */
    public function shareFolderWith(string $folder, array $deviceIds): void
    {
        if ($folder === '') {
            throw new \InvalidArgumentException('Folder ID must not be empty');
        }

        $shared = [];
        foreach ($deviceIds as $deviceId) {
            if (!is_string($deviceId) || $deviceId === '') {
                throw new \InvalidArgumentException('Shared device IDs must be non-empty strings');
            }
            $shared[$deviceId] = true;
        }

        $this->folderDevices[$folder] = $shared;
    }

    /**
     * Mirrors `model.DownloadProgress`: ignore unknown folders and devices the
     * folder is not shared with, otherwise update the peer's temporary state
     * and emit a RemoteDownloadProgress-style summary.
     *
     * @return array{device:string, folder:string, state:array<string, int>}|null
     */
    public function receiveDownloadProgress(string $deviceId, DownloadProgress $progress): ?array
    {
        if (!$this->isShared($progress->folder, $deviceId)) {
            return null;
        }

        $downloads = $this->deviceDownloads[$deviceId] ??= new DeviceDownloadState();
        $downloads->update($progress->folder, $progress->updates);

        $event = [
            'device' => $deviceId,
            'folder' => $progress->folder,
            'state' => $downloads->getBlockCounts($progress->folder),
        ];
        $this->events[] = $event;

        return $event;
    }

    /**
     * @return list<array{device:string, folder:string, state:array<string, int>}>
     */
    public function remoteDownloadProgressEvents(): array
    {
        return $this->events;
    }

    /**
     * @return array<string, int>
     */
    public function remoteBlockCounts(string $deviceId, string $folder): array
    {
        $downloads = $this->deviceDownloads[$deviceId] ?? null;

        return $downloads?->getBlockCounts($folder) ?? [];
    }

    public function bytesDownloaded(string $deviceId, string $folder): int
    {
        $downloads = $this->deviceDownloads[$deviceId] ?? null;

        return $downloads?->bytesDownloaded($folder) ?? 0;
    }

    /**
     * @param list<string> $completeDeviceIds devices with the full file in the global index
     *
     * @return list<Availability>
     */
    public function availability(string $folder, FileInfo $file, Block $block, array $completeDeviceIds = []): array
    {
        if (!isset($this->folderDevices[$folder])) {
            return [];
        }

        $availabilities = [];
        foreach ($completeDeviceIds as $deviceId) {
            if (!is_string($deviceId) || $deviceId === '') {
                throw new \InvalidArgumentException('Complete device IDs must be non-empty strings');
            }
            if ($this->isShared($folder, $deviceId)) {
                $availabilities[] = new Availability($deviceId, fromTemporary: false);
            }
        }

        $blockIndex = $this->blockIndex($file, $block);
        foreach (array_keys($this->folderDevices[$folder]) as $deviceId) {
            $downloads = $this->deviceDownloads[$deviceId] ?? null;
            if ($downloads !== null && $downloads->has($folder, $file->name, $file->version, $blockIndex)) {
                $availabilities[] = new Availability($deviceId, fromTemporary: true);
            }
        }

        return $availabilities;
    }

    /**
     * @param list<string> $completeDeviceIds devices with the full file in the global index
     */
    public function planBlockRequest(
        string $folder,
        FileInfo $file,
        Block $block,
        array $completeDeviceIds = [],
        int $requestId = 0,
    ): ?BlockRequestPlan {
        $availability = $this->availability($folder, $file, $block, $completeDeviceIds);
        if ($availability === []) {
            return null;
        }

        $selected = $availability[0];

        return new BlockRequestPlan(
            $selected->deviceId,
            $this->requestForBlock($folder, $file, $block, $selected->fromTemporary, $requestId),
        );
    }

    public function requestForBlock(string $folder, FileInfo $file, Block $block, bool $fromTemporary, int $requestId = 0): Request
    {
        return new Request(
            id: $requestId,
            folder: $folder,
            name: $file->name,
            offset: $block->offset,
            size: $block->size,
            hashHex: strtolower($block->hashHex),
            fromTemporary: $fromTemporary,
            blockNo: $this->blockIndex($file, $block),
        );
    }

    private function isShared(string $folder, string $deviceId): bool
    {
        return isset($this->folderDevices[$folder][$deviceId]);
    }

    private function blockIndex(FileInfo $file, Block $block): int
    {
        if ($block->offset < 0 || $block->size <= 0) {
            throw new \InvalidArgumentException('Block offset and size must describe a positive block range');
        }

        return intdiv($block->offset, $file->blockSize());
    }
}
