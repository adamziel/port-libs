<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class Index
{
    /**
     * @param list<FileInfo> $files
     */
    public function __construct(
        public readonly string $folder,
        public readonly array $files = [],
        public readonly int $lastSequence = 0,
    ) {
        if ($this->lastSequence < 0) {
            throw new \InvalidArgumentException('Index sequence numbers must not be negative');
        }
        foreach ($this->files as $file) {
            if (!$file instanceof FileInfo) {
                throw new \InvalidArgumentException('Expected only FileInfo instances');
            }
        }
    }

    public function checkConsistency(): void
    {
        ProtocolValidation::checkIndexConsistency($this->files);
    }

    public function normalizedForWire(string $directorySeparator = DIRECTORY_SEPARATOR): self
    {
        $files = [];
        foreach ($this->files as $file) {
            $files[] = $file->withName(ProtocolValidation::normalizeWireName($file->name, $directorySeparator));
        }

        return new self(
            folder: $this->folder,
            files: $files,
            lastSequence: $this->lastSequence,
        );
    }
}
