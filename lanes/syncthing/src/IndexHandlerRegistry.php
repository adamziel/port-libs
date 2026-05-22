<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class IndexHandlerRegistry
{
    private readonly ServiceMap $indexHandlers;
    private readonly \Closure $handlerFactory;

    /**
     * @var array<string, IndexHandlerStartInfo>
     */
    private array $startInfos = [];

    /**
     * @var array<string, array{folder: Folder, runner: mixed}>
     */
    private array $folderStates = [];

    /**
     * @var array<string, FolderIndexState>
     */
    private array $folderIndexStates = [];

    /**
     * @param array<string, FolderIndexState> $folderIndexStates
     */
    public function __construct(
        private readonly string $remoteDeviceIdHex,
        private readonly int $localIndexId = 0,
        private readonly int $localCurrentSequence = 0,
        ?callable $handlerFactory = null,
        ?ServiceMap $indexHandlers = null,
        private readonly ?DeviceDownloadState $downloads = null,
        private readonly mixed $eventLogger = null,
        array $folderIndexStates = [],
    ) {
        $this->assertDeviceId($remoteDeviceIdHex);
        if ($this->localIndexId < 0 || $this->localCurrentSequence < 0) {
            throw new \InvalidArgumentException('Index IDs and sequence numbers must not be negative');
        }
        if ($this->eventLogger !== null && !is_callable($this->eventLogger)) {
            throw new \InvalidArgumentException('Index event logger must be callable');
        }

        $this->indexHandlers = $indexHandlers ?? new ServiceMap();
        $this->handlerFactory = \Closure::fromCallable($handlerFactory ?? $this->defaultHandlerFactory(...));
        foreach ($folderIndexStates as $folder => $state) {
            if (!is_string($folder) && !is_int($folder)) {
                throw new \InvalidArgumentException('Folder index state keys must be folder IDs');
            }
            if (!$state instanceof FolderIndexState) {
                throw new \InvalidArgumentException('Expected only FolderIndexState instances');
            }

            $this->registerFolderIndexState((string) $folder, $state);
        }
    }

    public function addIndexInfo(string $folder, IndexHandlerStartInfo $startInfo): ?IndexHandler
    {
        $this->assertFolderId($folder);
        $this->indexHandlers->removeAndWait($folder);

        if (!isset($this->folderStates[$folder])) {
            $this->startInfos[$folder] = $startInfo;

            return null;
        }

        $state = $this->folderStates[$folder];

        return $this->start($state['folder'], $state['runner'], $startInfo);
    }

    public function registerFolderState(Folder $folder, mixed $runner = null): ?IndexHandler
    {
        if (!$this->folderSharedWithRemote($folder)) {
            $this->remove($folder->id);

            return null;
        }

        if (!$folder->isRunning()) {
            return $this->folderPaused($folder->id);
        }

        return $this->folderRunning($folder, $runner);
    }

    public function remove(string $folder): void
    {
        $this->assertFolderId($folder);
        $this->indexHandlers->removeAndWait($folder);
        unset($this->startInfos[$folder], $this->folderStates[$folder]);
    }

    /**
     * @param array<int|string, mixed> $except
     */
    public function removeAllExcept(array $except): void
    {
        $keep = $this->folderSet($except);

        foreach ($this->handlerFolders() as $folder) {
            if (!isset($keep[$folder])) {
                $this->indexHandlers->removeAndWait($folder);
            }
        }
        foreach (array_keys($this->startInfos) as $folder) {
            if (!isset($keep[$folder])) {
                unset($this->startInfos[$folder]);
            }
        }
        foreach (array_keys($this->folderStates) as $folder) {
            if (!isset($keep[$folder])) {
                unset($this->folderStates[$folder]);
            }
        }
    }

    public function handler(string $folder): ?IndexHandler
    {
        $handler = $this->indexHandlers->get($folder);

        return $handler instanceof IndexHandler ? $handler : null;
    }

    public function registerFolderIndexState(string $folder, FolderIndexState $state): void
    {
        $this->assertFolderId($folder);
        $this->folderIndexStates[$folder] = $state;
    }

    public function folderIndexState(string $folder): ?FolderIndexState
    {
        $this->assertFolderId($folder);

        return $this->folderIndexStates[$folder] ?? null;
    }

    public function pendingStartInfo(string $folder): ?IndexHandlerStartInfo
    {
        return $this->startInfos[$folder] ?? null;
    }

    /**
     * @return list<string>
     */
    public function pendingFolders(): array
    {
        return array_values(array_keys($this->startInfos));
    }

    /**
     * @return list<string>
     */
    public function handlerFolders(): array
    {
        return array_map('strval', $this->indexHandlers->keys());
    }

    /**
     * @return list<string>
     */
    public function runningFolders(): array
    {
        $folders = [];
        foreach ($this->handlerFolders() as $folder) {
            $handler = $this->handler($folder);
            if ($handler !== null && !$handler->isPaused()) {
                $folders[] = $folder;
            }
        }

        return $folders;
    }

    /**
     * @return list<string>
     */
    public function registeredFolders(): array
    {
        return array_values(array_keys($this->folderStates));
    }

    /**
     * @param list<FileInfo> $files
     */
    public function receiveIndex(
        string $folder,
        array $files,
        bool $update,
        string $operation,
        int $prevSequence = 0,
        int $lastSequence = 0,
    ): IndexReceiveResult {
        $this->assertFolderId($folder);
        $handler = $this->handler($folder);
        if ($handler === null) {
            throw new \RuntimeException($folder . ': no such folder');
        }

        $result = $handler->receiveIndex(
            files: $files,
            update: $update,
            operation: $operation,
            prevSequence: $prevSequence,
            lastSequence: $lastSequence,
            downloads: $this->downloads,
            remoteDeviceIdHex: $this->remoteDeviceIdHex,
            eventLogger: $this->eventLogger,
        );
        $state = $this->folderIndexStates[$folder] ?? null;
        $state?->update($this->remoteDeviceIdHex, $files, reset: !$update);

        return $result;
    }

    private function folderPaused(string $folder): ?IndexHandler
    {
        unset($this->folderStates[$folder]);
        $handler = $this->handler($folder);
        if ($handler !== null) {
            $handler->pause();
        }

        return $handler;
    }

    private function folderRunning(Folder $folder, mixed $runner): ?IndexHandler
    {
        $this->folderStates[$folder->id] = [
            'folder' => $folder,
            'runner' => $runner,
        ];

        $handler = $this->handler($folder->id);
        if (isset($this->startInfos[$folder->id])) {
            $startInfo = $this->startInfos[$folder->id];
            unset($this->startInfos[$folder->id]);

            return $this->start($folder, $runner, $startInfo);
        }
        if ($handler !== null) {
            $handler->resume($runner);

            return $handler;
        }

        return null;
    }

    private function start(Folder $folder, mixed $runner, IndexHandlerStartInfo $startInfo): IndexHandler
    {
        $this->indexHandlers->removeAndWait($folder->id);
        unset($this->startInfos[$folder->id]);

        $startSequence = $startInfo->localStartSequence($this->localIndexId, $this->localCurrentSequence);
        $handler = ($this->handlerFactory)($folder, $startInfo, $startSequence, $runner);
        if (!$handler instanceof IndexHandler) {
            throw new \UnexpectedValueException('Index handler factory must return an IndexHandler');
        }

        $this->indexHandlers->add($folder->id, $handler);
        $this->schedulePull($runner);

        return $handler;
    }

    private function defaultHandlerFactory(
        Folder $folder,
        IndexHandlerStartInfo $startInfo,
        int $startSequence,
        mixed $runner,
    ): IndexHandler {
        return new IndexHandler(
            folder: $folder->id,
            localPrevSequence: $startSequence,
            sentPrevSequence: $startSequence,
            folderIsReceiveEncrypted: $folder->type === Folder::TYPE_RECEIVE_ENCRYPTED,
            runner: $runner,
        );
    }

    private function schedulePull(mixed $runner): void
    {
        if ($runner === null) {
            return;
        }
        if (is_callable($runner)) {
            $runner();

            return;
        }
        if (is_object($runner) && method_exists($runner, 'schedulePull')) {
            $runner->schedulePull();

            return;
        }
        if (is_object($runner) && method_exists($runner, 'SchedulePull')) {
            $runner->SchedulePull();
        }
    }

    private function folderSharedWithRemote(Folder $folder): bool
    {
        foreach ($folder->devices as $device) {
            if ($device->idHex === $this->remoteDeviceIdHex) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int|string, mixed> $folders
     *
     * @return array<string, true>
     */
    private function folderSet(array $folders): array
    {
        $set = [];
        foreach ($folders as $key => $value) {
            $folder = is_int($key) ? $value : $key;
            if (!is_string($folder) && !is_int($folder)) {
                throw new \InvalidArgumentException('Folder keep set must contain folder IDs');
            }
            $this->assertFolderId((string) $folder);
            $set[(string) $folder] = true;
        }

        return $set;
    }

    private function assertFolderId(string $folder): void
    {
        if ($folder === '') {
            throw new \InvalidArgumentException('Folder ID must not be empty');
        }
    }

    private function assertDeviceId(string $deviceIdHex): void
    {
        if ($deviceIdHex === '' || !preg_match('/^(?:[0-9a-f]{2})+$/', $deviceIdHex)) {
            throw new \InvalidArgumentException('Expected lowercase hexadecimal bytes for remote device ID');
        }
    }
}
