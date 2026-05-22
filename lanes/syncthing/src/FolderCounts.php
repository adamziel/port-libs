<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class FolderCounts
{
    public function __construct(
        public readonly int $bytes = 0,
        public readonly int $files = 0,
        public readonly int $directories = 0,
        public readonly int $symlinks = 0,
        public readonly int $deleted = 0,
    ) {
        foreach ([
            'bytes' => $this->bytes,
            'files' => $this->files,
            'directories' => $this->directories,
            'symlinks' => $this->symlinks,
            'deleted' => $this->deleted,
        ] as $label => $value) {
            if ($value < 0) {
                throw new \InvalidArgumentException('Folder count ' . $label . ' must not be negative');
            }
        }
    }

    public function items(): int
    {
        return $this->files + $this->directories + $this->symlinks;
    }

    public function subtractDownloadedBytes(int $downloadedBytes): self
    {
        if ($downloadedBytes < 0) {
            throw new \InvalidArgumentException('Downloaded bytes must not be negative');
        }

        return new self(
            bytes: max(0, $this->bytes - $downloadedBytes),
            files: $this->files,
            directories: $this->directories,
            symlinks: $this->symlinks,
            deleted: $this->deleted,
        );
    }
}
