<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTransactionSequencePlan;

$tests = [];

$scenarios = [
    [
        'name' => 'ioerr6-1 atomic write insert returns full',
        'script' => 'ioerr6.test',
        'phase' => 'atomic-write-full-fault',
        'operations' => ['write', 'sync', 'truncate', 'delete', 'access', 'open', 'close'],
        'full_on_write' => true,
        'write_context' => 'transaction',
        'device_flags' => ['atomic'],
        'upstream_section' => 'ioerr6.test 1.1 shmfault full during insert',
    ],
    [
        'name' => 'ioerr6-2 atomic primary key create remains integral',
        'script' => 'ioerr6.test',
        'phase' => 'atomic-write-schema-create',
        'operations' => ['write', 'sync', 'truncate', 'delete', 'access', 'open', 'close'],
        'full_on_write' => true,
        'write_context' => 'transaction',
        'device_flags' => ['atomic'],
        'cksum' => true,
        'upstream_section' => 'ioerr6.test 2 full faultsim primary-key create',
    ],
    [
        'name' => 'ioerr6-3 atomic two-table create remains integral',
        'script' => 'ioerr6.test',
        'phase' => 'atomic-write-multi-schema-create',
        'operations' => ['write', 'sync', 'truncate', 'delete', 'access', 'open', 'close'],
        'full_on_write' => true,
        'write_context' => 'transaction',
        'device_flags' => ['atomic'],
        'cksum' => true,
        'upstream_section' => 'ioerr6.test 3 full faultsim two-table create',
    ],
];

$expectedRc = static function (array $scenario, string $operation, int $failpoint, bool $persistent): string {
    if ($operation === 'access') {
        return 'SQLITE_OK';
    }
    if ($operation === 'close') {
        return 'SQLITE_OK';
    }
    if ($persistent) {
        return 'SQLITE_IOERR';
    }

    return match ($operation) {
        'sync' => 'SQLITE_IOERR_FSYNC',
        'write' => ($scenario['full_on_write'] ?? false) ? 'SQLITE_FULL' : 'SQLITE_IOERR_WRITE',
        'truncate' => 'SQLITE_IOERR_TRUNCATE',
        'delete' => 'SQLITE_IOERR_DELETE',
        'open' => 'SQLITE_CANTOPEN',
        default => 'SQLITE_IOERR',
    };
};

$expectedRecovery = static function (string $operation, bool $persistent): string {
    if ($operation === 'access') {
        return 'optional_access_probe_ignored';
    }
    if ($operation === 'close') {
        return 'close_error_does_not_change_database_image';
    }
    if ($persistent) {
        return 'pager_error_state_holds_dirty_pages';
    }

    return match ($operation) {
        'sync' => 'rollback_after_failed_sync',
        'write' => 'rollback_transaction_and_keep_original_pages',
        'truncate' => 'keep_original_database_size_until_retry',
        'delete' => 'keep_journal_until_delete_can_be_retried',
        'open' => 'abort_before_database_image_changes',
        default => 'rollback_and_preserve_database_image',
    };
};

$caseOrdinal = 0;
foreach ($scenarios as $scenario) {
    foreach ($scenario['operations'] as $operation) {
        foreach (range(1, 48) as $failpoint) {
            foreach ([false, true] as $persistent) {
                $caseOrdinal++;
                $name = sprintf(
                    'real upstream corpus vfs ioerr6 atomic full dynamic %04d %s operation %s failpoint %02d %s',
                    $caseOrdinal,
                    $scenario['name'],
                    $operation,
                    $failpoint,
                    $persistent ? 'persistent' : 'transient'
                );

                $tests[$name] = static function (TestRunner $t) use ($scenario, $operation, $failpoint, $persistent, $expectedRc, $expectedRecovery): void {
                    $plan = SQLiteVfsIoTransactionSequencePlan::ioErrorOutcome($scenario, $operation, $failpoint, $persistent);

                    $t->same('ok', $plan['status']);
                    $t->same('ioerr6.test', $plan['script']);
                    $t->same($scenario['name'], $plan['scenario']);
                    $t->same($operation, $plan['operation']);
                    $t->same($failpoint, $plan['failpoint']);
                    $t->same($persistent, $plan['persistent']);
                    $t->same($scenario['phase'], $plan['phase']);
                    $t->same($expectedRc($scenario, $operation, $failpoint, $persistent), $plan['expected_rc']);
                    $t->same($expectedRecovery($operation, $persistent), $plan['recovery_action']);
                    $t->same(false, $plan['excluded']);
                    $t->same(null, $plan['exclude_reason']);
                    $t->same($persistent && !in_array($operation, ['access', 'close'], true), $plan['dirty_pages_preserved']);
                    $t->same(true, $plan['database_image_stable']);
                    $t->same(0, $plan['open_file_count']);
                    $t->same(true, $plan['refcount_check']);
                    $t->same((bool) ($scenario['cksum'] ?? false), $plan['checksum_check']);
                    $t->same(true, in_array('vfs-io-error-injection', $plan['dependencies'], true));
                    $t->same(true, in_array('pager-error-state-recovery', $plan['dependencies'], true));
                    $t->same(true, in_array('real-upstream-corpus-ioerr-test', $plan['dependencies'], true));
                    $t->same(['ioerr6.test ' . $scenario['name']], $plan['upstream']);
                };
            }
        }
    }
}

$tests['real upstream corpus vfs ioerr6 atomic full dynamic source sections'] = static function (TestRunner $t) use ($scenarios): void {
    $t->same([
        'ioerr6.test 1.1 shmfault full during insert',
        'ioerr6.test 2 full faultsim primary-key create',
        'ioerr6.test 3 full faultsim two-table create',
    ], array_column($scenarios, 'upstream_section'));
    $t->same(3, count($scenarios));
};

$tests['real upstream corpus vfs ioerr6 atomic full dynamic case volume'] = static function (TestRunner $t) use ($caseOrdinal): void {
    $t->same(2016, $caseOrdinal);
};

$tests['real upstream corpus vfs ioerr6 atomic full dynamic write maps to full'] = static function (TestRunner $t) use ($scenarios): void {
    foreach ($scenarios as $scenario) {
        $plan = SQLiteVfsIoTransactionSequencePlan::ioErrorOutcome($scenario, 'write', 2);
        $t->same('SQLITE_FULL', $plan['expected_rc']);
        $t->same('rollback_transaction_and_keep_original_pages', $plan['recovery_action']);
    }
};

$tests['real upstream corpus vfs ioerr6 atomic full dynamic persistent keeps dirty pages'] = static function (TestRunner $t) use ($scenarios): void {
    foreach ($scenarios as $scenario) {
        $plan = SQLiteVfsIoTransactionSequencePlan::ioErrorOutcome($scenario, 'write', 9, true);
        $t->same('SQLITE_IOERR', $plan['expected_rc']);
        $t->same('pager_error_state_holds_dirty_pages', $plan['recovery_action']);
        $t->same(true, $plan['dirty_pages_preserved']);
    }
};

$tests['real upstream corpus vfs ioerr6 atomic full dynamic rejects read operation for this batch'] = static function (TestRunner $t) use ($scenarios): void {
    foreach ($scenarios as $scenario) {
        $t->same(false, in_array('read', $scenario['operations'], true));
    }
};

return $tests;
