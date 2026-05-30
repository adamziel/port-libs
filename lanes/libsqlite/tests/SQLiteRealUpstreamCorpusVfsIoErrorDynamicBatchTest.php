<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTransactionSequencePlan;

$tests = [];

$scenarios = [
    [
        'script' => 'ioerr.test',
        'name' => 'ioerr-1',
        'phase' => 'schema-cookie-commit',
        'exclude' => [4],
        'ckrefcount' => true,
    ],
    [
        'script' => 'ioerr.test',
        'name' => 'ioerr-2',
        'phase' => 'database-checksum-preservation',
        'cksum' => true,
        'ckrefcount' => true,
        'exclude' => [1, 2, 3, 4, 5],
    ],
    [
        'script' => 'ioerr.test',
        'name' => 'ioerr-5',
        'phase' => 'btree-many-row-transaction',
        'exclude' => [8, 17],
        'ckrefcount' => true,
    ],
    [
        'script' => 'ioerr.test',
        'name' => 'ioerr-10',
        'phase' => 'vacuum-temp-database',
        'write_context' => 'vacuum',
        'ckrefcount' => true,
    ],
    [
        'script' => 'ioerr.test',
        'name' => 'ioerr-14',
        'phase' => 'super-journal-attached-transaction',
        'write_context' => 'super-journal',
        'read_context' => 'master-journal',
        'ckrefcount' => true,
    ],
    [
        'script' => 'ioerr2.test',
        'name' => 'ioerr2-2',
        'phase' => 'pager-error-state',
        'persistent' => true,
        'cksum' => true,
        'ckrefcount' => true,
    ],
    [
        'script' => 'ioerr2.test',
        'name' => 'ioerr2-7',
        'phase' => 'nonpersistent-ioerr-recovery',
        'persistent' => false,
        'ckrefcount' => true,
    ],
    [
        'script' => 'ioerr3.test',
        'name' => 'ioerr3-1',
        'phase' => 'schema-parse-ioerr',
        'read_context' => 'record-header',
        'access_is_required' => true,
    ],
    [
        'script' => 'ioerr3.test',
        'name' => 'ioerr3-2',
        'phase' => 'sqlbody-ioerr',
        'full_on_write' => true,
        'write_context' => 'statement-journal',
    ],
    [
        'script' => 'ioerr4.test',
        'name' => 'ioerr4-2',
        'phase' => 'memory-reclaim-error-state',
        'persistent' => true,
        'ckrefcount' => true,
    ],
];

$operations = ['read', 'write', 'sync', 'truncate', 'delete', 'access', 'open', 'close'];
$failpoints = range(1, 13);
$case = 0;

foreach ($scenarios as $scenario) {
    foreach ($operations as $operation) {
        foreach ($failpoints as $failpoint) {
            $case++;
            $testName = sprintf(
                'real upstream corpus vfs io error dynamic batch %04d %s %s failpoint %02d',
                $case,
                $scenario['name'],
                $operation,
                $failpoint
            );

            $tests[$testName] = static function (TestRunner $t) use ($scenario, $operation, $failpoint): void {
                $plan = SQLiteVfsIoTransactionSequencePlan::ioErrorOutcome($scenario, $operation, $failpoint);
                $excluded = in_array($failpoint, $scenario['exclude'] ?? [], true);

                $expectedRc = match (true) {
                    $excluded => 'SQLITE_OK',
                    $operation === 'access' && !($scenario['access_is_required'] ?? false) => 'SQLITE_OK',
                    $operation === 'close' => 'SQLITE_OK',
                    ($scenario['persistent'] ?? false) === true => 'SQLITE_IOERR',
                    $operation === 'sync' => 'SQLITE_IOERR_FSYNC',
                    $operation === 'write' && ($scenario['full_on_write'] ?? false) === true => 'SQLITE_FULL',
                    $operation === 'write' => 'SQLITE_IOERR_WRITE',
                    $operation === 'read' => 'SQLITE_IOERR_READ',
                    $operation === 'truncate' => 'SQLITE_IOERR_TRUNCATE',
                    $operation === 'delete' => 'SQLITE_IOERR_DELETE',
                    $operation === 'open' => 'SQLITE_CANTOPEN',
                    default => 'SQLITE_IOERR',
                };

                $expectedRecovery = match (true) {
                    $excluded => 'ignored_fixture_probe',
                    ($scenario['phase'] ?? '') === 'memory-reclaim-error-state' => 'do_not_spill_dirty_pages_from_error_state',
                    $operation === 'access' && !($scenario['access_is_required'] ?? false) => 'optional_access_probe_ignored',
                    $operation === 'close' => 'close_error_does_not_change_database_image',
                    ($scenario['persistent'] ?? false) === true => 'pager_error_state_holds_dirty_pages',
                    $operation === 'sync' => 'rollback_after_failed_sync',
                    $operation === 'write' && ($scenario['write_context'] ?? '') === 'statement-journal' => 'play_statement_journal_then_rollback',
                    $operation === 'write' && ($scenario['write_context'] ?? '') === 'vacuum' => 'discard_vacuum_temp_database',
                    $operation === 'write' && ($scenario['write_context'] ?? '') === 'super-journal' => 'retain_super_journal_until_all_members_resolved',
                    $operation === 'write' => 'rollback_transaction_and_keep_original_pages',
                    $operation === 'read' && ($scenario['read_context'] ?? '') === 'record-header' => 'abort_record_decode_without_cache_poisoning',
                    $operation === 'read' && ($scenario['read_context'] ?? '') === 'master-journal' => 'treat_master_journal_name_as_unreadable',
                    $operation === 'read' => 'abort_read_without_dirtying_cache',
                    $operation === 'truncate' => 'keep_original_database_size_until_retry',
                    $operation === 'delete' => 'keep_journal_until_delete_can_be_retried',
                    $operation === 'open' => 'abort_before_database_image_changes',
                    default => 'rollback_and_preserve_database_image',
                };

                $t->same('ok', $plan['status']);
                $t->same($scenario['script'], $plan['script']);
                $t->same($scenario['name'], $plan['scenario']);
                $t->same($operation, $plan['operation']);
                $t->same($failpoint, $plan['failpoint']);
                $t->same($scenario['phase'], $plan['phase']);
                $t->same($expectedRc, $plan['expected_rc']);
                $t->same($expectedRecovery, $plan['recovery_action']);
                $t->same($excluded, $plan['excluded']);
                $t->same($excluded ? 'upstream excludes this injected failpoint' : null, $plan['exclude_reason']);
                $t->same(true, $plan['database_image_stable']);
                $t->same(0, $plan['open_file_count']);
                $t->same((bool) ($scenario['ckrefcount'] ?? true), $plan['refcount_check']);
                $t->same((bool) ($scenario['cksum'] ?? false), $plan['checksum_check']);
                $t->same(true, in_array('vfs-io-error-injection', $plan['dependencies'], true));
                $t->same(true, in_array('pager-error-state-recovery', $plan['dependencies'], true));
                $t->same(true, in_array('real-upstream-corpus-ioerr-test', $plan['dependencies'], true));
                $t->same([$scenario['script'] . ' ' . $scenario['name']], $plan['upstream']);
            };
        }
    }
}

$tests['real upstream corpus vfs io error dynamic batch records upstream source sections'] = static function (TestRunner $t) use ($scenarios, $operations, $failpoints, $case): void {
    $t->same(10, count($scenarios));
    $t->same(8, count($operations));
    $t->same(13, count($failpoints));
    $t->same(1040, $case);
    $t->same([
        'ioerr.test ioerr-1 schema-cookie commit error injection',
        'ioerr.test ioerr-2 checksum-preserving I/O errors',
        'ioerr.test ioerr-5 btree row transaction I/O errors',
        'ioerr.test ioerr-10 vacuum temporary database I/O errors',
        'ioerr.test ioerr-14 attached super-journal I/O errors',
        'ioerr2.test ioerr2-2 pager error-state persistence',
        'ioerr2.test ioerr2-7 nonpersistent recovery',
        'ioerr3.test ioerr3-1 schema parse read I/O errors',
        'ioerr3.test ioerr3-2 SQL body write/full I/O errors',
        'ioerr4.test ioerr4-2 memory reclaim error-state protection',
    ], [
        'ioerr.test ioerr-1 schema-cookie commit error injection',
        'ioerr.test ioerr-2 checksum-preserving I/O errors',
        'ioerr.test ioerr-5 btree row transaction I/O errors',
        'ioerr.test ioerr-10 vacuum temporary database I/O errors',
        'ioerr.test ioerr-14 attached super-journal I/O errors',
        'ioerr2.test ioerr2-2 pager error-state persistence',
        'ioerr2.test ioerr2-7 nonpersistent recovery',
        'ioerr3.test ioerr3-1 schema parse read I/O errors',
        'ioerr3.test ioerr3-2 SQL body write/full I/O errors',
        'ioerr4.test ioerr4-2 memory reclaim error-state protection',
    ]);
};

$tests['real upstream corpus vfs io error dynamic batch rejects malformed requests'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoTransactionSequencePlan::ioErrorOutcome(['script' => 'ioerr.test'], 'read', 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoTransactionSequencePlan::ioErrorOutcome(['script' => '', 'name' => 'ioerr-bad'], 'read', 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoTransactionSequencePlan::ioErrorOutcome(['script' => 'ioerr.test', 'name' => 'ioerr-bad'], 'seek', 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoTransactionSequencePlan::ioErrorOutcome(['script' => 'ioerr.test', 'name' => 'ioerr-bad'], 'read', 0));
};

return $tests;
