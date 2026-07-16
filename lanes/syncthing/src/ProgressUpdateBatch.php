<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class ProgressUpdateBatch
{
    /**
     * @param list<FileDownloadProgressUpdate> $updates
     */
    public function __construct(
        public readonly string $deviceId,
        public readonly string $folder,
        public readonly array $updates,
    ) {
        foreach ($this->updates as $update) {
            if (!$update instanceof FileDownloadProgressUpdate) {
                throw new \InvalidArgumentException('Expected only FileDownloadProgressUpdate instances');
            }
        }
    }

    public function toDownloadProgress(): DownloadProgress
    {
        return new DownloadProgress($this->folder, $this->updates);
    }
}
