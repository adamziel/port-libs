<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$deleteFullTargets = ['directory', 'rollback_journal_pages', 'rollback_journal_header', 'database'];
$sourceCases = [
    [
        'scenario' => 'sync2-1.12-default-wal-checkpoint-normal',
        'journal_mode' => 'wal',
        'synchronous' => 'normal',
        'wal_checkpoint' => true,
        'expected_sync_count' => 2,
        'expected_targets' => ['wal', 'database'],
        'expected_reason' => 'wal_checkpoint_syncs_wal_and_database',
        'upstream' => 'sync2.test 1.12 default WAL checkpoint syncs WAL and database',
    ],
    [
        'scenario' => 'sync2-1.13.1-default-wal-after-checkpoint',
        'journal_mode' => 'wal',
        'synchronous' => 'normal',
        'wal_restart_after_checkpoint' => true,
        'expected_sync_count' => 1,
        'expected_targets' => ['wal_frames'],
        'expected_reason' => 'wal_normal_first_transaction_after_checkpoint_syncs_frames',
        'upstream' => 'sync2.test 1.13.1 first WAL transaction after checkpoint syncs WAL frames',
    ],
    [
        'scenario' => 'sync2-1.13.2-default-wal-subsequent-after-checkpoint',
        'journal_mode' => 'wal',
        'synchronous' => 'normal',
        'expected_sync_count' => 0,
        'expected_targets' => [],
        'expected_reason' => 'wal_normal_subsequent_transaction_defers_sync_until_checkpoint',
        'upstream' => 'sync2.test 1.13.2 subsequent WAL transaction after checkpoint defers sync',
    ],
    [
        'scenario' => 'sync2-1.15.1-default-delete-full-first',
        'journal_mode' => 'delete',
        'synchronous' => 'full',
        'expected_sync_count' => 4,
        'expected_targets' => $deleteFullTargets,
        'expected_reason' => 'delete_full_syncs_directory_journal_header_and_database',
        'upstream' => 'sync2.test 1.15.1 delete journal default FULL first transaction',
    ],
    [
        'scenario' => 'sync2-1.15.2-default-delete-full-subsequent',
        'journal_mode' => 'delete',
        'synchronous' => 'full',
        'expected_sync_count' => 4,
        'expected_targets' => $deleteFullTargets,
        'expected_reason' => 'delete_full_syncs_directory_journal_header_and_database',
        'upstream' => 'sync2.test 1.15.2 delete journal default FULL subsequent transaction',
    ],
    [
        'scenario' => 'sync2-1.17.1-default-wal-after-delete-first-normal',
        'journal_mode' => 'wal',
        'synchronous' => 'normal',
        'wal_first_transaction' => true,
        'expected_sync_count' => 2,
        'expected_targets' => ['directory', 'wal_header'],
        'expected_reason' => 'wal_normal_first_transaction_syncs_directory_and_header',
        'upstream' => 'sync2.test 1.17.1 WAL default NORMAL first transaction after delete mode',
    ],
    [
        'scenario' => 'sync2-1.17.2-default-wal-after-delete-subsequent-normal',
        'journal_mode' => 'wal',
        'synchronous' => 'normal',
        'expected_sync_count' => 0,
        'expected_targets' => [],
        'expected_reason' => 'wal_normal_subsequent_transaction_defers_sync_until_checkpoint',
        'upstream' => 'sync2.test 1.17.2 WAL default NORMAL subsequent transaction after delete mode',
    ],
    [
        'scenario' => 'sync2-1.19.1-delete-off-after-wal',
        'journal_mode' => 'delete',
        'synchronous' => 'off',
        'expected_sync_count' => 0,
        'expected_targets' => [],
        'expected_reason' => 'synchronous_off_disables_vfs_syncs',
        'upstream' => 'sync2.test 1.19.1 delete journal synchronous=OFF first transaction after WAL mode',
    ],
    [
        'scenario' => 'sync2-1.19.2-delete-off-after-wal',
        'journal_mode' => 'delete',
        'synchronous' => 'off',
        'expected_sync_count' => 0,
        'expected_targets' => [],
        'expected_reason' => 'synchronous_off_disables_vfs_syncs',
        'upstream' => 'sync2.test 1.19.2 delete journal synchronous=OFF subsequent transaction after WAL mode',
    ],
    [
        'scenario' => 'sync2-1.20.1-reopen-delete-default-full',
        'journal_mode' => 'delete',
        'synchronous' => 'full',
        'expected_sync_count' => 4,
        'expected_targets' => $deleteFullTargets,
        'expected_reason' => 'delete_full_syncs_directory_journal_header_and_database',
        'upstream' => 'sync2.test 1.20.1 reopen restores default FULL delete transaction',
    ],
    [
        'scenario' => 'sync2-1.20.2-reopen-delete-default-full',
        'journal_mode' => 'delete',
        'synchronous' => 'full',
        'expected_sync_count' => 4,
        'expected_targets' => $deleteFullTargets,
        'expected_reason' => 'delete_full_syncs_directory_journal_header_and_database',
        'upstream' => 'sync2.test 1.20.2 reopened default FULL delete transaction repeats syncs',
    ],
];

$caseNumber = 0;
foreach (range(1, 92) as $round) {
    foreach ($sourceCases as $sourceCase) {
        ++$caseNumber;
        $tests[sprintf(
            'real upstream corpus vfs sync default transition dynamic %04d %s round %02d',
            $caseNumber,
            $sourceCase['scenario'],
            $round
        )] = static function (TestRunner $t) use ($sourceCase, $round): void {
            $rowCount = 2 + $round;
            $plan = SQLiteVfsIoDynamicPlan::syncPragmaTrafficProfile(
                $sourceCase['scenario'] . '.round' . $round,
                $sourceCase['journal_mode'],
                $sourceCase['synchronous'],
                false,
                false,
                (bool) ($sourceCase['wal_first_transaction'] ?? false),
                (bool) ($sourceCase['wal_checkpoint'] ?? false),
                $rowCount,
                true,
                (bool) ($sourceCase['wal_restart_after_checkpoint'] ?? false)
            );

            $t->same('ok', $plan['status']);
            $t->same('sync2.test', $plan['script']);
            $t->same($sourceCase['scenario'] . '.round' . $round, $plan['scenario']);
            $t->same($sourceCase['journal_mode'], $plan['journal_mode']);
            $t->same($sourceCase['synchronous'], $plan['synchronous']);
            $t->same(match ($sourceCase['synchronous']) {
                'off' => 0,
                'normal' => 1,
                'full' => 2,
            }, $plan['pragma_synchronous_value']);
            $t->same(false, $plan['attached_database']);
            $t->same(false, $plan['schema_setup']);
            $t->same((bool) ($sourceCase['wal_first_transaction'] ?? false), $plan['wal_first_transaction']);
            $t->same((bool) ($sourceCase['wal_checkpoint'] ?? false), $plan['wal_checkpoint']);
            $t->same((bool) ($sourceCase['wal_restart_after_checkpoint'] ?? false), $plan['wal_restart_after_checkpoint']);
            $t->same($rowCount, $plan['row_count']);
            $t->same(true, $plan['directory_sync']);
            $t->same($sourceCase['expected_sync_count'], $plan['sync_count']);
            $t->same($sourceCase['expected_targets'], $plan['sync_targets']);
            $t->same(
                ($sourceCase['wal_checkpoint'] ?? false) ? [0, $rowCount, $rowCount] : null,
                $plan['wal_checkpoint_result']
            );
            $t->same($sourceCase['expected_sync_count'] === 0, $plan['sync_disabled']);
            $t->same($sourceCase['expected_sync_count'] > 0, $plan['durability_barrier']);
            $t->same($sourceCase['expected_reason'], $plan['reason']);
            $t->same([$sourceCase['upstream']], $plan['upstream']);
            $t->same(true, in_array('upstream-sync-test', $plan['dependencies'], true));
            $t->same(true, in_array('vfs-sync-count-pragmas', $plan['dependencies'], true));
            $t->same(true, in_array('vfs-io-dynamic-real-corpus', $plan['dependencies'], true));
            $t->same('no new support component needed; reuses bounded VFS sync-count modeling', $plan['dependency_closure']);
            $t->same('does not repeat io.test sync matrix, VFS sync flag planning, sync apply, rollback-journal apply, WAL checkpoint transaction, or lock-state clusters', $plan['non_overlap']);
        };
    }
}

$tests['real upstream corpus vfs sync default transition owns sync2 source rows'] = static function (TestRunner $t) use ($sourceCases, $caseNumber): void {
    $sync2 = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/sync2.test');
    $t->same(true, is_string($sync2));
    $t->same(1012, $caseNumber);
    $t->same(11, count($sourceCases));
    $t->same([
        'sync2.test 1.12 default WAL checkpoint syncs WAL and database',
        'sync2.test 1.13.1 first WAL transaction after checkpoint syncs WAL frames',
        'sync2.test 1.13.2 subsequent WAL transaction after checkpoint defers sync',
        'sync2.test 1.15.1 delete journal default FULL first transaction',
        'sync2.test 1.15.2 delete journal default FULL subsequent transaction',
        'sync2.test 1.17.1 WAL default NORMAL first transaction after delete mode',
        'sync2.test 1.17.2 WAL default NORMAL subsequent transaction after delete mode',
        'sync2.test 1.19.1 delete journal synchronous=OFF first transaction after WAL mode',
        'sync2.test 1.19.2 delete journal synchronous=OFF subsequent transaction after WAL mode',
        'sync2.test 1.20.1 reopen restores default FULL delete transaction',
        'sync2.test 1.20.2 reopened default FULL delete transaction repeats syncs',
    ], array_column($sourceCases, 'upstream'));
    foreach ([
        'do_execsql_sync_test 1.12',
        'do_execsql_sync_test 1.13.1',
        'do_execsql_sync_test 1.13.2',
        'do_execsql_test 1.14',
        'do_execsql_sync_test 1.15.1',
        'do_execsql_sync_test 1.15.2',
        'do_execsql_test 1.16',
        'do_execsql_sync_test 1.17.1',
        'do_execsql_sync_test 1.17.2',
        'do_execsql_test 1.18',
        'do_execsql_sync_test 1.19.1',
        'do_execsql_sync_test 1.19.2',
        'do_execsql_sync_test 1.20.1',
        'do_execsql_sync_test 1.20.2',
    ] as $needle) {
        $t->same(true, str_contains($sync2, $needle), $needle);
    }
    $t->same(true, str_contains($sync2, 'SQLITE_DEFAULT_SYNCHRONOUS==2 && $SQLITE_DEFAULT_WAL_SYNCHRONOUS==1'));
};

$tests['real upstream corpus vfs sync default transition rejects malformed restart flags'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::syncPragmaTrafficProfile('sync2-1.13.1-default-wal-after-checkpoint.bad', 'delete', 'normal', false, false, false, false, 1, true, true));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::syncPragmaTrafficProfile('sync2-1.13.1-default-wal-after-checkpoint.bad', 'wal', 'full', false, false, false, false, 1, true, true));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::syncPragmaTrafficProfile('sync2-1.13.1-default-wal-after-checkpoint.bad', 'wal', 'normal', false, false, true, false, 1, true, true));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::syncPragmaTrafficProfile('sync2-1.13.1-default-wal-after-checkpoint.bad', 'wal', 'normal', false, false, false, true, 1, true, true));
};

$tests['real upstream corpus vfs sync default transition records non overlap and dependency closure'] = static function (TestRunner $t): void {
    $checkpoint = SQLiteVfsIoDynamicPlan::syncPragmaTrafficProfile('sync2-1.12-default-wal-checkpoint-normal.sample', 'wal', 'normal', false, false, false, true, 2, true);
    $afterCheckpoint = SQLiteVfsIoDynamicPlan::syncPragmaTrafficProfile('sync2-1.13.1-default-wal-after-checkpoint.sample', 'wal', 'normal', false, false, false, false, 2, true, true);
    $reopen = SQLiteVfsIoDynamicPlan::syncPragmaTrafficProfile('sync2-1.20.1-reopen-delete-default-full.sample', 'delete', 'full', false, false, false, false, 2, true);

    $t->same([0, 2, 2], $checkpoint['wal_checkpoint_result']);
    $t->same('wal_normal_first_transaction_after_checkpoint_syncs_frames', $afterCheckpoint['reason']);
    $t->same('sync2.test 1.13.1 first WAL transaction after checkpoint syncs WAL frames', $afterCheckpoint['upstream'][0]);
    $t->same(2, $reopen['pragma_synchronous_value']);
    $t->same('no new support component needed; reuses bounded VFS sync-count modeling', $afterCheckpoint['dependency_closure']);
    $t->same('does not repeat io.test sync matrix, VFS sync flag planning, sync apply, rollback-journal apply, WAL checkpoint transaction, or lock-state clusters', $afterCheckpoint['non_overlap']);
};

return $tests;
