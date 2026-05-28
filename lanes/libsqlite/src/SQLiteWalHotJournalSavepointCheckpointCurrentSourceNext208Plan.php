<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext208Plan
{
    /**
     * @param array<string,mixed> $consumerPlan
     * @param list<array{name:string,consumer_name:string,read_mark:int,reader_epoch:int,checkpoint_frame:int,observed_database_digest:string,observed_wal_digest:string,observed_page_digests:array<int,string>,lock_receipt?:bool,hot_journal_digest?:?string,savepoint_depth?:int,dirty?:bool,closed?:bool}> $readerSlots
     * @return array<string,mixed>
     */
    public static function plan(array $consumerPlan, array $readerSlots, int $checkpointFrame): array
    {
        if (($consumerPlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next206') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next208 requires an admitted next206 consumer plan');
        }
        if ($readerSlots === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next208 requires reader slot rows');
        }
        if ($checkpointFrame < 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next208 checkpoint frame must be non-negative');
        }

        $databaseDigest = self::digestField($consumerPlan, 'checkpointed_database_digest');
        $walDigest = self::digestField($consumerPlan, 'expected_wal_digest');
        $pageDigests = $consumerPlan['expected_page_digests'] ?? null;
        if (!is_array($pageDigests) || $pageDigests === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next208 requires checkpoint page digests');
        }
        $minimumGeneration = $consumerPlan['minimum_statement_generation'] ?? null;
        if (!is_int($minimumGeneration) || $minimumGeneration < 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next208 requires a minimum statement generation');
        }

        $admittedConsumers = self::stringSet($consumerPlan['admitted_consumer_names'] ?? []);
        $slotRows = [];
        $retained = [];
        $reopened = [];
        foreach ($readerSlots as $slot) {
            $row = self::slotDecision($slot, $admittedConsumers, $databaseDigest, $walDigest, $pageDigests, $minimumGeneration, $checkpointFrame);
            $slotRows[] = $row;
            if ($row['admitted']) {
                $retained[] = $row['name'];
            } else {
                $reopened[] = $row['name'];
            }
        }

        $guardRows = [
            [
                'name' => 'next206_statement_consumer_fence',
                'matched' => ($consumerPlan['blocked_guard_names'] ?? []) === [],
                'reason' => 'next206 must admit only current-source statement consumers before reader slots are reused',
            ],
            [
                'name' => 'reader_slot_reuse_mix',
                'matched' => $retained !== [] && $reopened !== [],
                'reason' => 'current reader slots are retained while stale checkpoint readers are reopened',
            ],
            [
                'name' => 'checkpoint_frame_not_exceeded',
                'matched' => self::retainedSlotsWithinCheckpoint($slotRows, $checkpointFrame),
                'reason' => 'retained reader slots cannot point past the published checkpoint frame',
            ],
        ];
        $blockedGuards = array_values(array_column(
            array_filter($guardRows, static fn (array $row): bool => !$row['matched']),
            'name'
        ));

        $status = $blockedGuards === []
            ? 'wal-hot-journal-savepoint-checkpoint-current-source-next208'
            : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next208';

        return [
            'status' => $status,
            'reason' => $status === 'wal-hot-journal-savepoint-checkpoint-current-source-next208'
                ? 'checkpoint_reader_slots_match_current_source_generation'
                : 'checkpoint_reader_slots_wait_for_current_source_reopen',
            'base_status' => $consumerPlan['status'],
            'database_path' => $consumerPlan['database_path'] ?? null,
            'wal_path' => $consumerPlan['wal_path'] ?? null,
            'journal_path' => $consumerPlan['journal_path'] ?? null,
            'page_size' => $consumerPlan['page_size'] ?? null,
            'checkpoint_frame' => $checkpointFrame,
            'minimum_statement_generation' => $minimumGeneration,
            'checkpointed_database_digest' => $databaseDigest,
            'expected_wal_digest' => $walDigest,
            'expected_page_digests' => $pageDigests,
            'slot_rows' => $slotRows,
            'retained_reader_slot_names' => $retained,
            'reopened_reader_slot_names' => $reopened,
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'blocked_guard_names' => $blockedGuards,
            'operation_names' => array_values(array_merge(
                $consumerPlan['operation_names'] ?? [],
                ['verify_checkpoint_reader_slots_current_source_next208'],
                array_map(
                    static fn (array $row): string => $row['admitted']
                        ? 'retain_checkpoint_reader_slot_current_source_next208'
                        : 'reopen_checkpoint_reader_slot_current_source_next208',
                    $slotRows
                )
            )),
            'reader_slot_digest' => hash('sha256', implode('|', array_merge(
                [$databaseDigest, $walDigest, (string) $minimumGeneration, (string) $checkpointFrame],
                array_column($slotRows, 'slot_transition')
            ))),
            'dependencies' => array_values(array_unique(array_merge($consumerPlan['dependencies'] ?? [], [
                'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next208',
                'sqlite-checkpoint-reader-slot-current-source-map',
                'wordpress-import-reader-slot-reopen-after-checkpoint',
            ]))),
            'dependency_closure' => 'no new support component needed; reuses next206 current-source statement fencing plus checkpoint page digests to decide reader-slot reuse after checkpoint publication',
            'non_overlap' => 'next208 maps post-checkpoint reader-slot reuse from next206 admitted consumers; it does not repeat next206 prepared-statement quarantine, next203 page-cache lease checks, WAL byte truncation, WAL sidecar writes, rollback-journal commit/apply, checkpoint transaction planning, or VFS savepoint rollback',
        ];
    }

    /**
     * @param array<string,mixed> $values
     */
    private static function digestField(array $values, string $key): string
    {
        $value = $values[$key] ?? null;
        if (!is_string($value) || strlen($value) !== 64) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next208 requires {$key}");
        }

        return $value;
    }

    /**
     * @param mixed $values
     * @return array<string,true>
     */
    private static function stringSet($values): array
    {
        if (!is_array($values) || $values === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next208 requires admitted consumer names');
        }
        $set = [];
        foreach ($values as $value) {
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next208 admitted consumer names must be non-empty strings');
            }
            $set[$value] = true;
        }

        return $set;
    }

    /**
     * @param array<string,mixed> $slot
     * @param array<string,true> $admittedConsumers
     * @param array<int,string> $pageDigests
     * @return array<string,mixed>
     */
    private static function slotDecision(array $slot, array $admittedConsumers, string $databaseDigest, string $walDigest, array $pageDigests, int $minimumGeneration, int $checkpointFrame): array
    {
        $name = self::stringField($slot, 'name');
        $consumerName = self::stringField($slot, 'consumer_name');
        $readMark = self::intField($slot, 'read_mark');
        $readerEpoch = self::intField($slot, 'reader_epoch');
        $slotCheckpointFrame = self::intField($slot, 'checkpoint_frame');
        if ($readMark < 0 || $readerEpoch < 0 || $slotCheckpointFrame < 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next208 {$name} numeric fields must be non-negative");
        }
        $observedDatabaseDigest = self::digestField($slot, 'observed_database_digest');
        $observedWalDigest = self::digestField($slot, 'observed_wal_digest');
        $rootPages = $slot['root_pages'] ?? null;
        $observedPageDigests = $slot['observed_page_digests'] ?? null;
        if (!is_array($rootPages) || $rootPages === [] || !is_array($observedPageDigests) || $observedPageDigests === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next208 {$name} page rows are required");
        }

        $pageRows = [];
        $stalePages = [];
        $missingPages = [];
        foreach ($rootPages as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next208 {$name} root page must be positive");
            }
            $expectedDigest = $pageDigests[$pageNumber] ?? null;
            if (!is_string($expectedDigest)) {
                $missingPages[] = $pageNumber;
                $pageRows[] = [
                    'page' => $pageNumber,
                    'matched' => false,
                    'reason' => 'page_outside_checkpoint_reader_generation',
                    'expected_digest' => null,
                    'observed_digest' => $observedPageDigests[$pageNumber] ?? null,
                ];
                continue;
            }
            $observedDigest = $observedPageDigests[$pageNumber] ?? null;
            if (!is_string($observedDigest) || strlen($observedDigest) !== 64) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next208 {$name} page digest is required");
            }
            $matched = hash_equals($expectedDigest, $observedDigest);
            if (!$matched) {
                $stalePages[] = $pageNumber;
            }
            $pageRows[] = [
                'page' => $pageNumber,
                'matched' => $matched,
                'reason' => $matched ? 'checkpoint_reader_page_matches' : 'checkpoint_reader_page_stale',
                'expected_digest' => $expectedDigest,
                'observed_digest' => $observedDigest,
            ];
        }

        $hotJournalDigest = $slot['hot_journal_digest'] ?? null;
        if ($hotJournalDigest !== null && (!is_string($hotJournalDigest) || strlen($hotJournalDigest) !== 64)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next208 {$name} hot journal digest must be a sha256 string or null");
        }

        $reasons = [];
        if (!isset($admittedConsumers[$consumerName])) {
            $reasons[] = 'reader_slot_consumer_not_admitted_by_next206';
        }
        if ($readerEpoch < $minimumGeneration) {
            $reasons[] = 'reader_slot_epoch_predates_checkpoint_generation';
        }
        if ($slotCheckpointFrame > $checkpointFrame) {
            $reasons[] = 'reader_slot_frame_exceeds_checkpoint_publication';
        }
        if (!hash_equals($databaseDigest, $observedDatabaseDigest)) {
            $reasons[] = 'reader_slot_database_digest_mismatch';
        }
        if (!hash_equals($walDigest, $observedWalDigest)) {
            $reasons[] = 'reader_slot_wal_digest_mismatch';
        }
        if ($stalePages !== [] || $missingPages !== []) {
            $reasons[] = 'reader_slot_page_digest_mismatch';
        }
        if ($hotJournalDigest !== null) {
            $reasons[] = 'reader_slot_retains_hot_journal_digest';
        }
        if (($slot['savepoint_depth'] ?? 0) !== 0) {
            $reasons[] = 'reader_slot_savepoint_scope_not_closed';
        }
        if (empty($slot['lock_receipt'])) {
            $reasons[] = 'reader_slot_missing_shared_lock_receipt';
        }
        if (!empty($slot['dirty'])) {
            $reasons[] = 'reader_slot_dirty_before_checkpoint_publication';
        }
        if (!empty($slot['closed'])) {
            $reasons[] = 'reader_slot_closed_before_checkpoint_publication';
        }

        $admitted = $reasons === [];

        return array_merge($slot, [
            'admitted' => $admitted,
            'slot_reason' => $admitted ? 'reader_slot_matches_checkpoint_generation' : $reasons[0],
            'blocked_reasons' => $reasons,
            'expected_database_digest' => $databaseDigest,
            'expected_wal_digest' => $walDigest,
            'root_pages' => array_values($rootPages),
            'page_rows' => $pageRows,
            'stale_pages' => $stalePages,
            'missing_pages' => $missingPages,
            'hot_journal_retained' => $hotJournalDigest !== null,
            'slot_transition' => $name . '>' . ($admitted ? 'retain-reader-slot' : 'reopen-reader-slot'),
        ]);
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function stringField(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next208 {$key} must be a non-empty string");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function intField(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (!is_int($value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next208 {$key} must be an integer");
        }

        return $value;
    }

    /**
     * @param list<array<string,mixed>> $slotRows
     */
    private static function retainedSlotsWithinCheckpoint(array $slotRows, int $checkpointFrame): bool
    {
        foreach ($slotRows as $row) {
            if (($row['admitted'] ?? false) === true && ($row['checkpoint_frame'] ?? 0) > $checkpointFrame) {
                return false;
            }
        }

        return true;
    }
}
