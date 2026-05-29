<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext153Plan
{
    /**
     * @param list<int> $pageNumbers
     * @param list<array{pages:array<int,string>,database_page_count?:int|null,commit?:bool}> $nextTransactions
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $dirtyDatabaseBytes,
        string $journalBytes,
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        array $pageNumbers,
        array $nextTransactions,
        int $currentReaderEndFrame,
        string $mode = 'restart',
        bool $reservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null
    ): array {
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next153 requires a savepoint name');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next153 requires page numbers');
        }
        if ($nextTransactions === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next153 requires next transactions');
        }
        if ($currentReaderEndFrame < 0 || $currentReaderEndFrame > $wal->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next153 reader frame is outside the WAL frame range');
        }

        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next153 requires restart or truncate mode');
        }

        $hot = SQLiteWalHotJournalCheckpointRestartCurrentSourceNext129Plan::plan(
            $databasePath,
            $dirtyDatabaseBytes,
            $journalBytes,
            $wal,
            $walBytes,
            $pageNumbers,
            $currentReaderEndFrame,
            $reservedLock,
            $requiresSuperJournal,
            $superJournalExists
        );
        $hotDatabaseBytes = (string) ($hot['hot_journal']['database_bytes'] ?? '');
        if ($hotDatabaseBytes === '') {
            throw new \UnexpectedValueException('SQLite WAL hot-journal savepoint checkpoint next153 requires recovered hot-journal database bytes');
        }

        $rollback = SQLiteWalSavepointCheckpointPlan::afterRollbackTo(
            $savepoints,
            $savepoint,
            $wal,
            $walBytes,
            $hotDatabaseBytes,
            $mode,
            $currentReaderEndFrame
        );
        $currentWalBytes = (string) $rollback['current_wal_bytes'];
        $currentWal = SQLiteWal::parse($currentWalBytes, $wal->header->pageSize, true);
        $releasedCheckpoint = $currentWal->durableCheckpointResult($hotDatabaseBytes, $mode, null);
        $nextWal = self::nextWalAfterCheckpoint($currentWal, $releasedCheckpoint);
        $append = SQLiteWalAppendPlan::appendTransactions($nextWal, $databasePath, $nextTransactions);
        $nextWalAfterAppend = SQLiteWal::parse((string) $append['wal_bytes'], $wal->header->pageSize, true);

        $rows = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next153 pages must be one-based integers');
            }

            $hotRow = self::databasePage($hotDatabaseBytes, $wal->header->pageSize, $pageNumber, 'hot-journal-database');
            $currentRow = $currentWal->readerSnapshotPageImage($hotDatabaseBytes, $pageNumber, min($currentReaderEndFrame, $currentWal->frameCount()));
            $nextRow = $nextWalAfterAppend->readerSnapshotPageImage((string) $releasedCheckpoint['database_bytes'], $pageNumber, $nextWalAfterAppend->frameCount());
            $rows[] = [
                'page_number' => $pageNumber,
                'hot_source' => $hotRow['source'],
                'current_source' => $currentRow['source'],
                'next_source' => $nextRow['source'],
                'current_frame' => $currentRow['frame_index'],
                'next_frame' => $nextRow['frame_index'],
                'hot_label' => self::label((string) $hotRow['image']),
                'current_label' => self::label((string) $currentRow['image']),
                'next_label' => self::label((string) $nextRow['image']),
                'current_kept_rolled_back_source' => $currentRow['image'] !== $hotRow['image'] || $currentRow['source'] === 'wal',
                'next_separated_from_current' => $nextRow['image'] !== $currentRow['image'] || $nextRow['source'] !== $currentRow['source'],
                'source_transition' => $hotRow['source'] . '>' . $currentRow['source'] . '>' . $nextRow['source'],
            ];
        }

        $currentSources = array_column($rows, 'current_source');
        $nextSources = array_column($rows, 'next_source');
        $status = (bool) $hot['hot_recovered']
            && (bool) $rollback['busy']
            && $rollback['reason'] === 'reader_blocks_wal_reset'
            && (bool) $rollback['discarded_frame_count']
            && !(bool) $releasedCheckpoint['busy']
            && in_array($releasedCheckpoint['wal_action'], ['restart_wal', 'truncate_wal'], true)
            ? 'wal-hot-journal-savepoint-checkpoint-current-source-next153'
            : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next153';

        return [
            'status' => $status,
            'reason' => $status === 'wal-hot-journal-savepoint-checkpoint-current-source-next153'
                ? 'hot_journal_recovered_before_savepoint_rollback_current_wal_prefix_pins_checkpoint_until_reader_release'
                : 'hot_journal_savepoint_checkpoint_current_source_blocked',
            'database_path' => $databasePath,
            'journal_path' => $hot['journal_path'],
            'wal_path' => $databasePath . '-wal',
            'savepoint' => $savepoint,
            'mode' => $mode,
            'page_size' => $wal->header->pageSize,
            'reader_end_frame' => $currentReaderEndFrame,
            'hot_recovered' => (bool) $hot['hot_recovered'],
            'retained_frame_count' => $rollback['retained_frame_count'],
            'discarded_frame_count' => $rollback['discarded_frame_count'],
            'current_checkpoint_busy' => $rollback['busy'],
            'current_checkpoint_reason' => $rollback['reason'],
            'released_checkpoint_busy' => (bool) $releasedCheckpoint['busy'],
            'released_checkpoint_reason' => $releasedCheckpoint['reason'],
            'released_wal_action' => $releasedCheckpoint['wal_action'],
            'current_wal_bytes_length' => strlen($currentWalBytes),
            'next_append_frame_count' => $append['appended_frame_count'],
            'next_append_last_commit_frame' => $append['last_commit_frame'],
            'current_sources' => $currentSources,
            'next_sources' => $nextSources,
            'current_frame_indexes' => array_column($rows, 'current_frame'),
            'next_frame_indexes' => array_column($rows, 'next_frame'),
            'source_transitions' => array_column($rows, 'source_transition'),
            'current_reader_pins_checkpoint' => (bool) $rollback['busy'],
            'reader_release_unblocks_checkpoint' => !(bool) $releasedCheckpoint['busy'],
            'next_reader_uses_new_generation' => in_array('wal', $nextSources, true),
            'rows' => $rows,
            'hot_journal' => $hot['hot_journal'],
            'rollback_checkpoint' => $rollback,
            'released_checkpoint' => $releasedCheckpoint,
            'append' => $append,
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
            'dependencies' => array_values(array_unique(array_merge(
                $hot['dependencies'],
                $rollback['dependencies'],
                $releasedCheckpoint['dependencies'],
                $append['dependencies'],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next153',
                    'sqlite-wal-hot-journal-checkpoint-restart-current-source-next129',
                    'sqlite-wal-savepoint-checkpoint-current',
                    'sqlite-wal-append-transaction',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses native hot rollback-journal recovery, WAL savepoint truncation, checkpoint, and append transaction primitives',
            'non_overlap' => 'does not repeat accepted hot-journal restart, savepoint byte truncation, rollback-journal apply, or checkpoint transaction slices; this combines hot recovered database pages with a rolled-back current WAL prefix before a released-reader checkpoint opens the next generation',
        ];
    }

    /**
     * @param array<string,mixed> $checkpoint
     */
    private static function nextWalAfterCheckpoint(SQLiteWal $wal, array $checkpoint): SQLiteWal
    {
        if (($checkpoint['wal_action'] ?? null) === 'preserve_wal') {
            return SQLiteWal::parse((string) $checkpoint['wal_bytes'], $wal->header->pageSize, true);
        }

        $salt = $checkpoint['next_wal_header_salt'];
        $headerBytes = pack(
            'N*',
            $wal->header->magic,
            $wal->header->formatVersion,
            $wal->header->pageSize,
            ($wal->header->checkpointSequence + 1) & 0xffffffff,
            $salt[0],
            $salt[1]
        );
        $checksum = SQLiteWal::checksumPair($headerBytes, $wal->header->usesLittleEndianChecksums());

        return SQLiteWal::parse($headerBytes . pack('N*', $checksum[0], $checksum[1]), $wal->header->pageSize, true);
    }

    /**
     * @return array{page_number:int,source:string,frame_index:null,database_offset:int,image:string,snapshot_end_frame:int,snapshot_commit_frame:null,database_page_count:int}
     */
    private static function databasePage(string $databaseBytes, int $pageSize, int $pageNumber, string $source): array
    {
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next153 database bytes must be page aligned');
        }
        $pageCount = intdiv(strlen($databaseBytes), $pageSize);
        if ($pageNumber > $pageCount) {
            throw new \OutOfBoundsException("SQLite WAL hot-journal savepoint checkpoint next153 page {$pageNumber} is outside the database image");
        }

        return [
            'page_number' => $pageNumber,
            'source' => $source,
            'frame_index' => null,
            'database_offset' => ($pageNumber - 1) * $pageSize,
            'image' => substr($databaseBytes, ($pageNumber - 1) * $pageSize, $pageSize),
            'snapshot_end_frame' => 0,
            'snapshot_commit_frame' => null,
            'database_page_count' => $pageCount,
        ];
    }

    private static function label(string $image): string
    {
        return rtrim(substr($image, 0, 96), ".\0");
    }
}
