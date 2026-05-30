<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTransactionSequencePlan;

$tests = [];

$scenarios = [
    ['script' => 'ioerr.test', 'name' => 'ioerr-1', 'phase' => 'rollback-transaction', 'exclude' => [4], 'ckrefcount' => true],
    ['script' => 'ioerr.test', 'name' => 'ioerr-2', 'phase' => 'vacuum', 'exclude' => [1, 9], 'ckrefcount' => true, 'cksum' => true, 'write_context' => 'vacuum'],
    ['script' => 'ioerr.test', 'name' => 'ioerr-3', 'phase' => 'delete-update-commit', 'ckrefcount' => true],
    ['script' => 'ioerr.test', 'name' => 'ioerr-4', 'phase' => 'overflow-record-header', 'ckrefcount' => true, 'read_context' => 'record-header'],
    ['script' => 'ioerr.test', 'name' => 'ioerr-5', 'phase' => 'multi-file-commit', 'exclude' => [4, 17], 'ckrefcount' => true, 'write_context' => 'super-journal'],
    ['script' => 'ioerr.test', 'name' => 'ioerr-7', 'phase' => 'hot-journal-rollback', 'exclude' => [1], 'read_context' => 'hot-journal'],
    ['script' => 'ioerr.test', 'name' => 'ioerr-8', 'phase' => 'short-field-read', 'ckrefcount' => true, 'read_context' => 'record-header'],
    ['script' => 'ioerr.test', 'name' => 'ioerr-9', 'phase' => 'master-journal-name-read', 'read_context' => 'master-journal'],
    ['script' => 'ioerr.test', 'name' => 'ioerr-10', 'phase' => 'statement-playback', 'ckrefcount' => true, 'write_context' => 'statement-journal'],
    ['script' => 'ioerr.test', 'name' => 'ioerr-11', 'phase' => 'update-write', 'ckrefcount' => true],
    ['script' => 'ioerr.test', 'name' => 'ioerr-12', 'phase' => 'incremental-vacuum', 'ckrefcount' => true, 'write_context' => 'vacuum'],
    ['script' => 'ioerr.test', 'name' => 'ioerr-13', 'phase' => 'balance-quick-pointer-map', 'ckrefcount' => true, 'write_context' => 'pointer-map'],
    ['script' => 'ioerr.test', 'name' => 'ioerr-14', 'phase' => 'balance-deeper-pointer-map', 'ckrefcount' => true, 'write_context' => 'pointer-map'],
    ['script' => 'ioerr.test', 'name' => 'ioerr-15', 'phase' => 'index-delete-overflow-commit', 'ckrefcount' => true],
    ['script' => 'ioerr.test', 'name' => 'ioerr-16', 'phase' => 'incremental-vacuum-commit', 'ckrefcount' => true, 'write_context' => 'vacuum'],
    ['script' => 'ioerr5.test', 'name' => 'ioerr5-1', 'phase' => 'memory-reclaim-error-state', 'persistent' => true, 'ckrefcount' => true],
];

$operations = ['read', 'write', 'sync', 'truncate', 'delete', 'access', 'open', 'close'];
$failpoints = range(1, 8);

foreach ($scenarios as $scenarioIndex => $scenario) {
    foreach ($operations as $operation) {
        foreach ($failpoints as $failpoint) {
            $name = sprintf(
                'real upstream corpus vfs io error dynamic %s %s failpoint %02d',
                $scenario['name'],
                $operation,
                $failpoint
            );

            $tests[$name] = static function (TestRunner $t) use ($scenario, $scenarioIndex, $operation, $failpoint): void {
                $persistent = (($scenarioIndex + $failpoint) % 5) === 0;
                $plan = SQLiteVfsIoTransactionSequencePlan::ioErrorOutcome($scenario, $operation, $failpoint, $persistent);
                $excluded = in_array($failpoint, $scenario['exclude'] ?? [], true);
                $scenarioPersistent = $persistent || (bool) ($scenario['persistent'] ?? false);

                $t->same('ok', $plan['status']);
                $t->same($scenario['script'], $plan['script']);
                $t->same($scenario['name'], $plan['scenario']);
                $t->same($operation, $plan['operation']);
                $t->same($failpoint, $plan['failpoint']);
                $t->same((string) $scenario['phase'], $plan['phase']);
                $t->same($excluded, $plan['excluded']);
                $t->same($excluded ? 'upstream excludes this injected failpoint' : null, $plan['exclude_reason']);
                $t->same(0, $plan['open_file_count']);
                $t->same((bool) ($scenario['ckrefcount'] ?? true), $plan['refcount_check']);
                $t->same((bool) ($scenario['cksum'] ?? false), $plan['checksum_check']);
                $t->same(true, $plan['database_image_stable']);
                $t->same($scenarioPersistent, $plan['persistent']);
                $expectedDirtyPreserved = !$excluded
                    && (
                        $scenario['phase'] === 'memory-reclaim-error-state'
                        || (!in_array($operation, ['access', 'close'], true) && $scenarioPersistent)
                    );
                $t->same($expectedDirtyPreserved, $plan['dirty_pages_preserved']);
                $t->same(true, in_array('vfs-io-error-injection', $plan['dependencies'], true));
                $t->same(true, in_array('pager-error-state-recovery', $plan['dependencies'], true));
                $t->same(true, in_array('real-upstream-corpus-ioerr-test', $plan['dependencies'], true));
                $t->same([$scenario['script'] . ' ' . $scenario['name']], $plan['upstream']);

                if ($excluded) {
                    $t->same('SQLITE_OK', $plan['expected_rc']);
                    $t->same('ignored_fixture_probe', $plan['recovery_action']);
                } elseif ($operation === 'access') {
                    $t->same('SQLITE_OK', $plan['expected_rc']);
                    $t->same(
                        $scenario['phase'] === 'memory-reclaim-error-state' ? 'do_not_spill_dirty_pages_from_error_state' : 'optional_access_probe_ignored',
                        $plan['recovery_action']
                    );
                } elseif ($operation === 'close') {
                    $t->same('SQLITE_OK', $plan['expected_rc']);
                    $t->same(
                        $scenario['phase'] === 'memory-reclaim-error-state' ? 'do_not_spill_dirty_pages_from_error_state' : 'close_error_does_not_change_database_image',
                        $plan['recovery_action']
                    );
                } elseif ($scenario['phase'] === 'memory-reclaim-error-state') {
                    $t->same('SQLITE_IOERR', $plan['expected_rc']);
                    $t->same('do_not_spill_dirty_pages_from_error_state', $plan['recovery_action']);
                } elseif ($scenarioPersistent) {
                    $t->same('SQLITE_IOERR', $plan['expected_rc']);
                    $t->same('pager_error_state_holds_dirty_pages', $plan['recovery_action']);
                } elseif ($operation === 'sync') {
                    $t->same('SQLITE_IOERR_FSYNC', $plan['expected_rc']);
                    $t->same('rollback_after_failed_sync', $plan['recovery_action']);
                } elseif ($operation === 'write' && ($scenario['write_context'] ?? '') === 'pointer-map') {
                    $t->same('SQLITE_IOERR_WRITE', $plan['expected_rc']);
                    $t->same('rollback_pointer_map_update', $plan['recovery_action']);
                } elseif ($operation === 'read' && ($scenario['read_context'] ?? '') === 'record-header') {
                    $t->same('SQLITE_IOERR_READ', $plan['expected_rc']);
                    $t->same('abort_record_decode_without_cache_poisoning', $plan['recovery_action']);
                } else {
                    $t->same(true, in_array($plan['expected_rc'], ['SQLITE_OK', 'SQLITE_IOERR_READ', 'SQLITE_IOERR_WRITE', 'SQLITE_IOERR_TRUNCATE', 'SQLITE_IOERR_DELETE', 'SQLITE_CANTOPEN'], true));
                }
            };
        }
    }
}

$tests['real upstream corpus vfs io error dynamic rejects missing scenario name'] = static fn (TestRunner $t) => $t->throws(
    InvalidArgumentException::class,
    static fn () => SQLiteVfsIoTransactionSequencePlan::ioErrorOutcome([], 'read', 1)
);

$tests['real upstream corpus vfs io error dynamic rejects unknown operation'] = static fn (TestRunner $t) => $t->throws(
    InvalidArgumentException::class,
    static fn () => SQLiteVfsIoTransactionSequencePlan::ioErrorOutcome(['name' => 'ioerr-1'], 'seek', 1)
);

$tests['real upstream corpus vfs io error dynamic rejects nonpositive failpoint'] = static fn (TestRunner $t) => $t->throws(
    InvalidArgumentException::class,
    static fn () => SQLiteVfsIoTransactionSequencePlan::ioErrorOutcome(['name' => 'ioerr-1'], 'read', 0)
);

return $tests;
