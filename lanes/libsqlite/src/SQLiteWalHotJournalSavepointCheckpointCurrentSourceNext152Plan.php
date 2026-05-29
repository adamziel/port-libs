<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext152Plan
{
    /**
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $databaseBytes,
        string $journalBytes,
        SQLiteWal $wal,
        string $walBytes,
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        array $pageNumbers,
        string $mode = 'restart',
        ?int $readerEndFrame = null,
        bool $reservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next152 requires a database path');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next152 requires database bytes');
        }
        if ($journalBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next152 requires rollback journal bytes');
        }
        if ($walBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next152 requires WAL bytes');
        }
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next152 requires a savepoint name');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next152 requires page numbers');
        }
        if ($wal->toBytes() !== $walBytes) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next152 parsed WAL does not match current bytes');
        }

        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['passive', 'full', 'restart', 'truncate'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite WAL hot-journal savepoint checkpoint next152 mode: {$mode}");
        }

        $pageSize = $wal->header->pageSize;
        self::assertPageAligned($databaseBytes, $pageSize, 'database');
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next152 pages must be one-based integers');
            }
        }

        $journal = SQLiteRollbackJournal::parse($journalBytes, true);
        if ($journal->header->pageSize !== $pageSize) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next152 journal page size does not match WAL page size');
        }

        $hot = $journal->hotJournalRecoveryResult(
            $databaseBytes,
            $journalBytes,
            $reservedLock,
            $requiresSuperJournal,
            $superJournalExists
        );
        if (!$hot['recovered']) {
            return [
                'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next152',
                'reason' => $hot['reason'],
                'database_path' => $databasePath,
                'journal_path' => $databasePath . '-journal',
                'wal_path' => $databasePath . '-wal',
                'page_size' => $pageSize,
                'savepoint' => $savepoint,
                'mode' => $mode,
                'hot_recovered' => false,
                'journal_action' => $hot['journal_action'],
                'checkpoint_allowed' => false,
                'reader_end_frame' => $readerEndFrame,
                'hot_journal' => $hot,
                'dependencies' => [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next152',
                    'sqlite-hot-journal-recovery',
                ],
            ];
        }

        $hotDatabaseBytes = (string) $hot['database_bytes'];
        $checkpoint = SQLiteWalSavepointCheckpointPlan::afterRollbackTo(
            $savepoints,
            $savepoint,
            $wal,
            $walBytes,
            $hotDatabaseBytes,
            $mode,
            $readerEndFrame
        );
        $retainedWal = SQLiteWal::parse((string) $checkpoint['current_wal_bytes'], $pageSize, true);
        $durable = $checkpoint['current_durable'];
        $nextWal = $durable['wal_bytes'] === ''
            ? null
            : SQLiteWal::parse((string) $durable['wal_bytes'], $pageSize, true);
        $readerEndFrame ??= $retainedWal->frameCount();
        if ($readerEndFrame < 0 || $readerEndFrame > $retainedWal->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next152 reader frame is outside retained WAL range');
        }

        $rows = [];
        foreach ($pageNumbers as $pageNumber) {
            $dirty = self::databasePageVisibility($databaseBytes, $pageSize, $pageNumber);
            $recovered = self::databasePageVisibility($hotDatabaseBytes, $pageSize, $pageNumber);
            $current = $retainedWal->readerSnapshotPageImage($hotDatabaseBytes, $pageNumber, $readerEndFrame);
            $next = $nextWal === null
                ? self::databasePageVisibility((string) $durable['database_bytes'], $pageSize, $pageNumber)
                : $nextWal->readerSnapshotPageImage((string) $durable['database_bytes'], $pageNumber, $nextWal->frameCount());
            $rows[] = [
                'page_number' => $pageNumber,
                'dirty_source' => $dirty['source'],
                'hot_source' => $recovered['source'],
                'current_source' => $current['source'],
                'next_source' => $next['source'],
                'current_frame' => $current['frame_index'],
                'next_frame' => $next['frame_index'],
                'dirty_label' => self::label((string) $dirty['image']),
                'hot_label' => self::label((string) $recovered['image']),
                'current_label' => self::label((string) $current['image']),
                'next_label' => self::label((string) $next['image']),
                'hot_restored_dirty_page' => $dirty['image'] !== $recovered['image'],
                'savepoint_changed_current' => $recovered['image'] !== $current['image'],
                'checkpoint_changed_next' => $current['image'] !== $next['image'],
                'source_transition' => $dirty['source'] . '>' . $recovered['source'] . '>' . $current['source'] . '>' . $next['source'],
            ];
        }

        return [
            'status' => $checkpoint['busy']
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-busy-next152'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-next152',
            'reason' => $checkpoint['busy']
                ? $checkpoint['reason']
                : 'hot_journal_recovery_precedes_savepoint_wal_checkpoint_current_source',
            'database_path' => $databasePath,
            'journal_path' => $databasePath . '-journal',
            'wal_path' => $databasePath . '-wal',
            'page_size' => $pageSize,
            'savepoint' => $savepoint,
            'mode' => $mode,
            'reader_end_frame' => $readerEndFrame,
            'hot_recovered' => true,
            'journal_action' => $hot['journal_action'],
            'checkpoint_allowed' => !$checkpoint['busy'],
            'wal_action' => $durable['wal_action'],
            'checkpoint_reason' => $checkpoint['reason'],
            'original_frame_count' => $checkpoint['original_frame_count'],
            'retained_frame_count' => $checkpoint['retained_frame_count'],
            'discarded_frame_count' => $checkpoint['discarded_frame_count'],
            'truncate_to_bytes' => $checkpoint['truncate_to_bytes'],
            'current_wal_bytes_length' => $checkpoint['current_wal_bytes_length'],
            'durable_wal_bytes_length' => $durable['wal_bytes_length'],
            'hot_database_sha256' => hash('sha256', $hotDatabaseBytes),
            'durable_database_sha256' => hash('sha256', (string) $durable['database_bytes']),
            'current_sources' => array_column($rows, 'current_source'),
            'next_sources' => array_column($rows, 'next_source'),
            'current_frame_indexes' => array_column($rows, 'current_frame'),
            'next_frame_indexes' => array_column($rows, 'next_frame'),
            'source_transitions' => array_column($rows, 'source_transition'),
            'hot_restored_page_numbers' => self::pageColumn($rows, 'hot_restored_dirty_page'),
            'savepoint_restored_page_numbers' => self::pageColumn($rows, 'savepoint_changed_current'),
            'checkpoint_changed_page_numbers' => self::pageColumn($rows, 'checkpoint_changed_next'),
            'rows' => $rows,
            'hot_journal' => $hot,
            'checkpoint' => $checkpoint,
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
            'dependencies' => array_values(array_unique(array_merge(
                $checkpoint['dependencies'],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next152',
                    'sqlite-hot-journal-recovery',
                    'sqlite-savepoint-wal-current-prefix',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses native rollback-journal recovery, WAL savepoint byte truncation, and checkpoint durability helpers',
            'non_overlap' => 'avoids accepted next145/next146 reader restart/truncate work by requiring hot rollback-journal recovery to establish the current database source before savepoint WAL rollback and checkpoint',
        ];
    }

    private static function assertPageAligned(string $bytes, int $pageSize, string $label): void
    {
        if (strlen($bytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint next152 {$label} bytes must be page-size aligned");
        }
    }

    /**
     * @return array{page_number:int,source:string,frame_index:null,database_offset:int,image:string}
     */
    private static function databasePageVisibility(string $databaseBytes, int $pageSize, int $pageNumber): array
    {
        $offset = ($pageNumber - 1) * $pageSize;
        if ($pageNumber < 1 || $offset + $pageSize > strlen($databaseBytes)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next152 page is outside database image');
        }

        return [
            'page_number' => $pageNumber,
            'source' => 'database',
            'frame_index' => null,
            'database_offset' => $offset,
            'image' => substr($databaseBytes, $offset, $pageSize),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<int>
     */
    private static function pageColumn(array $rows, string $flag): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($rows, static fn (array $row): bool => $row[$flag] === true)
        ));
    }

    private static function label(string $image): string
    {
        return rtrim(substr($image, 0, 96), ".\0");
    }
}
