<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class BlobMergeResult
{
    public const RESOLUTION_COMPLETE = 'complete';
    public const RESOLUTION_CONFLICT = 'conflict';
    public const RESOLUTION_AUTO_RESOLVED = 'complete-auto-resolved';

    public function __construct(
        public readonly string $content,
        public readonly string $resolution,
        public readonly int $conflictCount,
    ) {
        if (!in_array($resolution, [self::RESOLUTION_COMPLETE, self::RESOLUTION_CONFLICT, self::RESOLUTION_AUTO_RESOLVED], true)) {
            throw new \InvalidArgumentException("Unsupported blob merge resolution: {$resolution}");
        }
        if ($conflictCount < 0) {
            throw new \InvalidArgumentException('Conflict count cannot be negative');
        }
    }

    public function isClean(): bool
    {
        return $this->resolution !== self::RESOLUTION_CONFLICT;
    }
}
