<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class FileInfoScanResult
{
    /**
     * @param list<FileInfo> $files
     * @param list<string> $resumeSubs
     */
    public function __construct(
        public readonly array $files,
        public readonly bool $cancelled = false,
        public readonly ?string $cancelledAt = null,
        public readonly array $resumeSubs = [],
    ) {
        foreach ($this->files as $file) {
            if (!$file instanceof FileInfo) {
                throw new \InvalidArgumentException('Scan result files must be FileInfo instances');
            }
        }
        if (!$this->cancelled && $this->cancelledAt !== null) {
            throw new \InvalidArgumentException('Scan result cannot include a cancellation path when not cancelled');
        }
        foreach ($this->resumeSubs as $sub) {
            if (!is_string($sub)) {
                throw new \InvalidArgumentException('Scan result resume subs must be strings');
            }
        }
    }

    /**
     * @return list<string>
     */
    public function completedPaths(): array
    {
        return array_map(
            static fn (FileInfo $file): string => $file->name,
            $this->files,
        );
    }

    /**
     * @return list<FileInfo>
     */
    public function resumeCurrentFiles(): array
    {
        return $this->files;
    }

    /**
     * @return array{cancelled:bool,cancelledAt:?string,resumeSubs:list<string>,completedPaths:list<string>,fileCount:int}
     */
    public function toArray(): array
    {
        return [
            'cancelled' => $this->cancelled,
            'cancelledAt' => $this->cancelledAt,
            'resumeSubs' => $this->resumeSubs,
            'completedPaths' => $this->completedPaths(),
            'fileCount' => count($this->files),
        ];
    }
}
