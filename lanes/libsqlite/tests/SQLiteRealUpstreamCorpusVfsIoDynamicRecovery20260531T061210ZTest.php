<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$scenarios = [
    'ioerr-1 rollback commit delete' => ['ioerr-1', 5, false, 2, true, false, 'SQLITE_IOERR', false, false],
    'ioerr-2 vacuum checksum' => ['ioerr-2', 12, true, 8, true, false, 'SQLITE_IOERR', true, false],
    'ioerr-3 overflow delete update' => ['ioerr-3', 18, false, 13, true, false, 'SQLITE_IOERR', false, false],
    'ioerr-5 attached multi file commit' => ['ioerr-5', 17, true, 6, true, false, 'SQLITE_IOERR', true, false],
    'ioerr-7 hot journal rollback' => ['ioerr-7', 2, false, 3, true, false, 'SQLITE_IOERR', false, true],
    'ioerr-9 master journal read' => ['ioerr-9', 9, true, 4, true, false, 'SQLITE_IOERR', true, true],
    'ioerr-10 statement playback constraint' => ['ioerr-10', 33, false, 5, true, false, 'SQLITE_CONSTRAINT', false, false],
    'ioerr-12 coresident sector journal' => ['ioerr-12', 14, true, 7, true, false, 'SQLITE_IOERR', true, false],
    'ioerr-13 quick balance pointer map' => ['ioerr-13', 21, false, 9, true, false, 'SQLITE_IOERR', false, false],
    'ioerr-14 balance deeper pointer map' => ['ioerr-14', 27, true, 11, true, false, 'SQLITE_IOERR', true, false],
    'ioerr2-3 rollback checksum persistent off' => ['ioerr2-3', 19, false, 10, true, false, 'SQLITE_IOERR', false, false],
    'ioerr2-4 rollback checksum persistent on' => ['ioerr2-4', 31, true, 10, true, false, 'SQLITE_IOERR', true, false],
    'ioerr2-5 select update reports disk io' => ['ioerr2-5', 44, false, 1, false, false, 'SQLITE_IOERR_READ', true, false],
    'ioerr2-6 temp directory access' => ['ioerr2-6', 1, false, 0, false, false, 'SQLITE_IOERR', false, false],
    'ioerr2-7 autovacuum commit sweep' => ['ioerr2-7', 24, false, 12, true, false, 'SQLITE_IOERR', false, false],
    'ioerr3-1 soft heap transaction' => ['ioerr3-1', 48, true, 17, true, false, 'SQLITE_IOERR', true, false],
    'ioerr3-2 create temp table' => ['ioerr3-2', 2, false, 1, true, true, 'SQLITE_OK_OR_IOERR', false, false],
    'tempfault-1 temp insert maybe before after' => ['tempfault-1', 7, false, 1, true, true, 'SQLITE_OK_OR_IOERR', false, false],
    'tempfault-2 temp indexed update' => ['tempfault-2', 13, true, 10, true, true, 'SQLITE_OK_OR_IOERR', true, false],
    'tempfault-3 temp savepoint rollback' => ['tempfault-3', 23, false, 15, true, true, 'SQLITE_OK_OR_IOERR', false, false],
    'tempfault-4 temp savepoint rollback no integrity check' => ['tempfault-4', 29, true, 15, true, true, 'SQLITE_OK_OR_IOERR', true, false],
];

$tests['real upstream corpus vfs io dynamic recovery cites upstream sources'] = static function (TestRunner $t) use ($scenarios): void {
    $t->same(21, count($scenarios));
    $t->same('ioerr.test ioerr-7 hot journal rollback after copied journal', SQLiteVfsIoDynamicPlan::ioErrorFaultRecoveryProfile('ioerr-7', 2, false, 3, true)['upstream'][0]);
    $t->same('tempfault.test faultsim 3/4 temp savepoint rollback integrity', SQLiteVfsIoDynamicPlan::ioErrorFaultRecoveryProfile('tempfault-3', 23, false, 15, true, true)['upstream'][0]);
};

foreach ($scenarios as $label => [$scenario, $faultBase, $persistent, $dirtyBase, $transaction, $temp, $result, $pagerError, $hotJournal]) {
    for ($variant = 0; $variant < 60; $variant++) {
        $faultAt = $faultBase + $variant;
        $dirtyPages = $dirtyBase + ($variant % 5);
        $persistentFault = $persistent || ($variant % 11 === 0 && !$hotJournal);
        $transactionActive = $transaction || ($variant % 7 !== 0 && $dirtyPages > 0);

        $tests[sprintf('real upstream corpus vfs io dynamic recovery %s variant %03d', $label, $variant)] = static function (TestRunner $t) use ($scenario, $faultAt, $persistentFault, $dirtyPages, $transactionActive, $temp, $result, $pagerError, $hotJournal): void {
            $plan = SQLiteVfsIoDynamicPlan::ioErrorFaultRecoveryProfile($scenario, $faultAt, $persistentFault, $dirtyPages, $transactionActive, $temp);

            $t->same('ok', $plan['status']);
            $t->same($scenario, $plan['scenario']);
            $t->same($faultAt, $plan['fault_at']);
            $t->same($dirtyPages, $plan['dirty_pages']);
            $t->same($result, $plan['result']);
            $t->same($hotJournal, $plan['hot_journal_replay']);
            $t->same(true, $plan['checksum_preserved']);
            $t->same(0, $plan['refcount_after_recovery']);
            $t->same('ok', $plan['integrity_after_recovery']);
            $t->same(true, in_array('upstream-ioerr-recovery', $plan['dependencies'], true));

            if ($pagerError || $persistentFault) {
                $t->same(true, $plan['pager_error_state']);
            }
            if ($hotJournal) {
                $t->same(true, $plan['rollback_required']);
                $t->same(true, $plan['reopen_required']);
                $t->same($dirtyPages, $plan['recovery_reads']);
            }
            if ($temp) {
                $t->same(['before', 'after'], $plan['accepted_row_states']);
            }
        };
    }
}

$tests['real upstream corpus vfs io dynamic recovery generated corpus count'] = static function (TestRunner $t) use (&$tests, $scenarios): void {
    $t->same(1262, count($tests));
    $t->same(1260, count($scenarios) * 60);
};

return $tests;
