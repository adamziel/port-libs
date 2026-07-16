<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTrafficPlan;

$tests = [];

$faultCases = [
    'first xWrite failure retries with legacy journal' => [1, 'xWrite'],
    'second xWrite failure retries with legacy journal' => [2, 'xWrite'],
    'begin atomic file-control failure retries with legacy journal' => [3, 'xFileControl-BEGIN_ATOMIC_WRITE'],
    'post begin xWrite failure retries with legacy journal' => [4, 'xWrite'],
    'second post begin xWrite failure retries with legacy journal' => [5, 'xWrite'],
];

foreach ($faultCases as $name => [$failAt, $method]) {
    $tests['real upstream corpus vfs atomic2 ' . $name] = static function (TestRunner $t) use ($failAt, $method): void {
        $plan = SQLiteVfsIoTrafficPlan::atomicBatchWriteFallback($failAt);

        $t->same('atomic2.test', $plan['script']);
        $t->same('atomic2-2.0', $plan['scenario']);
        $t->same($failAt, $plan['fail_at']);
        $t->same($method, $plan['callback_method']);
        $t->same(false, $plan['commit_atomic_seen']);
        $t->same(true, $plan['io_error_injected']);
        $t->same(true, $plan['legacy_journal_fallback']);
        $t->same(false, $plan['atomic_commit_boundary']);
        $t->same(100, $plan['rows_before']);
        $t->same(100, $plan['rows_inserted']);
        $t->same(200, $plan['rows_after']);
        $t->same('ok', $plan['integrity_check']);
        $t->same(true, $plan['journal_created']);
        $t->same(false, $plan['rollback_required']);
        $t->same('atomic_batch_write_ioerr_retries_with_legacy_journal_commit', $plan['reason']);
        $t->same(true, in_array('sqlite-upstream-atomic2-test', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-vfs-atomic-batch-write-fallback', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-pager-io-traffic', $plan['dependencies'], true));
    };
}

$commitBoundaryCases = [
    'commit atomic file-control clears pending fault injection' => [6, 'xFileControl-COMMIT_ATOMIC_WRITE'],
    'fault after commit atomic boundary is not injected' => [7, 'xWrite'],
    'late fault index remains committed after atomic boundary' => [12, 'xFileControl-COMMIT_ATOMIC_WRITE'],
];

foreach ($commitBoundaryCases as $name => [$failAt, $method]) {
    $tests['real upstream corpus vfs atomic2 ' . $name] = static function (TestRunner $t) use ($failAt, $method): void {
        $plan = SQLiteVfsIoTrafficPlan::atomicBatchWriteFallback($failAt);

        $t->same('atomic2.test', $plan['script']);
        $t->same('atomic2-2.0', $plan['scenario']);
        $t->same($failAt, $plan['fail_at']);
        $t->same($method, $plan['callback_method']);
        $t->same(true, $plan['commit_atomic_seen']);
        $t->same(false, $plan['io_error_injected']);
        $t->same(false, $plan['legacy_journal_fallback']);
        $t->same(true, $plan['atomic_commit_boundary']);
        $t->same(100, $plan['rows_before']);
        $t->same(100, $plan['rows_inserted']);
        $t->same(200, $plan['rows_after']);
        $t->same('ok', $plan['integrity_check']);
        $t->same(false, $plan['journal_created']);
        $t->same(false, $plan['rollback_required']);
        $t->same('commit_atomic_write_boundary_suppresses_later_fault_injection', $plan['reason']);
        $t->same(true, in_array('sqlite-upstream-atomic2-test', $plan['dependencies'], true));
    };
}

for ($failAt = 1; $failAt <= 120; $failAt++) {
    $tests["real upstream corpus vfs atomic2 fault injection preserves count and integrity {$failAt}"] = static function (TestRunner $t) use ($failAt): void {
        $plan = SQLiteVfsIoTrafficPlan::atomicBatchWriteFallback($failAt, 100, 100);

        $t->same(200, $plan['rows_after']);
        $t->same('ok', $plan['integrity_check']);
        $t->same(false, $plan['rollback_required']);
        $t->same($failAt < 6, $plan['legacy_journal_fallback']);
        $t->same($failAt >= 6, $plan['atomic_commit_boundary']);
    };
}

$tests['real upstream corpus vfs atomic2 custom row counts preserve committed total'] = static function (TestRunner $t): void {
    $plan = SQLiteVfsIoTrafficPlan::atomicBatchWriteFallback(2, 75, 25);

    $t->same(75, $plan['rows_before']);
    $t->same(25, $plan['rows_inserted']);
    $t->same(100, $plan['rows_after']);
    $t->same(true, $plan['legacy_journal_fallback']);
    $t->same('ok', $plan['integrity_check']);
};

$tests['real upstream corpus vfs atomic2 rejects zero failure index'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::atomicBatchWriteFallback(0));
};

$tests['real upstream corpus vfs atomic2 rejects negative existing rows'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::atomicBatchWriteFallback(1, -1, 100));
};

$tests['real upstream corpus vfs atomic2 rejects empty inserted batch'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::atomicBatchWriteFallback(1, 100, 0));
};

return $tests;
