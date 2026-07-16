<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class PullJobQueue
{
    /**
     * @var list<string>
     */
    private array $progress = [];

    /**
     * @var list<array{name:string, size:int, modifiedNs:int}>
     */
    private array $queued = [];

    public function push(string $file, int $size = 0, int $modifiedNs = 0): void
    {
        if ($file === '') {
            throw new \InvalidArgumentException('Pull job file name must not be empty');
        }
        if ($size < 0) {
            throw new \InvalidArgumentException('Pull job size must not be negative');
        }

        $this->queued[] = [
            'name' => $file,
            'size' => $size,
            'modifiedNs' => $modifiedNs,
        ];
    }

    public function pop(): ?string
    {
        if ($this->queued === []) {
            return null;
        }

        $entry = array_shift($this->queued);
        \assert(is_array($entry));
        $this->progress[] = $entry['name'];

        return $entry['name'];
    }

    public function bringToFront(string $filename): void
    {
        foreach ($this->queued as $index => $entry) {
            if ($entry['name'] !== $filename) {
                continue;
            }

            if ($index > 0) {
                array_splice($this->queued, $index, 1);
                array_unshift($this->queued, $entry);
            }

            return;
        }
    }

    public function done(string $file): void
    {
        foreach ($this->progress as $index => $progressFile) {
            if ($progressFile === $file) {
                array_splice($this->progress, $index, 1);

                return;
            }
        }
    }

    /**
     * @return array{progress:list<string>, queued:list<string>, skipped:int}
     */
    public function jobs(int $page, int $perPage): array
    {
        if ($page < 1 || $perPage < 1) {
            throw new \InvalidArgumentException('Pull job pagination starts at page 1 with a positive page size');
        }

        $toSkip = ($page - 1) * $perPage;
        $progressCount = count($this->progress);
        $queuedCount = count($this->queued);
        $total = $progressCount + $queuedCount;

        if ($total <= $toSkip) {
            return [
                'progress' => [],
                'queued' => [],
                'skipped' => $total,
            ];
        }

        if ($progressCount >= $toSkip + $perPage) {
            return [
                'progress' => array_slice($this->progress, $toSkip, $perPage),
                'queued' => [],
                'skipped' => $toSkip,
            ];
        }

        $progress = [];
        if ($progressCount > $toSkip) {
            $progress = array_slice($this->progress, $toSkip);
            $toSkip = 0;
        } else {
            $toSkip -= $progressCount;
        }

        $queuedSlots = $perPage - count($progress);
        $queued = [];
        foreach (array_slice($this->queued, $toSkip, $queuedSlots) as $entry) {
            $queued[] = $entry['name'];
        }

        return [
            'progress' => $progress,
            'queued' => $queued,
            'skipped' => ($page - 1) * $perPage,
        ];
    }

    public function reset(): void
    {
        $this->progress = [];
        $this->queued = [];
    }

    public function queuedCount(): int
    {
        return count($this->queued);
    }

    public function progressCount(): int
    {
        return count($this->progress);
    }
}
