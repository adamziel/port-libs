<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamPagerWalDynamicPlan;

$tests = [];

foreach (SQLiteRealUpstreamPagerWalDynamicPlan::wal2HeaderRecoveryCases() as $case) {
    $tests['real upstream pager wal dynamic wal2 header recovery ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $t->same('wal2.test', $case['source_file']);
        $t->true(str_starts_with($case['upstream'], 'wal2-1.'));
        $t->true($case['inserted_value'] >= 5);
        $t->true($case['count'] >= 5);
        $t->same($case['inserted_value'], $case['count']);
        $t->same(array_sum(range(1, $case['inserted_value'])), $case['sum']);
        $t->same([$case['count'], $case['sum']], $case['final_snapshot']);
        $t->true(is_int($case['wal_index_header_field']));
        $t->same($case['wal_index_header_field'] >= 0, $case['recovery_required']);
        $t->true(count($case['lock_sequence']) >= 4);
        $t->same($case['recovery_required'] ? 6 : 1, $case['exclusive_lock_count']);
        $t->same(1, $case['shared_lock_count']);
        $t->same('lock', $case['lock_sequence'][0]['op']);
        $t->same($case['recovery_required'] ? 0 : 4, $case['lock_sequence'][0]['slot']);
        $t->same('unlock', $case['lock_sequence'][count($case['lock_sequence']) - 1]['op']);
        $t->same('shared', $case['lock_sequence'][count($case['lock_sequence']) - 1]['level']);
        $t->same(true, $case['lock_sequence'] === array_values($case['lock_sequence']));
    };
}

foreach (SQLiteRealUpstreamPagerWalDynamicPlan::wal2OutOfDateHeaderCases() as $case) {
    $tests['real upstream pager wal dynamic wal2 stale header ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $t->same('wal2.test', $case['source_file']);
        $t->true(str_starts_with($case['upstream'], 'wal2-2.'));
        $t->true($case['inserted_value'] >= 5);
        $t->same($case['inserted_value'], $case['fresh_snapshot'][0]);
        $t->same($case['inserted_value'] - 1, $case['stale_snapshot'][0]);
        $t->same(array_sum(range(1, $case['inserted_value'])), $case['fresh_snapshot'][1]);
        $t->same(array_sum(range(1, $case['inserted_value'] - 1)), $case['stale_snapshot'][1]);
        $t->same(false, $case['first_read_runs_recovery']);
        $t->same(true, $case['second_read_runs_recovery']);
        $t->true($case['wal_index_header_field'] >= 0);
        $t->same(6, count($case['lock_sequence']));
        $t->same('exclusive', $case['lock_sequence'][0]['level']);
        $t->same('shared', $case['lock_sequence'][4]['level']);
        $t->same('unlock', $case['lock_sequence'][5]['op']);
        $t->same(true, $case['lock_sequence'] === array_values($case['lock_sequence']));
    };
}

foreach (SQLiteRealUpstreamPagerWalDynamicPlan::pager1LockTransitionCases() as $case) {
    $tests['real upstream pager wal dynamic pager1 lock transition ' . $case['upstream'] . ' ' . $case['action']] = static function (TestRunner $t) use ($case): void {
        $t->true(str_starts_with($case['upstream'], 'pager1-'));
        $t->true(in_array($case['connection'], ['writer', 'reader'], true));
        $t->true($case['action'] !== '');
        $t->true(in_array($case['writer_lock'], ['unlocked', 'reserved', 'pending'], true));
        $t->true(in_array($case['reader_lock'], ['unlocked', 'shared', 'transaction-open'], true));
        $t->true(in_array($case['observer_lock'], ['unlocked', 'blocked'], true));
        $t->true($case['writer_rows'] === null || count($case['writer_rows']) >= 2);
        $t->true($case['reader_rows'] === null || count($case['reader_rows']) >= 2);
        $t->true($case['observer_rows'] === null || count($case['observer_rows']) >= 2);
        $t->same($case['writer_lock'] === 'pending', $case['observer_lock'] === 'blocked');
        $t->same($case['error'] === 'database is locked', $case['action'] === 'write-while-writer-reserved' || $case['action'] === 'autocommit-write-blocked-by-shared-reader' || $case['action'] === 'commit-blocked-upgrades-to-pending' || $case['action'] === 'reader-commit-while-writer-pending');
        if ($case['writer_rows'] !== null) {
            $t->same('one', $case['writer_rows'][0][1]);
            $t->same('two', $case['writer_rows'][1][1]);
        }
        if ($case['reader_rows'] !== null && $case['writer_lock'] === 'reserved') {
            $t->same(1, $case['reader_rows'][0][0]);
        }
        if ($case['action'] === 'pending-writer-final-commit') {
            $t->same($case['writer_rows'], $case['reader_rows']);
            $t->same($case['reader_rows'], $case['observer_rows']);
        }
        $t->same(true, $case['writer_rows'] === null || $case['writer_rows'] === array_values($case['writer_rows']));
    };
}

foreach (SQLiteRealUpstreamPagerWalDynamicPlan::walRestartCheckpointRaceCases() as $case) {
    $tests['real upstream pager wal dynamic walrestart checkpoint race ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $t->true(str_starts_with($case['upstream'], 'walrestart-1.'));
        $t->true($case['phase'] !== '');
        $t->same(3, count($case['checkpoint']));
        $t->same(0, $case['checkpoint'][0]);
        $t->true($case['checkpoint'][1] >= $case['checkpoint'][2]);
        $t->true(in_array($case['writer'], ['db', 'db2'], true));
        $t->true($case['concurrent_writer'] === null || $case['concurrent_writer'] === 'db2');
        $t->true($case['rows_touched'] >= 0);
        $t->same('ok', $case['integrity']);
        $t->same($case['phase'] === 'mxframe-before-backfill-race', $case['concurrent_writer'] === 'db2');
        if ($case['phase'] === 'mxframe-before-backfill-race') {
            $t->same([0, 45, 0], $case['checkpoint']);
        }
        if ($case['phase'] === 'initial-populate-checkpoint') {
            $t->same([0, 49, 49], $case['checkpoint']);
        }
        $t->same(true, $case['checkpoint'] === array_values($case['checkpoint']));
    };
}

return $tests;
