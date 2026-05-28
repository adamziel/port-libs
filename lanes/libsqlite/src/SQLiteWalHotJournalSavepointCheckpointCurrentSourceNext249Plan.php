<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext249Plan
{
    /**
     * @param array<string,mixed> $handoffPlan
     * @param array<string,mixed> $reopenedState
     * @return array<string,mixed>
     */
    public static function verifyReopenedCurrentSource(array $handoffPlan, array $reopenedState): array
    {
        if (($handoffPlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next246'
            || ($handoffPlan['durable_handoff_admitted'] ?? null) !== true
        ) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next249 requires an admitted next246 durable handoff');
        }

        $databasePath = self::path($handoffPlan['database_path'] ?? null, 'database path');
        $walPath = self::path($handoffPlan['wal_path'] ?? null, 'wal path');
        $journalPath = self::path($handoffPlan['journal_path'] ?? null, 'journal path');
        $sourceToken = self::token($handoffPlan['source_token'] ?? null, 'source token');
        $commitGeneration = self::positiveInt($handoffPlan['commit_generation'] ?? null, 'commit generation');
        $schemaCookie = self::positiveInt($handoffPlan['schema_cookie'] ?? null, 'schema cookie');
        $databaseDigest = self::digest($handoffPlan['database_digest'] ?? null, 'database digest');
        $pageCacheDigest = self::digest($handoffPlan['page_cache_digest'] ?? null, 'page cache digest');
        $checkpointFrame = self::nonNegativeInt($handoffPlan['checkpoint_frame'] ?? null, 'checkpoint frame');
        $dirtyPages = self::positiveIntSet($handoffPlan['dirty_pages'] ?? null, 'dirty pages');
        $commitFrames = self::positiveIntSet($handoffPlan['commit_frames'] ?? null, 'commit frames');
        $readerNames = self::tokenSet($handoffPlan['accepted_reader_names'] ?? null, 'accepted reader names');

        $observedDatabaseDigest = self::digest($reopenedState['database_digest'] ?? null, 'reopened database digest');
        $observedPageCacheDigest = self::digest($reopenedState['page_cache_digest'] ?? null, 'reopened page cache digest');
        $observedCommitFrames = self::positiveIntSet($reopenedState['wal_commit_frames'] ?? null, 'reopened WAL commit frames');
        $observedCleanPages = self::positiveIntSet($reopenedState['clean_page_numbers'] ?? null, 'reopened clean page numbers');
        $readerRows = [];
        foreach (self::readerStates($reopenedState['reader_states'] ?? null) as $readerState) {
            $readerRows[] = self::readerRow($readerState, $readerNames, $sourceToken, $commitGeneration, $checkpointFrame);
        }
        $observedReaderNames = array_values(array_unique(array_column($readerRows, 'name')));
        $missingReaderNames = array_values(array_diff($readerNames, $observedReaderNames));

        $missingFrames = array_values(array_diff($commitFrames, $observedCommitFrames));
        $extraFrames = array_values(array_diff($observedCommitFrames, $commitFrames));
        $missingCleanPages = array_values(array_diff($dirtyPages, $observedCleanPages));
        $blockedReaderRows = array_values(array_filter($readerRows, static fn (array $row): bool => !$row['admitted']));

        $databaseMatches = hash_equals($databaseDigest, $observedDatabaseDigest);
        $pageCacheMatches = hash_equals($pageCacheDigest, $observedPageCacheDigest);
        $journalRetired = ($reopenedState['journal_exists'] ?? null) === false;
        $walRetained = ($reopenedState['wal_exists'] ?? null) === true;
        $schemaCookieMatches = ($reopenedState['schema_cookie'] ?? null) === $schemaCookie;
        $generationMatches = ($reopenedState['commit_generation'] ?? null) === $commitGeneration;
        $sourceMatches = self::token($reopenedState['source_token'] ?? null, 'reopened source token') === $sourceToken;
        $pathsMatch = self::path($reopenedState['database_path'] ?? null, 'reopened database path') === $databasePath
            && self::path($reopenedState['wal_path'] ?? null, 'reopened WAL path') === $walPath
            && self::path($reopenedState['journal_path'] ?? null, 'reopened journal path') === $journalPath;

        $guardRows = [
            [
                'name' => 'database_digest_matches_handoff',
                'matched' => $databaseMatches,
                'reason' => 'reopened database bytes must be the durable checkpoint image promoted by next246',
            ],
            [
                'name' => 'page_cache_digest_matches_handoff',
                'matched' => $pageCacheMatches,
                'reason' => 'clean page cache must be derived from the same checkpoint image',
            ],
            [
                'name' => 'wal_commit_frames_match_handoff',
                'matched' => $missingFrames === [] && $extraFrames === [],
                'reason' => 'the reopened WAL must expose exactly the committed frames kept for reader continuity',
            ],
            [
                'name' => 'dirty_pages_clean_after_reopen',
                'matched' => $missingCleanPages === [],
                'reason' => 'every dirty page written by the checkpoint handoff must reopen as clean',
            ],
            [
                'name' => 'hot_journal_retired_after_reopen',
                'matched' => $journalRetired,
                'reason' => 'the hot journal must be gone after checkpoint bytes and directory sync are durable',
            ],
            [
                'name' => 'wal_sidecar_retained_for_reader_epoch',
                'matched' => $walRetained,
                'reason' => 'next246 retains committed WAL frames until reader epochs advance',
            ],
            [
                'name' => 'schema_cookie_generation_and_source_match',
                'matched' => $schemaCookieMatches && $generationMatches && $sourceMatches && $pathsMatch,
                'reason' => 'reopened metadata must still point at the admitted current source',
            ],
            [
                'name' => 'all_readers_reopened_on_current_source',
                'matched' => $blockedReaderRows === [] && $missingReaderNames === [],
                'reason' => 'reader snapshots must reopen on the same source token, generation, and checkpoint frame',
            ],
        ];
        $blockedGuards = array_values(array_column(
            array_filter($guardRows, static fn (array $row): bool => !$row['matched']),
            'name'
        ));
        $admitted = $blockedGuards === [];

        $blockedReasons = [];
        if (!$databaseMatches) {
            $blockedReasons[] = 'reopened_database_digest_mismatch';
        }
        if (!$pageCacheMatches) {
            $blockedReasons[] = 'reopened_page_cache_digest_mismatch';
        }
        if ($missingFrames !== []) {
            $blockedReasons[] = 'reopened_wal_commit_frame_missing';
        }
        if ($extraFrames !== []) {
            $blockedReasons[] = 'reopened_wal_commit_frame_unexpected';
        }
        if ($missingCleanPages !== []) {
            $blockedReasons[] = 'reopened_dirty_page_not_clean';
        }
        if (!$journalRetired) {
            $blockedReasons[] = 'reopened_hot_journal_still_exists';
        }
        if (!$walRetained) {
            $blockedReasons[] = 'reopened_wal_sidecar_missing';
        }
        if (!$schemaCookieMatches) {
            $blockedReasons[] = 'reopened_schema_cookie_mismatch';
        }
        if (!$generationMatches) {
            $blockedReasons[] = 'reopened_commit_generation_mismatch';
        }
        if (!$sourceMatches) {
            $blockedReasons[] = 'reopened_source_token_mismatch';
        }
        if (!$pathsMatch) {
            $blockedReasons[] = 'reopened_path_mismatch';
        }
        if ($missingReaderNames !== []) {
            $blockedReasons[] = 'reopened_reader_missing';
        }
        foreach ($blockedReaderRows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        return [
            'status' => $admitted
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next249'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next249',
            'reason' => $admitted
                ? 'reopened_files_confirm_durable_checkpoint_current_source'
                : 'reopened_files_hold_prior_checkpoint_current_source',
            'base_status' => $handoffPlan['status'],
            'database_path' => $databasePath,
            'wal_path' => $walPath,
            'journal_path' => $journalPath,
            'source_token' => $sourceToken,
            'commit_generation' => $commitGeneration,
            'schema_cookie' => $schemaCookie,
            'checkpoint_frame' => $checkpointFrame,
            'database_digest' => $databaseDigest,
            'page_cache_digest' => $pageCacheDigest,
            'observed_database_digest' => $observedDatabaseDigest,
            'observed_page_cache_digest' => $observedPageCacheDigest,
            'dirty_pages' => $dirtyPages,
            'clean_page_numbers' => $observedCleanPages,
            'missing_clean_pages' => $missingCleanPages,
            'expected_commit_frames' => $commitFrames,
            'observed_commit_frames' => $observedCommitFrames,
            'missing_commit_frames' => $missingFrames,
            'unexpected_commit_frames' => $extraFrames,
            'reader_rows' => $readerRows,
            'expected_reader_names' => $readerNames,
            'missing_reader_names' => $missingReaderNames,
            'accepted_reader_names' => array_values(array_column(
                array_filter($readerRows, static fn (array $row): bool => $row['admitted']),
                'name'
            )),
            'blocked_reader_names' => array_values(array_column($blockedReaderRows, 'name')),
            'journal_exists_after_reopen' => $reopenedState['journal_exists'],
            'wal_exists_after_reopen' => $reopenedState['wal_exists'],
            'blocked_reasons' => $blockedReasons,
            'reopened_current_source_admitted' => $admitted,
            'checkpoint_action' => $admitted ? 'serve_checkpoint_database_as_current_source' : 'retain_prior_current_source_until_reopen_matches',
            'wal_action' => $admitted ? 'keep_wal_sidecar_until_reader_epoch_advances' : 'preserve_wal_for_recovery_replay',
            'journal_action' => $admitted ? 'confirm_hot_journal_retired' : 'treat_hot_journal_as_recovery_blocker',
            'reader_action' => $admitted ? 'serve_reopened_readers_from_generation_' . $commitGeneration : 'hold_readers_on_prior_generation',
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'blocked_guard_names' => $blockedGuards,
            'reopen_digest' => hash('sha256', json_encode([$sourceToken, $commitGeneration, $databaseDigest, $pageCacheDigest, $observedCommitFrames, $observedCleanPages, $readerRows, $guardRows], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($handoffPlan['operation_names'] ?? null) ? $handoffPlan['operation_names'] : [],
                [
                    'verify_reopened_current_source_after_durable_handoff_next249',
                    $admitted ? 'admit_reopened_current_source_next249' : 'hold_reopened_current_source_next249',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($handoffPlan['dependencies'] ?? null) ? $handoffPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next249',
                    'sqlite-wal-reopen-current-source-after-durable-handoff',
                    'wordpress-import-reopen-after-hot-journal-checkpoint',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next246 durable handoff metadata, reopened file digests, WAL commit-frame inventory, clean page-cache inventory, and reader epoch rows',
            'non_overlap' => 'next249 verifies reopened current-source visibility after an accepted next246 durable handoff; it does not repeat VFS receipt ordering, checkpoint transaction planning, WAL byte truncation, savepoint rollback application, rollback-journal commit/apply, reader snapshot admission, JSON, SELECT, or B-tree behavior',
        ];
    }

    /**
     * @param list<string> $readerNames
     * @return array<string,mixed>
     */
    private static function readerRow(array $readerState, array $readerNames, string $sourceToken, int $commitGeneration, int $checkpointFrame): array
    {
        $name = self::token($readerState['name'] ?? null, 'reader name');
        $reasons = [];

        if (!in_array($name, $readerNames, true)) {
            $reasons[] = 'reopened_reader_unexpected';
        }
        if (self::token($readerState['source_token'] ?? null, "{$name} source token") !== $sourceToken) {
            $reasons[] = 'reopened_reader_source_token_mismatch';
        }
        if (self::positiveInt($readerState['reader_generation'] ?? null, "{$name} reader generation") !== $commitGeneration) {
            $reasons[] = 'reopened_reader_generation_mismatch';
        }
        if (self::nonNegativeInt($readerState['checkpoint_frame'] ?? null, "{$name} checkpoint frame") !== $checkpointFrame) {
            $reasons[] = 'reopened_reader_checkpoint_frame_mismatch';
        }
        if (($readerState['snapshot_reopened'] ?? null) !== true) {
            $reasons[] = 'reopened_reader_snapshot_not_reopened';
        }
        if (($readerState['readmark_cleared'] ?? null) !== true) {
            $reasons[] = 'reopened_reader_readmark_not_cleared';
        }

        return [
            'name' => $name,
            'source_token' => $readerState['source_token'],
            'reader_generation' => $readerState['reader_generation'],
            'checkpoint_frame' => $readerState['checkpoint_frame'],
            'snapshot_reopened' => $readerState['snapshot_reopened'] ?? null,
            'readmark_cleared' => $readerState['readmark_cleared'] ?? null,
            'admitted' => $reasons === [],
            'blocked_reasons' => array_values(array_unique($reasons)),
            'reader_reason' => $reasons === [] ? 'reader_reopened_on_checkpoint_current_source' : 'reader_blocks_reopened_checkpoint_current_source',
        ];
    }

    private static function path(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        return $value;
    }

    private static function token(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[a-z0-9][a-z0-9._:-]*$/i', $value)) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        return $value;
    }

    /** @return list<string> */
    private static function tokenSet(mixed $value, string $label): array
    {
        if (!is_array($value) || $value === []) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        $tokens = [];
        foreach ($value as $item) {
            $tokens[] = self::token($item, $label);
        }
        return array_values(array_unique($tokens));
    }

    private static function digest(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        return $value;
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 1) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        return $value;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        return $value;
    }

    /** @return list<int> */
    private static function positiveIntSet(mixed $value, string $label): array
    {
        if (!is_array($value) || $value === []) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        $set = [];
        foreach ($value as $item) {
            if (!is_int($item) || $item < 1) {
                throw new \InvalidArgumentException("Invalid {$label}");
            }
            $set[$item] = true;
        }
        $values = array_map('intval', array_keys($set));
        sort($values);
        return $values;
    }

    /** @return list<array<string,mixed>> */
    private static function readerStates(mixed $value): array
    {
        if (!is_array($value) || $value === []) {
            throw new \InvalidArgumentException('Invalid reader states');
        }
        $rows = [];
        foreach ($value as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('Invalid reader states');
            }
            $rows[] = $row;
        }
        return $rows;
    }
}
