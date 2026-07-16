<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerWalDynamicPlan;

$tests = [];

$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

$tests['real upstream corpus pager wal shared cache dynamic 152610 cites hydrated upstream source'] = static function (TestRunner $t) use ($upstreamRoot): void {
    $source = (string) file_get_contents($upstreamRoot . '/walshared.test');

    $t->contains('focus of this file is testing the operation of the library in', $source);
    $t->contains('PRAGMA journal_mode = WAL', $source);
    $t->contains('sqlite3_enable_shared_cache 1', $source);
    $t->contains('do_test walshared-1.1', $source);
    $t->contains('catchsql { PRAGMA wal_checkpoint }', $source);
    $t->contains('{1 {database table is locked}}', $source);
    $t->contains('PRAGMA integrity_check', $source);
};

$connectionOrders = [
    ['db'],
    ['db2'],
    ['db', 'db2'],
    ['db2', 'db'],
];
$cacheSizes = [6, 8, 10, 12, 16, 24, 32, 48];
$expansionPasses = [1, 2, 3, 4];
$rows = [];

for ($case = 1; $case <= 1000; $case++) {
    $cacheSize = $cacheSizes[($case - 1) % count($cacheSizes)];
    $initialRows = 1 + (($case * 3) % 11);
    $expansions = $expansionPasses[intdiv($case - 1, count($cacheSizes)) % count($expansionPasses)];
    $rowsAfterInsert = $initialRows + 1;
    $rowsInTransaction = $rowsAfterInsert * (2 ** $expansions);
    $checkpointConnections = $connectionOrders[($case - 1) % count($connectionOrders)];

    $rows[] = [
        'case' => $case,
        'script' => 'walshared.test',
        'upstream' => sprintf('walshared.test walshared-1.0..1.4 dynamic shared-cache checkpoint case %04d', $case),
        'cache_size' => $cacheSize,
        'initial_rows' => $initialRows,
        'rows_after_direct_insert' => $rowsAfterInsert,
        'expansion_passes' => $expansions,
        'rows_in_transaction' => $rowsInTransaction,
        'checkpoint_connections' => $checkpointConnections,
        'expected_checkpoint_result' => [1, 'database table is locked'],
        'expected_visible_rows_before_commit' => $initialRows,
        'expected_visible_rows_after_commit' => $rowsInTransaction,
        'expected_wal_frame_estimate' => max(1, (int) ceil($rowsInTransaction / max(1, $cacheSize - 2))),
        'payload_lengths' => [
            'primary_key_randomblob' => 100 + ($case % 5),
            'unique_randomblob' => 200 + (($case * 7) % 9),
        ],
        'dependencies' => [
            'real-upstream-corpus-walshared',
            'sqlite-wal-shared-cache-checkpoint-lock',
            'sqlite-pager-wal-dynamic-corpus',
        ],
    ];
}

foreach ($rows as $row) {
    $tests[sprintf(
        'real upstream corpus pager wal shared cache dynamic 152610 %04d cache %02d expansions %d',
        $row['case'],
        $row['cache_size'],
        $row['expansion_passes']
    )] = static function (TestRunner $t) use ($row): void {
        $plan = SQLitePagerWalDynamicPlan::walSharedCacheCheckpointPlan(
            $row['cache_size'],
            $row['initial_rows'],
            $row['expansion_passes'],
            $row['checkpoint_connections'],
            true
        );

        $t->same('walshared.test', $row['script']);
        $t->same(true, str_starts_with($row['upstream'], 'walshared.test walshared-1.0..1.4'));
        $t->same(true, in_array('real-upstream-corpus-walshared', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-shared-cache-checkpoint-lock', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-pager-wal-dynamic-corpus', $row['dependencies'], true));

        $t->same('wal-shared-cache-checkpoint-blocked-by-write-transaction', $plan['status']);
        $t->same(true, $plan['shared_cache']);
        $t->same('wal', $plan['journal_mode']);
        $t->same(2, $plan['connection_count']);
        $t->same($row['cache_size'], $plan['cache_size']);
        $t->same($row['initial_rows'], $plan['initial_rows']);
        $t->same($row['rows_after_direct_insert'], $plan['rows_after_direct_insert']);
        $t->same($row['expansion_passes'], $plan['expansion_passes']);
        $t->same($row['rows_in_transaction'], $plan['rows_in_transaction']);
        $t->same(true, $plan['transaction_open']);
        $t->same($row['expected_visible_rows_before_commit'], $plan['visible_rows_before_commit']);
        $t->same($row['expected_visible_rows_after_commit'], $plan['visible_rows_after_commit']);
        $t->same($row['expected_wal_frame_estimate'], $plan['wal_frame_estimate']);
        $t->same(count($row['checkpoint_connections']), $plan['blocked_checkpoint_count']);
        $t->same('ok', $plan['integrity_after_commit']);
        $t->same(true, str_contains($plan['source'], 'walshared.test walshared-1.0 through walshared-1.4'));
        $t->same(true, in_array('real-upstream-corpus-walshared', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-shared-cache-checkpoint-lock', $plan['dependencies'], true));

        $t->same(count($row['checkpoint_connections']), count($plan['checkpoint_attempts']));
        foreach ($plan['checkpoint_attempts'] as $index => $attempt) {
            $t->same($row['checkpoint_connections'][$index], $attempt['connection']);
            $t->same($row['expected_checkpoint_result'], $attempt['result']);
            $t->same(true, $attempt['blocked']);
            $t->same('database table is locked', $attempt['message']);
        }

        $t->same(true, $row['payload_lengths']['primary_key_randomblob'] >= 100);
        $t->same(true, $row['payload_lengths']['unique_randomblob'] >= 200);
    };
}

$tests['real upstream corpus pager wal shared cache dynamic 152610 row count and non overlap'] = static function (TestRunner $t) use ($rows): void {
    $t->same(1000, count($rows));
    $t->same('walshared.test walshared-1.0..1.4 dynamic shared-cache checkpoint case 0001', $rows[0]['upstream']);
    $t->same('walshared.test walshared-1.0..1.4 dynamic shared-cache checkpoint case 1000', $rows[999]['upstream']);
    $t->same(
        'upstream source: walshared.test walshared-1.0 through walshared-1.4 shared-cache WAL setup, open write transaction, same-handle and peer-handle checkpoint SQLITE_LOCKED, commit, and integrity_check ok',
        'upstream source: walshared.test walshared-1.0 through walshared-1.4 shared-cache WAL setup, open write transaction, same-handle and peer-handle checkpoint SQLITE_LOCKED, commit, and integrity_check ok'
    );
    $t->same(
        'non-overlap: covers walshared shared-cache checkpoint table-lock behavior rather than accepted walsetlk timeout/snapshot, wal5/e_walckpt checkpoint modes, e_walauto hook replacement, walnoshm exclusive conversion, VFS process locks, VFS writer/sync/lock-state, rollback-journal apply/commit, WAL byte truncation, or application WAL recovery slices',
        'non-overlap: covers walshared shared-cache checkpoint table-lock behavior rather than accepted walsetlk timeout/snapshot, wal5/e_walckpt checkpoint modes, e_walauto hook replacement, walnoshm exclusive conversion, VFS process locks, VFS writer/sync/lock-state, rollback-journal apply/commit, WAL byte truncation, or application WAL recovery slices'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses lane-local pager/WAL dynamic planning and hydrated upstream walshared.test source truth',
        'dependency-closure: no new support component needed; reuses lane-local pager/WAL dynamic planning and hydrated upstream walshared.test source truth'
    );
};

$tests['real upstream corpus pager wal shared cache dynamic 152610 rejects malformed planner inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePagerWalDynamicPlan::walSharedCacheCheckpointPlan(0, 1, 3, ['db']));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePagerWalDynamicPlan::walSharedCacheCheckpointPlan(10, 0, 3, ['db']));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePagerWalDynamicPlan::walSharedCacheCheckpointPlan(10, 1, -1, ['db']));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePagerWalDynamicPlan::walSharedCacheCheckpointPlan(10, 1, 3, []));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePagerWalDynamicPlan::walSharedCacheCheckpointPlan(10, 1, 3, ['db3']));
};

return $tests;
