<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class DownloadProgress
{
    /**
     * @param list<FileDownloadProgressUpdate> $updates
     */
    public function __construct(
        public readonly string $folder,
        public readonly array $updates = [],
    ) {
        foreach ($this->updates as $update) {
            if (!$update instanceof FileDownloadProgressUpdate) {
                throw new \InvalidArgumentException('Expected only FileDownloadProgressUpdate instances');
            }
        }
    }

    public function normalizedForWire(string $directorySeparator = DIRECTORY_SEPARATOR): self
    {
        $updates = [];
        foreach ($this->updates as $update) {
            $updates[] = $update->withName(ProtocolValidation::normalizeWireName($update->name, $directorySeparator));
        }

        return new self($this->folder, $updates);
    }
}
