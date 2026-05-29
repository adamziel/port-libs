<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalCheckpointHotJournalSavepointCurrentSourceNextPlan
{
    /**
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public static function plan(
        SQLiteRollbackJournal $journal,
        string $databaseBytes,
        string $journalBytes,
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $databasePath,
        array $pageNumbers,
        string $mode = 'restart',
        ?int $readerEndFrame = null,
        ?int $databasePageSize = null,
        bool $databaseReservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null,
    ): array {
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint requires a savepoint name');
        }
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint requires a database path');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint requires page numbers');
        }
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint page numbers must be one-based integers');
            }
        }
        if ($journal->toBytes() !== $journalBytes) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint parsed journal does not match current-source bytes');
        }
        if ($wal->toBytes() !== $walBytes) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint parsed WAL does not match current-source bytes');
        }

        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite hot-journal savepoint checkpoint mode: {$mode}");
        }

        $pageSize = self::pageSize($wal, $databasePageSize, $databaseBytes);
        $dirtyWal = SQLiteWal::parse($walBytes, $pageSize, true);
        $dirtyReader = self::readerRows($dirtyWal, $databaseBytes, $pageNumbers, $dirtyWal->frameCount());

        $recovery = SQLitePagerHotJournalWalRecoveryPlan::recover(
            $journal,
            $databaseBytes,
            $journalBytes,
            $walBytes,
            $databasePath,
            $pageSize,
            $databaseReservedLock,
            $requiresSuperJournal,
            $superJournalExists
        );

        $hotDatabaseBytes = $recovery['payloads'][$databasePath . '#hot-journal'] ?? $databaseBytes;
        if (!is_string($hotDatabaseBytes)) {
            throw new \UnexpectedValueException('SQLite hot-journal savepoint checkpoint did not expose recovered database bytes');
        }

        $committedWalBytes = (string) $recovery['wal_recovery']['committed_wal_bytes'];
        $committedWal = SQLiteWal::parse($committedWalBytes, $pageSize, true);
        $hotReader = self::readerRows($committedWal, $hotDatabaseBytes, $pageNumbers, $committedWal->frameCount());

        $checkpoint = SQLiteWalSavepointCheckpointPlan::afterRollbackTo(
            $savepoints,
            $savepoint,
            $committedWal,
            $committedWalBytes,
            $hotDatabaseBytes,
            $mode,
            $readerEndFrame
        );

        $currentWal = SQLiteWal::parse((string) $checkpoint['current_wal_bytes'], $pageSize, true);
        $currentReaderEndFrame = $currentWal->frameCount();
        $currentReader = self::readerRows($currentWal, $hotDatabaseBytes, $pageNumbers, $currentReaderEndFrame);

        $durable = $checkpoint['current_durable'];
        $nextDatabaseBytes = (string) $durable['database_bytes'];
        $nextWalBytes = (string) $durable['wal_bytes'];
        $nextWal = $nextWalBytes === '' ? null : SQLiteWal::parse($nextWalBytes, $pageSize, true);
        $nextReaderEndFrame = $nextWal?->frameCount() ?? 0;
        $nextReader = $nextWal === null
            ? self::databaseRows($nextDatabaseBytes, $pageSize, $pageNumbers)
            : self::readerRows($nextWal, $nextDatabaseBytes, $pageNumbers, $nextReaderEndFrame);

        $dirtySources = self::column($dirtyReader, 'source');
        $hotSources = self::column($hotReader, 'source');
        $currentSources = self::column($currentReader, 'source');
        $nextSources = self::column($nextReader, 'source');
        $dirtyImages = self::column($dirtyReader, 'image');
        $hotImages = self::column($hotReader, 'image');
        $currentImages = self::column($currentReader, 'image');
        $nextImages = self::column($nextReader, 'image');

        return [
            'status' => $recovery['hot_recovered']
                ? ($checkpoint['busy'] ? 'busy' : 'hot-journal-savepoint-checkpoint-ready-next114')
                : 'hot-journal-savepoint-checkpoint-skipped-next114',
            'reason' => $recovery['hot_recovered']
                ? 'hot_journal_recovered_before_savepoint_checkpoint_current_source'
                : 'hot_journal_not_hot_savepoint_checkpoint_current_source',
            'database_path' => $databasePath,
            'journal_path' => $databasePath . '-journal',
            'wal_path' => $databasePath . '-wal',
            'savepoint' => $savepoint,
            'mode' => $mode,
            'page_size' => $pageSize,
            'page_numbers' => array_values($pageNumbers),
            'dirty_reader_end_frame' => $dirtyWal->frameCount(),
            'hot_reader_end_frame' => $committedWal->frameCount(),
            'current_reader_end_frame' => $currentReaderEndFrame,
            'next_reader_end_frame' => $nextReaderEndFrame,
            'hot_recovered' => $recovery['hot_recovered'],
            'journal_action' => $recovery['journal_action'],
            'wal_status' => $recovery['wal_status'],
            'committed_frame_count' => $recovery['committed_frame_count'],
            'discarded_valid_tail_frame_count' => $recovery['discarded_valid_tail_frame_count'],
            'discarded_corrupt_tail_frame_count' => $recovery['discarded_corrupt_tail_frame_count'],
            'retained_frame_count' => $checkpoint['retained_frame_count'],
            'savepoint_discarded_frame_count' => $checkpoint['discarded_frame_count'],
            'checkpoint_busy' => $checkpoint['busy'],
            'checkpoint_reason' => $checkpoint['reason'],
            'wal_action' => $durable['wal_action'],
            'dirty_reader' => $dirtyReader,
            'hot_reader' => $hotReader,
            'current_reader' => $currentReader,
            'next_reader' => $nextReader,
            'dirty_sources' => $dirtySources,
            'hot_sources' => $hotSources,
            'current_sources' => $currentSources,
            'next_sources' => $nextSources,
            'dirty_frame_indexes' => self::column($dirtyReader, 'frame_index'),
            'hot_frame_indexes' => self::column($hotReader, 'frame_index'),
            'current_frame_indexes' => self::column($currentReader, 'frame_index'),
            'next_frame_indexes' => self::column($nextReader, 'frame_index'),
            'dirty_to_hot_images_match' => $dirtyImages === $hotImages,
            'hot_to_current_images_match' => $hotImages === $currentImages,
            'current_to_next_images_match' => $currentImages === $nextImages,
            'next_uses_checkpoint_database' => !in_array('wal', $nextSources, true),
            'next_uses_preserved_wal' => in_array('wal', $nextSources, true),
            'current_uses_recovered_hot_database' => $recovery['hot_recovered'],
            'current_uses_savepoint_wal_prefix' => in_array('wal', $currentSources, true),
            'source_transitions' => self::sourceTransitions($dirtySources, $hotSources, $currentSources, $nextSources),
            'rows' => self::rows($pageNumbers, $dirtyReader, $hotReader, $currentReader, $nextReader),
            'hot_recovery' => $recovery,
            'checkpoint' => $checkpoint,
            'operation_reasons' => self::operationReasons($recovery['operations'], $checkpoint, $durable),
            'source_digest' => hash('sha256', $databaseBytes . $journalBytes . $committedWalBytes . $savepoint . $mode),
            'dependencies' => array_values(array_unique(array_merge(
                $recovery['dependencies'],
                $checkpoint['dependencies'],
                [
                    'sqlite-wal-checkpoint-hot-journal-savepoint-current-source-next114',
                    'sqlite-hot-journal-before-savepoint-checkpoint-current-source',
                ]
            ))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return list<array<string,mixed>>
     */
    private static function readerRows(SQLiteWal $wal, string $databaseBytes, array $pageNumbers, int $endFrame): array
    {
        $rows = [];
        foreach ($pageNumbers as $pageNumber) {
            $rows[] = $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $endFrame);
        }

        return $rows;
    }

    /**
     * @param list<int> $pageNumbers
     * @return list<array<string,mixed>>
     */
    private static function databaseRows(string $databaseBytes, int $pageSize, array $pageNumbers): array
    {
        $rows = [];
        foreach ($pageNumbers as $pageNumber) {
            $offset = ($pageNumber - 1) * $pageSize;
            $rows[] = [
                'page_number' => $pageNumber,
                'source' => 'database',
                'frame_index' => null,
                'database_offset' => $offset,
                'image' => substr($databaseBytes, $offset, $pageSize),
                'snapshot_end_frame' => 0,
                'snapshot_commit_frame' => null,
                'database_page_count' => intdiv(strlen($databaseBytes), $pageSize),
            ];
        }

        return $rows;
    }

    private static function pageSize(SQLiteWal $wal, ?int $databasePageSize, string $databaseBytes): int
    {
        if ($wal->header->pageSize >= 512) {
            return $wal->header->pageSize;
        }
        if ($databasePageSize !== null && $databasePageSize >= 512) {
            return $databasePageSize;
        }
        if ($databaseBytes !== '') {
            return SQLiteHeader::parse($databaseBytes)->pageSize;
        }

        throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint requires a page size');
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<mixed>
     */
    private static function column(array $rows, string $column): array
    {
        return array_map(static fn (array $row): mixed => $row[$column] ?? null, $rows);
    }

    /**
     * @param list<string> $dirty
     * @param list<string> $hot
     * @param list<string> $current
     * @param list<string> $next
     * @return list<string>
     */
    private static function sourceTransitions(array $dirty, array $hot, array $current, array $next): array
    {
        $transitions = [];
        foreach ($dirty as $index => $source) {
            $transitions[] = $source . '>' . ($hot[$index] ?? 'missing') . '>' . ($current[$index] ?? 'missing') . '>' . ($next[$index] ?? 'missing');
        }

        return $transitions;
    }

    /**
     * @param list<int> $pageNumbers
     * @param list<array<string,mixed>> $dirty
     * @param list<array<string,mixed>> $hot
     * @param list<array<string,mixed>> $current
     * @param list<array<string,mixed>> $next
     * @return list<array<string,mixed>>
     */
    private static function rows(array $pageNumbers, array $dirty, array $hot, array $current, array $next): array
    {
        $rows = [];
        foreach ($pageNumbers as $index => $pageNumber) {
            $dirtyImage = (string) ($dirty[$index]['image'] ?? '');
            $hotImage = (string) ($hot[$index]['image'] ?? '');
            $currentImage = (string) ($current[$index]['image'] ?? '');
            $nextImage = (string) ($next[$index]['image'] ?? '');
            $rows[] = [
                'page_number' => $pageNumber,
                'dirty_source' => $dirty[$index]['source'] ?? null,
                'hot_source' => $hot[$index]['source'] ?? null,
                'current_source' => $current[$index]['source'] ?? null,
                'next_source' => $next[$index]['source'] ?? null,
                'dirty_frame' => $dirty[$index]['frame_index'] ?? null,
                'hot_frame' => $hot[$index]['frame_index'] ?? null,
                'current_frame' => $current[$index]['frame_index'] ?? null,
                'next_frame' => $next[$index]['frame_index'] ?? null,
                'dirty_label' => rtrim(substr($dirtyImage, 0, 80), ".\0"),
                'hot_label' => rtrim(substr($hotImage, 0, 80), ".\0"),
                'current_label' => rtrim(substr($currentImage, 0, 80), ".\0"),
                'next_label' => rtrim(substr($nextImage, 0, 80), ".\0"),
                'hot_recovery_changed_current' => $dirtyImage !== $hotImage,
                'savepoint_rollback_changed_current' => $hotImage !== $currentImage,
                'checkpoint_changed_next' => $currentImage !== $nextImage,
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $recoveryOperations
     * @param array<string,mixed> $checkpoint
     * @param array<string,mixed> $durable
     * @return list<string>
     */
    private static function operationReasons(array $recoveryOperations, array $checkpoint, array $durable): array
    {
        $reasons = [];
        foreach ($recoveryOperations as $operation) {
            if (isset($operation['reason'])) {
                $reasons[] = (string) $operation['reason'];
            }
        }
        $reasons[] = 'rollback_savepoint_to_hot_journal_recovered_wal_prefix';
        $reasons[] = 'checkpoint_recovered_savepoint_wal_prefix';
        $reasons[] = ((string) $durable['wal_action']) . '_after_hot_journal_savepoint_checkpoint';
        if (($checkpoint['busy'] ?? false) === true) {
            $reasons[] = 'preserve_wal_reset_until_reader_releases_hot_journal_savepoint_checkpoint';
        }

        return $reasons;
    }
}
