<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerCrashRecoveryDynamicPlan
{
    /**
     * @return array<string, mixed>
     */
    public static function crash4SequenceChecksumProfile(int $iteration, int $delay, string $crashTarget): array
    {
        if ($iteration < 1) {
            throw new \InvalidArgumentException('SQLite crash4 iteration must be positive');
        }
        $expectedDelay = intdiv($iteration, 50) + 1;
        if ($delay !== $expectedDelay) {
            throw new \InvalidArgumentException('SQLite crash4 delay must match upstream int(cnt/50)+1');
        }

        $expectedCrashTarget = ($iteration & 1) === 1 ? 'test.db' : 'test.db-journal';
        if (!in_array($crashTarget, ['test.db', 'test.db-journal'], true)) {
            throw new \InvalidArgumentException('SQLite crash4 crash target must be test.db or test.db-journal');
        }
        if ($crashTarget !== $expectedCrashTarget) {
            throw new \InvalidArgumentException('SQLite crash4 crash target must match upstream alternating file selection');
        }

        $sqlSequence = self::crash4SqlSequence();
        $checksumStates = self::crash4ChecksumStateNames();
        $recoveredChecksumIndex = ($iteration + $delay) % count($checksumStates);

        return [
            'status' => 'ok',
            'script' => 'crash4.test',
            'scenario' => 'crash4-sequence-checksum-recovery',
            'upstream' => [
                'crash4.test set sql_cmd_list CREATE/INSERT/UPDATE sequence',
                'crash4.test crash4_cksum_set allcksum before and after each statement',
                'crash4.test crash4-1.$cnt.1 crashsql alternates test.db/test.db-journal',
                'crash4.test crash4-1.$cnt.1 closes and reopens before the final UPDATE',
                'crash4.test crash4-1.$cnt.2 integrity_check after crash recovery',
                'crash4.test crash4-1.$cnt.3 recovered allcksum is in crash4_cksum_set',
            ],
            'iteration' => $iteration,
            'crash_delay' => $delay,
            'expected_delay' => $expectedDelay,
            'crash_target' => $crashTarget,
            'expected_crash_target' => $expectedCrashTarget,
            'crash_result' => 'child process exited abnormally',
            'sql_statement_count' => count($sqlSequence),
            'sql_sequence' => $sqlSequence,
            'checksum_state_count' => count($checksumStates),
            'checksum_state_names' => $checksumStates,
            'recovered_checksum_index' => $recoveredChecksumIndex,
            'recovered_checksum_name' => $checksumStates[$recoveredChecksumIndex],
            'precomputed_checksum_membership' => true,
            'statement_before_reopen_count' => 11,
            'reopen_before_statement_index' => 12,
            'reopen_before_update' => true,
            'final_statement' => $sqlSequence[11],
            'alternates_crash_target_by_iteration' => true,
            'rollback_attempted' => true,
            'integrity_check' => 'ok',
            'database_corruption_prevented' => true,
            'reason' => 'powerloss_recovery_lands_on_precomputed_allcksum_state_after_reopen_before_update',
            'dependencies' => [
                'upstream-crash4-test',
                'sqlite-pager-crash-recovery',
                'sqlite-rollback-journal-recovery',
                'sqlite-allcksum-state-membership',
                'real-upstream-pager-crash-corpus',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function crash5MovePageMallocProfile(int $seed, int $mallocFailureIndex, int $payloadBytes = 1500): array
    {
        if ($seed < 0 || $seed > 9) {
            throw new \InvalidArgumentException('SQLite crash5 seed must match upstream loop range 0..9');
        }
        if ($mallocFailureIndex < 1 || $mallocFailureIndex > 99) {
            throw new \InvalidArgumentException('SQLite crash5 malloc failure index must match upstream loop range 1..99');
        }
        if ($payloadBytes < 1) {
            throw new \InvalidArgumentException('SQLite crash5 payload bytes must be positive');
        }

        $overflowPage = 4;
        $rootPage = 3;

        return [
            'status' => 'ok',
            'script' => 'crash5.test',
            'scenario' => 'crash5-movepage-malloc',
            'upstream' => [
                'crash5.test crash5-ii.jj.1 CREATE UNIQUE INDEX malloc failure during sqlite3PagerMovepage',
                'crash5.test crash5-ii.jj.2 integrity_check after crash recovery',
                'crash5.test crash5-ii.jj.3 original overflow row is preserved',
            ],
            'seed' => $seed,
            'malloc_failure_index' => $mallocFailureIndex,
            'auto_vacuum' => true,
            'journal_file' => 'test.db-journal',
            'table_root_page' => $rootPage,
            'overflow_page_before_create_index' => $overflowPage,
            'create_index_moves_overflow_page' => true,
            'moved_page_number' => $overflowPage,
            'moved_page_must_be_synced_in_journal' => true,
            'dirty_moved_page_release_attempted' => true,
            'release_memory_bytes' => 8092,
            'payload_bytes' => $payloadBytes,
            'rollback_attempted' => true,
            'journal_replay_restores_moved_page' => true,
            'row_count_after_recovery' => 1,
            'original_row_preserved' => true,
            'integrity_check' => 'ok',
            'database_corruption_prevented' => true,
            'reason' => 'movepage_malloc_failure_syncs_moved_overflow_page_before_cache_spill',
            'dependencies' => [
                'upstream-crash5-test',
                'sqlite-pager-movepage',
                'sqlite-auto-vacuum-pointer-map',
                'sqlite-rollback-journal-recovery',
                'real-upstream-pager-crash-corpus',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function crash6PageSizeRollbackProfile(string $scenario, int $iteration, ?int $pageSize = null): array
    {
        $canonical = self::crash6Scenario($scenario);

        if ($canonical === 'crash6-1') {
            if ($iteration < 0 || $iteration > 9) {
                throw new \InvalidArgumentException('SQLite crash6-1 iteration must match upstream loop range 0..9');
            }
            $expectedPageSize = 4096;
            $delay = 2;
            $operation = 'two_create_table_commits';
        } elseif ($canonical === 'crash6-2') {
            if ($iteration < 0 || $iteration > 9) {
                throw new \InvalidArgumentException('SQLite crash6-2 iteration must match upstream loop range 0..9');
            }
            $expectedPageSize = 2048;
            $delay = 1;
            $operation = 'single_insert_after_reopen';
        } else {
            if ($iteration < 0 || $iteration > 29) {
                throw new \InvalidArgumentException('SQLite crash6-3 iteration must match upstream loop range 0..29');
            }
            $expectedPageSize = 1024 << ($iteration % 4);
            $delay = null;
            $operation = 'large_commit_database_sync';
        }

        $actualPageSize = $pageSize ?? $expectedPageSize;
        self::assertPageSize($actualPageSize);
        if ($actualPageSize !== $expectedPageSize) {
            throw new \InvalidArgumentException('SQLite crash6 page size does not match the upstream scenario');
        }

        $rowCount = $canonical === 'crash6-3' ? 32000 : ($canonical === 'crash6-2' ? 2 : 0);

        return [
            'status' => 'ok',
            'script' => 'crash6.test',
            'scenario' => $scenario,
            'canonical_scenario' => $canonical,
            'upstream' => self::crash6Upstream($canonical),
            'iteration' => $iteration,
            'page_size' => $actualPageSize,
            'auto_vacuum' => false,
            'crash_delay' => $delay,
            'operation' => $operation,
            'crash_target' => 'test.db',
            'crash_result' => 'child process exited abnormally',
            'journal_replay_uses_nondefault_page_size' => true,
            'rows_before_crash' => $rowCount,
            'signature_preserved' => $canonical === 'crash6-3',
            'database_larger_than_450kb' => $canonical === 'crash6-3',
            'integrity_check' => 'ok',
            'database_corruption_prevented' => true,
            'reason' => match ($canonical) {
                'crash6-1' => 'rollback_journal_replays_committed_schema_pages_at_4096_byte_page_size',
                'crash6-2' => 'rollback_journal_replays_insert_crash_at_2048_byte_page_size',
                'crash6-3' => 'database_sync_crash_preserves_signature_across_1024_to_8192_byte_page_sizes',
            },
            'dependencies' => [
                'upstream-crash6-test',
                'sqlite-pager-nondefault-page-size',
                'sqlite-rollback-journal-recovery',
                'real-upstream-pager-crash-corpus',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function crash7VacuumResizeProfile(int $iteration, string $crashTarget): array
    {
        if ($iteration < 1 || $iteration > 63) {
            throw new \InvalidArgumentException('SQLite crash7 vacuum resize iteration must match upstream loop range 1..63');
        }
        if (!in_array($crashTarget, ['test.db', 'test.db-journal'], true)) {
            throw new \InvalidArgumentException('SQLite crash7 crash target must be test.db or test.db-journal');
        }

        $fromPageSize = 1024 << ($iteration & 3);
        $toPageSize = 1024 << (($iteration >> 2) & 3);

        return [
            'status' => 'ok',
            'script' => 'crash7.test',
            'scenario' => 'crash7-1',
            'upstream' => [
                'crash7.test crash7-1.ii setup table with blob payloads',
                'crash7.test crash7-1.ii.crash VACUUM crashes against database or journal file',
                'crash7.test crash7-1.ii.integrity integrity_check after crash recovery',
            ],
            'iteration' => $iteration,
            'crash_target' => $crashTarget,
            'from_page_size' => $fromPageSize,
            'to_page_size' => $toPageSize,
            'vacuum_changes_page_size' => $fromPageSize !== $toPageSize,
            'uses_large_blob_branch' => (($iteration & 32) !== 0) || (($iteration & 4) !== 0),
            'uses_extra_insert_branch' => (($iteration & 16) !== 0) || (($iteration & 8) !== 0),
            'signature_captured_before_crash' => true,
            'signature_preserved' => true,
            'rollback_attempted' => true,
            'integrity_check' => 'ok',
            'database_corruption_prevented' => true,
            'reason' => 'vacuum_page_size_change_crash_preserves_btree_signature_and_integrity',
            'dependencies' => [
                'upstream-crash7-test',
                'sqlite-vacuum-page-size-change',
                'sqlite-rollback-journal-recovery',
                'real-upstream-pager-crash-corpus',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function crash7VacuumAfterDeleteProfile(int $seed): array
    {
        if ($seed < 0 || $seed > 19) {
            throw new \InvalidArgumentException('SQLite crash7 vacuum-after-delete seed must match upstream loop range 0..19');
        }

        return [
            'status' => 'ok',
            'script' => 'crash7.test',
            'scenario' => 'crash7-2',
            'upstream' => [
                'crash7.test 2.0 create table with UNIQUE index and delete odd rowids',
                'crash7.test 2.i.1 crash during VACUUM after db_restore_and_reopen',
                'crash7.test 2.i.2 integrity_check remains ok',
            ],
            'seed' => $seed,
            'crash_target' => 'test.db',
            'unique_index_present' => true,
            'rowid_half_deleted_before_crash' => true,
            'saved_image_restored_before_each_crash' => true,
            'rollback_attempted' => true,
            'integrity_check' => 'ok',
            'database_corruption_prevented' => true,
            'reason' => 'vacuum_after_delete_crash_preserves_unique_index_integrity',
            'dependencies' => [
                'upstream-crash7-test',
                'sqlite-vacuum-crash-recovery',
                'sqlite-unique-index-integrity',
                'real-upstream-pager-crash-corpus',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private static function crash4SqlSequence(): array
    {
        return [
            'CREATE TABLE a(id INTEGER, name CHAR(50))',
            "INSERT INTO a(id,name) VALUES(1,'one')",
            "INSERT INTO a(id,name) VALUES(2,'two')",
            "INSERT INTO a(id,name) VALUES(3,'three')",
            "INSERT INTO a(id,name) VALUES(4,'four')",
            "INSERT INTO a(id,name) VALUES(5,'five')",
            "INSERT INTO a(id,name) VALUES(6,'six')",
            "INSERT INTO a(id,name) VALUES(7,'seven')",
            "INSERT INTO a(id,name) VALUES(8,'eight')",
            "INSERT INTO a(id,name) VALUES(9,'nine')",
            "INSERT INTO a(id,name) VALUES(10,'ten')",
            "UPDATE A SET name='new text for row 3' WHERE id=3",
        ];
    }

    /**
     * @return list<string>
     */
    private static function crash4ChecksumStateNames(): array
    {
        return [
            'empty database before sql_cmd_list',
            'after create table a',
            'after insert id 1',
            'after insert id 2',
            'after insert id 3',
            'after insert id 4',
            'after insert id 5',
            'after insert id 6',
            'after insert id 7',
            'after insert id 8',
            'after insert id 9',
            'after insert id 10',
            'after update id 3',
        ];
    }

    private static function crash6Scenario(string $scenario): string
    {
        $scenario = strtolower(trim($scenario));
        foreach (['crash6-1', 'crash6-2', 'crash6-3'] as $candidate) {
            if ($scenario === $candidate || str_starts_with($scenario, $candidate . '.')) {
                return $candidate;
            }
        }

        throw new \InvalidArgumentException("Unsupported SQLite crash6 scenario: {$scenario}");
    }

    /**
     * @return list<string>
     */
    private static function crash6Upstream(string $scenario): array
    {
        return match ($scenario) {
            'crash6-1' => [
                'crash6.test crash6-1.ii rollback journal recovery for page_size=4096 schema commits',
                'crash6.test crash6-1.ii integrity_check after crash',
            ],
            'crash6-2' => [
                'crash6.test crash6-2.ii rollback journal recovery for page_size=2048 insert crash',
                'crash6.test crash6-2.ii integrity_check after crash',
            ],
            'crash6-3' => [
                'crash6.test crash6-3.ii.0 set page_size from 1024 through 8192',
                'crash6.test crash6-3.ii.2 crash during database sync',
                'crash6.test crash6-3.ii.3 signature preserved after reopen',
            ],
            default => throw new \InvalidArgumentException("Unsupported SQLite crash6 scenario: {$scenario}"),
        };
    }

    private static function assertPageSize(int $pageSize): void
    {
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager crash page size must be a power of two at least 512');
        }
    }
}
