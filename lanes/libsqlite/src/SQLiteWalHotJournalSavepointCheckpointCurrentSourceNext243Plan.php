<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext243Plan
{
    /**
     * @param array<string,mixed> $baselinePlan
     * @param list<array<string,mixed>> $readerReceipts
     * @return array<string,mixed>
     */
    public static function admitReaderSnapshotBaseline(array $baselinePlan, array $readerReceipts): array
    {
        if (($baselinePlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next240'
            || ($baselinePlan['autocheckpoint_baseline_allowed'] ?? null) !== true
        ) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next243 requires an admitted next240 autocheckpoint baseline');
        }
        if ($readerReceipts === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next243 requires reader snapshot receipts');
        }

        $databasePath = self::path($baselinePlan['database_path'] ?? null, 'database path');
        $walPath = self::path($baselinePlan['wal_path'] ?? null, 'wal path');
        $journalPath = self::path($baselinePlan['journal_path'] ?? null, 'journal path');
        $sourceToken = self::token($baselinePlan['source_token'] ?? null, 'source token');
        $commitGeneration = self::positiveInt($baselinePlan['commit_generation'] ?? null, 'commit generation');
        $schemaCookie = self::positiveInt($baselinePlan['schema_cookie'] ?? null, 'schema cookie');
        $databaseDigest = self::digest($baselinePlan['database_digest'] ?? null, 'database digest');
        $pageCacheDigest = self::digest($baselinePlan['page_cache_digest'] ?? null, 'page cache digest');
        $walIndexSalt = self::saltPair($baselinePlan['wal_index_salt'] ?? null, 'wal index salt');
        $walIndexMxFrame = self::nonNegativeInt($baselinePlan['wal_index_mx_frame'] ?? null, 'wal index mx frame');
        $checkpointFrame = self::nonNegativeInt($baselinePlan['checkpoint_frame'] ?? null, 'checkpoint frame');
        $dirtyPages = self::positiveIntSet($baselinePlan['dirty_pages'] ?? null, 'dirty pages');
        $commitFrames = self::positiveIntSet($baselinePlan['commit_frames'] ?? null, 'commit frames');

        $rows = [];
        foreach ($readerReceipts as $receipt) {
            $rows[] = self::readerReceiptRow(
                $receipt,
                $sourceToken,
                $commitGeneration,
                $schemaCookie,
                $databaseDigest,
                $pageCacheDigest,
                $walIndexSalt,
                $walIndexMxFrame,
                $checkpointFrame,
                $dirtyPages,
                $commitFrames
            );
        }

        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => !$row['accepted']));
        $duplicateNames = self::duplicates(array_column($rows, 'name'));
        $readerNames = array_values(array_column($rows, 'name'));
        $acceptedNames = array_values(array_column(array_filter($rows, static fn (array $row): bool => $row['accepted']), 'name'));
        $blockedNames = array_values(array_column($blockedRows, 'name'));
        $observedPages = [];
        foreach ($rows as $row) {
            if ($row['accepted']) {
                foreach ($row['observed_pages'] as $page) {
                    $observedPages[$page] = true;
                }
            }
        }
        $observedPages = array_map('intval', array_keys($observedPages));
        sort($observedPages);
        $missingDirtyPages = array_values(array_diff($dirtyPages, $observedPages));

        $blockedReasons = [];
        foreach ($blockedRows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }
        if ($duplicateNames !== []) {
            $blockedReasons[] = 'reader_snapshot_receipt_name_duplicate';
        }
        if ($missingDirtyPages !== []) {
            $blockedReasons[] = 'reader_snapshot_dirty_page_unobserved';
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        $guardRows = [
            [
                'name' => 'next240_autocheckpoint_baseline_admitted',
                'matched' => true,
                'reason' => 'the next-writer commit already promoted the checkpoint current source to an autocheckpoint baseline',
            ],
            [
                'name' => 'reader_snapshot_receipt_names_unique',
                'matched' => $duplicateNames === [],
                'reason' => 'each reopened reader snapshot must have one attributable receipt',
            ],
            [
                'name' => 'all_dirty_pages_observed_by_reopened_readers',
                'matched' => $missingDirtyPages === [],
                'reason' => 'WordPress import readers must observe every dirty page promoted by the checkpoint baseline',
            ],
            [
                'name' => 'all_reader_snapshots_match_checkpoint_baseline',
                'matched' => $blockedRows === [],
                'reason' => 'reader snapshots must match source token, generation, cookies, WAL-index, page-cache, and clean pager state',
            ],
        ];
        $blockedGuards = array_values(array_column(
            array_filter($guardRows, static fn (array $row): bool => !$row['matched']),
            'name'
        ));
        $admitted = $blockedGuards === [];

        return [
            'status' => $admitted
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next243'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next243',
            'reason' => $admitted
                ? 'reopened_reader_snapshots_match_autocheckpoint_current_source'
                : 'reopened_reader_snapshots_hold_autocheckpoint_current_source',
            'base_status' => $baselinePlan['status'],
            'database_path' => $databasePath,
            'wal_path' => $walPath,
            'journal_path' => $journalPath,
            'source_token' => $sourceToken,
            'commit_generation' => $commitGeneration,
            'schema_cookie' => $schemaCookie,
            'database_digest' => $databaseDigest,
            'page_cache_digest' => $pageCacheDigest,
            'wal_index_salt' => $walIndexSalt,
            'wal_index_mx_frame' => $walIndexMxFrame,
            'checkpoint_frame' => $checkpointFrame,
            'dirty_pages' => $dirtyPages,
            'commit_frames' => $commitFrames,
            'reader_rows' => $rows,
            'reader_names' => $readerNames,
            'accepted_reader_names' => $acceptedNames,
            'blocked_reader_names' => $blockedNames,
            'duplicate_reader_names' => $duplicateNames,
            'observed_dirty_pages' => $observedPages,
            'missing_dirty_pages' => $missingDirtyPages,
            'blocked_reader_reasons' => $blockedReasons,
            'reader_snapshot_admitted' => $admitted,
            'reader_action' => $admitted ? 'serve_reopened_readers_from_autocheckpoint_current_source' : 'force_reopen_readers_before_current_source_switch',
            'pager_action' => $admitted ? 'promote_clean_checkpoint_page_cache_to_reader_snapshot' : 'retain_checkpoint_page_cache_until_readers_match',
            'wal_index_action' => $admitted ? 'publish_committed_wal_index_readmark_baseline' : 'hold_wal_index_readmark_baseline',
            'hot_journal_action' => $admitted ? 'keep_hot_journal_deleted_for_reopened_readers' : 'fence_hot_journal_visible_readers',
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'blocked_guard_names' => $blockedGuards,
            'snapshot_digest' => hash('sha256', json_encode([$sourceToken, $commitGeneration, $databaseDigest, $pageCacheDigest, $walIndexSalt, $rows, $guardRows], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($baselinePlan['operation_names'] ?? null) ? $baselinePlan['operation_names'] : [],
                [
                    'verify_reopened_reader_snapshot_baseline_next243',
                    $admitted ? 'admit_reopened_reader_snapshot_baseline_next243' : 'hold_reopened_reader_snapshot_baseline_next243',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($baselinePlan['dependencies'] ?? null) ? $baselinePlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next243',
                    'sqlite-wal-reopened-reader-snapshot-baseline',
                    'wordpress-import-reader-snapshot-after-autocheckpoint',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next240 autocheckpoint baseline receipts plus native PHP WAL-index salt, page-cache digest, reader readmark, hot-journal, and savepoint-depth metadata',
            'non_overlap' => 'next243 validates reopened reader snapshot admission after next240 autocheckpoint baseline; it does not repeat checkpoint publication, WAL byte truncation, VFS savepoint rollback/apply, rollback-journal commit/apply, VFS sync/file writer, process locks, super-journal commits, or SELECT/JSON/B-tree surfaces',
        ];
    }

    /**
     * @param array<string,mixed> $receipt
     * @param list<string> $walIndexSalt
     * @param list<int> $dirtyPages
     * @param list<int> $commitFrames
     * @return array<string,mixed>
     */
    private static function readerReceiptRow(
        array $receipt,
        string $sourceToken,
        int $commitGeneration,
        int $schemaCookie,
        string $databaseDigest,
        string $pageCacheDigest,
        array $walIndexSalt,
        int $walIndexMxFrame,
        int $checkpointFrame,
        array $dirtyPages,
        array $commitFrames
    ): array {
        $name = self::token($receipt['name'] ?? null, 'reader receipt name');
        $observedPages = self::positiveIntSet($receipt['observed_pages'] ?? null, "{$name} observed pages");
        $readmarkFrame = self::nonNegativeInt($receipt['readmark_frame'] ?? null, "{$name} readmark frame");
        $observedCommitFrames = self::positiveIntSet($receipt['observed_commit_frames'] ?? null, "{$name} observed commit frames");
        $reasons = [];

        foreach ($observedPages as $page) {
            if (!in_array($page, $dirtyPages, true)) {
                $reasons[] = 'reader_snapshot_page_not_in_dirty_set';
            }
        }
        foreach ($commitFrames as $frame) {
            if (!in_array($frame, $observedCommitFrames, true)) {
                $reasons[] = 'reader_snapshot_commit_frame_missing';
                break;
            }
        }
        if (self::token($receipt['source_token'] ?? null, "{$name} source token") !== $sourceToken) {
            $reasons[] = 'reader_snapshot_source_token_mismatch';
        }
        if (self::positiveInt($receipt['commit_generation'] ?? null, "{$name} commit generation") !== $commitGeneration) {
            $reasons[] = 'reader_snapshot_commit_generation_mismatch';
        }
        if (self::positiveInt($receipt['schema_cookie'] ?? null, "{$name} schema cookie") !== $schemaCookie) {
            $reasons[] = 'reader_snapshot_schema_cookie_mismatch';
        }
        if (self::digest($receipt['database_digest'] ?? null, "{$name} database digest") !== $databaseDigest) {
            $reasons[] = 'reader_snapshot_database_digest_mismatch';
        }
        if (self::digest($receipt['page_cache_digest'] ?? null, "{$name} page cache digest") !== $pageCacheDigest) {
            $reasons[] = 'reader_snapshot_page_cache_digest_mismatch';
        }
        if (self::saltPair($receipt['wal_index_salt'] ?? null, "{$name} wal index salt") !== $walIndexSalt) {
            $reasons[] = 'reader_snapshot_wal_index_salt_mismatch';
        }
        if (self::nonNegativeInt($receipt['wal_index_mx_frame'] ?? null, "{$name} wal index mx frame") !== $walIndexMxFrame) {
            $reasons[] = 'reader_snapshot_wal_index_mx_frame_mismatch';
        }
        if (self::nonNegativeInt($receipt['checkpoint_frame'] ?? null, "{$name} checkpoint frame") !== $checkpointFrame) {
            $reasons[] = 'reader_snapshot_checkpoint_frame_mismatch';
        }
        if ($readmarkFrame > $walIndexMxFrame) {
            $reasons[] = 'reader_snapshot_readmark_beyond_mx_frame';
        }
        if (($receipt['hot_journal_visible'] ?? false) === true) {
            $reasons[] = 'reader_snapshot_hot_journal_visible';
        }
        if (self::nonNegativeInt($receipt['savepoint_depth'] ?? null, "{$name} savepoint depth") !== 0) {
            $reasons[] = 'reader_snapshot_savepoint_scope_open';
        }
        if (($receipt['page_cache_clean'] ?? null) !== true) {
            $reasons[] = 'reader_snapshot_page_cache_dirty';
        }
        if (($receipt['shared_lock_held'] ?? null) !== true) {
            $reasons[] = 'reader_snapshot_shared_lock_missing';
        }
        if (($receipt['reader_reopened_after_commit'] ?? null) !== true) {
            $reasons[] = 'reader_snapshot_not_reopened_after_commit';
        }

        $reasons = array_values(array_unique($reasons));

        return [
            'name' => $name,
            'source_token' => (string) $receipt['source_token'],
            'commit_generation' => (int) $receipt['commit_generation'],
            'schema_cookie' => (int) $receipt['schema_cookie'],
            'database_digest' => (string) $receipt['database_digest'],
            'page_cache_digest' => (string) $receipt['page_cache_digest'],
            'wal_index_salt' => self::saltPair($receipt['wal_index_salt'], "{$name} wal index salt"),
            'wal_index_mx_frame' => (int) $receipt['wal_index_mx_frame'],
            'checkpoint_frame' => (int) $receipt['checkpoint_frame'],
            'readmark_frame' => $readmarkFrame,
            'observed_pages' => $observedPages,
            'observed_commit_frames' => $observedCommitFrames,
            'hot_journal_visible' => ($receipt['hot_journal_visible'] ?? false) === true,
            'savepoint_depth' => (int) $receipt['savepoint_depth'],
            'page_cache_clean' => ($receipt['page_cache_clean'] ?? null) === true,
            'shared_lock_held' => ($receipt['shared_lock_held'] ?? null) === true,
            'reader_reopened_after_commit' => ($receipt['reader_reopened_after_commit'] ?? null) === true,
            'accepted' => $reasons === [],
            'reader_reason' => $reasons === [] ? 'reader_snapshot_matches_autocheckpoint_current_source' : implode('|', $reasons),
            'blocked_reasons' => $reasons,
        ];
    }

    private static function path(mixed $value, string $name): string
    {
        if (!is_string($value) || $value === '' || str_contains($value, "\0")) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next243 {$name} is invalid");
        }

        return $value;
    }

    private static function token(mixed $value, string $name): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_.:-]+$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next243 {$name} is invalid");
        }

        return $value;
    }

    private static function positiveInt(mixed $value, string $name): int
    {
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next243 {$name} must be positive");
        }

        return $value;
    }

    private static function nonNegativeInt(mixed $value, string $name): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next243 {$name} must be non-negative");
        }

        return $value;
    }

    private static function digest(mixed $value, string $name): string
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next243 {$name} must be a sha256 digest");
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function saltPair(mixed $value, string $name): array
    {
        if (!is_array($value) || count($value) !== 2) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next243 {$name} must contain two salts");
        }
        $salts = array_values($value);
        foreach ($salts as $salt) {
            if (!is_string($salt) || $salt === '') {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next243 {$name} must contain non-empty salts");
            }
        }

        return $salts;
    }

    /**
     * @return list<int>
     */
    private static function positiveIntSet(mixed $value, string $name): array
    {
        if (!is_array($value) || $value === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next243 {$name} must be a non-empty integer list");
        }
        $set = [];
        foreach ($value as $item) {
            if (!is_int($item) || $item <= 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next243 {$name} must contain positive integers");
            }
            $set[$item] = $item;
        }
        $items = array_values($set);
        sort($items);

        return $items;
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private static function duplicates(array $values): array
    {
        $seen = [];
        $duplicates = [];
        foreach ($values as $value) {
            if (isset($seen[$value])) {
                $duplicates[$value] = true;
                continue;
            }
            $seen[$value] = true;
        }

        return array_keys($duplicates);
    }
}
