<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class FolderErrorTracker
{
    public const MAX_PULLER_ITERATIONS = 3;

    /**
     * @var list<array{path:string, error:string}>
     */
    private array $scanErrors = [];

    /**
     * @var list<array{path:string, error:string}>
     */
    private array $pullErrors = [];

    /**
     * @var array<string, string>
     */
    private array $tempPullErrors = [];

    /**
     * @var list<array{type:string, data:array{folder:string, errors:list<array{path:string, error:string}>}}>
     */
    private array $events = [];

    /**
     * @param callable(string, array{folder:string, errors:list<array{path:string, error:string}>}): void|null $eventLogger
     */
    public function __construct(
        private readonly string $folderId,
        private readonly mixed $eventLogger = null,
    ) {
        if ($this->folderId === '') {
            throw new \InvalidArgumentException('Folder error tracker requires a folder ID');
        }
        if ($this->eventLogger !== null && !is_callable($this->eventLogger)) {
            throw new \InvalidArgumentException('Folder error event logger must be callable or null');
        }
    }

    public function startPull(): void
    {
        $this->pullErrors = [];
    }

    public function startPullerIteration(): void
    {
        $this->tempPullErrors = [];
    }

    public function newPullError(string $path, \Throwable|string $error): void
    {
        $this->assertPath($path);
        $message = $error instanceof \Throwable ? $error->getMessage() : $error;
        $message = trim($message);
        if ($message === '') {
            throw new \InvalidArgumentException('Pull error must not be empty');
        }
        if ($this->isContextStopError($message)) {
            return;
        }
        if (isset($this->tempPullErrors[$path])) {
            return;
        }

        $this->tempPullErrors[$path] = 'syncing: ' . $message;
    }

    public function addScanError(string $path, \Throwable|string $error): void
    {
        $this->assertPath($path);
        $message = $error instanceof \Throwable ? $error->getMessage() : $error;
        $message = trim($message);
        if ($message === '') {
            throw new \InvalidArgumentException('Scan error must not be empty');
        }

        $this->scanErrors[] = [
            'path' => $path,
            'error' => $message,
        ];
    }

    /**
     * @param list<string> $subDirs
     */
    public function clearScanErrors(array $subDirs = []): void
    {
        if ($subDirs === []) {
            $this->scanErrors = [];
            return;
        }

        foreach ($subDirs as $subDir) {
            $this->assertPath($subDir);
        }

        $this->scanErrors = array_values(array_filter(
            $this->scanErrors,
            static function (array $error) use ($subDirs): bool {
                foreach ($subDirs as $subDir) {
                    if ($error['path'] === $subDir || str_starts_with($error['path'], rtrim($subDir, '/') . '/')) {
                        return false;
                    }
                }

                return true;
            },
        ));
    }

    public function completePull(int $changed): FolderPullResult
    {
        if ($changed < 0) {
            throw new \InvalidArgumentException('Changed count must not be negative');
        }

        $promoted = count($this->tempPullErrors);
        if ($promoted > 0) {
            $this->pullErrors = [];
            foreach ($this->tempPullErrors as $path => $error) {
                $this->pullErrors[] = [
                    'path' => $path,
                    'error' => $error,
                ];
            }
            $this->tempPullErrors = [];
        }

        $event = null;
        $errors = $this->errors();
        if ($promoted > 0) {
            $event = [
                'type' => 'FolderErrors',
                'data' => [
                    'folder' => $this->folderId,
                    'errors' => $errors,
                ],
            ];
            $this->events[] = $event;
            if ($this->eventLogger !== null) {
                ($this->eventLogger)($event['type'], $event['data']);
            }
        }

        return new FolderPullResult(
            success: $changed === 0 && $promoted === 0,
            changed: $changed,
            promotedPullErrors: $promoted,
            errors: $errors,
            folderErrorsEvent: $event,
        );
    }

    /**
     * @return list<array{path:string, error:string}>
     */
    public function errors(): array
    {
        $errors = array_merge($this->scanErrors, $this->pullErrors);
        usort(
            $errors,
            static fn (array $a, array $b): int => ($a['path'] <=> $b['path']) ?: ($a['error'] <=> $b['error']),
        );

        return $errors;
    }

    /**
     * @return list<array{path:string, error:string}>
     */
    public function pullErrors(): array
    {
        return $this->pullErrors;
    }

    /**
     * @return array<string, string>
     */
    public function tempPullErrors(): array
    {
        return $this->tempPullErrors;
    }

    /**
     * @return list<array{path:string, error:string}>
     */
    public function scanErrors(): array
    {
        return $this->scanErrors;
    }

    /**
     * @return list<array{type:string, data:array{folder:string, errors:list<array{path:string, error:string}>}}>
     */
    public function folderErrorsEvents(): array
    {
        return $this->events;
    }

    private function assertPath(string $path): void
    {
        if ($path === '' || str_contains($path, "\0")) {
            throw new \InvalidArgumentException('Folder error paths must be non-empty relative paths');
        }
        ProtocolValidation::checkFilename($path);
    }

    private function isContextStopError(string $message): bool
    {
        return in_array($message, ['context canceled', 'context deadline exceeded'], true);
    }
}
