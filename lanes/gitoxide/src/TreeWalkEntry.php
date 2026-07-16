<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class TreeWalkEntry
{
    public function __construct(
        public readonly string $path,
        public readonly TreeEntry $entry,
        public readonly string $matchKind,
        public readonly int $pathspecSequenceNumber,
    ) {
    }
}
