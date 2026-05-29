<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalReaderCheckpointCurrentSourceNextPlan
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
        ?int $readerEndFrame = null,
        bool $reservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null
    ): array {
        if ($checkpointDatabaseBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal reader checkpoint current-source next148 requires checkpoint database bytes');
        }

        $base = SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan::next144Plan(
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

        $pageSize = (int) $base['page_size'];
        self::assertPageAligned($checkpointDatabaseBytes, $pageSize);
        $hotDatabaseBytes = (string) ($base['hot_journal']['database_bytes'] ?? '');
        if ($hotDatabaseBytes === '') {
            throw new \UnexpectedValueException('SQLite WAL hot-journal reader checkpoint current-source next148 requires recovered hot-journal database bytes');
        }
        self::assertPageAligned($hotDatabaseBytes, $pageSize);

        $readerWal = SQLiteWal::parse($readerWalBytes, $pageSize, true);
        $readerEndFrame = (int) $base['reader_end_frame'];
        $currentEndFrame = $currentWal->frameCount();

        $readerRows = [];
        $expectedCheckpointRows = [];
        $actualCheckpointRows = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal reader checkpoint current-source next148 pages must be one-based integers');
            }

            $readerRows[] = $readerWal->readerSnapshotPageImage($readerDatabaseBytes, $pageNumber, $readerEndFrame);
            $expectedCheckpointRows[] = $currentWal->readerSnapshotPageImage($hotDatabaseBytes, $pageNumber, $currentEndFrame);
            $actualCheckpointRows[] = self::databaseRow($checkpointDatabaseBytes, $pageSize, $pageNumber);
        }

        $rows = [];
        foreach ($pageNumbers as $index => $pageNumber) {
            $readerImage = (string) $readerRows[$index]['image'];
            $expectedImage = (string) $expectedCheckpointRows[$index]['image'];
            $actualImage = (string) $actualCheckpointRows[$index]['image'];
            $rows[] = [
                'page_number' => $pageNumber,
                'reader_source' => $readerRows[$index]['source'],
                'reader_frame' => $readerRows[$index]['frame_index'],
                'checkpoint_expected_source' => $expectedCheckpointRows[$index]['source'],
                'checkpoint_expected_frame' => $expectedCheckpointRows[$index]['frame_index'],
                'checkpoint_actual_source' => $actualCheckpointRows[$index]['source'],
                'reader_label' => self::label($readerImage),
                'checkpoint_expected_label' => self::label($expectedImage),
                'checkpoint_actual_label' => self::label($actualImage),
                'checkpoint_page_matches_expected' => $actualImage === $expectedImage,
                'reader_differs_from_checkpoint' => $readerImage !== $actualImage || $readerRows[$index]['source'] !== $actualCheckpointRows[$index]['source'],
                'source_transition' => $readerRows[$index]['source'] . '>' . $expectedCheckpointRows[$index]['source'] . '>checkpoint-db',
            ];
        }

        $mismatchedCheckpointPages = array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($rows, static fn (array $row): bool => $row['checkpoint_page_matches_expected'] === false)
        ));
        $readerSeparatedPages = array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($rows, static fn (array $row): bool => $row['reader_differs_from_checkpoint'] === true)
        ));
        $checkpointMatchesExpected = $mismatchedCheckpointPages === [];
        $checkpointAllowed = (bool) $base['checkpoint_allowed'] && $checkpointMatchesExpected;
        $status = !(bool) $base['hot_recovered']
            ? 'wal-hot-journal-reader-checkpoint-current-source-blocked-next148'
            : ($checkpointAllowed
                ? 'wal-hot-journal-reader-checkpoint-current-source-next148'
                : 'wal-hot-journal-reader-checkpoint-current-source-deferred-next148');

        return [
            'status' => $status,
            'reason' => !(bool) $base['hot_recovered']
                ? (string) $base['reason']
                : ($checkpointAllowed
                    ? 'checkpoint_database_matches_hot_journal_current_source_before_reader_reset'
                    : 'checkpoint_database_or_reader_source_mismatch_defers_reset'),
            'database_path' => $databasePath,
            'journal_path' => $base['journal_path'],
            'wal_path' => $base['wal_path'],
            'page_size' => $pageSize,
            'reader_end_frame' => $readerEndFrame,
            'hot_recovered' => (bool) $base['hot_recovered'],
            'base_checkpoint_allowed' => (bool) $base['checkpoint_allowed'],
            'checkpoint_allowed' => $checkpointAllowed,
            'reader_reopen_required' => (bool) $base['reader_reopen_required'] || !$checkpointMatchesExpected,
            'checkpoint_database_matches_expected' => $checkpointMatchesExpected,
            'checkpoint_mismatched_page_numbers' => $mismatchedCheckpointPages,
            'reader_separated_from_checkpoint_page_numbers' => $readerSeparatedPages,
            'reader_separated_from_checkpoint_page_count' => count($readerSeparatedPages),
            'reader_sources' => self::column($readerRows, 'source'),
            'reader_frame_indexes' => self::column($readerRows, 'frame_index'),
            'checkpoint_expected_sources' => self::column($expectedCheckpointRows, 'source'),
            'checkpoint_expected_frame_indexes' => self::column($expectedCheckpointRows, 'frame_index'),
            'checkpoint_actual_sources' => self::column($actualCheckpointRows, 'source'),
            'reader_labels' => array_column($rows, 'reader_label'),
            'checkpoint_expected_labels' => array_column($rows, 'checkpoint_expected_label'),
            'checkpoint_actual_labels' => array_column($rows, 'checkpoint_actual_label'),
            'checkpoint_database_source' => self::databaseSource($checkpointDatabaseBytes, $pageSize),
            'hot_database_source' => $base['current_database_source'],
            'reader_database_source' => $base['reader_database_source'],
            'current_wal_source' => $base['current_wal_source'],
            'reader_wal_source' => $base['reader_wal_source'],
            'rows' => $rows,
            'source_transitions' => array_column($rows, 'source_transition'),
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
            'operation_reasons' => array_merge($base['operation_reasons'], $checkpointAllowed ? [
                'apply_checkpoint_from_hot_journal_current_source_next148',
                'keep_reader_on_current_source_until_reopen_next148',
            ] : [
                'defer_checkpoint_reset_until_current_source_matches_next148',
            ]),
            'base_plan' => $base,
            'dependencies' => array_values(array_unique(array_merge($base['dependencies'], [
                'sqlite-wal-hot-journal-reader-checkpoint-current-source-next148',
                'sqlite-wal-checkpoint-hot-journal-reader-current-source-next144',
                'sqlite-checkpoint-database-source-token',
            ]))),
            'dependency_closure' => 'no new support component needed; reuses native hot-journal recovery, WAL reader snapshots, and checkpoint database source-token comparison',
            'non_overlap' => 'avoids next144 reader database-source admission and next143 reader restart generation by checking the checkpoint database bytes produced from the hot-recovered current source before reset',
        ];
    }

    private static function assertPageAligned(string $bytes, int $pageSize): void
    {
        if (strlen($bytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal reader checkpoint current-source next148 checkpoint database bytes must be page-size aligned');
        }
    }

    /**
     * @return array{page_number:int,source:string,frame_index:null,database_offset:int,image:string,snapshot_end_frame:int,snapshot_commit_frame:null,database_page_count:int}
     */
    private static function databaseRow(string $databaseBytes, int $pageSize, int $pageNumber): array
    {
        $pageCount = intdiv(strlen($databaseBytes), $pageSize);
        if ($pageNumber > $pageCount) {
            throw new \OutOfBoundsException("SQLite WAL hot-journal reader checkpoint current-source next148 page {$pageNumber} is outside the checkpoint database image");
        }

        return [
            'page_number' => $pageNumber,
            'source' => 'checkpoint-database',
            'frame_index' => null,
            'database_offset' => ($pageNumber - 1) * $pageSize,
            'image' => substr($databaseBytes, ($pageNumber - 1) * $pageSize, $pageSize),
            'snapshot_end_frame' => 0,
            'snapshot_commit_frame' => null,
            'database_page_count' => $pageCount,
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

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<mixed>
     */
    private static function column(array $rows, string $column): array
    {
        return array_map(static fn (array $row): mixed => $row[$column] ?? null, $rows);
    }

    private static function label(string $image): string
    {
        return rtrim(substr($image, 0, 96), ".\0");
    }
}
