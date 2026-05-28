<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext240Plan
{
    /**
     * @param array<string,mixed> $finalizerPlan
     * @param list<array<string,mixed>> $commitReceipts
     * @return array<string,mixed>
     */
    public static function admitAutocheckpointBaseline(array $finalizerPlan, array $commitReceipts, int $commitGeneration): array
    {
        if (($finalizerPlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next236') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next240 requires an admitted next236 finalizer plan');
        }
        if (($finalizerPlan['next_writer_allowed'] ?? null) !== true) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next240 requires next-writer admission');
        }
        if ($commitReceipts === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next240 requires commit receipts');
        }

        $sourceToken = self::token($finalizerPlan['source_token'] ?? null, 'source token');
        $releasedGeneration = self::positiveInt($finalizerPlan, 'next_writer_generation');
        if ($commitGeneration <= $releasedGeneration) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next240 requires a commit generation after the released writer');
        }
        $schemaCookie = self::positiveInt($finalizerPlan, 'schema_cookie');
        $databaseDigest = self::digest($finalizerPlan['database_digest'] ?? null, 'database digest');
        $expectedStatements = self::stringSet($finalizerPlan['finalized_statement_names'] ?? null, 'finalized statement names');

        $expectedSalt = self::walSalt($finalizerPlan['wal_index_salt'] ?? ['next240-salt-a', 'next240-salt-b']);
        $expectedMxFrame = self::positiveValue($finalizerPlan['wal_index_mx_frame'] ?? 0, 'wal_index_mx_frame');
        $expectedCheckpointFrame = self::positiveValue($finalizerPlan['checkpoint_frame'] ?? $expectedMxFrame, 'checkpoint_frame');
        $expectedPageCacheDigest = self::digest($finalizerPlan['page_cache_digest'] ?? $databaseDigest, 'page cache digest');

        $rows = [];
        foreach ($commitReceipts as $receipt) {
            $rows[] = self::receiptRow(
                $receipt,
                $sourceToken,
                $releasedGeneration,
                $commitGeneration,
                $schemaCookie,
                $databaseDigest,
                $expectedStatements,
                $expectedSalt,
                $expectedMxFrame,
                $expectedCheckpointFrame,
                $expectedPageCacheDigest
            );
        }

        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => !$row['admitted']));
        $blockedReasons = [];
        foreach ($blockedRows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        $coveredStatements = [];
        $dirtyPages = [];
        $committedFrames = [];
        foreach ($rows as $row) {
            if (!$row['admitted']) {
                continue;
            }
            foreach ($row['covered_statements'] as $statementName) {
                $coveredStatements[$statementName] = true;
            }
            foreach ($row['dirty_pages'] as $pageNumber) {
                $dirtyPages[$pageNumber] = true;
            }
            foreach ($row['commit_frames'] as $frameNumber) {
                $committedFrames[$frameNumber] = true;
            }
        }
        ksort($coveredStatements);
        ksort($dirtyPages);
        ksort($committedFrames);
        $missingStatements = array_values(array_diff(array_keys($expectedStatements), array_keys($coveredStatements)));

        $guardRows = [
            [
                'name' => 'next236_finalizers_released',
                'matched' => true,
                'reason' => 'prepared-statement finalizers released checkpoint readers before the next writer opened',
            ],
            [
                'name' => 'all_finalized_statements_covered_by_commit_receipts',
                'matched' => $missingStatements === [],
                'reason' => 'the next writer commit must account for every finalized statement that released the checkpoint source',
            ],
            [
                'name' => 'all_commit_receipts_match_checkpoint_current_source',
                'matched' => $blockedRows === [],
                'reason' => 'commit receipts must match the source token, writer generation, WAL-index salt, mxFrame, checkpoint frame, schema cookie, database digest, and page-cache digest',
            ],
        ];
        $blockedGuards = array_values(array_column(
            array_filter($guardRows, static fn (array $row): bool => !$row['matched']),
            'name'
        ));
        $admitted = $blockedGuards === [];

        return [
            'status' => $admitted
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next240'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next240',
            'reason' => $admitted
                ? 'next_writer_commit_admits_autocheckpoint_baseline_after_hot_journal_savepoint_checkpoint'
                : 'next_writer_commit_holds_autocheckpoint_baseline_after_hot_journal_savepoint_checkpoint',
            'base_status' => $finalizerPlan['status'],
            'database_path' => $finalizerPlan['database_path'] ?? null,
            'journal_path' => $finalizerPlan['journal_path'] ?? null,
            'wal_path' => $finalizerPlan['wal_path'] ?? null,
            'source_token' => $sourceToken,
            'released_writer_generation' => $releasedGeneration,
            'commit_generation' => $commitGeneration,
            'schema_cookie' => $schemaCookie,
            'database_digest' => $databaseDigest,
            'wal_index_salt' => $expectedSalt,
            'wal_index_mx_frame' => $expectedMxFrame,
            'checkpoint_frame' => $expectedCheckpointFrame,
            'page_cache_digest' => $expectedPageCacheDigest,
            'expected_statement_names' => array_keys($expectedStatements),
            'covered_statement_names' => array_keys($coveredStatements),
            'missing_statement_names' => $missingStatements,
            'dirty_pages' => array_keys($dirtyPages),
            'commit_frames' => array_keys($committedFrames),
            'receipt_rows' => $rows,
            'admitted_receipt_names' => array_values(array_column(array_filter($rows, static fn (array $row): bool => $row['admitted']), 'name')),
            'blocked_receipt_names' => array_values(array_column($blockedRows, 'name')),
            'blocked_receipt_reasons' => $blockedReasons,
            'autocheckpoint_baseline_allowed' => $admitted,
            'writer_action' => $admitted ? 'commit_next_writer_generation_' . $commitGeneration : 'hold_next_writer_commit_for_checkpoint_current_source',
            'wal_index_action' => $admitted ? 'publish_wal_index_baseline_for_autocheckpoint' : 'retain_wal_index_checkpoint_baseline',
            'page_cache_action' => $admitted ? 'promote_clean_pages_to_commit_generation_' . $commitGeneration : 'discard_stale_checkpoint_page_cache',
            'hook_action' => $admitted ? 'run_wal_hook_and_autocheckpoint_for_generation_' . $commitGeneration : 'defer_wal_hook_and_autocheckpoint_until_receipts_match',
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'blocked_guard_names' => $blockedGuards,
            'baseline_digest' => hash('sha256', json_encode([$sourceToken, $releasedGeneration, $commitGeneration, $expectedSalt, $rows, $missingStatements], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($finalizerPlan['operation_names'] ?? null) ? $finalizerPlan['operation_names'] : [],
                [
                    'verify_next_writer_commit_autocheckpoint_baseline_next240',
                    $admitted ? 'admit_autocheckpoint_baseline_next240' : 'hold_autocheckpoint_baseline_next240',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($finalizerPlan['dependencies'] ?? null) ? $finalizerPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next240',
                    'sqlite-wal-index-autocheckpoint-baseline-after-hot-journal',
                    'wordpress-import-next-writer-autocheckpoint-after-hot-journal',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next236 finalizer release, WAL-index salt/mxFrame metadata, checkpoint-frame receipts, and page-cache digests',
            'non_overlap' => 'next240 admits the next writer commit/autocheckpoint baseline after next236 finalizers; it does not repeat checkpoint publication, prepared-statement admission, finalizer release, WAL byte truncation, VFS savepoint rollback, rollback-journal commit/apply, or checkpoint transaction planning',
        ];
    }

    /**
     * @param array<string,mixed> $receipt
     * @param array<string,true> $expectedStatements
     * @param list<string> $expectedSalt
     * @return array<string,mixed>
     */
    private static function receiptRow(array $receipt, string $sourceToken, int $releasedGeneration, int $commitGeneration, int $schemaCookie, string $databaseDigest, array $expectedStatements, array $expectedSalt, int $expectedMxFrame, int $expectedCheckpointFrame, string $expectedPageCacheDigest): array
    {
        $name = self::token($receipt['name'] ?? null, 'receipt name');
        $observedSource = self::token($receipt['source_token'] ?? null, "{$name} source token");
        $observedReleasedGeneration = self::intField($receipt, 'released_generation', $name);
        $observedCommitGeneration = self::intField($receipt, 'commit_generation', $name);
        $observedSchemaCookie = self::intField($receipt, 'schema_cookie', $name);
        $observedDigest = self::digest($receipt['database_digest'] ?? null, "{$name} database digest");
        $observedPageCacheDigest = self::digest($receipt['page_cache_digest'] ?? null, "{$name} page cache digest");
        $observedSalt = self::walSalt($receipt['wal_index_salt'] ?? null);
        $observedMxFrame = self::intField($receipt, 'wal_index_mx_frame', $name);
        $observedCheckpointFrame = self::intField($receipt, 'checkpoint_frame', $name);
        $coveredStatements = self::stringList($receipt['covered_statement_names'] ?? null, "{$name} covered statement names");
        $dirtyPages = self::pageList($receipt['dirty_pages'] ?? [], "{$name} dirty pages");
        $commitFrames = self::pageList($receipt['commit_frames'] ?? [], "{$name} commit frames");

        $reasons = [];
        if ($observedSource !== $sourceToken) {
            $reasons[] = 'receipt_source_token_mismatch';
        }
        if ($observedReleasedGeneration !== $releasedGeneration) {
            $reasons[] = 'receipt_released_generation_mismatch';
        }
        if ($observedCommitGeneration !== $commitGeneration) {
            $reasons[] = 'receipt_commit_generation_mismatch';
        }
        if ($observedSchemaCookie !== $schemaCookie) {
            $reasons[] = 'receipt_schema_cookie_mismatch';
        }
        if (!hash_equals($databaseDigest, $observedDigest)) {
            $reasons[] = 'receipt_database_digest_mismatch';
        }
        if (!hash_equals($expectedPageCacheDigest, $observedPageCacheDigest)) {
            $reasons[] = 'receipt_page_cache_digest_mismatch';
        }
        if ($observedSalt !== $expectedSalt) {
            $reasons[] = 'receipt_wal_index_salt_mismatch';
        }
        if ($observedMxFrame !== $expectedMxFrame) {
            $reasons[] = 'receipt_wal_index_mx_frame_mismatch';
        }
        if ($observedCheckpointFrame !== $expectedCheckpointFrame) {
            $reasons[] = 'receipt_checkpoint_frame_mismatch';
        }
        foreach ($coveredStatements as $statementName) {
            if (!isset($expectedStatements[$statementName])) {
                $reasons[] = 'receipt_statement_not_finalized';
            }
        }
        if (($receipt['commit_mark_seen'] ?? false) !== true) {
            $reasons[] = 'receipt_commit_mark_missing';
        }
        if (($receipt['writer_lock_released'] ?? false) !== true) {
            $reasons[] = 'receipt_writer_lock_not_released';
        }
        if (($receipt['wal_hook_receipt'] ?? false) !== true) {
            $reasons[] = 'receipt_wal_hook_missing';
        }
        if (($receipt['autocheckpoint_receipt'] ?? false) !== true) {
            $reasons[] = 'receipt_autocheckpoint_missing';
        }
        if (($receipt['hot_journal_present'] ?? false) === true) {
            $reasons[] = 'receipt_hot_journal_still_visible';
        }
        if (($receipt['savepoint_open'] ?? false) === true) {
            $reasons[] = 'receipt_savepoint_still_open';
        }
        if (($receipt['dirty_checkpoint_cache'] ?? false) === true) {
            $reasons[] = 'receipt_dirty_checkpoint_cache';
        }

        $reasons = array_values(array_unique($reasons));

        return [
            'name' => $name,
            'source_token' => $observedSource,
            'released_generation' => $observedReleasedGeneration,
            'commit_generation' => $observedCommitGeneration,
            'schema_cookie' => $observedSchemaCookie,
            'database_digest' => $observedDigest,
            'page_cache_digest' => $observedPageCacheDigest,
            'wal_index_salt' => $observedSalt,
            'wal_index_mx_frame' => $observedMxFrame,
            'checkpoint_frame' => $observedCheckpointFrame,
            'covered_statements' => $coveredStatements,
            'dirty_pages' => $dirtyPages,
            'commit_frames' => $commitFrames,
            'commit_mark_seen' => ($receipt['commit_mark_seen'] ?? false) === true,
            'writer_lock_released' => ($receipt['writer_lock_released'] ?? false) === true,
            'wal_hook_receipt' => ($receipt['wal_hook_receipt'] ?? false) === true,
            'autocheckpoint_receipt' => ($receipt['autocheckpoint_receipt'] ?? false) === true,
            'hot_journal_present' => ($receipt['hot_journal_present'] ?? false) === true,
            'savepoint_open' => ($receipt['savepoint_open'] ?? false) === true,
            'dirty_checkpoint_cache' => ($receipt['dirty_checkpoint_cache'] ?? false) === true,
            'admitted' => $reasons === [],
            'receipt_reason' => $reasons === [] ? 'receipt_promotes_checkpoint_current_source_to_autocheckpoint_baseline' : implode('|', $reasons),
            'blocked_reasons' => $reasons,
        ];
    }

    /**
     * @param array<string,mixed> $values
     */
    private static function positiveInt(array $values, string $key): int
    {
        $value = $values[$key] ?? null;
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next240 requires positive {$key}");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $values
     */
    private static function intField(array $values, string $key, string $name): int
    {
        $value = $values[$key] ?? null;
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next240 {$name} {$key} is invalid");
        }

        return $value;
    }

    private static function positiveValue(mixed $value, string $key): int
    {
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next240 requires positive {$key}");
        }

        return $value;
    }

    private static function token(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_.:-]+$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next240 {$label} is invalid");
        }

        return $value;
    }

    private static function digest(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next240 requires {$label}");
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function walSalt(mixed $value): array
    {
        if (!is_array($value) || count($value) !== 2) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next240 requires two WAL-index salt values');
        }

        $salt = array_values($value);
        foreach ($salt as $part) {
            self::token($part, 'WAL-index salt');
        }

        return $salt;
    }

    /**
     * @return array<string,true>
     */
    private static function stringSet(mixed $value, string $label): array
    {
        $names = self::stringList($value, $label);
        if ($names === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next240 requires {$label}");
        }

        $set = [];
        foreach ($names as $name) {
            $set[$name] = true;
        }
        ksort($set);

        return $set;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next240 requires {$label}");
        }

        $names = [];
        foreach ($value as $name) {
            $names[] = self::token($name, $label);
        }

        return array_values(array_unique($names));
    }

    /**
     * @return list<int>
     */
    private static function pageList(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next240 requires {$label}");
        }

        $pages = [];
        foreach ($value as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber <= 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next240 {$label} contains an invalid page/frame number");
            }
            $pages[$pageNumber] = true;
        }
        ksort($pages);

        return array_keys($pages);
    }
}
