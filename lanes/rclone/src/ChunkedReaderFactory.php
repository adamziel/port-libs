<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class ChunkedReaderFactory
{
    public static function create(
        MemoryProvider $provider,
        string $path,
        int $initialChunkSize,
        int $maxChunkSize,
        int $streams,
    ): SequentialChunkedReader|ParallelChunkedReader {
        if ($initialChunkSize <= 0) {
            $initialChunkSize = -1;
        }
        if ($maxChunkSize !== -1 && $maxChunkSize < $initialChunkSize) {
            $maxChunkSize = $initialChunkSize;
        }
        if ($streams < 0) {
            $streams = 0;
        }

        if ($streams <= 1 || $provider->info($path)->size < 0) {
            return new SequentialChunkedReader($provider, $path, $initialChunkSize, $maxChunkSize);
        }

        return new ParallelChunkedReader($provider, $path, $initialChunkSize, $streams);
    }
}
