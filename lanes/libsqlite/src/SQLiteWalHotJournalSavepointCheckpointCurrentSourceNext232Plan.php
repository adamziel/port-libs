<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext232Plan
{
    /**
     * @param array<string,mixed> $handlePlan
     * @param list<array<string,mixed>> $readerSlots
     * @return array<string,mixed>
     */
    public static function admitReaderSlots(array $handlePlan, array $readerSlots, int $expectedSchemaCookie, string $expectedWalSalt): array
    {
        if (($handlePlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next229') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next232 requires an admitted next229 handle plan');
        }
        if (($handlePlan['current_source_admitted'] ?? null) !== true) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next232 requires current-source admitted handles');
        }
        if ($readerSlots === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next232 requires reader slots');
        }
        if ($expectedSchemaCookie <= 0 || !preg_match('/^[a-f0-9]{16}$/', $expectedWalSalt)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next232 requires schema cookie and WAL salt');
        }

        $sourceToken = self::token($handlePlan['source_token'] ?? null, 'source token');
        $generation = self::positiveInt($handlePlan, 'next_writer_generation');
        $databaseDigest = self::digest($handlePlan['database_digest'] ?? null, 'database digest');
        $walDigest = self::digest($handlePlan['previous_wal_digest'] ?? null, 'previous wal digest');
        $expectedPages = self::positiveIntList($handlePlan['expected_page_numbers'] ?? null, 'expected page numbers');
        $coveredPages = self::positiveIntList($handlePlan['covered_page_numbers'] ?? null, 'covered page numbers');
        $missingPages = array_values(array_diff($expectedPages, $coveredPages));

        $rows = [];
        foreach ($readerSlots as $slot) {
            $rows[] = self::slotRow($slot, $sourceToken, $generation, $databaseDigest, $walDigest, $expectedSchemaCookie, $expectedWalSalt, $expectedPages);
        }

        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => !$row['admitted']));
        $blockedReasons = [];
        foreach ($blockedRows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }
        $blockedReasons = array_values(array_unique($blockedReasons));
        $admittedNames = array_values(array_column(array_filter($rows, static fn (array $row): bool => $row['admitted']), 'name'));

        $guardRows = [
            [
                'name' => 'next229_handles_admitted',
                'matched' => true,
                'reason' => 'next229 already proved reopened handles match the checkpoint current source',
            ],
            [
                'name' => 'checkpoint_pages_still_covered',
                'matched' => $missingPages === [],
                'reason' => 'reader slots cannot publish a checkpoint source with uncovered root pages',
            ],
            [
                'name' => 'all_reader_slots_current',
                'matched' => $blockedRows === [],
                'reason' => 'every reader slot must carry the current generation, digests, schema cookie, WAL salt, and clean cache state',
            ],
            [
                'name' => 'at_least_one_reader_slot_admitted',
                'matched' => $admittedNames !== [],
                'reason' => 'the reopened checkpoint source needs a reader slot to serve page images',
            ],
        ];
        $blockedGuards = array_values(array_column(
            array_filter($guardRows, static fn (array $row): bool => !$row['matched']),
            'name'
        ));
        $admitted = $blockedGuards === [];

        return [
            'status' => $admitted
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next232'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next232',
            'reason' => $admitted
                ? 'reader_slots_admit_reopened_checkpoint_current_source'
                : 'reader_slots_hold_reopened_checkpoint_current_source',
            'base_status' => $handlePlan['status'],
            'database_path' => $handlePlan['database_path'] ?? null,
            'journal_path' => $handlePlan['journal_path'] ?? null,
            'wal_path' => $handlePlan['wal_path'] ?? null,
            'source_token' => $sourceToken,
            'next_writer_generation' => $generation,
            'database_digest' => $databaseDigest,
            'previous_wal_digest' => $walDigest,
            'expected_schema_cookie' => $expectedSchemaCookie,
            'expected_wal_salt' => $expectedWalSalt,
            'expected_page_numbers' => $expectedPages,
            'covered_page_numbers' => $coveredPages,
            'missing_page_numbers' => $missingPages,
            'reader_slot_rows' => $rows,
            'admitted_reader_slot_names' => $admittedNames,
            'blocked_reader_slot_names' => array_values(array_column($blockedRows, 'name')),
            'blocked_reader_slot_reasons' => $blockedReasons,
            'reader_slot_count' => count($rows),
            'current_source_readable' => $admitted,
            'reader_action' => $admitted ? 'serve_checkpoint_pages_from_reopened_slots' : 'force_reader_slot_reopen',
            'wal_action' => $admitted ? 'allow_restarted_wal_readers' : 'hold_previous_wal_until_slots_reopen',
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'blocked_guard_names' => $blockedGuards,
            'reader_slot_digest' => hash('sha256', json_encode([$sourceToken, $generation, $expectedSchemaCookie, $expectedWalSalt, $rows], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($handlePlan['operation_names'] ?? null) ? $handlePlan['operation_names'] : [],
                [
                    'verify_checkpoint_reader_slots_current_source_next232',
                    $admitted ? 'admit_reopened_checkpoint_reader_slots_next232' : 'block_reopened_checkpoint_reader_slots_next232',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($handlePlan['dependencies'] ?? null) ? $handlePlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next232',
                    'sqlite-wal-reopened-reader-slot-current-source',
                    'wordpress-import-hot-journal-checkpoint-reader-slots',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next229 handle publication, reader-slot generation metadata, schema-cookie, WAL-salt, and page coverage receipts',
            'non_overlap' => 'next232 validates reopened reader-slot admission after next229 handle publication; it does not repeat reset publication, reader handle digest coverage, WAL byte truncation, VFS savepoint rollback, rollback-journal commit/apply, checkpoint transaction planning, or VFS file writing',
        ];
    }

    /**
     * @param array<string,mixed> $slot
     * @param list<int> $expectedPages
     * @return array<string,mixed>
     */
    private static function slotRow(array $slot, string $sourceToken, int $generation, string $databaseDigest, string $walDigest, int $schemaCookie, string $walSalt, array $expectedPages): array
    {
        $name = self::token($slot['name'] ?? null, 'reader slot name');
        $slotPages = self::positiveIntList($slot['page_numbers'] ?? null, "{$name} page numbers");
        $reasons = [];

        if (self::token($slot['source_token'] ?? null, "{$name} source token") !== $sourceToken) {
            $reasons[] = 'slot_source_token_mismatch';
        }
        if (self::positiveInt($slot, 'generation', $name) !== $generation) {
            $reasons[] = 'slot_generation_mismatch';
        }
        if (self::digest($slot['database_digest'] ?? null, "{$name} database digest") !== $databaseDigest) {
            $reasons[] = 'slot_database_digest_mismatch';
        }
        if (self::digest($slot['wal_digest'] ?? null, "{$name} wal digest") === $walDigest) {
            $reasons[] = 'slot_reuses_previous_wal_digest';
        }
        if (self::positiveInt($slot, 'schema_cookie', $name) !== $schemaCookie) {
            $reasons[] = 'slot_schema_cookie_mismatch';
        }
        if (($slot['wal_salt'] ?? null) !== $walSalt) {
            $reasons[] = 'slot_wal_salt_mismatch';
        }
        foreach ($slotPages as $page) {
            if (!in_array($page, $expectedPages, true)) {
                $reasons[] = 'slot_page_not_in_checkpoint_set';
            }
        }
        if (($slot['read_mark_frame'] ?? 0) !== 0) {
            $reasons[] = 'slot_read_mark_not_reset';
        }
        if (($slot['hot_journal_visible'] ?? false) === true) {
            $reasons[] = 'slot_hot_journal_visible';
        }
        if (($slot['savepoint_depth'] ?? 0) !== 0) {
            $reasons[] = 'slot_savepoint_scope_open';
        }
        if (($slot['dirty_cache'] ?? false) === true) {
            $reasons[] = 'slot_dirty_cache';
        }
        if (($slot['lock_receipt'] ?? false) !== true) {
            $reasons[] = 'slot_lock_receipt_missing';
        }

        $reasons = array_values(array_unique($reasons));

        return [
            'name' => $name,
            'source_token' => (string) $slot['source_token'],
            'generation' => (int) $slot['generation'],
            'database_digest' => (string) $slot['database_digest'],
            'wal_digest' => (string) $slot['wal_digest'],
            'schema_cookie' => (int) $slot['schema_cookie'],
            'wal_salt' => (string) ($slot['wal_salt'] ?? ''),
            'page_numbers' => $slotPages,
            'read_mark_frame' => (int) ($slot['read_mark_frame'] ?? 0),
            'hot_journal_visible' => ($slot['hot_journal_visible'] ?? false) === true,
            'savepoint_depth' => (int) ($slot['savepoint_depth'] ?? 0),
            'dirty_cache' => ($slot['dirty_cache'] ?? false) === true,
            'lock_receipt' => ($slot['lock_receipt'] ?? false) === true,
            'admitted' => $reasons === [],
            'slot_reason' => $reasons === [] ? 'reader_slot_matches_checkpoint_current_source' : implode('|', $reasons),
            'blocked_reasons' => $reasons,
        ];
    }

    /**
     * @param array<string,mixed> $values
     */
    private static function positiveInt(array $values, string $key, string $name = 'plan'): int
    {
        $value = $values[$key] ?? null;
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next232 {$name} {$key} must be positive");
        }

        return $value;
    }

    private static function token(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_.:-]+$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next232 {$label} is invalid");
        }

        return $value;
    }

    private static function digest(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next232 requires {$label}");
        }

        return $value;
    }

    /**
     * @param mixed $values
     * @return list<int>
     */
    private static function positiveIntList(mixed $values, string $label): array
    {
        if (!is_array($values) || $values === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next232 requires {$label}");
        }
        $out = [];
        foreach ($values as $value) {
            if (!is_int($value) || $value <= 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next232 {$label} must contain positive integers");
            }
            $out[] = $value;
        }
        $out = array_values(array_unique($out));
        sort($out);

        return $out;
    }
}
