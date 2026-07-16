<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class IndexReceiveResult
{
    /**
     * @param list<FileDownloadProgressUpdate> $forgetUpdates
     * @param list<array{description:string, extra:array<string, string>}> $anomalies
     * @param array{type:string, data:array<string, mixed>} $event
     */
    public function __construct(
        public readonly string $folder,
        public readonly string $remoteDeviceIdHex,
        public readonly bool $update,
        public readonly string $operation,
        public readonly int $prevSequence,
        public readonly int $lastSequence,
        public readonly int $sequence,
        public readonly int $items,
        public readonly array $forgetUpdates,
        public readonly array $anomalies,
        public readonly array $event,
    ) {
        if ($this->folder === '' || $this->operation === '') {
            throw new \InvalidArgumentException('Index receive result requires folder and operation');
        }
        if ($this->prevSequence < 0 || $this->lastSequence < 0 || $this->sequence < 0 || $this->items < 0) {
            throw new \InvalidArgumentException('Index receive result numeric fields must not be negative');
        }
        foreach ($this->forgetUpdates as $update) {
            if (!$update instanceof FileDownloadProgressUpdate) {
                throw new \InvalidArgumentException('Expected only FileDownloadProgressUpdate instances');
            }
        }
    }
}
