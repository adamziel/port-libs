<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

require_once __DIR__ . '/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext212Plan
{
    /**
     * @param array<string,mixed> $writerPlan
     * @param list<array<string,mixed>> $readers
     * @return array<string,mixed>
     */
    public static function passiveCheckpoint(array $writerPlan, array $readers, int $requestedFrame): array
    {
        return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next212PassiveCheckpoint(
            $writerPlan,
            $readers,
            $requestedFrame
        );
    }
}
