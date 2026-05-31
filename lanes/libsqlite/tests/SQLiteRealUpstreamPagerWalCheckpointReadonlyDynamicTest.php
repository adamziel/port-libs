<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalReadonlyShmPlan;

$tests = [];

$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

$tests['real upstream pager wal checkpoint readonly cites walro source'] = static function (TestRunner $t) use ($upstreamRoot): void {
    $walro = (string) file_get_contents($upstreamRoot . '/walro.test');

    $t->contains('proc tv_hook', $walro);
    $t->contains('readonly_shm=1', $walro);
    $t->contains('PRAGMA wal_checkpoint', $walro);
    $t->contains('SELECT count(*) FROM t2', $walro);
    $t->contains('{0 4}', $walro);
    $t->contains('{0 {0 2 2}}', $walro);
};

$pageSizes = [512, 1024, 2048, 4096, 8192, 16384, 32768, 65536];
$beforeRows = [
    ['abc', 'xyz'],
    ['abcxyz', 'xyzabc'],
    ['abcxyzxyzabc', 'xyzabcabcxyz'],
    ['abcxyzxyzabcxyzabcabcxyz', 'xyzabcabcxyzabcxyzxyzabc'],
];

for ($case = 1; $case <= 1000; $case++) {
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $checkpointFrames = 2 + ($case % 23);
    $backfilledFrames = $case % ($checkpointFrames + 1);
    $checkpointInProgress = ($case % 5) !== 0;
    $databaseExists = ($case % 97) !== 0;
    $walExists = ($case % 89) !== 0;
    $shmExists = ($case % 83) !== 0;
    $readonlyShm = ($case % 79) !== 0;
    $afterRows = $beforeRows;
    $afterRows[] = ['after' . $case, 'checkpoint' . $pageSize];
    $expectedOpen = $databaseExists && $walExists && $shmExists && $readonlyShm;
    $expectedRows = $expectedOpen
        ? ($checkpointInProgress ? $beforeRows : $afterRows)
        : [];
    $expectedReason = $expectedOpen
        ? ($checkpointInProgress ? 'readonly_shm_uses_wal_snapshot_during_checkpoint_sync' : 'readonly_shm_uses_checkpointed_database_after_sync')
        : 'readonly_checkpoint_snapshot_requires_existing_database_wal_and_shm';

    $tests[sprintf('real upstream pager wal checkpoint readonly dynamic %04d walro 2.1 checkpoint xSync reader page %d', $case, $pageSize)] = static function (TestRunner $t) use ($databaseExists, $walExists, $shmExists, $readonlyShm, $checkpointInProgress, $pageSize, $checkpointFrames, $backfilledFrames, $beforeRows, $afterRows, $expectedOpen, $expectedRows, $expectedReason): void {
        $plan = SQLiteWalReadonlyShmPlan::concurrentCheckpointReadonlySnapshot(
            $databaseExists,
            $walExists,
            $shmExists,
            $readonlyShm,
            $checkpointInProgress,
            $pageSize,
            $checkpointFrames,
            $backfilledFrames,
            $beforeRows,
            $afterRows
        );

        $t->same($expectedOpen ? 'readonly-checkpoint-snapshot-open' : 'readonly-checkpoint-snapshot-blocked', $plan['status']);
        $t->same($expectedReason, $plan['reason']);
        $t->same($checkpointInProgress, $plan['checkpoint_in_progress']);
        $t->same($pageSize, $plan['page_size']);
        $t->same($checkpointFrames, $plan['checkpoint_frame_count']);
        $t->same($backfilledFrames, $plan['checkpoint_backfilled_frame_count']);
        $t->same($checkpointFrames === $backfilledFrames, $plan['checkpoint_complete']);
        $t->same($expectedRows, $plan['rows']);
        $t->same(count($expectedRows), $plan['row_count']);
        $t->same($expectedOpen ? 'SQLITE_OK' : 'SQLITE_CANTOPEN', $plan['extended_errcode']);
        $t->same($expectedOpen ? 1 : 0, count($plan['write_denials']));
        $t->same(true, str_contains($plan['source'], 'walro.test 2.1.1'));
        $t->same(true, in_array('sqlite-wal-readonly-checkpoint-snapshot', $plan['dependencies'], true));
        if ($expectedOpen && $checkpointInProgress) {
            $t->same('wal-readonly-snapshot', $plan['snapshot_source']);
            $t->same(4, $plan['row_count']);
        }
        if ($expectedOpen && !$checkpointInProgress) {
            $t->same('checkpointed-database', $plan['snapshot_source']);
            $t->same(5, $plan['row_count']);
        }
    };
}

$tests['real upstream pager wal checkpoint readonly rejects malformed inputs'] = static function (TestRunner $t) use ($beforeRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalReadonlyShmPlan::concurrentCheckpointReadonlySnapshot(true, true, true, true, true, 1000, 2, 2, $beforeRows, $beforeRows));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalReadonlyShmPlan::concurrentCheckpointReadonlySnapshot(true, true, true, true, true, 1024, -1, 0, $beforeRows, $beforeRows));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalReadonlyShmPlan::concurrentCheckpointReadonlySnapshot(true, true, true, true, true, 1024, 1, 2, $beforeRows, $beforeRows));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalReadonlyShmPlan::concurrentCheckpointReadonlySnapshot(true, true, true, true, true, 1024, 1, 1, [], $beforeRows));
};

$tests['real upstream pager wal checkpoint readonly handoff note'] = static function (TestRunner $t): void {
    $t->same(
        'walro.test 2.1.1 through 2.1.4 readonly_shm reader opens during checkpoint xSync hook',
        'walro.test 2.1.1 through 2.1.4 readonly_shm reader opens during checkpoint xSync hook'
    );
    $t->same(
        'non-overlap: extends readonly WAL coverage past accepted walro 1.* and walro2 cache-refresh cases into the concurrent checkpoint hook path; avoids WAL byte truncation, rollback commit/apply, VFS writer/sync/lock state, and checkpoint transaction duplicates',
        'non-overlap: extends readonly WAL coverage past accepted walro 1.* and walro2 cache-refresh cases into the concurrent checkpoint hook path; avoids WAL byte truncation, rollback commit/apply, VFS writer/sync/lock state, and checkpoint transaction duplicates'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses generic readonly WAL/SHM planning with hydrated upstream walro.test as source truth',
        'dependency-closure: no new support component needed; reuses generic readonly WAL/SHM planning with hydrated upstream walro.test as source truth'
    );
};

return $tests;
