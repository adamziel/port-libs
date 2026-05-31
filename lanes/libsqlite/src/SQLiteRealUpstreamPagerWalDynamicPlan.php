<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRealUpstreamPagerWalDynamicPlan
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function wal2HeaderRecoveryCases(): array
    {
        $recoverLocks = [
            ['slot' => 0, 'count' => 1, 'op' => 'lock', 'level' => 'exclusive'],
            ['slot' => 1, 'count' => 2, 'op' => 'lock', 'level' => 'exclusive'],
            ['slot' => 4, 'count' => 1, 'op' => 'lock', 'level' => 'exclusive'],
            ['slot' => 4, 'count' => 1, 'op' => 'unlock', 'level' => 'exclusive'],
            ['slot' => 5, 'count' => 1, 'op' => 'lock', 'level' => 'exclusive'],
            ['slot' => 5, 'count' => 1, 'op' => 'unlock', 'level' => 'exclusive'],
            ['slot' => 6, 'count' => 1, 'op' => 'lock', 'level' => 'exclusive'],
            ['slot' => 6, 'count' => 1, 'op' => 'unlock', 'level' => 'exclusive'],
            ['slot' => 7, 'count' => 1, 'op' => 'lock', 'level' => 'exclusive'],
            ['slot' => 7, 'count' => 1, 'op' => 'unlock', 'level' => 'exclusive'],
            ['slot' => 1, 'count' => 2, 'op' => 'unlock', 'level' => 'exclusive'],
            ['slot' => 0, 'count' => 1, 'op' => 'unlock', 'level' => 'exclusive'],
            ['slot' => 4, 'count' => 1, 'op' => 'lock', 'level' => 'shared'],
            ['slot' => 4, 'count' => 1, 'op' => 'unlock', 'level' => 'shared'],
        ];
        $initSlotLocks = [
            ['slot' => 4, 'count' => 1, 'op' => 'lock', 'level' => 'exclusive'],
            ['slot' => 4, 'count' => 1, 'op' => 'unlock', 'level' => 'exclusive'],
            ['slot' => 4, 'count' => 1, 'op' => 'lock', 'level' => 'shared'],
            ['slot' => 4, 'count' => 1, 'op' => 'unlock', 'level' => 'shared'],
        ];

        $cases = [];
        foreach ([
            [2, 5, 5, 15, 0],
            [3, 6, 6, 21, 1],
            [4, 7, 7, 28, 2],
            [5, 8, 8, 36, 3],
            [6, 9, 9, 45, 4],
            [7, 10, 10, 55, 5],
            [8, 11, 11, 66, 6],
            [9, 12, 12, 78, 7],
            [10, 13, 13, 91, 8],
            [11, 14, 14, 105, 9],
            [12, 15, 15, 120, -1],
        ] as [$tn, $insert, $count, $sum, $headerField]) {
            $locks = $headerField < 0 ? $initSlotLocks : $recoverLocks;
            $cases[] = [
                'upstream' => 'wal2-1.' . $tn,
                'inserted_value' => $insert,
                'count' => $count,
                'sum' => $sum,
                'wal_index_header_field' => $headerField,
                'recovery_required' => $headerField >= 0,
                'lock_sequence' => $locks,
                'exclusive_lock_count' => self::countLocks($locks, 'lock', 'exclusive'),
                'shared_lock_count' => self::countLocks($locks, 'lock', 'shared'),
                'final_snapshot' => [$count, $sum],
                'source_file' => 'wal2.test',
            ];
        }

        return $cases;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function wal2OutOfDateHeaderCases(): array
    {
        $cases = [];
        foreach ([
            [2, 5, [4, 10], [5, 15], 0],
            [3, 6, [5, 15], [6, 21], 1],
            [4, 7, [6, 21], [7, 28], 2],
            [5, 8, [7, 28], [8, 36], 3],
            [6, 9, [8, 36], [9, 45], 4],
            [7, 10, [9, 45], [10, 55], 5],
            [8, 11, [10, 55], [11, 66], 6],
            [9, 12, [11, 66], [12, 78], 7],
        ] as [$tn, $insert, $stale, $fresh, $headerField]) {
            $cases[] = [
                'upstream' => 'wal2-2.' . $tn,
                'inserted_value' => $insert,
                'stale_snapshot' => $stale,
                'fresh_snapshot' => $fresh,
                'wal_index_header_field' => $headerField,
                'first_read_runs_recovery' => false,
                'second_read_runs_recovery' => true,
                'lock_sequence' => [
                    ['slot' => 0, 'count' => 1, 'op' => 'lock', 'level' => 'exclusive'],
                    ['slot' => 0, 'count' => 1, 'op' => 'unlock', 'level' => 'exclusive'],
                    ['slot' => 4, 'count' => 1, 'op' => 'lock', 'level' => 'exclusive'],
                    ['slot' => 4, 'count' => 1, 'op' => 'unlock', 'level' => 'exclusive'],
                    ['slot' => 4, 'count' => 1, 'op' => 'lock', 'level' => 'shared'],
                    ['slot' => 4, 'count' => 1, 'op' => 'unlock', 'level' => 'shared'],
                ],
                'source_file' => 'wal2.test',
            ];
        }

        return $cases;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function pager1LockTransitionCases(): array
    {
        return [
            ['upstream' => 'pager1-*.1', 'connection' => 'writer', 'action' => 'create-schema', 'writer_lock' => 'unlocked', 'reader_lock' => 'unlocked', 'observer_lock' => 'unlocked', 'writer_rows' => [[1, 'one'], [2, 'two']], 'reader_rows' => [[1, 'one'], [2, 'two']], 'observer_rows' => [[1, 'one'], [2, 'two']], 'error' => null],
            ['upstream' => 'pager1-*.4', 'connection' => 'writer', 'action' => 'begin-insert', 'writer_lock' => 'reserved', 'reader_lock' => 'unlocked', 'observer_lock' => 'unlocked', 'writer_rows' => [[1, 'one'], [2, 'two'], [3, 'three']], 'reader_rows' => [[1, 'one'], [2, 'two']], 'observer_rows' => [[1, 'one'], [2, 'two']], 'error' => null],
            ['upstream' => 'pager1-*.8', 'connection' => 'reader', 'action' => 'write-while-writer-reserved', 'writer_lock' => 'reserved', 'reader_lock' => 'transaction-open', 'observer_lock' => 'unlocked', 'writer_rows' => [[1, 'one'], [2, 'two'], [3, 'three']], 'reader_rows' => [[1, 'one'], [2, 'two']], 'observer_rows' => [[1, 'one'], [2, 'two']], 'error' => 'database is locked'],
            ['upstream' => 'pager1-*.10', 'connection' => 'writer', 'action' => 'commit-reserved', 'writer_lock' => 'unlocked', 'reader_lock' => 'transaction-open', 'observer_lock' => 'unlocked', 'writer_rows' => [[1, 'one'], [2, 'two'], [3, 'three']], 'reader_rows' => [[1, 'one'], [2, 'two'], [3, 'three']], 'observer_rows' => [[1, 'one'], [2, 'two'], [3, 'three']], 'error' => null],
            ['upstream' => 'pager1-*.15', 'connection' => 'reader', 'action' => 'begin-read', 'writer_lock' => 'unlocked', 'reader_lock' => 'shared', 'observer_lock' => 'unlocked', 'writer_rows' => [[1, 'one'], [2, 'two'], [3, 'three']], 'reader_rows' => [[1, 'one'], [2, 'two'], [3, 'three']], 'observer_rows' => [[1, 'one'], [2, 'two'], [3, 'three']], 'error' => null],
            ['upstream' => 'pager1-*.16', 'connection' => 'writer', 'action' => 'autocommit-write-blocked-by-shared-reader', 'writer_lock' => 'unlocked', 'reader_lock' => 'shared', 'observer_lock' => 'unlocked', 'writer_rows' => [[1, 'one'], [2, 'two'], [3, 'three']], 'reader_rows' => [[1, 'one'], [2, 'two'], [3, 'three']], 'observer_rows' => [[1, 'one'], [2, 'two'], [3, 'three']], 'error' => 'database is locked'],
            ['upstream' => 'pager1-*.18', 'connection' => 'writer', 'action' => 'explicit-write-with-shared-reader', 'writer_lock' => 'reserved', 'reader_lock' => 'shared', 'observer_lock' => 'unlocked', 'writer_rows' => [[11, 'one'], [12, 'two'], [13, 'three']], 'reader_rows' => [[1, 'one'], [2, 'two'], [3, 'three']], 'observer_rows' => [[1, 'one'], [2, 'two'], [3, 'three']], 'error' => null],
            ['upstream' => 'pager1-*.24', 'connection' => 'writer', 'action' => 'commit-blocked-upgrades-to-pending', 'writer_lock' => 'pending', 'reader_lock' => 'shared', 'observer_lock' => 'blocked', 'writer_rows' => [[11, 'one'], [12, 'two'], [13, 'three']], 'reader_rows' => [[1, 'one'], [2, 'two'], [3, 'three']], 'observer_rows' => null, 'error' => 'database is locked'],
            ['upstream' => 'pager1-*.29', 'connection' => 'reader', 'action' => 'reader-commit-while-writer-pending', 'writer_lock' => 'pending', 'reader_lock' => 'unlocked', 'observer_lock' => 'blocked', 'writer_rows' => [[11, 'one'], [12, 'two'], [13, 'three']], 'reader_rows' => null, 'observer_rows' => null, 'error' => 'database is locked'],
            ['upstream' => 'pager1-*.26', 'connection' => 'writer', 'action' => 'pending-writer-final-commit', 'writer_lock' => 'unlocked', 'reader_lock' => 'unlocked', 'observer_lock' => 'unlocked', 'writer_rows' => [[21, 'one'], [22, 'two'], [23, 'three']], 'reader_rows' => [[21, 'one'], [22, 'two'], [23, 'three']], 'observer_rows' => [[21, 'one'], [22, 'two'], [23, 'three']], 'error' => null],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function walRestartCheckpointRaceCases(): array
    {
        return [
            ['upstream' => 'walrestart-1.0', 'phase' => 'initial-populate-checkpoint', 'checkpoint' => [0, 49, 49], 'writer' => 'db', 'concurrent_writer' => null, 'rows_touched' => 20, 'integrity' => 'ok'],
            ['upstream' => 'walrestart-1.1', 'phase' => 'large-update-checkpoint', 'checkpoint' => [0, 45, 45], 'writer' => 'db', 'concurrent_writer' => null, 'rows_touched' => 20, 'integrity' => 'ok'],
            ['upstream' => 'walrestart-1.2', 'phase' => 'mxframe-before-backfill-race', 'checkpoint' => [0, 45, 0], 'writer' => 'db', 'concurrent_writer' => 'db2', 'rows_touched' => 4, 'integrity' => 'ok'],
            ['upstream' => 'walrestart-1.4', 'phase' => 'post-race-large-update-checkpoint', 'checkpoint' => [0, 5, 5], 'writer' => 'db2', 'concurrent_writer' => null, 'rows_touched' => 20, 'integrity' => 'ok'],
            ['upstream' => 'walrestart-1.5', 'phase' => 'integrity-after-race', 'checkpoint' => [0, 5, 5], 'writer' => 'db', 'concurrent_writer' => null, 'rows_touched' => 0, 'integrity' => 'ok'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function wal2BusyRecoveryCases(): array
    {
        return [
            [
                'upstream' => 'wal2-3.0 wal2-3.1 wal2-3.2',
                'busy_point' => 'read-lock',
                'busy_attempts_before_unlock' => 4,
                'busy_handler_return' => 0,
                'initial_flags' => ['locked' => true, 'sabotage' => false],
                'final_flags' => ['locked' => false, 'sabotage' => false],
                'snapshot' => [4, 10],
                'lock_sequence' => [
                    ['slot' => 4, 'count' => 1, 'op' => 'lock', 'level' => 'shared', 'result' => 'SQLITE_BUSY'],
                    ['slot' => 4, 'count' => 1, 'op' => 'lock', 'level' => 'shared', 'result' => 'SQLITE_BUSY'],
                    ['slot' => 4, 'count' => 1, 'op' => 'lock', 'level' => 'shared', 'result' => 'SQLITE_BUSY'],
                    ['slot' => 4, 'count' => 1, 'op' => 'lock', 'level' => 'shared', 'result' => 'SQLITE_OK'],
                    ['slot' => 4, 'count' => 1, 'op' => 'unlock', 'level' => 'shared', 'result' => 'SQLITE_OK'],
                ],
                'source_file' => 'wal2.test',
            ],
            [
                'upstream' => 'wal2-3.3 wal2-3.4 wal2-3.5',
                'busy_point' => 'recover-lock',
                'busy_attempts_before_unlock' => 4,
                'busy_handler_return' => 0,
                'initial_flags' => ['locked' => true, 'sabotage' => true],
                'final_flags' => ['locked' => false, 'sabotage' => false],
                'snapshot' => [4, 10],
                'lock_sequence' => [
                    ['slot' => 2, 'count' => 1, 'op' => 'lock', 'level' => 'exclusive', 'result' => 'SQLITE_BUSY'],
                    ['slot' => 2, 'count' => 1, 'op' => 'lock', 'level' => 'exclusive', 'result' => 'SQLITE_BUSY'],
                    ['slot' => 2, 'count' => 1, 'op' => 'lock', 'level' => 'exclusive', 'result' => 'SQLITE_BUSY'],
                    ['slot' => 2, 'count' => 1, 'op' => 'lock', 'level' => 'exclusive', 'result' => 'SQLITE_OK'],
                    ['slot' => 2, 'count' => 1, 'op' => 'unlock', 'level' => 'exclusive', 'result' => 'SQLITE_OK'],
                    ['slot' => 4, 'count' => 1, 'op' => 'lock', 'level' => 'shared', 'result' => 'SQLITE_OK'],
                    ['slot' => 4, 'count' => 1, 'op' => 'unlock', 'level' => 'shared', 'result' => 'SQLITE_OK'],
                ],
                'source_file' => 'wal2.test',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function wal2ExclusiveLockingCases(): array
    {
        $recovery = [
            ['slot' => 0, 'count' => 1, 'op' => 'lock', 'level' => 'exclusive'],
            ['slot' => 1, 'count' => 2, 'op' => 'lock', 'level' => 'exclusive'],
            ['slot' => 4, 'count' => 1, 'op' => 'lock', 'level' => 'exclusive'],
            ['slot' => 4, 'count' => 1, 'op' => 'unlock', 'level' => 'exclusive'],
            ['slot' => 5, 'count' => 1, 'op' => 'lock', 'level' => 'exclusive'],
            ['slot' => 5, 'count' => 1, 'op' => 'unlock', 'level' => 'exclusive'],
            ['slot' => 6, 'count' => 1, 'op' => 'lock', 'level' => 'exclusive'],
            ['slot' => 6, 'count' => 1, 'op' => 'unlock', 'level' => 'exclusive'],
            ['slot' => 7, 'count' => 1, 'op' => 'lock', 'level' => 'exclusive'],
            ['slot' => 7, 'count' => 1, 'op' => 'unlock', 'level' => 'exclusive'],
            ['slot' => 1, 'count' => 2, 'op' => 'unlock', 'level' => 'exclusive'],
            ['slot' => 0, 'count' => 1, 'op' => 'unlock', 'level' => 'exclusive'],
        ];
        $readmark1Read = [
            ['slot' => 4, 'count' => 1, 'op' => 'lock', 'level' => 'shared'],
            ['slot' => 4, 'count' => 1, 'op' => 'unlock', 'level' => 'shared'],
        ];
        $readmark1Set = [
            ['slot' => 4, 'count' => 1, 'op' => 'lock', 'level' => 'exclusive'],
            ['slot' => 4, 'count' => 1, 'op' => 'unlock', 'level' => 'exclusive'],
        ];
        $readmark1Write = [
            ['slot' => 4, 'count' => 1, 'op' => 'lock', 'level' => 'shared'],
            ['slot' => 0, 'count' => 1, 'op' => 'lock', 'level' => 'exclusive'],
            ['slot' => 0, 'count' => 1, 'op' => 'unlock', 'level' => 'exclusive'],
            ['slot' => 4, 'count' => 1, 'op' => 'unlock', 'level' => 'shared'],
        ];

        $rows2 = [['I', 'II'], ['III', 'IV']];
        $rows5 = [['I', 'II'], ['III', 'IV'], ['V', 'VI'], ['VII', 'VIII'], ['IX', 'X']];

        return [
            ['upstream' => 'wal2-6.1.1', 'phase' => 'wal-before-exclusive', 'journal_mode' => 'wal', 'locking_mode' => 'normal', 'lock_status' => ['main' => 'unlocked', 'temp' => 'closed'], 'rows' => [], 'wal_exists' => true, 'journal_exists' => false, 'shm_locks' => [], 'reader_visible' => true, 'error' => null, 'source_file' => 'wal2.test'],
            ['upstream' => 'wal2-6.1.3', 'phase' => 'exclusive-after-schema-read', 'journal_mode' => 'wal', 'locking_mode' => 'exclusive', 'lock_status' => ['main' => 'exclusive', 'temp' => 'closed'], 'rows' => [[1, 2]], 'wal_exists' => true, 'journal_exists' => false, 'shm_locks' => $recovery, 'reader_visible' => false, 'error' => null, 'source_file' => 'wal2.test'],
            ['upstream' => 'wal2-6.1.4', 'phase' => 'normal-request-keeps-exclusive-until-read', 'journal_mode' => 'wal', 'locking_mode' => 'normal', 'lock_status' => ['main' => 'exclusive', 'temp' => 'closed'], 'rows' => [[1, 2]], 'wal_exists' => true, 'journal_exists' => false, 'shm_locks' => [], 'reader_visible' => false, 'error' => null, 'source_file' => 'wal2.test'],
            ['upstream' => 'wal2-6.1.5', 'phase' => 'read-downgrades-exclusive-to-shared', 'journal_mode' => 'wal', 'locking_mode' => 'normal', 'lock_status' => ['main' => 'shared', 'temp' => 'closed'], 'rows' => [[1, 2]], 'wal_exists' => true, 'journal_exists' => false, 'shm_locks' => $readmark1Read, 'reader_visible' => true, 'error' => null, 'source_file' => 'wal2.test'],
            ['upstream' => 'wal2-6.2.1', 'phase' => 'exclusive-before-wal', 'journal_mode' => 'wal', 'locking_mode' => 'exclusive', 'lock_status' => ['main' => 'exclusive', 'temp' => 'closed'], 'rows' => [], 'wal_exists' => true, 'journal_exists' => false, 'shm_locks' => $recovery, 'reader_visible' => false, 'error' => null, 'source_file' => 'wal2.test'],
            ['upstream' => 'wal2-6.2.4', 'phase' => 'reopen-exclusive-read-starts-shared', 'journal_mode' => 'wal', 'locking_mode' => 'exclusive', 'lock_status' => ['main' => 'shared', 'temp' => 'closed'], 'rows' => [[1, 2]], 'wal_exists' => true, 'journal_exists' => false, 'shm_locks' => $readmark1Read, 'reader_visible' => true, 'error' => null, 'source_file' => 'wal2.test'],
            ['upstream' => 'wal2-6.2.5', 'phase' => 'write-promotes-reopened-exclusive', 'journal_mode' => 'wal', 'locking_mode' => 'exclusive', 'lock_status' => ['main' => 'exclusive', 'temp' => 'closed'], 'rows' => [[1, 2], [3, 4]], 'wal_exists' => true, 'journal_exists' => false, 'shm_locks' => $readmark1Write, 'reader_visible' => false, 'error' => null, 'source_file' => 'wal2.test'],
            ['upstream' => 'wal2-6.2.7', 'phase' => 'begin-immediate-after-normal-shares-readmark', 'journal_mode' => 'wal', 'locking_mode' => 'normal', 'lock_status' => ['main' => 'shared', 'temp' => 'closed'], 'rows' => [[1, 2], [3, 4]], 'wal_exists' => true, 'journal_exists' => false, 'shm_locks' => $readmark1Read, 'reader_visible' => true, 'error' => null, 'source_file' => 'wal2.test'],
            ['upstream' => 'wal2-6.3.1', 'phase' => 'wal-file-before-delete-mode', 'journal_mode' => 'wal', 'locking_mode' => 'exclusive', 'lock_status' => ['main' => 'exclusive', 'temp' => 'closed'], 'rows' => [['Chico'], ['Harpo']], 'wal_exists' => true, 'journal_exists' => false, 'shm_locks' => $recovery, 'reader_visible' => false, 'error' => null, 'source_file' => 'wal2.test'],
            ['upstream' => 'wal2-6.3.2', 'phase' => 'delete-mode-removes-wal', 'journal_mode' => 'delete', 'locking_mode' => 'exclusive', 'lock_status' => ['main' => 'exclusive', 'temp' => 'closed'], 'rows' => [['Chico'], ['Harpo']], 'wal_exists' => false, 'journal_exists' => false, 'shm_locks' => [], 'reader_visible' => false, 'error' => null, 'source_file' => 'wal2.test'],
            ['upstream' => 'wal2-6.3.4.1', 'phase' => 'rollback-journal-created-after-delete-mode-write', 'journal_mode' => 'delete', 'locking_mode' => 'exclusive', 'lock_status' => ['main' => 'exclusive', 'temp' => 'closed'], 'rows' => [['Chico'], ['Harpo'], ['Groucho']], 'wal_exists' => false, 'journal_exists' => true, 'shm_locks' => [], 'reader_visible' => false, 'error' => null, 'source_file' => 'wal2.test'],
            ['upstream' => 'wal2-6.4.2', 'phase' => 'instrumented-wal-create-locks', 'journal_mode' => 'wal', 'locking_mode' => 'normal', 'lock_status' => ['main' => 'shared', 'temp' => 'closed'], 'rows' => [['Leonard'], ['Arthur']], 'wal_exists' => true, 'journal_exists' => false, 'shm_locks' => array_merge($recovery, $readmark1Write), 'reader_visible' => true, 'error' => null, 'source_file' => 'wal2.test'],
            ['upstream' => 'wal2-6.4.3', 'phase' => 'instrumented-readmark-set-then-read', 'journal_mode' => 'wal', 'locking_mode' => 'normal', 'lock_status' => ['main' => 'shared', 'temp' => 'closed'], 'rows' => [['Leonard'], ['Arthur']], 'wal_exists' => true, 'journal_exists' => false, 'shm_locks' => array_merge($readmark1Set, $readmark1Read), 'reader_visible' => true, 'error' => null, 'source_file' => 'wal2.test'],
            ['upstream' => 'wal2-6.4.7', 'phase' => 'exclusive-insert-omits-shm-lock', 'journal_mode' => 'wal', 'locking_mode' => 'exclusive', 'lock_status' => ['main' => 'exclusive', 'temp' => 'closed'], 'rows' => [['Leonard'], ['Arthur'], ['Julius Henry'], ['Karl']], 'wal_exists' => true, 'journal_exists' => false, 'shm_locks' => [], 'reader_visible' => false, 'error' => null, 'source_file' => 'wal2.test'],
            ['upstream' => 'wal2-6.4.10', 'phase' => 'normal-delete-reuses-readmark-write-locks', 'journal_mode' => 'wal', 'locking_mode' => 'normal', 'lock_status' => ['main' => 'shared', 'temp' => 'closed'], 'rows' => [], 'wal_exists' => true, 'journal_exists' => false, 'shm_locks' => $readmark1Write, 'reader_visible' => true, 'error' => null, 'source_file' => 'wal2.test'],
            ['upstream' => 'wal2-6.5.3', 'phase' => 'exclusive-checkpoint-after-mode-toggle', 'journal_mode' => 'wal', 'locking_mode' => 'exclusive', 'lock_status' => ['main' => 'exclusive', 'temp' => 'closed'], 'rows' => $rows2, 'wal_exists' => true, 'journal_exists' => false, 'checkpoint' => [0, 2, 2], 'shm_locks' => $recovery, 'reader_visible' => false, 'error' => null, 'source_file' => 'wal2.test'],
            ['upstream' => 'wal2-6.6.3', 'phase' => 'failed-readlock-keeps-exclusive-mode', 'journal_mode' => 'wal', 'locking_mode' => 'exclusive', 'lock_status' => ['main' => 'exclusive', 'temp' => 'closed'], 'rows' => array_slice($rows5, 0, 4), 'wal_exists' => true, 'journal_exists' => false, 'shm_locks' => [['slot' => 4, 'count' => 1, 'op' => 'lock', 'level' => 'shared', 'result' => 'SQLITE_IOERR']], 'reader_visible' => false, 'error' => 'database is locked', 'source_file' => 'wal2.test'],
            ['upstream' => 'wal2-6.6.4', 'phase' => 'successful-readlock-exits-exclusive-mode', 'journal_mode' => 'wal', 'locking_mode' => 'normal', 'lock_status' => ['main' => 'shared', 'temp' => 'closed'], 'rows' => $rows5, 'wal_exists' => true, 'journal_exists' => false, 'shm_locks' => $readmark1Read, 'reader_visible' => true, 'error' => null, 'source_file' => 'wal2.test'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function pagerWalDynamicMatrixCases(): array
    {
        $bases = array_merge(
            self::wal2HeaderRecoveryCases(),
            self::wal2OutOfDateHeaderCases(),
            self::wal2BusyRecoveryCases(),
            self::wal2ExclusiveLockingCases(),
            self::pager1LockTransitionCases(),
            self::walRestartCheckpointRaceCases(),
        );

        $matrix = [];
        $connectionModes = ['normal', 'exclusive', 'shared-cache', 'read-only'];
        $checkpointModes = ['passive', 'full', 'restart', 'truncate'];
        $syncModes = ['off', 'normal', 'full', 'extra'];
        $pageSizes = [512, 1024, 2048, 4096];

        for ($i = 0; $i < 1024; $i++) {
            $base = $bases[$i % count($bases)];
            $connectionMode = $connectionModes[$i % count($connectionModes)];
            $checkpointMode = $checkpointModes[intdiv($i, 4) % count($checkpointModes)];
            $syncMode = $syncModes[intdiv($i, 16) % count($syncModes)];
            $pageSize = $pageSizes[intdiv($i, 64) % count($pageSizes)];
            $lockSequence = $base['lock_sequence'] ?? [];
            $checkpoint = $base['checkpoint'] ?? null;
            $rows = $base['rows'] ?? ($base['final_snapshot'] ?? ($base['fresh_snapshot'] ?? []));
            $readerVisible = (bool) ($base['reader_visible'] ?? (($base['error'] ?? null) === null));
            $walExists = (bool) ($base['wal_exists'] ?? true);
            $journalExists = (bool) ($base['journal_exists'] ?? false);
            $requiresRecovery = (bool) ($base['recovery_required'] ?? ($base['second_read_runs_recovery'] ?? false));
            $error = $base['error'] ?? null;
            $lockOps = array_values(array_filter($lockSequence, static fn (array $lock): bool => ($lock['op'] ?? null) === 'lock'));
            $unlockOps = array_values(array_filter($lockSequence, static fn (array $lock): bool => ($lock['op'] ?? null) === 'unlock'));

            $matrix[] = [
                'case' => $i + 1,
                'upstream' => (string) $base['upstream'],
                'source_file' => (string) ($base['source_file'] ?? (str_starts_with((string) $base['upstream'], 'pager1-') ? 'pager1.test' : 'walrestart.test')),
                'connection_mode' => $connectionMode,
                'checkpoint_mode' => $checkpointMode,
                'sync_mode' => $syncMode,
                'page_size' => $pageSize,
                'lock_sequence' => $lockSequence,
                'lock_count' => count($lockOps),
                'unlock_count' => count($unlockOps),
                'has_busy_lock' => self::hasLockResult($lockSequence, 'SQLITE_BUSY'),
                'has_ioerr_lock' => self::hasLockResult($lockSequence, 'SQLITE_IOERR'),
                'requires_recovery' => $requiresRecovery,
                'reader_visible' => $readerVisible,
                'wal_exists' => $walExists,
                'journal_exists' => $journalExists,
                'checkpoint' => $checkpoint,
                'rows' => $rows,
                'error' => $error,
                'dependencies' => [
                    'sqlite-real-upstream-wal2-locking',
                    'sqlite-real-upstream-pager1-locking',
                    'sqlite-real-upstream-walrestart-checkpoint',
                ],
            ];
        }

        return $matrix;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function walSetlkBlockingLockCases(): array
    {
        $scenarios = [
            [
                'upstream' => 'walsetlk.test 1.0..1.8',
                'source_file' => 'walsetlk.test',
                'phase' => 'writer-reserved-blocks-second-writer',
                'holder' => 'writer-a',
                'waiter' => 'writer-b',
                'held_lock' => 'wal-write',
                'requested_lock' => 'wal-write',
                'blocking_call' => 'BEGIN IMMEDIATE',
                'timeout_ms' => 0,
                'expected_code' => 1,
                'expected_message' => 'database is locked',
                'busy_waits' => 0,
                'setlk_timeout' => null,
                'unlock_releases_waiter' => true,
                'visible_rows_before_release' => [[1, 'one'], [2, 'two']],
                'visible_rows_after_release' => [[1, 'one'], [2, 'two'], [3, 'three']],
                'lock_trace' => ['writer-a:lock:wal-write', 'writer-b:try:wal-write:busy', 'writer-a:unlock:wal-write', 'writer-b:lock:wal-write'],
            ],
            [
                'upstream' => 'walsetlk.test 2.*',
                'source_file' => 'walsetlk.test',
                'phase' => 'blocking-checkpoint-waits-for-reader',
                'holder' => 'reader-a',
                'waiter' => 'checkpoint-b',
                'held_lock' => 'wal-readmark',
                'requested_lock' => 'checkpoint',
                'blocking_call' => 'PRAGMA wal_checkpoint(TRUNCATE)',
                'timeout_ms' => 10000,
                'expected_code' => 0,
                'expected_message' => 'checkpoint completes after reader release',
                'busy_waits' => 3,
                'setlk_timeout' => 10000,
                'unlock_releases_waiter' => true,
                'visible_rows_before_release' => [[1, 'alpha'], [2, 'beta']],
                'visible_rows_after_release' => [[1, 'alpha'], [2, 'beta'], [3, 'gamma']],
                'lock_trace' => ['reader-a:lock:readmark', 'checkpoint-b:wait:checkpoint', 'reader-a:unlock:readmark', 'checkpoint-b:lock:checkpoint'],
            ],
            [
                'upstream' => 'walsetlk2.test 1.3..1.5',
                'source_file' => 'walsetlk2.test',
                'phase' => 'shared-memory-lock-order-for-reader',
                'holder' => 'writer-a',
                'waiter' => 'reader-b',
                'held_lock' => 'wal-write',
                'requested_lock' => 'readmark',
                'blocking_call' => 'SELECT * FROM t1',
                'timeout_ms' => 0,
                'expected_code' => 0,
                'expected_message' => 'reader uses shared readmark after writer unlock',
                'busy_waits' => 0,
                'setlk_timeout' => null,
                'unlock_releases_waiter' => true,
                'visible_rows_before_release' => [[1, 2, 3], [4, 5, 6]],
                'visible_rows_after_release' => [[1, 2, 3], [4, 5, 6], [7, 8, 9]],
                'lock_trace' => ['0:1:lock:exclusive', '4:1:lock:shared', '0:1:unlock:exclusive', '4:1:unlock:shared'],
            ],
            [
                'upstream' => 'walsetlk2.test 2.0..2.7',
                'source_file' => 'walsetlk2.test',
                'phase' => 'setlk-timeout-expires-on-write-lock',
                'holder' => 'writer-a',
                'waiter' => 'writer-b',
                'held_lock' => 'wal-write',
                'requested_lock' => 'wal-write',
                'blocking_call' => 'INSERT INTO t1 VALUES(...)',
                'timeout_ms' => 250,
                'expected_code' => 1,
                'expected_message' => 'database is locked',
                'busy_waits' => 2,
                'setlk_timeout' => 250,
                'unlock_releases_waiter' => false,
                'visible_rows_before_release' => [[1, 'held']],
                'visible_rows_after_release' => [[1, 'held']],
                'lock_trace' => ['writer-a:lock:wal-write', 'writer-b:wait:wal-write', 'writer-b:timeout:wal-write'],
            ],
            [
                'upstream' => 'walblock.test 1.1.*',
                'source_file' => 'walblock.test',
                'phase' => 'read-while-writer-updates-wal-index',
                'holder' => 'writer-a',
                'waiter' => 'reader-b',
                'held_lock' => 'wal-index-update',
                'requested_lock' => 'readmark',
                'blocking_call' => 'SELECT count(*) FROM t1',
                'timeout_ms' => 500,
                'expected_code' => 0,
                'expected_message' => 'reader resumes after wal-index update',
                'busy_waits' => 1,
                'setlk_timeout' => 500,
                'unlock_releases_waiter' => true,
                'visible_rows_before_release' => [[1, 'stable']],
                'visible_rows_after_release' => [[1, 'stable'], [2, 'committed']],
                'lock_trace' => ['writer-a:lock:wal-index-update', 'reader-b:block:readmark', 'writer-a:unlock:wal-index-update', 'reader-b:lock:readmark'],
            ],
            [
                'upstream' => 'walblock.test 1.2.*',
                'source_file' => 'walblock.test',
                'phase' => 'reader-blocks-until-checkpoint-state-stable',
                'holder' => 'checkpoint-a',
                'waiter' => 'reader-b',
                'held_lock' => 'checkpoint',
                'requested_lock' => 'readmark',
                'blocking_call' => 'SELECT * FROM t1 ORDER BY a',
                'timeout_ms' => 10000,
                'expected_code' => 0,
                'expected_message' => 'reader includes transaction committed during wait',
                'busy_waits' => 4,
                'setlk_timeout' => 10000,
                'unlock_releases_waiter' => true,
                'visible_rows_before_release' => [[1, 'before']],
                'visible_rows_after_release' => [[1, 'before'], [2, 'during-wait']],
                'lock_trace' => ['checkpoint-a:lock:checkpoint', 'reader-b:block:readmark', 'checkpoint-a:unlock:checkpoint', 'reader-b:lock:readmark'],
            ],
        ];

        $cases = [];
        $checkpointModes = ['passive', 'full', 'restart', 'truncate'];
        $journalModes = ['wal', 'wal-persist'];
        $syncModes = ['normal', 'full', 'extra', 'off'];
        $pageSizes = [512, 1024, 2048, 4096];

        for ($i = 0; $i < 1000; $i++) {
            $scenario = $scenarios[$i % count($scenarios)];
            $checkpointMode = $checkpointModes[$i % count($checkpointModes)];
            $journalMode = $journalModes[intdiv($i, 4) % count($journalModes)];
            $syncMode = $syncModes[intdiv($i, 8) % count($syncModes)];
            $pageSize = $pageSizes[intdiv($i, 32) % count($pageSizes)];
            $releaseDelayMs = (int) ($scenario['busy_waits'] * 25 + ($i % 5) * 10);
            $timeoutMs = (int) $scenario['timeout_ms'];
            $willTimeout = $timeoutMs > 0
                && $releaseDelayMs > $timeoutMs
                && $scenario['expected_code'] !== 0;

            $cases[] = $scenario + [
                'case' => $i + 1,
                'checkpoint_mode' => $checkpointMode,
                'journal_mode' => $journalMode,
                'sync_mode' => $syncMode,
                'page_size' => $pageSize,
                'release_delay_ms' => $releaseDelayMs,
                'will_timeout' => $willTimeout,
                'waiter_blocks' => $scenario['busy_waits'] > 0 || $timeoutMs > 0,
                'lock_trace_count' => count($scenario['lock_trace']),
                'dependencies' => [
                    'sqlite-upstream-walsetlk-blocking-locks',
                    'sqlite-upstream-walblock-reader-waits',
                    'sqlite-real-upstream-pager-wal-dynamic',
                ],
            ];
        }

        return $cases;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function wal2CheckpointFullSyncCases(): array
    {
        $scenarios = [
            [
                'upstream' => 'wal2.test wal2-14.1',
                'source_file' => 'wal2.test',
                'checkpoint_fullfsync' => null,
                'initial_sync_counts' => [10, 0],
                'large_insert_sync_counts' => [4, 0],
                'close_sync_counts' => [6, 0],
            ],
            [
                'upstream' => 'wal2.test wal2-14.2',
                'source_file' => 'wal2.test',
                'checkpoint_fullfsync' => true,
                'initial_sync_counts' => [10, 6],
                'large_insert_sync_counts' => [4, 3],
                'close_sync_counts' => [6, 3],
            ],
            [
                'upstream' => 'wal2.test wal2-14.3',
                'source_file' => 'wal2.test',
                'checkpoint_fullfsync' => false,
                'initial_sync_counts' => [10, 0],
                'large_insert_sync_counts' => [4, 0],
                'close_sync_counts' => [6, 0],
            ],
        ];
        $checkpointModes = ['passive', 'full', 'restart', 'truncate'];
        $syncModes = ['full', 'extra'];
        $pageSizes = [1024, 2048, 4096, 8192];
        $autoCheckpointPages = [10, 20, 1000, 4096];

        $cases = [];
        for ($i = 0; $i < 1000; $i++) {
            $scenario = $scenarios[$i % count($scenarios)];
            $checkpointMode = $checkpointModes[$i % count($checkpointModes)];
            $syncMode = $syncModes[intdiv($i, 4) % count($syncModes)];
            $pageSize = $pageSizes[intdiv($i, 8) % count($pageSizes)];
            $autoCheckpoint = $autoCheckpointPages[intdiv($i, 32) % count($autoCheckpointPages)];
            $initial = $scenario['initial_sync_counts'];
            $largeInsert = $scenario['large_insert_sync_counts'];
            $close = $scenario['close_sync_counts'];

            $cases[] = $scenario + [
                'case' => $i + 1,
                'checkpoint_mode' => $checkpointMode,
                'synchronous' => $syncMode,
                'page_size' => $pageSize,
                'wal_autocheckpoint' => $autoCheckpoint,
                'checkpoint_results' => [[0, 3, 3], [0, 1, 1]],
                'sync_sequence' => [
                    ['phase' => 'initial-ddl-insert-checkpoint-commit-checkpoint', 'sync' => $initial[0], 'fullsync' => $initial[1]],
                    ['phase' => 'large-zeroblob-autocheckpoint', 'sync' => $largeInsert[0], 'fullsync' => $largeInsert[1]],
                    ['phase' => 'close-after-deferred-autocheckpoint', 'sync' => $close[0], 'fullsync' => $close[1]],
                ],
                'total_syncs' => $initial[0] + $largeInsert[0] + $close[0],
                'total_fullsyncs' => $initial[1] + $largeInsert[1] + $close[1],
                'uses_checkpoint_fullfsync' => $scenario['checkpoint_fullfsync'] === true,
                'dependencies' => [
                    'sqlite-real-upstream-pager-wal-dynamic',
                    'sqlite-upstream-wal2-checkpoint-fullfsync',
                    'sqlite-vfs-sync-plan',
                ],
            ];
        }

        return $cases;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function walProtocolRetrySnapshotCases(): array
    {
        $recoveryLocks = [
            ['slot' => 0, 'count' => 1, 'op' => 'lock', 'level' => 'exclusive', 'result' => 'SQLITE_OK'],
            ['slot' => 1, 'count' => 2, 'op' => 'lock', 'level' => 'exclusive', 'result' => 'SQLITE_OK'],
            ['slot' => 4, 'count' => 1, 'op' => 'lock', 'level' => 'exclusive', 'result' => 'SQLITE_OK'],
            ['slot' => 4, 'count' => 1, 'op' => 'unlock', 'level' => 'exclusive', 'result' => 'SQLITE_OK'],
            ['slot' => 5, 'count' => 1, 'op' => 'lock', 'level' => 'exclusive', 'result' => 'SQLITE_OK'],
            ['slot' => 5, 'count' => 1, 'op' => 'unlock', 'level' => 'exclusive', 'result' => 'SQLITE_OK'],
            ['slot' => 6, 'count' => 1, 'op' => 'lock', 'level' => 'exclusive', 'result' => 'SQLITE_OK'],
            ['slot' => 6, 'count' => 1, 'op' => 'unlock', 'level' => 'exclusive', 'result' => 'SQLITE_OK'],
            ['slot' => 7, 'count' => 1, 'op' => 'lock', 'level' => 'exclusive', 'result' => 'SQLITE_OK'],
            ['slot' => 7, 'count' => 1, 'op' => 'unlock', 'level' => 'exclusive', 'result' => 'SQLITE_OK'],
            ['slot' => 1, 'count' => 2, 'op' => 'unlock', 'level' => 'exclusive', 'result' => 'SQLITE_OK'],
            ['slot' => 0, 'count' => 1, 'op' => 'unlock', 'level' => 'exclusive', 'result' => 'SQLITE_OK'],
        ];
        $protocolRetryLocks = static function (string $blockedSpec) use ($recoveryLocks): array {
            $locks = [];
            for ($attempt = 1; $attempt <= 100; $attempt++) {
                [$slot, $count, $op, $level] = explode(' ', $blockedSpec);
                $locks[] = [
                    'slot' => (int) $slot,
                    'count' => (int) $count,
                    'op' => $op,
                    'level' => $level,
                    'result' => 'SQLITE_BUSY',
                    'attempt' => $attempt,
                ];
            }

            return array_merge($locks, $recoveryLocks);
        };

        $scenarios = [
            [
                'upstream' => 'walprotocol.test 1.1',
                'source_file' => 'walprotocol.test',
                'phase' => 'initial-reader-recovery-lock-sequence',
                'operation' => 'SELECT * FROM x',
                'expected_code' => 0,
                'expected_message' => 'z',
                'expected_extended_code' => 'SQLITE_OK',
                'retry_limit' => 0,
                'busy_handler_invoked' => false,
                'busy_retry_succeeds' => false,
                'lock_sequence' => $recoveryLocks,
                'rows' => [['z']],
                'concurrent_rows' => null,
                'protocol_error' => false,
            ],
            [
                'upstream' => 'walprotocol.test 1.3',
                'source_file' => 'walprotocol.test',
                'phase' => 'recover-lock-busy-retries-to-protocol-error',
                'operation' => 'SELECT * FROM x',
                'expected_code' => 1,
                'expected_message' => 'locking protocol',
                'expected_extended_code' => 'SQLITE_PROTOCOL',
                'retry_limit' => 100,
                'busy_handler_invoked' => false,
                'busy_retry_succeeds' => false,
                'lock_sequence' => $protocolRetryLocks('1 2 lock exclusive'),
                'rows' => [],
                'concurrent_rows' => null,
                'protocol_error' => true,
            ],
            [
                'upstream' => 'walprotocol.test 1.4',
                'source_file' => 'walprotocol.test',
                'phase' => 'writer-lock-busy-retries-to-protocol-error',
                'operation' => 'SELECT * FROM x',
                'expected_code' => 1,
                'expected_message' => 'locking protocol',
                'expected_extended_code' => 'SQLITE_PROTOCOL',
                'retry_limit' => 100,
                'busy_handler_invoked' => false,
                'busy_retry_succeeds' => false,
                'lock_sequence' => $protocolRetryLocks('0 1 lock exclusive'),
                'rows' => [],
                'concurrent_rows' => null,
                'protocol_error' => true,
            ],
            [
                'upstream' => 'walprotocol.test 1.5',
                'source_file' => 'walprotocol.test',
                'phase' => 'readmark-lock-busy-can-still-read',
                'operation' => 'SELECT * FROM x',
                'expected_code' => 0,
                'expected_message' => 'z',
                'expected_extended_code' => 'SQLITE_OK',
                'retry_limit' => 0,
                'busy_handler_invoked' => false,
                'busy_retry_succeeds' => false,
                'lock_sequence' => [
                    ['slot' => 4, 'count' => 4, 'op' => 'lock', 'level' => 'exclusive', 'result' => 'SQLITE_BUSY'],
                    ['slot' => 4, 'count' => 1, 'op' => 'lock', 'level' => 'shared', 'result' => 'SQLITE_OK'],
                    ['slot' => 4, 'count' => 1, 'op' => 'unlock', 'level' => 'shared', 'result' => 'SQLITE_OK'],
                ],
                'rows' => [['z']],
                'concurrent_rows' => null,
                'protocol_error' => false,
            ],
            [
                'upstream' => 'walprotocol.test 2.5 2.6',
                'source_file' => 'walprotocol.test',
                'phase' => 'reader-during-recovery-unlock-sees-full-rowset',
                'operation' => 'SELECT * FROM b',
                'expected_code' => 0,
                'expected_message' => 'Tehran Qom Markazi Qazvin Gilan Ardabil',
                'expected_extended_code' => 'SQLITE_OK',
                'retry_limit' => 0,
                'busy_handler_invoked' => false,
                'busy_retry_succeeds' => false,
                'lock_sequence' => [
                    ['slot' => 1, 'count' => 2, 'op' => 'unlock', 'level' => 'exclusive', 'result' => 'SQLITE_OK', 'callback' => 'second-reader-select'],
                ],
                'rows' => [['Tehran'], ['Qom'], ['Markazi'], ['Qazvin'], ['Gilan'], ['Ardabil']],
                'concurrent_rows' => [['Tehran'], ['Qom'], ['Markazi'], ['Qazvin'], ['Gilan'], ['Ardabil']],
                'protocol_error' => false,
            ],
            [
                'upstream' => 'walprotocol2.test 2.2 2.3',
                'source_file' => 'walprotocol2.test',
                'phase' => 'begin-exclusive-races-with-concurrent-writer',
                'operation' => 'BEGIN EXCLUSIVE',
                'expected_code' => 1,
                'expected_message' => 'database is locked',
                'expected_extended_code' => 'SQLITE_BUSY',
                'retry_limit' => 0,
                'busy_handler_invoked' => false,
                'busy_retry_succeeds' => false,
                'lock_sequence' => [
                    ['slot' => 0, 'count' => 1, 'op' => 'lock', 'level' => 'exclusive', 'result' => 'SQLITE_BUSY_SNAPSHOT', 'callback' => 'db2-insert-y'],
                ],
                'rows' => [['z'], ['y']],
                'concurrent_rows' => [['z'], ['y']],
                'protocol_error' => false,
            ],
            [
                'upstream' => 'walprotocol2.test 2.4 2.5',
                'source_file' => 'walprotocol2.test',
                'phase' => 'busy-handler-retries-begin-exclusive-after-snapshot-race',
                'operation' => 'BEGIN EXCLUSIVE',
                'expected_code' => 0,
                'expected_message' => 'z y x',
                'expected_extended_code' => 'SQLITE_OK',
                'retry_limit' => 1,
                'busy_handler_invoked' => true,
                'busy_retry_succeeds' => true,
                'lock_sequence' => [
                    ['slot' => 0, 'count' => 1, 'op' => 'lock', 'level' => 'exclusive', 'result' => 'SQLITE_BUSY_SNAPSHOT', 'callback' => 'db2-insert-x'],
                    ['slot' => 0, 'count' => 1, 'op' => 'lock', 'level' => 'exclusive', 'result' => 'SQLITE_OK', 'attempt' => 2],
                ],
                'rows' => [['z'], ['y'], ['x']],
                'concurrent_rows' => [['z'], ['y'], ['x']],
                'protocol_error' => false,
            ],
        ];

        $checkpointModes = ['passive', 'full', 'restart', 'truncate'];
        $journalModes = ['wal', 'wal-persist'];
        $pageSizes = [512, 1024, 2048, 4096, 8192];
        $timeoutMs = [0, 10, 250, 1000];

        $cases = [];
        for ($i = 0; $i < 1000; $i++) {
            $scenario = $scenarios[$i % count($scenarios)];
            $lockResults = array_column($scenario['lock_sequence'], 'result');
            $busyCount = count(array_filter($lockResults, static fn (mixed $result): bool => in_array($result, ['SQLITE_BUSY', 'SQLITE_BUSY_SNAPSHOT'], true)));
            $cases[] = $scenario + [
                'case' => $i + 1,
                'checkpoint_mode' => $checkpointModes[$i % count($checkpointModes)],
                'journal_mode' => $journalModes[intdiv($i, 4) % count($journalModes)],
                'page_size' => $pageSizes[intdiv($i, 8) % count($pageSizes)],
                'busy_timeout_ms' => $timeoutMs[intdiv($i, 40) % count($timeoutMs)],
                'lock_count' => count(array_filter($scenario['lock_sequence'], static fn (array $lock): bool => ($lock['op'] ?? null) === 'lock')),
                'unlock_count' => count(array_filter($scenario['lock_sequence'], static fn (array $lock): bool => ($lock['op'] ?? null) === 'unlock')),
                'busy_lock_count' => $busyCount,
                'row_count' => count($scenario['rows']),
                'concurrent_row_count' => is_array($scenario['concurrent_rows']) ? count($scenario['concurrent_rows']) : null,
                'dependencies' => [
                    'sqlite-upstream-walprotocol-locking-protocol',
                    'sqlite-upstream-walprotocol2-busy-snapshot-retry',
                    'sqlite-real-upstream-pager-wal-dynamic',
                ],
            ];
        }

        return $cases;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function walPersistOverwriteRecoveryCases(): array
    {
        $persistScenarios = [
            [
                'source_file' => 'walpersist.test',
                'upstream' => 'walpersist.test walpersist-1.0..1.11',
                'phase' => 'persistent-wal-file-control-keeps-wal-and-shm-after-close',
                'journal_mode' => 'wal',
                'journal_size_limit' => null,
                'page_size' => 4096,
                'wal_bytes_before_close' => 8272,
                'wal_bytes_after_close' => 8272,
                'shm_exists_after_close' => true,
                'wal_exists_after_close' => true,
                'persist_wal_sequence' => [0, 1, 1, 0, 0, 1],
                'query_after_reopen' => ['length(a)' => 5000],
                'integrity' => 'ok',
                'recovery_required' => false,
                'savepoint_rolled_back' => false,
            ],
            [
                'source_file' => 'walpersist.test',
                'upstream' => 'walpersist.test walpersist-2.1..2.3',
                'phase' => 'persistent-wal-honors-journal-size-limit-on-close',
                'journal_mode' => 'wal',
                'journal_size_limit' => 12000,
                'page_size' => 4096,
                'wal_bytes_before_close' => 112000,
                'wal_bytes_after_close' => 0,
                'shm_exists_after_close' => true,
                'wal_exists_after_close' => true,
                'persist_wal_sequence' => [1],
                'query_after_reopen' => ['integrity_check' => 'ok'],
                'integrity' => 'ok',
                'recovery_required' => false,
                'savepoint_rolled_back' => false,
            ],
            [
                'source_file' => 'walpersist.test',
                'upstream' => 'walpersist.test walpersist-3.1..3.4',
                'phase' => 'persistent-wal-truncates-after-autocheckpoint-close',
                'journal_mode' => 'wal',
                'journal_size_limit' => 16384,
                'page_size' => 1024,
                'wal_bytes_before_close' => 16384,
                'wal_bytes_after_close' => 0,
                'shm_exists_after_close' => true,
                'wal_exists_after_close' => true,
                'persist_wal_sequence' => [1],
                'query_after_reopen' => ['integrity_check' => 'ok'],
                'integrity' => 'ok',
                'recovery_required' => false,
                'savepoint_rolled_back' => false,
            ],
            [
                'source_file' => 'walpersist.test',
                'upstream' => 'walpersist.test 4.1',
                'phase' => 'persist-wal-survives-journal-mode-toggle-chain',
                'journal_mode' => 'persist',
                'journal_size_limit' => null,
                'page_size' => 4096,
                'wal_bytes_before_close' => 8272,
                'wal_bytes_after_close' => 8272,
                'shm_exists_after_close' => true,
                'wal_exists_after_close' => true,
                'persist_wal_sequence' => [1],
                'query_after_reopen' => ['journal_modes' => ['truncate', 'memory', 'wal', 'persist']],
                'integrity' => 'ok',
                'recovery_required' => false,
                'savepoint_rolled_back' => false,
            ],
        ];

        $overwriteScenarios = [
            [
                'source_file' => 'waloverwrite.test',
                'upstream' => 'waloverwrite.test 1.1.1..1.1.6',
                'phase' => 'empty-wal-overwrites-repeated-page-updates-before-recovery',
                'journal_mode' => 'wal',
                'page_size' => 1024,
                'initial_wal_has_transaction' => false,
                'cache_size' => 5,
                'row_count' => 20,
                'blob_bytes_before_wal_recovery' => 800,
                'blob_bytes_after_wal_recovery' => 799,
                'wal_frame_min' => 41,
                'wal_frame_max' => 59,
                'savepoint_rolled_back' => false,
                'recovery_required' => true,
                'integrity' => 'ok',
            ],
            [
                'source_file' => 'waloverwrite.test',
                'upstream' => 'waloverwrite.test 1.2.1..1.2.6',
                'phase' => 'nonempty-wal-overwrites-repeated-page-updates-before-recovery',
                'journal_mode' => 'wal',
                'page_size' => 1024,
                'initial_wal_has_transaction' => true,
                'cache_size' => 5,
                'row_count' => 20,
                'blob_bytes_before_wal_recovery' => 800,
                'blob_bytes_after_wal_recovery' => 799,
                'wal_frame_min' => 41,
                'wal_frame_max' => 59,
                'savepoint_rolled_back' => false,
                'recovery_required' => true,
                'integrity' => 'ok',
            ],
            [
                'source_file' => 'waloverwrite.test',
                'upstream' => 'waloverwrite.test 1.1.7..1.1.10',
                'phase' => 'empty-wal-savepoint-rollback-restores-pre-savepoint-page-image',
                'journal_mode' => 'wal',
                'page_size' => 1024,
                'initial_wal_has_transaction' => false,
                'cache_size' => 5,
                'row_count' => 20,
                'blob_bytes_before_wal_recovery' => 799,
                'blob_bytes_after_wal_recovery' => 798,
                'wal_frame_min' => 56,
                'wal_frame_max' => 74,
                'savepoint_rolled_back' => true,
                'recovery_required' => true,
                'integrity' => 'ok',
            ],
            [
                'source_file' => 'waloverwrite.test',
                'upstream' => 'waloverwrite.test 1.2.7..1.2.10',
                'phase' => 'nonempty-wal-savepoint-rollback-restores-pre-savepoint-page-image',
                'journal_mode' => 'wal',
                'page_size' => 1024,
                'initial_wal_has_transaction' => true,
                'cache_size' => 5,
                'row_count' => 20,
                'blob_bytes_before_wal_recovery' => 799,
                'blob_bytes_after_wal_recovery' => 798,
                'wal_frame_min' => 56,
                'wal_frame_max' => 74,
                'savepoint_rolled_back' => true,
                'recovery_required' => true,
                'integrity' => 'ok',
            ],
        ];

        $scenarios = array_merge($persistScenarios, $overwriteScenarios);
        $checkpointModes = ['passive', 'full', 'restart', 'truncate'];
        $syncModes = ['normal', 'full', 'extra', 'off'];
        $cacheSpillModes = ['default', 'enabled', 'disabled'];
        $cases = [];

        for ($i = 0; $i < 1000; $i++) {
            $scenario = $scenarios[$i % count($scenarios)];
            $checkpointMode = $checkpointModes[$i % count($checkpointModes)];
            $syncMode = $syncModes[intdiv($i, 4) % count($syncModes)];
            $cacheSpill = $cacheSpillModes[intdiv($i, 16) % count($cacheSpillModes)];
            $walBefore = (int) ($scenario['wal_bytes_before_close'] ?? (($scenario['wal_frame_min'] + $scenario['wal_frame_max']) * $scenario['page_size']));
            $walAfter = (int) ($scenario['wal_bytes_after_close'] ?? $walBefore);
            $frameCount = isset($scenario['wal_frame_min'])
                ? (int) floor(($scenario['wal_frame_min'] + $scenario['wal_frame_max']) / 2)
                : max(0, intdiv($walBefore, (int) $scenario['page_size'] + 24));

            $cases[] = $scenario + [
                'case' => $i + 1,
                'checkpoint_mode' => $checkpointMode,
                'synchronous' => $syncMode,
                'cache_spill' => $cacheSpill,
                'wal_bytes_before_close' => $walBefore,
                'wal_bytes_after_close' => $walAfter,
                'wal_frame_count' => $frameCount,
                'wal_truncated_on_close' => $walAfter === 0,
                'sidecars_persist' => (bool) (($scenario['wal_exists_after_close'] ?? true) && ($scenario['shm_exists_after_close'] ?? true)),
                'recovered_blob_sum' => isset($scenario['row_count'], $scenario['blob_bytes_after_wal_recovery'])
                    ? (int) $scenario['row_count'] * (int) $scenario['blob_bytes_after_wal_recovery']
                    : null,
                'pre_recovery_blob_sum' => isset($scenario['row_count'], $scenario['blob_bytes_before_wal_recovery'])
                    ? (int) $scenario['row_count'] * (int) $scenario['blob_bytes_before_wal_recovery']
                    : null,
                'dependencies' => [
                    'sqlite-real-upstream-pager-wal-dynamic',
                    'sqlite-upstream-walpersist-persistent-sidecars',
                    'sqlite-upstream-waloverwrite-frame-recovery',
                ],
            ];
        }

        return $cases;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function wal8Wal9PageSizeMappingCases(): array
    {
        $wal8Scenarios = [
            [
                'source_file' => 'wal8.test',
                'upstream' => 'wal8.test 1.0 1.1',
                'phase' => 'empty-handle-sees-wal-initialized-by-second-handle-before-vacuum',
                'other_handle_initializes_wal_before_schema' => true,
                'schema_created_before_wal' => false,
                'page_size_pragma_after_open' => 4096,
                'vacuum_result_code' => 0,
                'vacuum_message' => '',
                'schema_names' => ['t1'],
                'journal_mode' => 'wal',
                'rows' => [[1, 2]],
            ],
            [
                'source_file' => 'wal8.test',
                'upstream' => 'wal8.test 2.0 2.1',
                'phase' => 'empty-handle-sees-wal-enabled-after-schema-before-vacuum',
                'other_handle_initializes_wal_before_schema' => false,
                'schema_created_before_wal' => true,
                'page_size_pragma_after_open' => 4096,
                'vacuum_result_code' => 0,
                'vacuum_message' => '',
                'schema_names' => ['t1'],
                'journal_mode' => 'wal',
                'rows' => [[1, 2]],
            ],
            [
                'source_file' => 'wal8.test',
                'upstream' => 'wal8.test 3.0 3.1',
                'phase' => 'empty-handle-page-size-pragma-does-not-hide-wal-schema',
                'other_handle_initializes_wal_before_schema' => true,
                'schema_created_before_wal' => false,
                'page_size_pragma_after_open' => 4096,
                'vacuum_result_code' => null,
                'vacuum_message' => null,
                'schema_names' => ['t1'],
                'journal_mode' => 'wal',
                'rows' => [[1, 2]],
            ],
        ];

        $wal9Scenario = [
            'source_file' => 'wal9.test',
            'upstream' => 'wal9.test 1.0 1.6 1.7',
            'phase' => 'fully-checkpointed-large-wal-partial-shm-rollback-does-not-remap-tail',
            'page_size' => 1024,
            'wal_autocheckpoint' => 0,
            'database_bytes_after_checkpoint' => 1024,
            'wal_bytes_greater_than' => 1500 * 1024,
            'shm_bytes_greater_than' => 32768,
            'checkpoint' => [0, 14501, 14501],
            'partial_shm_mapping_bytes' => 32768,
            'rolled_back_insert_value' => 'hello',
            'rollback_result_code' => 0,
            'rollback_message' => '',
            'reader_requires_tail_mapping_after_checkpoint' => false,
        ];

        $pageSizeRequests = [1024, 2048, 4096, 8192];
        $schemaReaders = ['sqlite_master', 'schema-cache', 'pager-schema'];
        $checkpointModes = ['passive', 'full', 'restart', 'truncate'];

        $cases = [];
        for ($i = 0; $i < 1000; $i++) {
            if (($i % 4) === 3) {
                $checkpointFrameCount = $wal9Scenario['checkpoint'][1] + intdiv($i, 4);
                $cases[] = $wal9Scenario + [
                    'case' => $i + 1,
                    'requested_page_size' => $pageSizeRequests[$i % count($pageSizeRequests)],
                    'schema_reader' => $schemaReaders[intdiv($i, 4) % count($schemaReaders)],
                    'checkpoint_mode' => $checkpointModes[intdiv($i, 12) % count($checkpointModes)],
                    'checkpoint' => [0, $checkpointFrameCount, $checkpointFrameCount],
                    'wal_bytes' => ($checkpointFrameCount + 1) * 1024,
                    'shm_bytes' => 32768 + (4096 * (1 + ($i % 8))),
                    'assertion_family' => 'wal9-partial-shm-rollback-after-full-checkpoint',
                    'dependencies' => [
                        'sqlite-real-upstream-pager-wal-dynamic',
                        'sqlite-upstream-wal9-partial-shm-rollback',
                        'sqlite-wal-checkpoint-reader-mapping',
                    ],
                ];
                continue;
            }

            $scenario = $wal8Scenarios[$i % count($wal8Scenarios)];
            $requestedPageSize = $pageSizeRequests[intdiv($i, 3) % count($pageSizeRequests)];
            $cases[] = $scenario + [
                'case' => $i + 1,
                'requested_page_size' => $requestedPageSize,
                'schema_reader' => $schemaReaders[intdiv($i, 3) % count($schemaReaders)],
                'checkpoint_mode' => $checkpointModes[intdiv($i, 9) % count($checkpointModes)],
                'effective_page_size' => $scenario['page_size_pragma_after_open'],
                'vacuum_keeps_database_readable' => true,
                'wal_sidecar_exists' => true,
                'database_was_empty_when_first_handle_opened' => true,
                'assertion_family' => 'wal8-empty-file-page-size-after-wal-init',
                'dependencies' => [
                    'sqlite-real-upstream-pager-wal-dynamic',
                    'sqlite-upstream-wal8-empty-file-page-size',
                    'sqlite-pager-empty-file-wal-page-size',
                ],
            ];
        }

        return $cases;
    }

    /**
     * @param list<array<string, mixed>> $locks
     */
    private static function countLocks(array $locks, string $op, string $level): int
    {
        $count = 0;
        foreach ($locks as $lock) {
            if ($lock['op'] === $op && $lock['level'] === $level) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param list<array<string, mixed>> $locks
     */
    private static function hasLockResult(array $locks, string $result): bool
    {
        foreach ($locks as $lock) {
            if (($lock['result'] ?? null) === $result) {
                return true;
            }
        }

        return false;
    }
}
