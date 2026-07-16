<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class TreeMergeConflict
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public readonly string $path,
        public readonly string $reason,
        public readonly ?TreeEntry $base,
        public readonly ?TreeEntry $ours,
        public readonly ?TreeEntry $theirs,
        public readonly array $context = [],
    ) {
    }
}
