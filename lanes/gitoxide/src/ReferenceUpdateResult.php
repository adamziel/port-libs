<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class ReferenceUpdateResult
{
    /**
     * @param list<ReferenceTransactionEdit> $edits
     */
    public function __construct(
        public readonly ResolvedReference $reference,
        public readonly array $edits,
    ) {
        foreach ($edits as $edit) {
            if (!$edit instanceof ReferenceTransactionEdit) {
                throw new \InvalidArgumentException('Reference update edits must be ReferenceTransactionEdit instances');
            }
        }
    }
}
