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
}
