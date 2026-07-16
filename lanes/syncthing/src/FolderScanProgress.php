<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class FolderScanProgress
{
    public function __construct(
        public readonly string $folder,
        public readonly int $current,
        public readonly int $total,
        public readonly float $rate = 0.0,
    ) {
        if ($this->current < 0 || $this->total < 0 || $this->rate < 0) {
            throw new \InvalidArgumentException('Folder scan progress values must not be negative');
        }
    }

    /**
     * @return array{folder:string,current:int,total:int,rate:float}
     */
    public function toArray(): array
    {
        return [
            'folder' => $this->folder,
            'current' => $this->current,
            'total' => $this->total,
            'rate' => $this->rate,
        ];
    }
}
