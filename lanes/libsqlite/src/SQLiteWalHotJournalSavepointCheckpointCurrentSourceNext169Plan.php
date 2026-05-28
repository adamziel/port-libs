<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext169Plan
{
    /**
     * @param list<int> $pageNumbers
     * @param list<string> $completedOperationReasons
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
        array $completedOperationReasons = [],
        string $mode = 'restart',
        ?int $readerEndFrame = null,
        bool $reservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null,
    ): array {
        $base = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext165Plan::plan(
            $databasePath,
            $dirtyDatabaseBytes,
            $journalBytes,
            $savepoints,
            $savepoint,
            $wal,
            $walBytes,
            $pageNumbers,
            $mode,
            $readerEndFrame,
            $reservedLock,
            $requiresSuperJournal,
            $superJournalExists,
        );

        if (($base['status'] ?? '') !== 'wal-hot-journal-savepoint-checkpoint-current-source-next165') {
            return array_merge($base, [
                'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next169',
                'reason' => $base['reason'] ?? 'current_source_publish_not_admitted',
                'resume_admitted' => false,
                'dependencies' => array_values(array_unique(array_merge(
                    $base['dependencies'] ?? [],
                    ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next169']
                ))),
            ]);
        }

        $completed = [];
        foreach ($completedOperationReasons as $reason) {
            if (!is_string($reason) || trim($reason) === '') {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next169 completed operation reasons must be non-empty strings');
            }

            $completed[] = trim($reason);
        }
        $completed = array_values(array_unique($completed));

        $knownReasons = $base['operation_reasons'];
        $unknownReasons = array_values(array_diff($completed, $knownReasons));
        if ($unknownReasons !== []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next169 completed operation reason is not part of the publish plan');
        }

        $phaseRows = [];
        foreach ($base['operations'] as $index => $operation) {
            $reason = (string) $operation['reason'];
            $done = in_array($reason, $completed, true);
            $phaseRows[] = [
                'index' => $index + 1,
                'op' => $operation['op'],
                'path' => $operation['path'],
                'reason' => $reason,
                'completed' => $done,
                'crash_window' => self::crashWindow($reason),
                'resume_action' => $done ? 'verify_persisted' : self::resumeAction($reason),
                'journal_must_exist' => self::journalMustExist($reason, $done, $completed),
                'wal_reset_admitted' => self::walResetAdmitted($completed),
                'reader_release_admitted' => self::readerReleaseAdmitted($completed),
            ];
        }

        $pendingRows = array_values(array_filter($phaseRows, static fn (array $row): bool => !$row['completed']));
        $completedRows = array_values(array_filter($phaseRows, static fn (array $row): bool => (bool) $row['completed']));
        $nextReason = $pendingRows === [] ? null : $pendingRows[0]['reason'];
        $resumeComplete = $pendingRows === [];
        $journalDeleteDone = in_array('delete_hot_journal_after_current_source_checkpoint_next165', $completed, true);
        $currentDatabaseSynced = in_array('sync_current_checkpoint_before_reader_release_next165', $completed, true);
        $currentWalPreserved = in_array('preserve_retained_wal_for_pinned_reader_next165', $completed, true);
        $releasedDatabaseSynced = in_array('sync_released_checkpoint_after_savepoint_publish_next165', $completed, true);

        return [
            'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next169',
            'reason' => 'resume_hot_journal_savepoint_checkpoint_publish_after_partial_vfs_apply',
            'database_path' => $databasePath,
            'journal_path' => $base['journal_path'],
            'wal_path' => $base['wal_path'],
            'savepoint' => $savepoint,
            'mode' => $base['mode'],
            'page_size' => $base['page_size'],
            'page_numbers' => $base['page_numbers'],
            'resume_admitted' => true,
            'resume_complete' => $resumeComplete,
            'next_operation_reason' => $nextReason,
            'completed_operation_reasons' => $completed,
            'pending_operation_reasons' => array_column($pendingRows, 'reason'),
            'completed_count' => count($completedRows),
            'pending_count' => count($pendingRows),
            'current_database_synced' => $currentDatabaseSynced,
            'current_wal_preserved' => $currentWalPreserved,
            'journal_delete_admitted' => $currentDatabaseSynced && $currentWalPreserved,
            'journal_delete_completed' => $journalDeleteDone,
            'journal_required_for_recovery' => !$journalDeleteDone,
            'reader_release_admitted' => self::readerReleaseAdmitted($completed),
            'wal_reset_admitted' => self::walResetAdmitted($completed),
            'released_database_synced' => $releasedDatabaseSynced,
            'crash_windows' => array_values(array_unique(array_column($phaseRows, 'crash_window'))),
            'resume_actions' => array_values(array_unique(array_column($phaseRows, 'resume_action'))),
            'phase_rows' => $phaseRows,
            'phase_digest' => hash('sha256', implode('|', array_map(
                static fn (array $row): string => $row['reason'] . ':' . ($row['completed'] ? 'done' : 'pending') . ':' . $row['resume_action'],
                $phaseRows
            ))),
            'base_publish_digest' => $base['publish_digest'],
            'base_plan' => $base,
            'dependencies' => array_values(array_unique(array_merge($base['dependencies'], [
                'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next169',
                'sqlite-wal-checkpoint-partial-publish-resume',
                'wordpress-import-hot-journal-savepoint-crash-resume',
            ]))),
            'dependency_closure' => 'no new support component needed; reuses native PHP hot-journal recovery, WAL savepoint truncation, checkpoint payloads, and VFS publish operation metadata',
            'non_overlap' => 'extends next165 publish payload ordering with crash-resume admission fences; avoids accepted WAL byte truncation, VFS writer/apply, rollback-journal commit, checkpoint transaction, and reader-token admission surfaces',
        ];
    }

    private static function crashWindow(string $reason): string
    {
        return match ($reason) {
            'publish_hot_journal_savepoint_current_checkpoint_database_next165',
            'trim_database_after_current_checkpoint_publish_next165',
            'preserve_retained_wal_for_pinned_reader_next165',
            'sync_current_checkpoint_before_reader_release_next165' => 'current_source_checkpoint_publish',
            'delete_hot_journal_after_current_source_checkpoint_next165' => 'hot_journal_retirement',
            'publish_released_savepoint_checkpoint_database_next165',
            'restart_wal_after_savepoint_release_next165',
            'truncate_wal_after_savepoint_release_next165',
            'sync_released_checkpoint_after_savepoint_publish_next165' => 'released_savepoint_checkpoint_publish',
            default => 'unknown_publish_phase',
        };
    }

    private static function resumeAction(string $reason): string
    {
        return match ($reason) {
            'publish_hot_journal_savepoint_current_checkpoint_database_next165' => 'rewrite_current_checkpoint_database_payload',
            'trim_database_after_current_checkpoint_publish_next165' => 'retrim_current_checkpoint_database_size',
            'preserve_retained_wal_for_pinned_reader_next165' => 'rewrite_retained_wal_for_reader',
            'sync_current_checkpoint_before_reader_release_next165' => 'resync_current_checkpoint_database',
            'delete_hot_journal_after_current_source_checkpoint_next165' => 'delete_hot_journal_after_current_payloads_durable',
            'publish_released_savepoint_checkpoint_database_next165' => 'rewrite_released_checkpoint_database_payload',
            'restart_wal_after_savepoint_release_next165' => 'rewrite_restarted_wal_header',
            'truncate_wal_after_savepoint_release_next165' => 'truncate_released_wal_sidecar',
            'sync_released_checkpoint_after_savepoint_publish_next165' => 'resync_released_checkpoint_database',
            default => 'rerun_publish_operation',
        };
    }

    /**
     * @param list<string> $completed
     */
    private static function walResetAdmitted(array $completed): bool
    {
        return in_array('sync_current_checkpoint_before_reader_release_next165', $completed, true)
            && in_array('delete_hot_journal_after_current_source_checkpoint_next165', $completed, true)
            && in_array('publish_released_savepoint_checkpoint_database_next165', $completed, true);
    }

    /**
     * @param list<string> $completed
     */
    private static function readerReleaseAdmitted(array $completed): bool
    {
        return in_array('sync_current_checkpoint_before_reader_release_next165', $completed, true)
            && in_array('delete_hot_journal_after_current_source_checkpoint_next165', $completed, true);
    }

    /**
     * @param list<string> $completed
     */
    private static function journalMustExist(string $reason, bool $done, array $completed): bool
    {
        if (in_array('delete_hot_journal_after_current_source_checkpoint_next165', $completed, true)) {
            return false;
        }

        if ($reason === 'delete_hot_journal_after_current_source_checkpoint_next165') {
            return !$done;
        }

        return !self::readerReleaseAdmitted($completed);
    }
}
