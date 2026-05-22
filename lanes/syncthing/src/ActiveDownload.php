<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class ActiveDownload
{
    /**
     * @param list<int> $availableBlockIndexes
     */
    public function __construct(
        public readonly string $folder,
        public readonly FileInfo $file,
        public readonly array $availableBlockIndexes = [],
        public readonly int $availableUpdated = 0,
        public readonly int $created = 0,
    ) {
        if ($this->availableUpdated < 0 || $this->created < 0) {
            throw new \InvalidArgumentException('Download timestamps must not be negative');
        }
        foreach ($this->availableBlockIndexes as $blockIndex) {
            if (!is_int($blockIndex) || $blockIndex < 0) {
                throw new \InvalidArgumentException('Available block indexes must be non-negative integers');
            }
        }
    }

    public function eligibleForTemporaryIndex(string $folder, int $minBlocks): bool
    {
        if ($minBlocks < 0) {
            throw new \InvalidArgumentException('Minimum block threshold must not be negative');
        }

        return $this->folder === $folder
            && !$this->file->isDirectory()
            && !$this->file->isSymlink()
            && count($this->file->blocks) > $minBlocks;
    }
}
