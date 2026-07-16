<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class ProgressEmitter
{
    /**
     * @var array<string, array<string, ActiveDownload>>
     */
    private array $registry = [];

    /**
     * @var array<string, array<string, PullerProgress>>
     */
    private array $progress = [];

    /**
     * @var array<string, SentDownloadState>
     */
    private array $sentDownloadStates = [];

    /**
     * @var array<string, true>
     */
    private array $connections = [];

    /**
     * @var array<string, list<string>>
     */
    private array $foldersByDevice = [];

    public function __construct(
        private int $minBlocks = 0,
        private bool $disabled = false,
    ) {
        if ($this->minBlocks < 0) {
            throw new \InvalidArgumentException('Minimum block threshold must not be negative');
        }
    }

    /**
     * Mirrors the relevant `CommitConfiguration` branch: a non-positive interval
     * disables the emitter and first returns forget updates for connected peers.
     *
     * @return list<ProgressUpdateBatch>
     */
    public function configure(int $progressUpdateIntervalSeconds, int $tempIndexMinBlocks): array
    {
        if ($tempIndexMinBlocks < 0) {
            throw new \InvalidArgumentException('Minimum block threshold must not be negative');
        }

        $this->minBlocks = $tempIndexMinBlocks;
        if ($progressUpdateIntervalSeconds > 0) {
            $this->disabled = false;

            return [];
        }

        if ($this->disabled) {
            return [];
        }

        $cleanup = $this->clear();
        $this->disabled = true;

        return $cleanup;
    }

    public function isDisabled(): bool
    {
        return $this->disabled;
    }

    public function register(ActiveDownload $download, ?PullerProgress $progress = null): void
    {
        if ($this->disabled) {
            return;
        }

        $folder = $download->folder;
        $name = $download->file->name;
        $this->registry[$folder][$name] = $download;
        $this->progress[$folder][$name] = $progress ?? PullerProgress::fromAvailable($download->file, count($download->availableBlockIndexes));
    }

    public function deregister(string $folder, string $name): void
    {
        if ($this->disabled) {
            return;
        }

        unset($this->registry[$folder][$name], $this->progress[$folder][$name]);
        if (($this->progress[$folder] ?? []) === []) {
            unset($this->progress[$folder]);
        }
    }

    /**
     * @param list<string> $folders
     */
    public function temporaryIndexSubscribe(string $deviceId, array $folders): void
    {
        foreach ($folders as $folder) {
            if (!is_string($folder)) {
                throw new \InvalidArgumentException('Temporary-index folders must be strings');
            }
        }

        $this->connections[$deviceId] = true;
        $this->foldersByDevice[$deviceId] = array_values($folders);
    }

    public function temporaryIndexUnsubscribe(string $deviceId): void
    {
        unset($this->connections[$deviceId], $this->foldersByDevice[$deviceId]);
    }

    /**
     * @return list<ProgressUpdateBatch>
     */
    public function computeProgressUpdates(): array
    {
        if ($this->disabled) {
            return [];
        }

        $batches = [];
        foreach (array_keys($this->connections) as $deviceId) {
            foreach ($this->foldersByDevice[$deviceId] ?? [] as $folder) {
                if (!isset($this->registry[$folder])) {
                    continue;
                }

                $state = $this->sentDownloadStates[$deviceId] ??= new SentDownloadState();
                $updates = $state->update($folder, array_values($this->registry[$folder]), $this->minBlocks);
                if ($updates !== []) {
                    $batches[] = new ProgressUpdateBatch($deviceId, $folder, $updates);
                }
            }
        }

        foreach (array_keys($this->sentDownloadStates) as $deviceId) {
            if (!isset($this->connections[$deviceId])) {
                unset($this->sentDownloadStates[$deviceId]);
            }
        }

        foreach ($this->sentDownloadStates as $deviceId => $state) {
            $sharedFolders = $this->foldersByDevice[$deviceId] ?? [];
            foreach ($state->folders() as $folder) {
                if (!in_array($folder, $sharedFolders, true)) {
                    $state->cleanup($folder);
                }
            }
        }

        return $batches;
    }

    /**
     * @return list<ProgressUpdateBatch>
     */
    public function clear(): array
    {
        $cleanup = [];
        foreach ($this->sentDownloadStates as $deviceId => $state) {
            if (!isset($this->connections[$deviceId])) {
                continue;
            }
            foreach ($state->folders() as $folder) {
                $updates = $state->cleanup($folder);
                if ($updates !== []) {
                    $cleanup[] = new ProgressUpdateBatch($deviceId, $folder, $updates);
                }
            }
        }

        $this->registry = [];
        $this->progress = [];
        $this->sentDownloadStates = [];
        $this->connections = [];
        $this->foldersByDevice = [];

        return $cleanup;
    }

    /**
     * @return array<string, array<string, PullerProgress>>
     */
    public function downloadProgressEvent(): array
    {
        $event = [];
        foreach ($this->progress as $folder => $files) {
            if ($files !== []) {
                $event[$folder] = $files;
            }
        }

        return $event;
    }

    public function bytesCompleted(string $folder): int
    {
        $bytes = 0;
        foreach ($this->progress[$folder] ?? [] as $progress) {
            $bytes += $progress->bytesDone;
        }

        return $bytes;
    }

    public function registeredCount(): int
    {
        $count = 0;
        foreach ($this->registry as $downloads) {
            $count += count($downloads);
        }

        return $count;
    }

    /**
     * @return array{latestUpdated:int, count:int}
     */
    public function progressRevision(): array
    {
        $latestUpdated = 0;
        $count = 0;
        foreach ($this->registry as $downloads) {
            foreach ($downloads as $download) {
                $count++;
                $latestUpdated = max($latestUpdated, $download->availableUpdated);
            }
        }

        return [
            'latestUpdated' => $latestUpdated,
            'count' => $count,
        ];
    }

    /**
     * @return list<string>
     */
    public function sentStateDevices(): array
    {
        $devices = array_keys($this->sentDownloadStates);
        sort($devices, SORT_STRING);

        return $devices;
    }
}
