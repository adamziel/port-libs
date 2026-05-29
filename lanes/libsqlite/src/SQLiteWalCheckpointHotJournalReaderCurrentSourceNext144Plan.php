<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalCheckpointHotJournalReaderCurrentSourceNext144Plan
{
    /**
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $databaseBytes,
        string $journalBytes,
        SQLiteWal $currentWal,
        string $currentWalBytes,
        string $readerDatabaseBytes,
        string $readerWalBytes,
        array $pageNumbers,
        ?int $readerEndFrame = null,
        bool $reservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal reader current-source next144 requires a database path');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal reader current-source next144 requires database bytes');
        }
        if ($journalBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal reader current-source next144 requires rollback journal bytes');
        }
        if ($currentWalBytes === '' || $readerWalBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal reader current-source next144 requires WAL bytes');
        }
        if ($readerDatabaseBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal reader current-source next144 requires reader database bytes');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal reader current-source next144 requires page numbers');
        }
        if ($currentWal->toBytes() !== $currentWalBytes) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal reader current-source next144 parsed WAL does not match current bytes');
        }

        $pageSize = $currentWal->header->pageSize;
        self::assertPageAligned($databaseBytes, $pageSize, 'database');
        self::assertPageAligned($readerDatabaseBytes, $pageSize, 'reader database');
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal reader current-source next144 pages must be one-based integers');
            }
        }

        $journal = SQLiteRollbackJournal::parse($journalBytes, true);
        if ($journal->header->pageSize !== $pageSize) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal reader current-source next144 journal page size does not match WAL page size');
        }

        $readerWal = SQLiteWal::parse($readerWalBytes, $pageSize, true);
        $readerEndFrame ??= $readerWal->frameCount();
        if ($readerEndFrame < 0 || $readerEndFrame > $readerWal->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal reader current-source next144 reader frame is outside the reader WAL frame range');
        }

        $hot = $journal->hotJournalRecoveryResult(
            $databaseBytes,
            $journalBytes,
            $reservedLock,
            $requiresSuperJournal,
            $superJournalExists
        );
        $hotDatabaseBytes = (string) $hot['database_bytes'];
        $currentDatabaseSource = self::databaseSource($hotDatabaseBytes, $pageSize);
        $readerDatabaseSource = self::databaseSource($readerDatabaseBytes, $pageSize);
        $currentWalSource = self::walSource($currentWal, $currentWalBytes);
        $readerWalSource = self::walSource($readerWal, $readerWalBytes);
        $walSourceMatches = $currentWalSource === $readerWalSource;
        $databaseSourceMatches = $currentDatabaseSource === $readerDatabaseSource;

        $readerRows = [];
        $currentRows = [];
        foreach ($pageNumbers as $pageNumber) {
            $readerRows[] = $readerWal->readerSnapshotPageImage($readerDatabaseBytes, $pageNumber, $readerEndFrame);
            $currentRows[] = $currentWal->readerSnapshotPageImage($hotDatabaseBytes, $pageNumber, min($readerEndFrame, $currentWal->frameCount()));
        }

        $rows = [];
        foreach ($pageNumbers as $index => $pageNumber) {
            $readerImage = (string) $readerRows[$index]['image'];
            $currentImage = (string) $currentRows[$index]['image'];
            $rows[] = [
                'page_number' => $pageNumber,
                'reader_source' => $readerRows[$index]['source'],
                'current_source' => $currentRows[$index]['source'],
                'reader_frame' => $readerRows[$index]['frame_index'],
                'current_frame' => $currentRows[$index]['frame_index'],
                'reader_label' => self::label($readerImage),
                'current_label' => self::label($currentImage),
                'reader_image_matches_current' => $readerImage === $currentImage,
                'database_source_transition' => $readerDatabaseSource['sha256'] . '>' . $currentDatabaseSource['sha256'],
                'source_transition' => $readerRows[$index]['source'] . '>' . $currentRows[$index]['source'] . '>' . ($databaseSourceMatches ? 'same-db-source' : 'reopen-db-source'),
            ];
        }

        $checkpointAllowed = (bool) $hot['recovered'] && $walSourceMatches && $databaseSourceMatches;
        $status = !$hot['recovered']
            ? 'wal-checkpoint-hot-journal-reader-current-source-blocked-next144'
            : ($checkpointAllowed
                ? 'wal-checkpoint-hot-journal-reader-current-source-next144'
                : 'wal-checkpoint-hot-journal-reader-current-source-reopen-next144');

        return [
            'status' => $status,
            'reason' => !$hot['recovered']
                ? $hot['reason']
                : ($checkpointAllowed
                    ? 'reader_wal_and_database_source_match_hot_journal_checkpoint_current_source'
                    : 'reader_database_source_mismatch_requires_reopen_before_checkpoint_reset'),
            'database_path' => $databasePath,
            'journal_path' => $databasePath . '-journal',
            'wal_path' => $databasePath . '-wal',
            'page_size' => $pageSize,
            'reader_end_frame' => $readerEndFrame,
            'hot_recovered' => (bool) $hot['recovered'],
            'journal_action' => $hot['journal_action'],
            'wal_source_matches_current' => $walSourceMatches,
            'database_source_matches_current' => $databaseSourceMatches,
            'checkpoint_allowed' => $checkpointAllowed,
            'reader_reopen_required' => (bool) $hot['recovered'] && (!$walSourceMatches || !$databaseSourceMatches),
            'current_database_source' => $currentDatabaseSource,
            'reader_database_source' => $readerDatabaseSource,
            'current_wal_source' => $currentWalSource,
            'reader_wal_source' => $readerWalSource,
            'current_frame_count' => $currentWal->frameCount(),
            'reader_frame_count' => $readerWal->frameCount(),
            'reader_sources' => self::column($readerRows, 'source'),
            'current_sources' => self::column($currentRows, 'source'),
            'reader_frame_indexes' => self::column($readerRows, 'frame_index'),
            'current_frame_indexes' => self::column($currentRows, 'frame_index'),
            'reader_images_match_current' => !in_array(false, array_column($rows, 'reader_image_matches_current'), true),
            'mismatched_page_numbers' => array_values(array_map(
                static fn (array $row): int => (int) $row['page_number'],
                array_filter($rows, static fn (array $row): bool => $row['reader_image_matches_current'] === false)
            )),
            'rows' => $rows,
            'source_transitions' => array_column($rows, 'source_transition'),
            'operation_reasons' => $checkpointAllowed
                ? [
                    'pin_reader_database_source_after_hot_journal_recovery_next144',
                    'allow_checkpoint_reset_for_matching_reader_source_next144',
                ]
                : [
                    'restore_hot_journal_database_before_reader_source_recheck_next144',
                    'preserve_current_wal_until_database_source_reader_reopens_next144',
                    'defer_checkpoint_reset_for_stale_database_source_next144',
                ],
            'hot_journal' => $hot,
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
            'dependencies' => [
                'sqlite-wal-checkpoint-hot-journal-reader-current-source-next144',
                'sqlite-hot-journal-recovery',
                'sqlite-wal-reader-current-source-validation',
                'sqlite-reader-database-source-token',
            ],
        ];
    }

    private static function assertPageAligned(string $bytes, int $pageSize, string $label): void
    {
        if (strlen($bytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException("SQLite WAL checkpoint hot-journal reader current-source next144 {$label} bytes must be page-size aligned");
        }
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
     * @return array{magic:int,version:int,page_size:int,checkpoint_sequence:int,salt_1:int,salt_2:int,frame_count:int,sha256:string}
     */
    private static function walSource(SQLiteWal $wal, string $walBytes): array
    {
        return [
            'magic' => $wal->header->magic,
            'version' => $wal->header->formatVersion,
            'page_size' => $wal->header->pageSize,
            'checkpoint_sequence' => $wal->header->checkpointSequence,
            'salt_1' => $wal->header->salt1,
            'salt_2' => $wal->header->salt2,
            'frame_count' => $wal->frameCount(),
            'sha256' => hash('sha256', $walBytes),
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
