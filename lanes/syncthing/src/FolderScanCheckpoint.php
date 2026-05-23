<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class FolderScanCheckpoint
{
    /**
     * @var array<string, FileInfo>
     */
    private array $currentFiles = [];

    /**
     * @param list<string> $resumeSubs
     * @param list<FileInfo> $currentFiles
     * @param list<array{type:string, data:array<string, mixed>}> $scanEvents
     * @param list<array{path:string, phase:string, error:string}> $scanErrors
     */
    public function __construct(
        private readonly string $folderId,
        private readonly array $resumeSubs = [],
        array $currentFiles = [],
        private readonly bool $cancelled = false,
        private readonly ?string $cancelledAt = null,
        private readonly array $scanEvents = [],
        private readonly array $scanErrors = [],
        private readonly int $attempts = 0,
    ) {
        if ($this->folderId === '') {
            throw new \InvalidArgumentException('Folder scan checkpoint requires a folder ID');
        }
        if ($this->attempts < 0) {
            throw new \InvalidArgumentException('Folder scan checkpoint attempts must not be negative');
        }
        if (!$this->cancelled && $this->cancelledAt !== null) {
            throw new \InvalidArgumentException('Folder scan checkpoint cannot include a cancellation path when not cancelled');
        }
        foreach ($this->resumeSubs as $sub) {
            if (!is_string($sub)) {
                throw new \InvalidArgumentException('Folder scan checkpoint resume subs must be strings');
            }
        }
        foreach ($currentFiles as $file) {
            if (!$file instanceof FileInfo) {
                throw new \InvalidArgumentException('Folder scan checkpoint current files must be FileInfo instances');
            }
            if ($file->name === '') {
                throw new \InvalidArgumentException('Folder scan checkpoint current files must have names');
            }

            $this->currentFiles[$file->name] = $file;
        }
        foreach ($this->scanEvents as $event) {
            if (
                !isset($event['type'], $event['data'])
                || !is_string($event['type'])
                || $event['type'] === ''
                || !is_array($event['data'])
            ) {
                throw new \InvalidArgumentException('Folder scan checkpoint events must include type and data');
            }
        }
        foreach ($this->scanErrors as $error) {
            if (
                !isset($error['path'], $error['phase'], $error['error'])
                || !is_string($error['path'])
                || $error['path'] === ''
                || !is_string($error['phase'])
                || $error['phase'] === ''
                || !is_string($error['error'])
                || $error['error'] === ''
            ) {
                throw new \InvalidArgumentException('Folder scan checkpoint scan errors must include path, phase, and error');
            }
        }
    }

    public static function fromResult(string $folderId, FileInfoScanResult $result): self
    {
        return (new self($folderId))->withResult($result);
    }

    public function withResult(FileInfoScanResult $result): self
    {
        $currentFiles = $this->currentFiles;
        foreach ($result->files as $file) {
            $currentFiles[$file->name] = $file;
        }

        return new self(
            $this->folderId,
            $result->cancelled ? $result->resumeSubs : [],
            array_values($currentFiles),
            $result->cancelled,
            $result->cancelledAt,
            array_merge($this->scanEvents, $result->scanEvents()),
            array_merge($this->scanErrors, $result->scanErrors()),
            $this->attempts + 1,
        );
    }

    public function folderId(): string
    {
        return $this->folderId;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function cancelled(): bool
    {
        return $this->cancelled;
    }

    public function cancelledAt(): ?string
    {
        return $this->cancelledAt;
    }

    public function state(): string
    {
        if ($this->attempts === 0) {
            return 'idle';
        }
        if ($this->failureEvents() !== []) {
            return 'failed';
        }
        if ($this->cancelled) {
            return 'cancelled';
        }

        return 'complete';
    }

    public function isComplete(): bool
    {
        return $this->state() === 'complete';
    }

    /**
     * @return list<string>
     */
    public function resumeSubs(): array
    {
        return $this->resumeSubs;
    }

    public function currentFile(string $name): ?FileInfo
    {
        return $this->currentFiles[$name] ?? null;
    }

    /**
     * @return list<FileInfo>
     */
    public function resumeCurrentFiles(): array
    {
        return array_values($this->currentFiles);
    }

    /**
     * @return list<string>
     */
    public function completedPaths(): array
    {
        return array_keys($this->currentFiles);
    }

    /**
     * @return list<array{type:string, data:array<string, mixed>}>
     */
    public function scanEvents(): array
    {
        return $this->scanEvents;
    }

    /**
     * @return list<array{type:string, data:array<string, mixed>}>
     */
    public function failureEvents(): array
    {
        return array_values(array_filter(
            $this->scanEvents,
            static fn (array $event): bool => $event['type'] === FileInfoScanner::WALK_FAILURE_EVENT,
        ));
    }

    /**
     * @return list<array{path:string, phase:string, error:string}>
     */
    public function scanErrors(): array
    {
        return $this->scanErrors;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastProgress(): ?array
    {
        for ($index = count($this->scanEvents) - 1; $index >= 0; $index--) {
            $event = $this->scanEvents[$index];
            if ($event['type'] === 'FolderScanProgress') {
                return $event['data'];
            }
        }

        return null;
    }

    /**
     * @return array{folder:string, state:string, attempts:int, complete:bool, cancelled:bool, cancelledAt:?string, resumeSubs:list<string>, completedPaths:list<string>, currentFileCount:int, progress:?array<string, mixed>, eventCount:int, scanErrorCount:int, failureCount:int, events:list<array{type:string, data:array<string, mixed>}>, scanErrors:list<array{path:string, phase:string, error:string}>, failureEvents:list<array{type:string, data:array<string, mixed>}>}
     */
    public function toRestStatus(): array
    {
        $failureEvents = $this->failureEvents();

        return [
            'folder' => $this->folderId,
            'state' => $this->state(),
            'attempts' => $this->attempts,
            'complete' => $this->isComplete(),
            'cancelled' => $this->cancelled,
            'cancelledAt' => $this->cancelledAt,
            'resumeSubs' => $this->resumeSubs,
            'completedPaths' => $this->completedPaths(),
            'currentFileCount' => count($this->currentFiles),
            'progress' => $this->lastProgress(),
            'eventCount' => count($this->scanEvents),
            'scanErrorCount' => count($this->scanErrors),
            'failureCount' => count($failureEvents),
            'events' => $this->scanEvents,
            'scanErrors' => $this->scanErrors,
            'failureEvents' => $failureEvents,
        ];
    }
}
