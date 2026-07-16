<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class ClusterConfig
{
    /**
     * @param list<Folder> $folders
     */
    public function __construct(
        public readonly array $folders = [],
        public readonly bool $secondary = false,
    ) {
        foreach ($this->folders as $folder) {
            if (!$folder instanceof Folder) {
                throw new \InvalidArgumentException('Expected only Folder instances');
            }
        }
    }
}

