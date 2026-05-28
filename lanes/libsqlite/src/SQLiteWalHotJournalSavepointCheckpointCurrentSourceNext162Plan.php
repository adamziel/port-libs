<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext162Plan
{
    /**
     * @param list<int> $pageNumbers
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
        string $mode = 'restart',
        ?int $readerEndFrame = null,
        bool $reservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null,
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next162 requires a database path');
        }
        if ($dirtyDatabaseBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next162 requires database bytes');
        }
        if ($journalBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next162 requires rollback journal bytes');
        }
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next162 requires a savepoint');
        }
        if ($walBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next162 requires WAL bytes');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next162 requires page numbers');
        }
        if ($wal->toBytes() !== $walBytes) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next162 parsed WAL does not match bytes');
        }

        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next162 requires restart or truncate mode');
        }

        $pageSize = $wal->header->pageSize;
        if ($pageSize < 512 || strlen($dirtyDatabaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next162 database bytes must be page-size aligned');
        }
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next162 pages must be one-based integers');
            }
        }

        $journal = SQLiteRollbackJournal::parse($journalBytes, true);
        if ($journal->header->pageSize !== $pageSize) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next162 journal page size does not match WAL page size');
        }

        $hot = $journal->hotJournalRecoveryResult(
            $dirtyDatabaseBytes,
            $journalBytes,
            $reservedLock,
            $requiresSuperJournal,
            $superJournalExists
        );
        if (!$hot['recovered']) {
            return [
                'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next162',
                'reason' => $hot['reason'],
                'database_path' => $databasePath,
                'journal_path' => $databasePath . '-journal',
                'wal_path' => $databasePath . '-wal',
                'savepoint' => $savepoint,
                'mode' => $mode,
                'page_size' => $pageSize,
                'hot_recovered' => false,
                'current_source_admitted' => false,
                'dependencies' => [
                    'sqlite-rollback-journal-hot-recovery',
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next162',
                ],
            ];
        }

        $hotDatabaseBytes = (string) $hot['database_bytes'];
        $rollback = $savepoints->walRollbackToByteTruncationPlan($savepoint, $wal, $walBytes);
        $retainedWalBytes = $savepoints->walRollbackToWalBytes($savepoint, $wal, $walBytes);
        $retainedWal = SQLiteWal::parse($retainedWalBytes, $pageSize, true);
        $readerEndFrame ??= $retainedWal->frameCount();
        if ($readerEndFrame < 0 || $readerEndFrame > $retainedWal->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next162 reader frame is outside retained WAL');
        }

        $currentCheckpoint = $retainedWal->durableCheckpointResult($hotDatabaseBytes, $mode, $readerEndFrame);
        $releasedCheckpoint = $retainedWal->durableCheckpointResult($hotDatabaseBytes, $mode);
        $staleCheckpoint = $retainedWal->durableCheckpointResult($dirtyDatabaseBytes, $mode, $readerEndFrame);

        $rows = [];
        foreach ($pageNumbers as $pageNumber) {
            $dirty = self::databasePage($dirtyDatabaseBytes, $pageSize, $pageNumber);
            $hotRow = self::databasePage($hotDatabaseBytes, $pageSize, $pageNumber);
            $retained = $readerEndFrame === 0
                ? $hotRow
                : $retainedWal->readerSnapshotPageImage($hotDatabaseBytes, $pageNumber, $readerEndFrame);
            $current = self::checkpointPage($currentCheckpoint, $pageSize, $pageNumber);
            $released = self::checkpointPage($releasedCheckpoint, $pageSize, $pageNumber);
            $stale = self::checkpointPage($staleCheckpoint, $pageSize, $pageNumber);

            $rows[] = [
                'page_number' => $pageNumber,
                'dirty_source' => $dirty['source'],
                'hot_source' => $hotRow['source'],
                'retained_source' => $retained['source'],
                'current_checkpoint_source' => $current['source'],
                'released_checkpoint_source' => $released['source'],
                'stale_checkpoint_source' => $stale['source'],
                'retained_frame' => $retained['frame_index'],
                'current_checkpoint_frame' => $current['frame_index'],
                'released_checkpoint_frame' => $released['frame_index'],
                'dirty_label' => self::label((string) $dirty['image']),
                'hot_label' => self::label((string) $hotRow['image']),
                'retained_label' => self::label((string) $retained['image']),
                'current_checkpoint_label' => self::label((string) $current['image']),
                'released_checkpoint_label' => self::label((string) $released['image']),
                'stale_checkpoint_label' => self::label((string) $stale['image']),
                'hot_replaced_dirty' => $dirty['image'] !== $hotRow['image'],
                'current_checkpoint_preserves_retained' => $current['image'] === $retained['image'],
                'released_checkpoint_preserves_retained' => $released['image'] === $retained['image'],
                'stale_checkpoint_would_publish_dirty' => $stale['image'] !== $current['image'],
                'source_transition' => implode('>', [$dirty['source'], $hotRow['source'], $retained['source'], $current['source'], $released['source']]),
            ];
        }

        $stalePages = array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($rows, static fn (array $row): bool => (bool) $row['stale_checkpoint_would_publish_dirty'])
        ));

        return [
            'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next162',
            'reason' => 'hot_journal_current_source_required_before_savepoint_checkpoint_publish',
            'database_path' => $databasePath,
            'journal_path' => $databasePath . '-journal',
            'wal_path' => $databasePath . '-wal',
            'savepoint' => $savepoint,
            'mode' => $mode,
            'page_size' => $pageSize,
            'page_numbers' => array_values($pageNumbers),
            'hot_recovered' => true,
            'journal_action' => $hot['journal_action'],
            'original_frame_count' => $wal->frameCount(),
            'retained_frame_count' => $retainedWal->frameCount(),
            'discarded_frame_count' => $rollback['discarded_frame_count'],
            'discarded_frame_indexes' => array_column($rollback['discarded_wal_frames'], 'frame_index'),
            'reader_end_frame' => $readerEndFrame,
            'current_checkpoint_busy' => $currentCheckpoint['busy'],
            'current_checkpoint_reason' => $currentCheckpoint['reason'],
            'current_checkpoint_wal_action' => $currentCheckpoint['wal_action'],
            'released_checkpoint_busy' => $releasedCheckpoint['busy'],
            'released_checkpoint_reason' => $releasedCheckpoint['reason'],
            'released_checkpoint_wal_action' => $releasedCheckpoint['wal_action'],
            'stale_checkpoint_rejected' => $stalePages !== [],
            'stale_checkpoint_dirty_page_numbers' => $stalePages,
            'dirty_database_sha256' => hash('sha256', $dirtyDatabaseBytes),
            'hot_database_sha256' => hash('sha256', $hotDatabaseBytes),
            'retained_wal_sha256' => hash('sha256', $retainedWalBytes),
            'current_checkpoint_database_sha256' => hash('sha256', (string) $currentCheckpoint['database_bytes']),
            'released_checkpoint_database_sha256' => hash('sha256', (string) $releasedCheckpoint['database_bytes']),
            'current_checkpoint_database_bytes' => (string) $currentCheckpoint['database_bytes'],
            'released_checkpoint_database_bytes' => (string) $releasedCheckpoint['database_bytes'],
            'current_checkpoint_wal_bytes_length' => strlen((string) $currentCheckpoint['wal_bytes']),
            'released_checkpoint_wal_bytes_length' => strlen((string) $releasedCheckpoint['wal_bytes']),
            'rows' => $rows,
            'source_transitions' => array_column($rows, 'source_transition'),
            'retained_sources' => array_column($rows, 'retained_source'),
            'current_checkpoint_sources' => array_column($rows, 'current_checkpoint_source'),
            'released_checkpoint_sources' => array_column($rows, 'released_checkpoint_source'),
            'retained_frame_indexes' => array_column($rows, 'retained_frame'),
            'current_checkpoint_frame_indexes' => array_column($rows, 'current_checkpoint_frame'),
            'released_checkpoint_frame_indexes' => array_column($rows, 'released_checkpoint_frame'),
            'hot_recovery_replaced_dirty_images' => in_array(true, array_column($rows, 'hot_replaced_dirty'), true),
            'current_checkpoint_preserved_retained_images' => !in_array(false, array_column($rows, 'current_checkpoint_preserves_retained'), true),
            'released_checkpoint_preserved_retained_images' => !in_array(false, array_column($rows, 'released_checkpoint_preserves_retained'), true),
            'current_source_admitted' => $stalePages !== [] && hash('sha256', $dirtyDatabaseBytes) !== hash('sha256', $hotDatabaseBytes),
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
            'operation_reasons' => [
                'recover_hot_journal_before_savepoint_checkpoint_next162',
                'rollback_savepoint_wal_prefix_after_hot_journal_next162',
                'reject_stale_dirty_database_checkpoint_source_next162',
                'publish_checkpoint_from_hot_current_source_next162',
            ],
            'dependencies' => array_values(array_unique(array_merge(
                $currentCheckpoint['dependencies'],
                $releasedCheckpoint['dependencies'],
                [
                    'sqlite-rollback-journal-hot-recovery',
                    'sqlite-savepoint-wal-prefix-truncation',
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next162',
                    'wordpress-import-current-source-checkpoint-admission',
                ]
            ))),
        ];
    }

    /**
     * @return array{page_number:int,source:string,frame_index:null,database_offset:int,image:string,snapshot_end_frame:int,snapshot_commit_frame:null,database_page_count:int}
     */
    private static function databasePage(string $databaseBytes, int $pageSize, int $pageNumber): array
    {
        $pageCount = intdiv(strlen($databaseBytes), $pageSize);
        if ($pageNumber > $pageCount) {
            throw new \OutOfBoundsException("SQLite WAL hot-journal savepoint checkpoint current-source next162 page {$pageNumber} is outside database image");
        }

        return [
            'page_number' => $pageNumber,
            'source' => 'database',
            'frame_index' => null,
            'database_offset' => ($pageNumber - 1) * $pageSize,
            'image' => substr($databaseBytes, ($pageNumber - 1) * $pageSize, $pageSize),
            'snapshot_end_frame' => 0,
            'snapshot_commit_frame' => null,
            'database_page_count' => $pageCount,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function checkpointPage(array $checkpoint, int $pageSize, int $pageNumber): array
    {
        $walBytes = (string) $checkpoint['wal_bytes'];
        if ($walBytes === '') {
            return self::databasePage((string) $checkpoint['database_bytes'], $pageSize, $pageNumber);
        }

        $wal = SQLiteWal::parse($walBytes, $pageSize, true);
        return $wal->readerSnapshotPageImage((string) $checkpoint['database_bytes'], $pageNumber, $wal->frameCount());
    }

    private static function label(string $image): string
    {
        return rtrim(substr($image, 0, 96), ".\0");
    }
}
