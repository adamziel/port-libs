<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class SyncPlan
{
    /**
     * @return list<string>
     */
    public function changedPaths(MemoryProvider $source, MemoryProvider $target): array
    {
        $changed = [];
        foreach ($source->list() as $sourceInfo) {
            try {
                $targetInfo = $target->info($sourceInfo->path);
            } catch (\RuntimeException) {
                $changed[] = $sourceInfo->path;
                continue;
            }
            if ($sourceInfo->sha256 !== $targetInfo->sha256 || $sourceInfo->size !== $targetInfo->size) {
                $changed[] = $sourceInfo->path;
            }
        }

        return $changed;
    }
}

