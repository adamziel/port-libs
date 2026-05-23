<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class PullScanner
{
    public const TYPE_FILE = 'file';
    public const TYPE_DIRECTORY = 'directory';
    public const TYPE_UNKNOWN = 'unknown';
    public const TYPE_MIXED = 'mixed';

    /**
     * @var array<string, array{path:string, type:string, sources:array<string, true>}>
     */
    private array $pending = [];

    /**
     * @var list<PullScanScheduleResult>
     */
    private array $scheduledResults = [];

    private bool $closed = false;

    /**
     * @param callable(list<string>):(\Throwable|null)|null $scheduleScan
     */
    public function __construct(
        private readonly mixed $scheduleScan = null,
        private readonly string $folderId = '',
    ) {
        if ($this->scheduleScan !== null && !is_callable($this->scheduleScan)) {
            throw new \InvalidArgumentException('Scan scheduler callback must be callable or null');
        }
    }

    public function queueFile(string $path, string $source = 'file'): void
    {
        $this->queuePath($path, self::TYPE_FILE, $source);
    }

    public function queueDirectory(string $path, string $source = 'directory'): void
    {
        $this->queuePath($path, self::TYPE_DIRECTORY, $source);
    }

    public function queueDeletion(FileInfo $file, ?string $scanName = null): void
    {
        $type = $file->isDirectory() ? self::TYPE_DIRECTORY : self::TYPE_FILE;
        $this->queuePath($scanName ?? $file->name, $type, 'deletion');
    }

    /**
     * @param list<string> $directoryNames
     */
    public function queueFinalization(PullFinalizationResult $result, array $directoryNames = []): void
    {
        if (!$result->closed) {
            return;
        }

        $directories = [];
        foreach ($directoryNames as $name) {
            $this->assertPath($name);
            $directories[$name] = true;
        }

        foreach ($result->scanNames as $name) {
            $type = isset($directories[$name]) ? self::TYPE_DIRECTORY : self::TYPE_FILE;
            $this->queuePath($name, $type, 'finalization');
        }
    }

    public function queuePath(string $path, string $type = self::TYPE_UNKNOWN, string $source = 'scan'): void
    {
        if ($this->closed) {
            throw new \LogicException('Cannot queue pull scans after the scanner has closed');
        }

        $this->assertPath($path);
        $this->assertType($type);
        if ($source === '') {
            throw new \InvalidArgumentException('Scan source must not be empty');
        }

        if (!isset($this->pending[$path])) {
            $this->pending[$path] = [
                'path' => $path,
                'type' => $type,
                'sources' => [$source => true],
            ];
            return;
        }

        $this->pending[$path]['type'] = $this->mergeType($this->pending[$path]['type'], $type);
        $this->pending[$path]['sources'][$source] = true;
    }

    public function close(): PullScanScheduleResult
    {
        if ($this->closed) {
            return new PullScanScheduleResult(false, alreadyClosed: true);
        }

        $this->closed = true;
        $items = $this->pendingItems();
        $paths = array_map(static fn (array $item): string => $item['path'], $items);
        $this->pending = [];

        $error = null;
        if ($paths !== [] && $this->scheduleScan !== null) {
            try {
                $result = ($this->scheduleScan)($paths);
            } catch (\Throwable $throwable) {
                $result = $throwable;
            }

            if ($result instanceof \Throwable) {
                $error = $result->getMessage();
            } elseif ($result !== null) {
                $error = 'scan scheduler must return null or Throwable';
            }
        }

        $scheduled = $paths !== [] && $error === null;
        $result = new PullScanScheduleResult($scheduled, $paths, $items, $error);
        if ($paths !== [] || $error !== null) {
            $this->scheduledResults[] = $result;
        }

        return $result;
    }

    public function folderId(): string
    {
        return $this->folderId;
    }

    public function pendingCount(): int
    {
        return count($this->pending);
    }

    /**
     * @return list<string>
     */
    public function pendingPaths(): array
    {
        return array_keys($this->pending);
    }

    /**
     * @return array{file:int, directory:int, unknown:int, mixed:int}
     */
    public function pendingCountsByType(): array
    {
        $counts = [
            self::TYPE_FILE => 0,
            self::TYPE_DIRECTORY => 0,
            self::TYPE_UNKNOWN => 0,
            self::TYPE_MIXED => 0,
        ];
        foreach ($this->pending as $item) {
            $counts[$item['type']]++;
        }

        return $counts;
    }

    /**
     * @return list<array{path:string, type:string, sources:list<string>}>
     */
    public function pendingItems(): array
    {
        $items = [];
        foreach ($this->pending as $item) {
            $sources = array_keys($item['sources']);
            sort($sources, SORT_STRING);
            $items[] = [
                'path' => $item['path'],
                'type' => $item['type'],
                'sources' => $sources,
            ];
        }

        return $items;
    }

    /**
     * @return list<PullScanScheduleResult>
     */
    public function scheduledResults(): array
    {
        return $this->scheduledResults;
    }

    private function mergeType(string $existing, string $next): string
    {
        if ($existing === $next) {
            return $existing;
        }
        if ($existing === self::TYPE_UNKNOWN) {
            return $next;
        }
        if ($next === self::TYPE_UNKNOWN) {
            return $existing;
        }

        return self::TYPE_MIXED;
    }

    private function assertPath(string $path): void
    {
        ProtocolValidation::checkFilename($path);
    }

    private function assertType(string $type): void
    {
        if (!in_array($type, [
            self::TYPE_FILE,
            self::TYPE_DIRECTORY,
            self::TYPE_UNKNOWN,
            self::TYPE_MIXED,
        ], true)) {
            throw new \InvalidArgumentException('Unknown pull scan item type');
        }
    }
}
