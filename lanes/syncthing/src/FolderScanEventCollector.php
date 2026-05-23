<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class FolderScanEventCollector
{
    /**
     * @var list<array{type:string, data:array<string, mixed>}>
     */
    private array $events = [];

    /**
     * @var list<array{path:string, phase:string, error:string}>
     */
    private array $scanErrors = [];

    /**
     * @param callable(string, array<string, mixed>): void|null $eventLogger
     */
    public function __construct(
        private readonly string $folderId,
        private readonly mixed $eventLogger = null,
    ) {
        if ($this->folderId === '') {
            throw new \InvalidArgumentException('Folder scan event collector requires a folder ID');
        }
        if ($this->eventLogger !== null && !is_callable($this->eventLogger)) {
            throw new \InvalidArgumentException('Folder scan event logger must be callable or null');
        }
    }

    public function progressLogger(): \Closure
    {
        return function (FolderScanProgress $progress): void {
            $this->recordProgress($progress);
        };
    }

    public function errorLogger(): \Closure
    {
        return function (string $path, \Throwable $error, string $phase): void {
            $this->recordScanError($path, $error, $phase);
        };
    }

    public function failureLogger(): \Closure
    {
        return function (string $type, array $data): void {
            $this->recordFailure($type, $data);
        };
    }

    public function recordProgress(FolderScanProgress $progress): void
    {
        $data = $progress->toArray();
        if ($data['folder'] === '') {
            $data['folder'] = $this->folderId;
        }

        $this->recordEvent('FolderScanProgress', $data);
    }

    public function recordScanError(string $path, \Throwable|string $error, string $phase): void
    {
        if ($path === '' || $phase === '') {
            throw new \InvalidArgumentException('Folder scan errors require non-empty path and phase');
        }

        $message = $error instanceof \Throwable ? $error->getMessage() : $error;
        $message = trim($message);
        if ($message === '') {
            throw new \InvalidArgumentException('Folder scan error message must not be empty');
        }

        $this->scanErrors[] = [
            'path' => $path,
            'phase' => $phase,
            'error' => $message,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function recordFailure(string $type, array $data): void
    {
        if ($type !== FileInfoScanner::WALK_FAILURE_EVENT) {
            throw new \InvalidArgumentException('Unsupported scanner event type: ' . $type);
        }

        $description = $this->stringData($data, 'description', FileInfoScanner::WALK_FAILURE_EVENT_DESCRIPTION);
        $sub = $this->stringData($data, 'sub', '.');
        $error = $this->stringData($data, 'error', '');
        if ($description === '' || $sub === '') {
            throw new \InvalidArgumentException('Scanner Failure event requires description and sub fields');
        }

        $this->recordEvent($type, [
            'folder' => $this->folderId,
            'description' => $description,
            'sub' => $sub,
            'error' => $error,
        ]);
    }

    /**
     * @return list<array{type:string, data:array<string, mixed>}>
     */
    public function events(): array
    {
        return $this->events;
    }

    /**
     * @return list<array{type:string, data:array<string, mixed>}>
     */
    public function failureEvents(): array
    {
        return array_values(array_filter(
            $this->events,
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
     * @return array{folder:string, events:list<array{type:string, data:array<string, mixed>}>, scanErrors:list<array{path:string, phase:string, error:string}>}
     */
    public function toArray(): array
    {
        return [
            'folder' => $this->folderId,
            'events' => $this->events,
            'scanErrors' => $this->scanErrors,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function stringData(array $data, string $key, string $default): string
    {
        if (!array_key_exists($key, $data)) {
            return $default;
        }
        if (!is_string($data[$key])) {
            throw new \InvalidArgumentException('Scanner event field must be a string: ' . $key);
        }

        return $data[$key];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function recordEvent(string $type, array $data): void
    {
        $event = [
            'type' => $type,
            'data' => $data,
        ];

        $this->events[] = $event;
        if ($this->eventLogger !== null) {
            ($this->eventLogger)($type, $data);
        }
    }
}
