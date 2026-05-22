<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class TreeMergeResult
{
    /**
     * @param list<TreeMergeConflict> $conflicts
     */
    public function __construct(
        public readonly Tree $tree,
        public readonly array $conflicts,
    ) {
        foreach ($conflicts as $conflict) {
            if (!$conflict instanceof TreeMergeConflict) {
                throw new \InvalidArgumentException('Tree merge conflicts must be TreeMergeConflict instances');
            }
        }
    }

    public function isClean(): bool
    {
        return $this->conflicts === [];
    }
}
