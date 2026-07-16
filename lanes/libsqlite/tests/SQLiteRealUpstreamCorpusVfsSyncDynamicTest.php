<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$sourceCases = [
    [
        'scenario' => 'sync2-1.1-delete-default',
        'journal_mode' => 'delete',
        'synchronous' => 'full',
        'expected_sync_count' => 4,
        'expected_targets' => ['directory', 'rollback_journal_pages', 'rollback_journal_header', 'database'],
        'upstream' => 'sync2.test 1.1 delete journal default/full transaction',
    ],
    [
        'scenario' => 'sync2-1.2.3-delete-normal',
        'journal_mode' => 'delete',
        'synchronous' => 'normal',
        'expected_sync_count' => 3,
        'expected_targets' => ['directory', 'rollback_journal_pages', 'database'],
        'upstream' => 'sync2.test 1.2.3 delete journal synchronous=NORMAL transaction',
    ],
    [
        'scenario' => 'sync2-1.3.3-delete-off',
        'journal_mode' => 'delete',
        'synchronous' => 'off',
        'expected_sync_count' => 0,
        'expected_targets' => [],
        'upstream' => 'sync2.test 1.3.3 delete journal synchronous=OFF transaction',
    ],
    [
        'scenario' => 'sync2-1.4.3-delete-full',
        'journal_mode' => 'delete',
        'synchronous' => 'full',
        'expected_sync_count' => 4,
        'expected_targets' => ['directory', 'rollback_journal_pages', 'rollback_journal_header', 'database'],
        'upstream' => 'sync2.test 1.4.3 delete journal synchronous=FULL transaction',
    ],
    [
        'scenario' => 'sync2-1.6-wal-full-first',
        'journal_mode' => 'wal',
        'synchronous' => 'full',
        'wal_first_transaction' => true,
        'expected_sync_count' => 3,
        'expected_targets' => ['directory', 'wal_header', 'wal_frames'],
        'upstream' => 'sync2.test 1.6 WAL synchronous=FULL first transaction',
    ],
    [
        'scenario' => 'sync2-1.7-wal-full-subsequent',
        'journal_mode' => 'wal',
        'synchronous' => 'full',
        'expected_sync_count' => 1,
        'expected_targets' => ['wal_frames'],
        'upstream' => 'sync2.test 1.7 WAL synchronous=FULL subsequent transaction',
    ],
    [
        'scenario' => 'sync2-1.8.3-wal-normal-subsequent',
        'journal_mode' => 'wal',
        'synchronous' => 'normal',
        'expected_sync_count' => 0,
        'expected_targets' => [],
        'upstream' => 'sync2.test 1.8.3 WAL synchronous=NORMAL subsequent transaction',
    ],
    [
        'scenario' => 'sync2-1.9-wal-checkpoint-normal',
        'journal_mode' => 'wal',
        'synchronous' => 'normal',
        'wal_checkpoint' => true,
        'expected_sync_count' => 2,
        'expected_targets' => ['wal', 'database'],
        'upstream' => 'sync2.test 1.9 WAL checkpoint syncs WAL and database',
    ],
    [
        'scenario' => 'sync2-1.10.3-wal-off',
        'journal_mode' => 'wal',
        'synchronous' => 'off',
        'expected_sync_count' => 0,
        'expected_targets' => [],
        'upstream' => 'sync2.test 1.10.3 WAL synchronous=OFF transaction',
    ],
    [
        'scenario' => 'sync2-1.11.1-default-wal-first-normal',
        'journal_mode' => 'wal',
        'synchronous' => 'normal',
        'wal_first_transaction' => true,
        'expected_sync_count' => 2,
        'expected_targets' => ['directory', 'wal_header'],
        'upstream' => 'sync2.test 1.11.1 default WAL synchronous=NORMAL first transaction',
    ],
    [
        'scenario' => 'sync2-1.11.2-default-wal-subsequent-normal',
        'journal_mode' => 'wal',
        'synchronous' => 'normal',
        'expected_sync_count' => 0,
        'expected_targets' => [],
        'upstream' => 'sync2.test 1.11.2 default WAL synchronous=NORMAL subsequent transaction',
    ],
    [
        'scenario' => 'sync-1.1-attach-schema-setup',
        'journal_mode' => 'delete',
        'synchronous' => 'full',
        'attached_database' => true,
        'schema_setup' => true,
        'expected_sync_count' => 8,
        'expected_targets' => ['main_directory', 'main_rollback_journal_pages', 'main_rollback_journal_header', 'main_database', 'attached_directory', 'attached_rollback_journal_pages', 'attached_rollback_journal_header', 'attached_database'],
        'upstream' => 'sync.test sync-1.1 main plus attached schema setup',
    ],
    [
        'scenario' => 'sync-1.2-attached-on',
        'journal_mode' => 'delete',
        'synchronous' => 'on',
        'attached_database' => true,
        'expected_sync_count' => 9,
        'expected_targets' => ['main_rollback_journal_pages', 'attached_rollback_journal_pages', 'master_journal', 'main_rollback_journal_master_name', 'attached_rollback_journal_master_name', 'main_database', 'attached_database', 'directory', 'master_journal_directory'],
        'upstream' => 'sync.test sync-1.2 attached synchronous=ON multi-database commit',
    ],
    [
        'scenario' => 'sync-1.3-attached-full',
        'journal_mode' => 'delete',
        'synchronous' => 'full',
        'attached_database' => true,
        'expected_sync_count' => 11,
        'expected_targets' => ['main_rollback_journal_pages', 'main_rollback_journal_header', 'attached_rollback_journal_pages', 'attached_rollback_journal_header', 'master_journal', 'main_rollback_journal_master_name', 'attached_rollback_journal_master_name', 'main_database', 'attached_database', 'directory', 'master_journal_directory'],
        'upstream' => 'sync.test sync-1.3 attached synchronous=FULL multi-database commit',
    ],
    [
        'scenario' => 'sync-1.4-attached-off',
        'journal_mode' => 'delete',
        'synchronous' => 'off',
        'attached_database' => true,
        'expected_sync_count' => 0,
        'expected_targets' => [],
        'upstream' => 'sync.test sync-1.4 attached synchronous=OFF multi-database commit',
    ],
];

$caseNumber = 0;
foreach (range(1, 80) as $round) {
    foreach ($sourceCases as $sourceCase) {
        ++$caseNumber;
        $tests[sprintf(
            'real upstream corpus vfs sync dynamic %04d %s round %02d',
            $caseNumber,
            $sourceCase['scenario'],
            $round
        )] = static function (TestRunner $t) use ($sourceCase, $round): void {
            $rowCount = 2 + $round;
            $plan = SQLiteVfsIoDynamicPlan::syncPragmaTrafficProfile(
                $sourceCase['scenario'] . '.' . $round,
                $sourceCase['journal_mode'],
                $sourceCase['synchronous'],
                (bool) ($sourceCase['attached_database'] ?? false),
                (bool) ($sourceCase['schema_setup'] ?? false),
                (bool) ($sourceCase['wal_first_transaction'] ?? false),
                (bool) ($sourceCase['wal_checkpoint'] ?? false),
                $rowCount,
                true
            );

            $t->same('ok', $plan['status']);
            $t->same(in_array($sourceCase['scenario'], ['sync-1.1-attach-schema-setup', 'sync-1.2-attached-on', 'sync-1.3-attached-full', 'sync-1.4-attached-off'], true) ? 'sync.test' : 'sync2.test', $plan['script']);
            $t->same($sourceCase['scenario'] . '.' . $round, $plan['scenario']);
            $t->same($sourceCase['journal_mode'], $plan['journal_mode']);
            $t->same($sourceCase['synchronous'] === 'on' ? 'normal' : $sourceCase['synchronous'], $plan['synchronous']);
            $t->same((bool) ($sourceCase['attached_database'] ?? false), $plan['attached_database']);
            $t->same((bool) ($sourceCase['schema_setup'] ?? false), $plan['schema_setup']);
            $t->same((bool) ($sourceCase['wal_first_transaction'] ?? false), $plan['wal_first_transaction']);
            $t->same((bool) ($sourceCase['wal_checkpoint'] ?? false), $plan['wal_checkpoint']);
            $t->same($rowCount, $plan['row_count']);
            $t->same($sourceCase['expected_sync_count'], $plan['sync_count']);
            $t->same($sourceCase['expected_targets'], $plan['sync_targets']);
            $t->same($sourceCase['expected_sync_count'] === 0, $plan['sync_disabled']);
            $t->same($sourceCase['expected_sync_count'] > 0, $plan['durability_barrier']);
            $t->same([$sourceCase['upstream']], $plan['upstream']);
            $t->same(true, in_array('upstream-sync-test', $plan['dependencies'], true));
            $t->same(true, in_array('vfs-sync-count-pragmas', $plan['dependencies'], true));
            $t->same(true, in_array('vfs-io-dynamic-real-corpus', $plan['dependencies'], true));
        };
    }
}

$tests['real upstream corpus vfs sync dynamic owns sync source rows'] = static function (TestRunner $t) use ($sourceCases, $caseNumber): void {
    $t->same(1200, $caseNumber);
    $t->same([
        'sync2.test 1.1 delete journal default/full transaction',
        'sync2.test 1.2.3 delete journal synchronous=NORMAL transaction',
        'sync2.test 1.3.3 delete journal synchronous=OFF transaction',
        'sync2.test 1.4.3 delete journal synchronous=FULL transaction',
        'sync2.test 1.6 WAL synchronous=FULL first transaction',
        'sync2.test 1.7 WAL synchronous=FULL subsequent transaction',
        'sync2.test 1.8.3 WAL synchronous=NORMAL subsequent transaction',
        'sync2.test 1.9 WAL checkpoint syncs WAL and database',
        'sync2.test 1.10.3 WAL synchronous=OFF transaction',
        'sync2.test 1.11.1 default WAL synchronous=NORMAL first transaction',
        'sync2.test 1.11.2 default WAL synchronous=NORMAL subsequent transaction',
        'sync.test sync-1.1 main plus attached schema setup',
        'sync.test sync-1.2 attached synchronous=ON multi-database commit',
        'sync.test sync-1.3 attached synchronous=FULL multi-database commit',
        'sync.test sync-1.4 attached synchronous=OFF multi-database commit',
    ], array_column($sourceCases, 'upstream'));
};

$tests['real upstream corpus vfs sync dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::syncPragmaTrafficProfile('', 'delete', 'full'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::syncPragmaTrafficProfile('sync2-1.1', 'memory', 'full'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::syncPragmaTrafficProfile('sync2-1.1', 'delete', 'extra'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::syncPragmaTrafficProfile('sync2-1.1', 'delete', 'full', false, false, false, false, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::syncPragmaTrafficProfile('sync2-1.9', 'delete', 'normal', false, false, false, true));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::syncPragmaTrafficProfile('sync-1.1', 'wal', 'full', true, true));
};

$tests['real upstream corpus vfs sync dynamic records non overlap and dependency closure'] = static function (TestRunner $t): void {
    $plan = SQLiteVfsIoDynamicPlan::syncPragmaTrafficProfile('sync2-1.9-wal-checkpoint-normal.sample', 'wal', 'normal', false, false, false, true, 9);

    $t->same('wal_checkpoint_syncs_wal_and_database', $plan['reason']);
    $t->same('no new support component needed; reuses bounded VFS sync-count modeling', $plan['dependency_closure']);
    $t->same('does not repeat io.test sync matrix, VFS sync flag planning, sync apply, rollback-journal apply, WAL checkpoint transaction, or lock-state clusters', $plan['non_overlap']);
};

return $tests;
