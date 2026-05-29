<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalReaderTruncateCurrentSourceNextPlan
{
    /**
     * @param list<array{pages:array<int,string>,database_page_count?:int|null,commit?:bool}> $nextTransactions
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
        array $nextTransactions,
        array $pageNumbers,
        ?int $readerEndFrame = null,
        bool $reservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null
    ): array {
        if ($nextTransactions === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal reader truncate current-source next137 requires next transactions');
        }

        $reader = SQLiteWalCheckpointReaderHotJournalCurrentSourceNext132Plan::plan(
            $databasePath,
            $databaseBytes,
            $journalBytes,
            $currentWal,
            $currentWalBytes,
            $readerWalBytes,
            $pageNumbers,
            $readerEndFrame,
            $reservedLock,
            $requiresSuperJournal,
            $superJournalExists
        );

        $hotDatabaseBytes = (string) ($reader['restart_plan']['hot_journal']['database_bytes'] ?? '');
        if ($hotDatabaseBytes === '') {
            return [
                'status' => 'wal-hot-journal-reader-truncate-current-source-blocked-next137',
                'reason' => 'hot_journal_reader_current_source_not_ready_for_truncate_checkpoint',
                'database_path' => $databasePath,
                'journal_path' => $reader['journal_path'],
                'wal_path' => $reader['wal_path'],
                'page_size' => $reader['page_size'],
                'reader_end_frame' => $reader['reader_end_frame'],
                'hot_recovered' => (bool) $reader['hot_recovered'],
                'reader_source_matches_current' => (bool) $reader['reader_source_matches_current'],
                'checkpoint_allowed' => (bool) $reader['checkpoint_allowed'],
                'truncate_ready' => false,
                'reader_plan' => $reader,
                'truncate_plan' => null,
                'dependencies' => array_values(array_unique(array_merge(
                    $reader['dependencies'],
                    ['sqlite-wal-hot-journal-reader-truncate-current-source-next137']
                ))),
            ];
        }

        $truncate = SQLiteWalCheckpointTruncateReaderCurrentSourceNext134Plan::plan(
            $currentWal,
            $currentWalBytes,
            $readerWalBytes,
            $hotDatabaseBytes,
            $databasePath,
            $nextTransactions,
            $pageNumbers,
            (int) $reader['reader_end_frame']
        );

        $rows = [];
        foreach ($pageNumbers as $index => $pageNumber) {
            $readerRow = $reader['rows'][$index];
            $truncateRow = $truncate['rows'][$index];
            $rows[] = [
                'page_number' => $pageNumber,
                'dirty_to_hot_source' => $readerRow['source_transition'],
                'truncate_source' => $truncateRow['source_transition'],
                'reader_label' => $readerRow['reader_label'],
                'hot_current_label' => $readerRow['current_label'],
                'pinned_after_checkpoint_label' => $truncateRow['pinned_after_checkpoint_label'],
                'next_label' => $truncateRow['next_label'],
                'hot_current_preserved_by_pinned_checkpoint' => $readerRow['current_label'] === $truncateRow['pinned_after_checkpoint_label'],
                'next_generation_changed_image' => $truncateRow['next_matches_current'] === false,
                'source_transition' => $readerRow['source_transition'] . '>' . $truncateRow['source_transition'],
            ];
        }

        $status = (bool) $reader['checkpoint_allowed']
            && $truncate['status'] === 'wal-checkpoint-truncate-reader-current-source-next134'
            ? 'wal-hot-journal-reader-truncate-current-source-next137'
            : 'wal-hot-journal-reader-truncate-current-source-blocked-next137';

        return [
            'status' => $status,
            'reason' => $status === 'wal-hot-journal-reader-truncate-current-source-next137'
                ? 'hot_journal_recovery_feeds_truncate_checkpoint_while_current_reader_pins_source'
                : 'hot_journal_reader_current_source_not_ready_for_truncate_checkpoint',
            'database_path' => $databasePath,
            'journal_path' => $reader['journal_path'],
            'wal_path' => $reader['wal_path'],
            'page_size' => $reader['page_size'],
            'reader_end_frame' => $reader['reader_end_frame'],
            'hot_recovered' => (bool) $reader['hot_recovered'],
            'reader_source_matches_current' => (bool) $reader['reader_source_matches_current'],
            'checkpoint_allowed' => (bool) $reader['checkpoint_allowed'],
            'truncate_ready' => $truncate['status'] === 'wal-checkpoint-truncate-reader-current-source-next134',
            'current_reader_pins_reset' => (bool) $truncate['current_reader_pins_reset'],
            'reader_release_unblocked_truncate' => (bool) $truncate['reader_release_unblocked_truncate'],
            'truncate_removed_old_wal_sidecar' => (bool) $truncate['truncate_removed_old_wal_sidecar'],
            'next_reader_uses_fresh_wal_generation' => (bool) $truncate['next_reader_uses_fresh_wal_generation'],
            'fresh_wal_checkpoint_sequence' => $truncate['fresh_wal_checkpoint_sequence'],
            'next_append_start_frame' => $truncate['next_append_start_frame'],
            'next_append_end_frame' => $truncate['next_append_end_frame'],
            'next_append_frame_count' => $truncate['next_append_frame_count'],
            'current_sources' => $truncate['current_sources'],
            'pinned_after_checkpoint_sources' => $truncate['pinned_after_checkpoint_sources'],
            'next_sources' => $truncate['next_sources'],
            'rows' => $rows,
            'source_transitions' => array_column($rows, 'source_transition'),
            'hot_current_preserved_by_pinned_checkpoint' => !in_array(false, array_column($rows, 'hot_current_preserved_by_pinned_checkpoint'), true),
            'next_generation_changed_page_numbers' => array_values(array_map(
                static fn (array $row): int => (int) $row['page_number'],
                array_filter($rows, static fn (array $row): bool => (bool) $row['next_generation_changed_image'])
            )),
            'operation_reasons' => array_values(array_unique(array_merge(
                $reader['operation_reasons'],
                [
                    'feed_hot_journal_database_into_truncate_checkpoint_next137',
                    'pin_current_reader_until_truncate_checkpoint_released_next137',
                    'append_next_writer_after_truncate_on_fresh_wal_generation_next137',
                ]
            ))),
            'reader_plan' => $reader,
            'truncate_plan' => $truncate,
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
            'dependencies' => array_values(array_unique(array_merge(
                $reader['dependencies'],
                $truncate['dependencies'],
                [
                    'sqlite-wal-hot-journal-reader-truncate-current-source-next137',
                    'sqlite-wal-checkpoint-reader-hot-journal-current-source-next132',
                    'sqlite-wal-checkpoint-truncate-reader-current-source-next134',
                ]
            ))),
        ];
    }
}
