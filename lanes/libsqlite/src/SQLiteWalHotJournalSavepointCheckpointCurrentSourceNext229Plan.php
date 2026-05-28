<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext229Plan
{
    /**
     * @param array<string,mixed> $publicationPlan
     * @param list<array<string,mixed>> $handles
     * @param array<int,string> $expectedPageDigests
     * @return array<string,mixed>
     */
    public static function verify(array $publicationPlan, array $handles, array $expectedPageDigests): array
    {
        if (($publicationPlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next224') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next229 requires a published next224 plan');
        }
        if (($publicationPlan['publication_allowed'] ?? null) !== true || ($publicationPlan['checkpoint_reset_visible'] ?? null) !== true) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next229 requires visible reset publication');
        }
        if ($handles === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next229 requires reopened handles');
        }

        $sourceToken = self::token($publicationPlan['source_token'] ?? null, 'source token');
        $generation = self::positiveInt($publicationPlan, 'next_writer_generation');
        $databaseDigest = self::digest($publicationPlan['database_digest'] ?? null, 'database digest');
        $walDigest = self::digest($publicationPlan['previous_wal_digest'] ?? null, 'previous wal digest');
        $pageDigests = self::pageDigestMap($expectedPageDigests);

        $rows = [];
        foreach ($handles as $handle) {
            $rows[] = self::handleRow($handle, $sourceToken, $generation, $databaseDigest, $walDigest, $pageDigests);
        }

        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => !$row['admitted']));
        $blockedReasons = [];
        foreach ($blockedRows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }
        $blockedReasons = array_values(array_unique($blockedReasons));
        $coveredPages = [];
        foreach ($rows as $row) {
            foreach ($row['page_numbers'] as $pageNumber) {
                if ($row['admitted']) {
                    $coveredPages[$pageNumber] = true;
                }
            }
        }
        ksort($coveredPages);
        $missingPages = array_values(array_diff(array_keys($pageDigests), array_keys($coveredPages)));

        $guardRows = [
            [
                'name' => 'next224_publication_visible',
                'matched' => true,
                'reason' => 'reset sidecar and reader publication was admitted by next224',
            ],
            [
                'name' => 'all_reopened_handles_current',
                'matched' => $blockedRows === [],
                'reason' => 'every reopened handle must match the published source token, generation, digests, and clean state',
            ],
            [
                'name' => 'checkpoint_pages_covered',
                'matched' => $missingPages === [],
                'reason' => 'all checkpointed root pages must have at least one current-source handle image',
            ],
        ];
        $blockedGuards = array_values(array_column(
            array_filter($guardRows, static fn (array $row): bool => !$row['matched']),
            'name'
        ));
        $admitted = $blockedGuards === [];

        return [
            'status' => $admitted
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next229'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next229',
            'reason' => $admitted
                ? 'reopened_handles_match_checkpoint_current_source_after_hot_journal_savepoint'
                : 'reopened_handles_hold_checkpoint_current_source_publication',
            'base_status' => $publicationPlan['status'],
            'database_path' => $publicationPlan['database_path'] ?? null,
            'journal_path' => $publicationPlan['journal_path'] ?? null,
            'wal_path' => $publicationPlan['wal_path'] ?? null,
            'source_token' => $sourceToken,
            'next_writer_generation' => $generation,
            'database_digest' => $databaseDigest,
            'previous_wal_digest' => $walDigest,
            'expected_page_numbers' => array_keys($pageDigests),
            'covered_page_numbers' => array_keys($coveredPages),
            'missing_page_numbers' => $missingPages,
            'handle_rows' => $rows,
            'admitted_handle_names' => array_values(array_column(array_filter($rows, static fn (array $row): bool => $row['admitted']), 'name')),
            'blocked_handle_names' => array_values(array_column($blockedRows, 'name')),
            'blocked_handle_reasons' => $blockedReasons,
            'current_source_admitted' => $admitted,
            'reader_action' => $admitted ? 'allow_reopened_handles_to_serve_checkpoint_source' : 'force_handle_reopen_before_current_source',
            'wal_action' => $admitted ? 'keep_next224_reset_publication_visible' : 'hold_previous_wal_generation_until_reopen',
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'blocked_guard_names' => $blockedGuards,
            'publication_digest' => hash('sha256', json_encode([$sourceToken, $generation, $rows, $missingPages], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($publicationPlan['operation_names'] ?? null) ? $publicationPlan['operation_names'] : [],
                [
                    'verify_reopened_handles_after_checkpoint_publication_next229',
                    $admitted ? 'admit_checkpoint_current_source_next229' : 'defer_checkpoint_current_source_next229',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($publicationPlan['dependencies'] ?? null) ? $publicationPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next229',
                    'sqlite-wal-checkpoint-reopened-handle-current-source',
                    'wordpress-import-hot-journal-savepoint-checkpoint-reopen',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next224 publication receipts and reopened handle/page digest metadata',
            'non_overlap' => 'next229 validates reopened handle visibility after next224 publication; it does not repeat reset admission, sidecar receipt publication, WAL byte truncation, VFS savepoint rollback apply, rollback-journal commit/apply, or checkpoint transaction planning',
        ];
    }

    /**
     * @param array<string,mixed> $handle
     * @param array<int,string> $expectedPageDigests
     * @return array<string,mixed>
     */
    private static function handleRow(array $handle, string $sourceToken, int $generation, string $databaseDigest, string $walDigest, array $expectedPageDigests): array
    {
        $name = self::token($handle['name'] ?? null, 'handle name');
        $observedSource = self::token($handle['source_token'] ?? null, "{$name} source token");
        $observedGeneration = self::intField($handle, 'generation', $name);
        $observedDatabaseDigest = self::digest($handle['database_digest'] ?? null, "{$name} database digest");
        $observedWalDigest = self::digest($handle['wal_digest'] ?? null, "{$name} wal digest");
        $observedPages = self::pageDigestMap($handle['page_digests'] ?? null, $name);

        $reasons = [];
        if ($observedSource !== $sourceToken) {
            $reasons[] = 'handle_source_token_mismatch';
        }
        if ($observedGeneration !== $generation) {
            $reasons[] = 'handle_generation_mismatch';
        }
        if (!hash_equals($databaseDigest, $observedDatabaseDigest)) {
            $reasons[] = 'handle_database_digest_mismatch';
        }
        if (hash_equals($walDigest, $observedWalDigest)) {
            $reasons[] = 'handle_reuses_previous_wal_digest';
        }
        foreach ($observedPages as $pageNumber => $digest) {
            if (!isset($expectedPageDigests[$pageNumber])) {
                $reasons[] = 'handle_page_not_in_checkpoint_set';
                continue;
            }
            if (!hash_equals($expectedPageDigests[$pageNumber], $digest)) {
                $reasons[] = 'handle_page_digest_mismatch';
            }
        }
        foreach (array_keys($expectedPageDigests) as $pageNumber) {
            if (($handle['require_all_pages'] ?? false) === true && !isset($observedPages[$pageNumber])) {
                $reasons[] = 'handle_missing_required_checkpoint_page';
            }
        }
        if (($handle['hot_journal_present'] ?? false) === true) {
            $reasons[] = 'handle_hot_journal_still_visible';
        }
        if ((int) ($handle['savepoint_depth'] ?? 0) !== 0) {
            $reasons[] = 'handle_savepoint_scope_open';
        }
        if (($handle['dirty_cache'] ?? false) === true) {
            $reasons[] = 'handle_dirty_cache';
        }
        if (($handle['lock_receipt'] ?? false) !== true) {
            $reasons[] = 'handle_lock_receipt_missing';
        }
        if (($handle['sync_receipt'] ?? false) !== true) {
            $reasons[] = 'handle_sync_receipt_missing';
        }

        $reasons = array_values(array_unique($reasons));

        return [
            'name' => $name,
            'source_token' => $observedSource,
            'generation' => $observedGeneration,
            'database_digest' => $observedDatabaseDigest,
            'wal_digest' => $observedWalDigest,
            'page_numbers' => array_keys($observedPages),
            'page_digest_count' => count($observedPages),
            'hot_journal_present' => ($handle['hot_journal_present'] ?? false) === true,
            'savepoint_depth' => (int) ($handle['savepoint_depth'] ?? 0),
            'dirty_cache' => ($handle['dirty_cache'] ?? false) === true,
            'lock_receipt' => ($handle['lock_receipt'] ?? false) === true,
            'sync_receipt' => ($handle['sync_receipt'] ?? false) === true,
            'admitted' => $reasons === [],
            'handle_reason' => $reasons === [] ? 'handle_matches_checkpoint_current_source' : implode('|', $reasons),
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
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next229 requires positive {$key}");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $values
     */
    private static function intField(array $values, string $key, string $name): int
    {
        $value = $values[$key] ?? null;
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next229 {$name} {$key} is invalid");
        }

        return $value;
    }

    private static function token(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_.:-]+$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next229 {$label} is invalid");
        }

        return $value;
    }

    private static function digest(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next229 requires {$label}");
        }

        return $value;
    }

    /**
     * @param mixed $values
     * @return array<int,string>
     */
    private static function pageDigestMap(mixed $values, string $name = 'expected pages'): array
    {
        if (!is_array($values) || $values === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next229 {$name} requires page digests");
        }
        $normalized = [];
        foreach ($values as $page => $digest) {
            $pageNumber = (int) $page;
            if ($pageNumber <= 0 || !is_string($digest) || !preg_match('/^[a-f0-9]{64}$/', $digest)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next229 {$name} page digests must map positive pages to sha256 strings");
            }
            $normalized[$pageNumber] = $digest;
        }
        ksort($normalized);

        return $normalized;
    }
}
