<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext260Plan
{
    /**
     * @param array<string,mixed> $handoffPlan
     * @param list<array<string,mixed>> $journalReceipts
     * @param list<array<string,mixed>> $savepointReceipts
     * @param list<array<string,mixed>> $checkpointReceipts
     * @param list<array<string,mixed>> $readerTokens
     * @return array<string,mixed>
     */
    public static function admitCurrentSource(array $handoffPlan, array $journalReceipts, array $savepointReceipts, array $checkpointReceipts, array $readerTokens): array
    {
        if (($handoffPlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next246') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next260 requires an admitted next246 handoff');
        }
        if (($handoffPlan['durable_handoff_admitted'] ?? null) !== true) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next260 requires a durable handoff');
        }
        if ($journalReceipts === [] || $savepointReceipts === [] || $checkpointReceipts === [] || $readerTokens === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint next260 requires journal, savepoint, checkpoint, and reader evidence');
        }

        $sourceToken = self::token($handoffPlan['source_token'] ?? null, 'source token');
        $databaseDigest = self::digest($handoffPlan['database_digest'] ?? null, 'database digest');
        $pageCacheDigest = self::digest($handoffPlan['page_cache_digest'] ?? null, 'page cache digest');
        $commitGeneration = self::positiveInt($handoffPlan, 'commit_generation');
        $schemaCookie = self::positiveInt($handoffPlan, 'schema_cookie');
        $checkpointFrame = self::positiveInt($handoffPlan, 'checkpoint_frame');
        $dirtyPages = self::intSet($handoffPlan['dirty_pages'] ?? null, 'dirty pages');
        $commitFrames = self::intSet($handoffPlan['commit_frames'] ?? null, 'commit frames');
        $readerNames = self::tokenSet($handoffPlan['accepted_reader_names'] ?? null, 'accepted reader names');

        $journalRows = array_map(
            static fn (array $receipt): array => self::journalRow($receipt, $sourceToken, $databaseDigest, $pageCacheDigest, $commitGeneration, $schemaCookie, $dirtyPages),
            $journalReceipts
        );
        $savepointRows = array_map(
            static fn (array $receipt): array => self::savepointRow($receipt, $sourceToken, $commitGeneration, $checkpointFrame, $commitFrames),
            $savepointReceipts
        );
        $checkpointRows = array_map(
            static fn (array $receipt): array => self::checkpointRow($receipt, $sourceToken, $databaseDigest, $pageCacheDigest, $commitGeneration, $schemaCookie, $checkpointFrame, $dirtyPages, $commitFrames),
            $checkpointReceipts
        );
        $readerRows = array_map(
            static fn (array $receipt): array => self::readerRow($receipt, $sourceToken, $databaseDigest, $pageCacheDigest, $commitGeneration, $schemaCookie, $checkpointFrame, $readerNames),
            $readerTokens
        );

        $blockedReasons = self::blockedReasons($journalRows, $savepointRows, $checkpointRows, $readerRows);
        $coveredPages = self::coveredInts($checkpointRows, 'database_pages');
        $coveredFrames = self::coveredInts($savepointRows, 'retained_wal_frames');
        $checkpointFrames = self::coveredInts($checkpointRows, 'checkpointed_wal_frames');
        $missingPages = array_values(array_diff(array_keys($dirtyPages), $coveredPages));
        $missingRetainedFrames = array_values(array_diff(array_keys($commitFrames), $coveredFrames));
        $missingCheckpointFrames = array_values(array_diff(array_keys($commitFrames), $checkpointFrames));
        $journalDeleted = self::anyAdmitted($journalRows, 'hot_journal_deleted');
        $savepointClosed = self::allAdmitted($savepointRows) && self::allBool($savepointRows, 'savepoint_scope_closed');
        $checkpointSynced = self::allAdmitted($checkpointRows) && self::allBool($checkpointRows, 'database_sync_done') && self::allBool($checkpointRows, 'wal_index_sync_done');
        $readersReopened = self::allAdmitted($readerRows) && self::allBool($readerRows, 'snapshot_reopened');

        $guardRows = [
            ['name' => 'next246_durable_handoff_admitted', 'matched' => true],
            ['name' => 'hot_journal_deleted_from_current_source', 'matched' => $journalDeleted],
            ['name' => 'savepoint_retained_prefix_matches_current_source', 'matched' => $missingRetainedFrames === [] && $savepointClosed],
            ['name' => 'checkpoint_writes_cover_current_dirty_pages', 'matched' => $missingPages === []],
            ['name' => 'checkpoint_frames_cover_retained_wal_prefix', 'matched' => $missingCheckpointFrames === []],
            ['name' => 'checkpoint_and_wal_index_synced', 'matched' => $checkpointSynced],
            ['name' => 'reader_tokens_reopened_on_current_source', 'matched' => $readersReopened],
            ['name' => 'all_evidence_matches_current_source', 'matched' => $blockedReasons === []],
        ];
        $blockedGuards = array_values(array_column(array_filter($guardRows, static fn (array $row): bool => !$row['matched']), 'name'));
        $admitted = $blockedGuards === [];

        return [
            'status' => $admitted
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next260'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next260',
            'reason' => $admitted
                ? 'hot_journal_savepoint_checkpoint_current_source_admitted'
                : 'hot_journal_savepoint_checkpoint_current_source_held',
            'base_status' => $handoffPlan['status'],
            'database_path' => $handoffPlan['database_path'] ?? null,
            'journal_path' => $handoffPlan['journal_path'] ?? null,
            'wal_path' => $handoffPlan['wal_path'] ?? null,
            'source_token' => $sourceToken,
            'commit_generation' => $commitGeneration,
            'schema_cookie' => $schemaCookie,
            'checkpoint_frame' => $checkpointFrame,
            'database_digest' => $databaseDigest,
            'page_cache_digest' => $pageCacheDigest,
            'dirty_pages' => array_keys($dirtyPages),
            'commit_frames' => array_keys($commitFrames),
            'accepted_reader_names' => array_keys($readerNames),
            'journal_rows' => $journalRows,
            'savepoint_rows' => $savepointRows,
            'checkpoint_rows' => $checkpointRows,
            'reader_rows' => $readerRows,
            'admitted_journal_names' => self::names($journalRows, true),
            'blocked_journal_names' => self::names($journalRows, false),
            'admitted_savepoint_names' => self::names($savepointRows, true),
            'blocked_savepoint_names' => self::names($savepointRows, false),
            'admitted_checkpoint_names' => self::names($checkpointRows, true),
            'blocked_checkpoint_names' => self::names($checkpointRows, false),
            'admitted_reader_names' => self::names($readerRows, true),
            'blocked_reader_names' => self::names($readerRows, false),
            'covered_database_pages' => $coveredPages,
            'missing_database_pages' => $missingPages,
            'retained_wal_frames' => $coveredFrames,
            'missing_retained_wal_frames' => $missingRetainedFrames,
            'checkpointed_wal_frames' => $checkpointFrames,
            'missing_checkpointed_wal_frames' => $missingCheckpointFrames,
            'blocked_reasons' => $blockedReasons,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'blocked_guard_names' => $blockedGuards,
            'current_source_admitted' => $admitted,
            'journal_action' => $admitted ? 'retire_hot_journal_after_delete_receipt' : 'retain_hot_journal_replay_source',
            'savepoint_action' => $admitted ? 'release_savepoint_prefix_for_checkpoint' : 'keep_savepoint_prefix_replayable',
            'checkpoint_action' => $admitted ? 'publish_checkpoint_as_current_source' : 'hold_checkpoint_until_source_evidence_matches',
            'reader_action' => $admitted ? 'advance_reopened_readers_to_generation_' . $commitGeneration : 'pin_readers_to_previous_current_source',
            'admission_digest' => hash('sha256', json_encode([$sourceToken, $commitGeneration, $journalRows, $savepointRows, $checkpointRows, $readerRows, $blockedGuards], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($handoffPlan['operation_names'] ?? null) ? $handoffPlan['operation_names'] : [],
                ['admit_hot_journal_savepoint_checkpoint_current_source_next260']
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($handoffPlan['dependencies'] ?? null) ? $handoffPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next260',
                    'sqlite-wal-hot-journal-savepoint-checkpoint-source-ordering',
                    'wordpress-import-hot-journal-savepoint-checkpoint-current-source',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses lane-local rollback-journal receipt, savepoint WAL prefix, checkpoint durability, and reader-token evidence',
            'non_overlap' => 'next260 adds post-next246 source-order admission across rollback-journal, savepoint WAL prefix, checkpoint, and reader tokens; it does not repeat next246 durable VFS handoff, checkpoint transaction planning, WAL byte truncation, rollback-journal commit/apply, or reader snapshot page-map helpers',
        ];
    }

    /** @param array<string,mixed> $row @param array<int,true> $dirtyPages @return array<string,mixed> */
    private static function journalRow(array $row, string $sourceToken, string $databaseDigest, string $pageCacheDigest, int $commitGeneration, int $schemaCookie, array $dirtyPages): array
    {
        $name = self::token($row['name'] ?? null, 'journal receipt name');
        $pages = self::intList($row['recovered_pages'] ?? null, "{$name} recovered pages");
        $reasons = self::commonReasons($row, $sourceToken, $databaseDigest, $pageCacheDigest, $commitGeneration, $schemaCookie, $name);
        foreach ($pages as $page) {
            if (!isset($dirtyPages[$page])) {
                $reasons[] = 'journal_unexpected_recovered_page';
                break;
            }
        }
        if (($row['journal_checksum_valid'] ?? false) !== true) {
            $reasons[] = 'journal_checksum_invalid';
        }
        if (($row['hot_journal_deleted'] ?? false) !== true) {
            $reasons[] = 'hot_journal_delete_missing';
        }
        if (($row['directory_sync_done'] ?? false) !== true) {
            $reasons[] = 'journal_directory_sync_missing';
        }

        return self::row($name, $reasons, [
            'recovered_pages' => $pages,
            'journal_checksum_valid' => ($row['journal_checksum_valid'] ?? false) === true,
            'hot_journal_deleted' => ($row['hot_journal_deleted'] ?? false) === true,
            'directory_sync_done' => ($row['directory_sync_done'] ?? false) === true,
        ], 'journal_receipt_matches_current_source');
    }

    /** @param array<string,mixed> $row @param array<int,true> $commitFrames @return array<string,mixed> */
    private static function savepointRow(array $row, string $sourceToken, int $commitGeneration, int $checkpointFrame, array $commitFrames): array
    {
        $name = self::token($row['name'] ?? null, 'savepoint receipt name');
        $frames = self::intList($row['retained_wal_frames'] ?? null, "{$name} retained WAL frames");
        $reasons = [];
        if (!hash_equals($sourceToken, self::token($row['source_token'] ?? null, "{$name} source token"))) {
            $reasons[] = 'savepoint_source_token_mismatch';
        }
        if (($row['commit_generation'] ?? null) !== $commitGeneration) {
            $reasons[] = 'savepoint_commit_generation_mismatch';
        }
        if (($row['checkpoint_frame'] ?? null) !== $checkpointFrame) {
            $reasons[] = 'savepoint_checkpoint_frame_mismatch';
        }
        foreach ($frames as $frame) {
            if (!isset($commitFrames[$frame])) {
                $reasons[] = 'savepoint_unexpected_wal_frame';
                break;
            }
        }
        if (($row['prefix_digest'] ?? '') !== self::prefixDigest(array_keys($commitFrames), $sourceToken, $commitGeneration)) {
            $reasons[] = 'savepoint_prefix_digest_mismatch';
        }
        if (($row['savepoint_scope_closed'] ?? false) !== true) {
            $reasons[] = 'savepoint_scope_still_open';
        }

        return self::row($name, $reasons, [
            'retained_wal_frames' => $frames,
            'savepoint_scope_closed' => ($row['savepoint_scope_closed'] ?? false) === true,
        ], 'savepoint_prefix_matches_current_source');
    }

    /** @param array<string,mixed> $row @param array<int,true> $dirtyPages @param array<int,true> $commitFrames @return array<string,mixed> */
    private static function checkpointRow(array $row, string $sourceToken, string $databaseDigest, string $pageCacheDigest, int $commitGeneration, int $schemaCookie, int $checkpointFrame, array $dirtyPages, array $commitFrames): array
    {
        $name = self::token($row['name'] ?? null, 'checkpoint receipt name');
        $pages = self::intList($row['database_pages'] ?? null, "{$name} database pages");
        $frames = self::intList($row['checkpointed_wal_frames'] ?? null, "{$name} checkpointed WAL frames");
        $reasons = self::commonReasons($row, $sourceToken, $databaseDigest, $pageCacheDigest, $commitGeneration, $schemaCookie, $name);
        if (($row['checkpoint_frame'] ?? null) !== $checkpointFrame) {
            $reasons[] = 'checkpoint_frame_mismatch';
        }
        foreach ($pages as $page) {
            if (!isset($dirtyPages[$page])) {
                $reasons[] = 'checkpoint_unexpected_database_page';
                break;
            }
        }
        foreach ($frames as $frame) {
            if (!isset($commitFrames[$frame])) {
                $reasons[] = 'checkpoint_unexpected_wal_frame';
                break;
            }
        }
        if (($row['database_sync_done'] ?? false) !== true) {
            $reasons[] = 'checkpoint_database_sync_missing';
        }
        if (($row['wal_index_sync_done'] ?? false) !== true) {
            $reasons[] = 'checkpoint_wal_index_sync_missing';
        }
        if (($row['exclusive_lock_held'] ?? false) !== true) {
            $reasons[] = 'checkpoint_exclusive_lock_missing';
        }

        return self::row($name, $reasons, [
            'database_pages' => $pages,
            'checkpointed_wal_frames' => $frames,
            'database_sync_done' => ($row['database_sync_done'] ?? false) === true,
            'wal_index_sync_done' => ($row['wal_index_sync_done'] ?? false) === true,
            'exclusive_lock_held' => ($row['exclusive_lock_held'] ?? false) === true,
        ], 'checkpoint_receipt_matches_current_source');
    }

    /** @param array<string,mixed> $row @param array<string,true> $readerNames @return array<string,mixed> */
    private static function readerRow(array $row, string $sourceToken, string $databaseDigest, string $pageCacheDigest, int $commitGeneration, int $schemaCookie, int $checkpointFrame, array $readerNames): array
    {
        $name = self::token($row['name'] ?? null, 'reader token name');
        $reasons = self::commonReasons($row, $sourceToken, $databaseDigest, $pageCacheDigest, $commitGeneration, $schemaCookie, $name);
        if (!isset($readerNames[$name])) {
            $reasons[] = 'reader_not_in_next246_admitted_set';
        }
        if (($row['checkpoint_frame'] ?? null) !== $checkpointFrame) {
            $reasons[] = 'reader_checkpoint_frame_mismatch';
        }
        if (($row['snapshot_reopened'] ?? false) !== true) {
            $reasons[] = 'reader_snapshot_not_reopened';
        }
        if (($row['hot_journal_seen'] ?? false) === true) {
            $reasons[] = 'reader_still_sees_hot_journal';
        }

        return self::row($name, $reasons, [
            'checkpoint_frame' => $row['checkpoint_frame'] ?? null,
            'snapshot_reopened' => ($row['snapshot_reopened'] ?? false) === true,
            'hot_journal_seen' => ($row['hot_journal_seen'] ?? false) === true,
        ], 'reader_token_matches_current_source');
    }

    /** @param array<string,mixed> $row @return list<string> */
    private static function commonReasons(array $row, string $sourceToken, string $databaseDigest, string $pageCacheDigest, int $commitGeneration, int $schemaCookie, string $name): array
    {
        $reasons = [];
        if (!hash_equals($sourceToken, self::token($row['source_token'] ?? null, "{$name} source token"))) {
            $reasons[] = 'source_token_mismatch';
        }
        if (($row['commit_generation'] ?? null) !== $commitGeneration) {
            $reasons[] = 'commit_generation_mismatch';
        }
        if (($row['schema_cookie'] ?? null) !== $schemaCookie) {
            $reasons[] = 'schema_cookie_mismatch';
        }
        if (!hash_equals($databaseDigest, self::digest($row['database_digest'] ?? null, "{$name} database digest"))) {
            $reasons[] = 'database_digest_mismatch';
        }
        if (!hash_equals($pageCacheDigest, self::digest($row['page_cache_digest'] ?? null, "{$name} page cache digest"))) {
            $reasons[] = 'page_cache_digest_mismatch';
        }

        return $reasons;
    }

    /** @param list<string> $reasons @param array<string,mixed> $extra @return array<string,mixed> */
    private static function row(string $name, array $reasons, array $extra, string $okReason): array
    {
        $reasons = array_values(array_unique($reasons));

        return array_merge([
            'name' => $name,
            'admitted' => $reasons === [],
            'reason' => $reasons === [] ? $okReason : implode('|', $reasons),
            'blocked_reasons' => $reasons,
        ], $extra);
    }

    /** @param mixed $value */
    private static function token($value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_.:-]+$/', $value)) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }

        return $value;
    }

    /** @param mixed $value */
    private static function digest($value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }

        return $value;
    }

    /** @param array<string,mixed> $row */
    private static function positiveInt(array $row, string $key): int
    {
        if (!isset($row[$key]) || !is_int($row[$key]) || $row[$key] <= 0) {
            throw new \InvalidArgumentException("Invalid {$key}");
        }

        return $row[$key];
    }

    /** @param mixed $value @return array<int,true> */
    private static function intSet($value, string $label): array
    {
        $set = [];
        foreach (self::intList($value, $label) as $int) {
            $set[$int] = true;
        }

        return $set;
    }

    /** @param mixed $value @return list<int> */
    private static function intList($value, string $label): array
    {
        if (!is_array($value) || $value === []) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        $list = [];
        foreach ($value as $item) {
            if (!is_int($item) || $item <= 0) {
                throw new \InvalidArgumentException("Invalid {$label}");
            }
            $list[] = $item;
        }
        $list = array_values(array_unique($list));
        sort($list);

        return $list;
    }

    /** @param mixed $value @return array<string,true> */
    private static function tokenSet($value, string $label): array
    {
        if (!is_array($value) || $value === []) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        $set = [];
        foreach ($value as $item) {
            $set[self::token($item, $label)] = true;
        }

        return $set;
    }

    /** @param list<int> $frames */
    private static function prefixDigest(array $frames, string $sourceToken, int $commitGeneration): string
    {
        sort($frames);

        return hash('sha256', json_encode([$sourceToken, $commitGeneration, $frames], JSON_THROW_ON_ERROR));
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function coveredInts(array $rows, string $key): array
    {
        $covered = [];
        foreach ($rows as $row) {
            if (!$row['admitted']) {
                continue;
            }
            foreach ($row[$key] as $int) {
                $covered[$int] = true;
            }
        }
        ksort($covered);

        return array_keys($covered);
    }

    /** @param list<array<string,mixed>> $rows */
    private static function anyAdmitted(array $rows, string $key): bool
    {
        foreach ($rows as $row) {
            if ($row['admitted'] && ($row[$key] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /** @param list<array<string,mixed>> $rows */
    private static function allAdmitted(array $rows): bool
    {
        foreach ($rows as $row) {
            if (!$row['admitted']) {
                return false;
            }
        }

        return true;
    }

    /** @param list<array<string,mixed>> $rows */
    private static function allBool(array $rows, string $key): bool
    {
        foreach ($rows as $row) {
            if (($row[$key] ?? false) !== true) {
                return false;
            }
        }

        return true;
    }

    /** @param list<array<string,mixed>> ...$groups @return list<string> */
    private static function blockedReasons(array ...$groups): array
    {
        $reasons = [];
        foreach ($groups as $rows) {
            foreach ($rows as $row) {
                foreach ($row['blocked_reasons'] as $reason) {
                    $reasons[$reason] = true;
                }
            }
        }
        ksort($reasons);

        return array_keys($reasons);
    }

    /** @param list<array<string,mixed>> $rows @return list<string> */
    private static function names(array $rows, bool $admitted): array
    {
        return array_values(array_column(array_filter($rows, static fn (array $row): bool => $row['admitted'] === $admitted), 'name'));
    }
}
