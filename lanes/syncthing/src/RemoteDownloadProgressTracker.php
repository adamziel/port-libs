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
     * @var array<string, true>
     */
    private array $connectedDevices = [];

    private bool $connectionFilteringEnabled = false;

    /**
     * @var list<array{device:string, folder:string, state:array<string, int>}>
     */
    private array $events = [];

    /**
     * @param array<string, list<string>> $sharedFolders
     */
    public function __construct(array $sharedFolders = [], private readonly ?DeviceActivity $activity = null)
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

    public function connectDevice(string $deviceId): void
    {
        $this->assertDeviceId($deviceId, 'Device ID');
        $this->connectionFilteringEnabled = true;
        $this->connectedDevices[$deviceId] = true;
    }

    public function disconnectDevice(string $deviceId): void
    {
        $this->assertDeviceId($deviceId, 'Device ID');
        $this->connectionFilteringEnabled = true;
        unset($this->connectedDevices[$deviceId], $this->deviceDownloads[$deviceId]);
    }

    /**
     * @return list<string>
     */
    public function connectedDeviceIds(): array
    {
        $deviceIds = array_keys($this->connectedDevices);
        sort($deviceIds);

        return $deviceIds;
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
        if (!$this->isShared($progress->folder, $deviceId) || !$this->isConnected($deviceId)) {
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
            $this->assertDeviceId($deviceId, 'Complete device IDs');
            if ($this->isShared($folder, $deviceId) && $this->isConnected($deviceId)) {
                $availabilities[] = new Availability($deviceId, fromTemporary: false);
            }
        }

        $blockIndex = $this->blockIndex($file, $block);
        foreach (array_keys($this->folderDevices[$folder]) as $deviceId) {
            if (!$this->isConnected($deviceId)) {
                continue;
            }
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

        $selected = ($this->activity ?? new DeviceActivity())->leastBusy($availability);
        if ($selected === null) {
            return null;
        }

        return new BlockRequestPlan(
            $selected->deviceId,
            $this->requestForBlock($folder, $file, $block, $selected->fromTemporary, $requestId),
            $selected,
        );
    }

    /**
     * Maps the request-selection part of Syncthing's `pullBlock`: choose the
     * least busy candidate, mark it in use only around the request, retry
     * failed or invalid candidates once each, and skip the network for sparse
     * zero blocks.
     *
     * The callback must return a successful `Response`, an error `Response`, a
     * raw byte string, or `null` for a generic request failure.
     *
     * @param list<string> $completeDeviceIds devices with the full file in the global index
     * @param callable(BlockRequestPlan): (Response|string|null) $request
     */
    public function pullBlock(
        string $folder,
        FileInfo $file,
        Block $block,
        array $completeDeviceIds,
        callable $request,
        int $requestId = 0,
        bool $receiveEncrypted = false,
    ): BlockPullResult {
        if ($block->offset < 0 || $block->size < 0) {
            throw new \InvalidArgumentException('Block offset and size must not be negative');
        }

        if ($block->isAllZeroes()) {
            return new BlockPullResult(
                block: $block,
                data: str_repeat("\0", $block->size),
                zeroBlock: true,
            );
        }

        $activity = $this->activity ?? new DeviceActivity();
        $candidates = $this->availability($folder, $file, $block, $completeDeviceIds);
        $attempts = [];
        $errors = [];

        while ($candidates !== []) {
            $index = $activity->leastBusyIndex($candidates);
            if ($index < 0) {
                break;
            }

            $selected = $candidates[$index];
            array_splice($candidates, $index, 1);

            $plan = new BlockRequestPlan(
                $selected->deviceId,
                $this->requestForBlock($folder, $file, $block, $selected->fromTemporary, $requestId + count($attempts)),
                $selected,
            );
            $attempts[] = $plan;

            $activity->using($selected);
            try {
                $response = $request($plan);
            } catch (\Throwable $throwable) {
                $errors[] = $throwable->getMessage() !== '' ? $throwable->getMessage() : $throwable::class;
                continue;
            } finally {
                $activity->done($selected);
            }

            if ($response instanceof Response) {
                if (!$response->successful()) {
                    $errors[] = $response->error() ?? Response::ERROR_GENERIC;
                    continue;
                }
                $data = $response->data;
            } elseif (is_string($response)) {
                $data = $response;
            } elseif ($response === null) {
                $errors[] = Response::ERROR_GENERIC;
                continue;
            } else {
                throw new \UnexpectedValueException('Pull callback must return Response, string, or null');
            }

            $validationError = $receiveEncrypted ? null : $this->validatePulledBlock($data, $block);
            if ($validationError !== null) {
                $errors[] = $validationError;
                continue;
            }

            return new BlockPullResult(
                block: $block,
                data: $data,
                plan: $plan,
                attempts: $attempts,
                errors: $errors,
            );
        }

        return new BlockPullResult(
            block: $block,
            error: $errors === [] ? 'no connected device has the required version of this file' : $errors[array_key_last($errors)],
            attempts: $attempts,
            errors: $errors,
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

    private function isConnected(string $deviceId): bool
    {
        return !$this->connectionFilteringEnabled || isset($this->connectedDevices[$deviceId]);
    }

    private function assertDeviceId(mixed $deviceId, string $label): void
    {
        if (!is_string($deviceId) || $deviceId === '') {
            throw new \InvalidArgumentException($label . ' must be a non-empty string');
        }
    }

    private function blockIndex(FileInfo $file, Block $block): int
    {
        if ($block->offset < 0 || $block->size <= 0) {
            throw new \InvalidArgumentException('Block offset and size must describe a positive block range');
        }

        return intdiv($block->offset, $file->blockSize());
    }

    private function validatePulledBlock(string $data, Block $block): ?string
    {
        $length = strlen($data);
        if ($length !== $block->size) {
            return 'length mismatch ' . $length . ' != ' . $block->size;
        }

        if (!hash_equals(strtolower($block->hashHex), hash('sha256', $data))) {
            return 'hash mismatch';
        }

        return null;
    }
}
