<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext155Plan
{
    /**
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $dirtyDatabaseBytes,
        string $journalBytes,
        SQLiteWal $currentWal,
        string $currentWalBytes,
        string $readerDatabaseBytes,
        string $readerWalBytes,
        string $checkpointDatabaseBytes,
        array $pageNumbers,
        int $savepointRollbackFrame,
        ?int $readerEndFrame = null,
        bool $reservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null
    ): array {
        if ($savepointRollbackFrame < 0 || $savepointRollbackFrame > $currentWal->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next155 rollback frame is outside the current WAL range');
        }
        if ($readerEndFrame !== null && $readerEndFrame < $savepointRollbackFrame) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next155 reader frame must not precede the rollback frame');
        }

        $base = SQLiteWalCheckpointHotJournalReaderCurrentSourceNext144Plan::plan(
            $databasePath,
            $dirtyDatabaseBytes,
            $journalBytes,
            $currentWal,
            $currentWalBytes,
            $readerDatabaseBytes,
            $readerWalBytes,
            $pageNumbers,
            $readerEndFrame,
            $reservedLock,
            $requiresSuperJournal,
            $superJournalExists
        );

        if ($checkpointDatabaseBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next155 requires checkpoint database bytes');
        }

        $pageSize = (int) $base['page_size'];
        self::assertPageAligned($checkpointDatabaseBytes, $pageSize);
        $hotDatabaseBytes = (string) ($base['hot_journal']['database_bytes'] ?? '');
        if ($hotDatabaseBytes === '') {
            throw new \UnexpectedValueException('SQLite WAL hot-journal savepoint checkpoint current-source next155 requires recovered hot-journal database bytes');
        }
        self::assertPageAligned($hotDatabaseBytes, $pageSize);

        $readerWal = SQLiteWal::parse($readerWalBytes, $pageSize, true);
        $readerEndFrame = (int) $base['reader_end_frame'];
        $rows = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next155 pages must be one-based integers');
            }

            $reader = $readerWal->readerSnapshotPageImage($readerDatabaseBytes, $pageNumber, $readerEndFrame);
            $rollbackExpected = $currentWal->readerSnapshotPageImage($hotDatabaseBytes, $pageNumber, $savepointRollbackFrame);
            $fullCurrent = $currentWal->readerSnapshotPageImage($hotDatabaseBytes, $pageNumber, $currentWal->frameCount());
            $checkpoint = self::databaseRow($checkpointDatabaseBytes, $pageSize, $pageNumber);
            $readerLabel = self::label((string) $reader['image']);
            $rollbackLabel = self::label((string) $rollbackExpected['image']);
            $fullLabel = self::label((string) $fullCurrent['image']);
            $checkpointLabel = self::label((string) $checkpoint['image']);

            $rows[] = [
                'page_number' => $pageNumber,
                'reader_source' => $reader['source'],
                'reader_frame' => $reader['frame_index'],
                'reader_label' => $readerLabel,
                'rollback_expected_source' => $rollbackExpected['source'],
                'rollback_expected_frame' => $rollbackExpected['frame_index'],
                'rollback_expected_label' => $rollbackLabel,
                'full_current_source' => $fullCurrent['source'],
                'full_current_frame' => $fullCurrent['frame_index'],
                'full_current_label' => $fullLabel,
                'checkpoint_source' => $checkpoint['source'],
                'checkpoint_label' => $checkpointLabel,
                'checkpoint_matches_rollback_source' => (string) $checkpoint['image'] === (string) $rollbackExpected['image'],
                'reader_kept_post_rollback_frame' => $readerLabel !== $rollbackLabel || $reader['source'] !== $rollbackExpected['source'],
                'full_current_differs_from_rollback' => $fullLabel !== $rollbackLabel || $fullCurrent['source'] !== $rollbackExpected['source'],
                'source_transition' => $reader['source'] . '>savepoint-rollback>' . $rollbackExpected['source'] . '>checkpoint-db',
            ];
        }

        $mismatchPages = array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($rows, static fn (array $row): bool => $row['checkpoint_matches_rollback_source'] === false)
        ));
        $postRollbackPages = array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($rows, static fn (array $row): bool => $row['reader_kept_post_rollback_frame'] === true)
        ));
        $discardedCurrentPages = array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($rows, static fn (array $row): bool => $row['full_current_differs_from_rollback'] === true)
        ));

        $checkpointMatchesRollback = $mismatchPages === [];
        $checkpointAllowed = (bool) $base['checkpoint_allowed'] && $checkpointMatchesRollback;
        $status = !(bool) $base['hot_recovered']
            ? 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next155'
            : ($checkpointAllowed
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next155'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-deferred-next155');

        return [
            'status' => $status,
            'reason' => !(bool) $base['hot_recovered']
                ? (string) $base['reason']
                : ($checkpointAllowed
                    ? 'checkpoint_uses_hot_journal_current_source_with_savepoint_rollback_wal_prefix_next155'
                    : 'checkpoint_database_does_not_match_savepoint_rollback_current_source_next155'),
            'database_path' => $databasePath,
            'journal_path' => $base['journal_path'],
            'wal_path' => $base['wal_path'],
            'page_size' => $pageSize,
            'reader_end_frame' => $readerEndFrame,
            'savepoint_rollback_frame' => $savepointRollbackFrame,
            'current_wal_frame_count' => $currentWal->frameCount(),
            'hot_recovered' => (bool) $base['hot_recovered'],
            'base_checkpoint_allowed' => (bool) $base['checkpoint_allowed'],
            'checkpoint_allowed' => $checkpointAllowed,
            'checkpoint_matches_savepoint_rollback_source' => $checkpointMatchesRollback,
            'checkpoint_mismatched_page_numbers' => $mismatchPages,
            'reader_post_rollback_page_numbers' => $postRollbackPages,
            'reader_post_rollback_page_count' => count($postRollbackPages),
            'discarded_current_page_numbers' => $discardedCurrentPages,
            'discarded_current_page_count' => count($discardedCurrentPages),
            'reader_reopen_required' => (bool) $base['reader_reopen_required'] || $postRollbackPages !== [] || !$checkpointMatchesRollback,
            'reader_sources' => array_column($rows, 'reader_source'),
            'reader_frame_indexes' => array_column($rows, 'reader_frame'),
            'rollback_expected_sources' => array_column($rows, 'rollback_expected_source'),
            'rollback_expected_frame_indexes' => array_column($rows, 'rollback_expected_frame'),
            'full_current_sources' => array_column($rows, 'full_current_source'),
            'full_current_frame_indexes' => array_column($rows, 'full_current_frame'),
            'checkpoint_sources' => array_column($rows, 'checkpoint_source'),
            'reader_labels' => array_column($rows, 'reader_label'),
            'rollback_expected_labels' => array_column($rows, 'rollback_expected_label'),
            'full_current_labels' => array_column($rows, 'full_current_label'),
            'checkpoint_labels' => array_column($rows, 'checkpoint_label'),
            'source_transitions' => array_column($rows, 'source_transition'),
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
            'checkpoint_database_source' => self::databaseSource($checkpointDatabaseBytes, $pageSize),
            'base_plan' => $base,
            'rows' => $rows,
            'operation_reasons' => array_merge($base['operation_reasons'], $checkpointAllowed ? [
                'apply_checkpoint_from_savepoint_rollback_wal_prefix_next155',
                'require_reader_reopen_for_post_rollback_frames_next155',
            ] : [
                'defer_checkpoint_until_savepoint_rollback_source_matches_next155',
            ]),
            'dependencies' => array_values(array_unique(array_merge($base['dependencies'], [
                'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next155',
                'sqlite-wal-checkpoint-hot-journal-reader-current-source-next144',
                'sqlite-savepoint-rollback-wal-prefix-current-source',
            ]))),
            'dependency_closure' => 'no new support component needed; reuses native hot-journal recovery, WAL reader snapshots, and savepoint rollback frame-boundary selection',
            'non_overlap' => 'avoids accepted next148 full-current-WAL checkpoint comparison by requiring checkpoint bytes to match the savepoint rollback WAL prefix while preserving reader visibility of post-rollback frames until reopen',
        ];
    }

    private static function assertPageAligned(string $bytes, int $pageSize): void
    {
        if (strlen($bytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next155 database bytes must be page-size aligned');
        }
    }

    /**
     * @return array{page_number:int,source:string,frame_index:null,database_offset:int,image:string}
     */
    private static function databaseRow(string $databaseBytes, int $pageSize, int $pageNumber): array
    {
        $pageCount = intdiv(strlen($databaseBytes), $pageSize);
        if ($pageNumber > $pageCount) {
            throw new \OutOfBoundsException("SQLite WAL hot-journal savepoint checkpoint current-source next155 page {$pageNumber} is outside the checkpoint database image");
        }

        return [
            'page_number' => $pageNumber,
            'source' => 'checkpoint-database',
            'frame_index' => null,
            'database_offset' => ($pageNumber - 1) * $pageSize,
            'image' => substr($databaseBytes, ($pageNumber - 1) * $pageSize, $pageSize),
        ];
    }

    /**
     * @return array{bytes:int,page_size:int,page_count:int,sha256:string}
     */
    private static function databaseSource(string $databaseBytes, int $pageSize): array
    {
        return [
            'bytes' => strlen($databaseBytes),
            'page_size' => $pageSize,
            'page_count' => intdiv(strlen($databaseBytes), $pageSize),
            'sha256' => hash('sha256', $databaseBytes),
        ];
    }

    private static function label(string $image): string
    {
        return rtrim(substr($image, 0, 96), ".\0");
    }
}
