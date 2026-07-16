<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalCheckpointReaderHotJournalCurrentSourceNextPlan
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
        string $readerWalBytes,
        array $pageNumbers,
        ?int $readerEndFrame = null,
        bool $reservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader hot-journal current-source next132 requires a database path');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader hot-journal current-source next132 requires database bytes');
        }
        if ($journalBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader hot-journal current-source next132 requires rollback journal bytes');
        }
        if ($currentWalBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader hot-journal current-source next132 requires current WAL bytes');
        }
        if ($readerWalBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader hot-journal current-source next132 requires reader WAL bytes');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader hot-journal current-source next132 requires page numbers');
        }
        if ($currentWal->toBytes() !== $currentWalBytes) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader hot-journal current-source next132 parsed WAL does not match current bytes');
        }

        $pageSize = $currentWal->header->pageSize;
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader hot-journal current-source next132 database bytes must be page-size aligned');
        }

        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL checkpoint reader hot-journal current-source next132 pages must be one-based integers');
            }
        }

        $readerWal = SQLiteWal::parse($readerWalBytes, $pageSize, true);
        $readerEndFrame ??= $readerWal->frameCount();
        if ($readerEndFrame < 0 || $readerEndFrame > $readerWal->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader hot-journal current-source next132 reader frame is outside the reader WAL frame range');
        }

        $currentHeader = self::walSource($currentWal, $currentWalBytes);
        $readerHeader = self::walSource($readerWal, $readerWalBytes);
        $sourceMatches = $currentHeader === $readerHeader;

        $journal = SQLiteRollbackJournal::parse($journalBytes, true);
        if ($journal->header->pageSize !== $pageSize) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader hot-journal current-source next132 journal page size does not match WAL page size');
        }

        $hot = $journal->hotJournalRecoveryResult(
            $databaseBytes,
            $journalBytes,
            $reservedLock,
            $requiresSuperJournal,
            $superJournalExists
        );
        $hotDatabaseBytes = (string) $hot['database_bytes'];

        $readerRows = [];
        $currentRows = [];
        foreach ($pageNumbers as $pageNumber) {
            $readerRows[] = $readerWal->readerSnapshotPageImage($databaseBytes, $pageNumber, $readerEndFrame);
            $currentRows[] = $currentWal->readerSnapshotPageImage($hotDatabaseBytes, $pageNumber, min($readerEndFrame, $currentWal->frameCount()));
        }

        $restart = null;
        if ($sourceMatches && $hot['recovered']) {
            $restart = SQLiteWalHotJournalCheckpointRestartCurrentSourceNextPlan::plan(
                $databasePath,
                $databaseBytes,
                $journalBytes,
                $currentWal,
                $currentWalBytes,
                $pageNumbers,
                min($readerEndFrame, $currentWal->frameCount()),
                $reservedLock,
                $requiresSuperJournal,
                $superJournalExists
            );
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
                'source_transition' => $readerRows[$index]['source'] . '>' . $currentRows[$index]['source'] . '>' . ($sourceMatches ? 'checkpoint' : 'reopen'),
            ];
        }

        $status = !$hot['recovered']
            ? 'wal-checkpoint-reader-hot-journal-current-source-blocked-next132'
            : ($sourceMatches
                ? 'wal-checkpoint-reader-hot-journal-current-source-next132'
                : 'wal-checkpoint-reader-hot-journal-current-source-stale-reader-next132');

        return [
            'status' => $status,
            'reason' => !$hot['recovered']
                ? $hot['reason']
                : ($sourceMatches
                    ? 'reader_wal_source_matches_hot_journal_current_source_checkpoint_allowed'
                    : 'reader_wal_source_mismatch_requires_reopen_before_checkpoint_reset'),
            'database_path' => $databasePath,
            'journal_path' => $databasePath . '-journal',
            'wal_path' => $databasePath . '-wal',
            'page_size' => $pageSize,
            'reader_end_frame' => $readerEndFrame,
            'hot_recovered' => (bool) $hot['recovered'],
            'journal_action' => $hot['journal_action'],
            'reader_source_matches_current' => $sourceMatches,
            'checkpoint_allowed' => $sourceMatches && (bool) $hot['recovered'],
            'reader_reopen_required' => !$sourceMatches && (bool) $hot['recovered'],
            'current_wal_sha256' => hash('sha256', $currentWalBytes),
            'reader_wal_sha256' => hash('sha256', $readerWalBytes),
            'current_wal_source' => $currentHeader,
            'reader_wal_source' => $readerHeader,
            'current_frame_count' => $currentWal->frameCount(),
            'reader_frame_count' => $readerWal->frameCount(),
            'reader_sources' => self::column($readerRows, 'source'),
            'current_sources' => self::column($currentRows, 'source'),
            'reader_frame_indexes' => self::column($readerRows, 'frame_index'),
            'current_frame_indexes' => self::column($currentRows, 'frame_index'),
            'reader_images_match_current' => !in_array(false, array_column($rows, 'reader_image_matches_current'), true),
            'rows' => $rows,
            'source_transitions' => array_column($rows, 'source_transition'),
            'operation_reasons' => $sourceMatches
                ? ($restart['operation_reasons'] ?? [])
                : [
                    'restore_hot_journal_database_before_reader_reopen_next132',
                    'preserve_current_wal_until_stale_reader_reopens_next132',
                    'defer_restart_checkpoint_for_current_source_reader_next132',
                ],
            'restart_plan' => $restart,
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
            'dependencies' => array_values(array_unique(array_merge(
                $restart['dependencies'] ?? [],
                [
                    'sqlite-wal-checkpoint-reader-hot-journal-current-source-next132',
                    'sqlite-hot-journal-recovery',
                    'sqlite-wal-reader-current-source-validation',
                ]
            ))),
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
