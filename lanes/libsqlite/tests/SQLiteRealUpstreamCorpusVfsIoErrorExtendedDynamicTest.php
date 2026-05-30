<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTransactionSequencePlan;

$tests = [];

$scenarios = [
    [
        'name' => 'ioerr3-1 soft heap limit transaction cache write',
        'script' => 'ioerr3.test',
        'phase' => 'soft-heap-limit-transaction-cache',
        'operations' => ['read', 'write', 'sync', 'truncate', 'delete', 'access', 'open', 'close'],
        'read_context' => 'database',
        'write_context' => 'transaction',
    ],
    [
        'name' => 'ioerr3-2 soft heap limit temp table create',
        'script' => 'ioerr3.test',
        'phase' => 'soft-heap-limit-temp-schema',
        'operations' => ['read', 'write', 'sync', 'truncate', 'delete', 'access', 'open', 'close'],
        'write_context' => 'statement-journal',
    ],
    [
        'name' => 'ioerr4-2 shared-cache incremental vacuum',
        'script' => 'ioerr4.test',
        'phase' => 'shared-cache-incremental-vacuum',
        'operations' => ['read', 'write', 'sync', 'truncate', 'delete', 'access', 'open', 'close'],
        'write_context' => 'vacuum',
        'cksum' => true,
        'access_is_required' => true,
    ],
    [
        'name' => 'ioerr5-1 normal locking pager error reclaim',
        'script' => 'ioerr5.test',
        'phase' => 'memory-reclaim-error-state',
        'operations' => ['read', 'write', 'sync', 'truncate', 'delete', 'access', 'open', 'close'],
        'persistent' => true,
        'locking_mode' => 'normal',
    ],
    [
        'name' => 'ioerr5-1 exclusive locking pager error reclaim',
        'script' => 'ioerr5.test',
        'phase' => 'memory-reclaim-error-state',
        'operations' => ['read', 'write', 'sync', 'truncate', 'delete', 'access', 'open', 'close'],
        'persistent' => true,
        'locking_mode' => 'exclusive',
    ],
    [
        'name' => 'ioerr5-2 normal release memory before commit',
        'script' => 'ioerr5.test',
        'phase' => 'memory-release-before-commit',
        'operations' => ['read', 'write', 'sync', 'truncate', 'delete', 'access', 'open', 'close'],
        'persistent' => true,
        'locking_mode' => 'normal',
    ],
    [
        'name' => 'ioerr5-2 exclusive release memory before commit',
        'script' => 'ioerr5.test',
        'phase' => 'memory-release-before-commit',
        'operations' => ['read', 'write', 'sync', 'truncate', 'delete', 'access', 'open', 'close'],
        'persistent' => true,
        'locking_mode' => 'exclusive',
    ],
    [
        'name' => 'ioerr6-1 atomic write insert returns full',
        'script' => 'ioerr6.test',
        'phase' => 'atomic-write-full-fault',
        'operations' => ['write', 'sync', 'truncate', 'delete', 'access', 'open', 'close'],
        'full_on_write' => true,
        'write_context' => 'transaction',
        'device_flags' => ['atomic'],
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
    ],
    [
        'name' => 'ioerr4-1 incremental vacuum setup freelist is protected',
        'script' => 'ioerr4.test',
        'phase' => 'incremental-vacuum-freelist-setup',
        'operations' => ['read', 'write', 'sync', 'truncate', 'delete', 'access', 'open', 'close'],
        'write_context' => 'vacuum',
        'exclude' => [2, 5],
        'cksum' => true,
    ],
    [
        'name' => 'ioerr3 soft heap temp cursor closes cleanly',
        'script' => 'ioerr3.test',
        'phase' => 'soft-heap-temp-cursor-cleanup',
        'operations' => ['read', 'write', 'sync', 'truncate', 'delete', 'access', 'open', 'close'],
        'read_context' => 'record-header',
        'exclude' => [3],
    ],
];

$expectedRc = static function (array $scenario, string $operation, int $failpoint): string {
    if (in_array($failpoint, $scenario['exclude'] ?? [], true)) {
        return 'SQLITE_OK';
    }
    if ($operation === 'access' && !($scenario['access_is_required'] ?? false)) {
        return 'SQLITE_OK';
    }
    if ($operation === 'close') {
        return 'SQLITE_OK';
    }
    if (($scenario['persistent'] ?? false) === true) {
        return 'SQLITE_IOERR';
    }

    return match ($operation) {
        'sync' => 'SQLITE_IOERR_FSYNC',
        'write' => ($scenario['full_on_write'] ?? false) ? 'SQLITE_FULL' : 'SQLITE_IOERR_WRITE',
        'read' => 'SQLITE_IOERR_READ',
        'truncate' => 'SQLITE_IOERR_TRUNCATE',
        'delete' => 'SQLITE_IOERR_DELETE',
        'open' => 'SQLITE_CANTOPEN',
        default => 'SQLITE_IOERR',
    };
};

$expectedRecovery = static function (array $scenario, string $operation, int $failpoint): string {
    if (($scenario['phase'] ?? '') === 'memory-reclaim-error-state') {
        return 'do_not_spill_dirty_pages_from_error_state';
    }
    if (in_array($failpoint, $scenario['exclude'] ?? [], true)) {
        return 'ignored_fixture_probe';
    }
    if ($operation === 'access' && !($scenario['access_is_required'] ?? false)) {
        return 'optional_access_probe_ignored';
    }
    if ($operation === 'close') {
        return 'close_error_does_not_change_database_image';
    }
    if (($scenario['persistent'] ?? false) === true) {
        return 'pager_error_state_holds_dirty_pages';
    }
    if ($operation === 'sync') {
        return 'rollback_after_failed_sync';
    }
    if ($operation === 'write') {
        return match ($scenario['write_context'] ?? 'transaction') {
            'statement-journal' => 'play_statement_journal_then_rollback',
            'pointer-map' => 'rollback_pointer_map_update',
            'vacuum' => 'discard_vacuum_temp_database',
            'super-journal' => 'retain_super_journal_until_all_members_resolved',
            default => 'rollback_transaction_and_keep_original_pages',
        };
    }
    if ($operation === 'read') {
        return match ($scenario['read_context'] ?? 'database') {
            'hot-journal' => 'defer_hot_journal_replay_until_read_succeeds',
            'record-header' => 'abort_record_decode_without_cache_poisoning',
            'master-journal' => 'treat_master_journal_name_as_unreadable',
            default => 'abort_read_without_dirtying_cache',
        };
    }

    return match ($operation) {
        'truncate' => 'keep_original_database_size_until_retry',
        'delete' => 'keep_journal_until_delete_can_be_retried',
        'open' => 'abort_before_database_image_changes',
        default => 'rollback_and_preserve_database_image',
    };
};

$operationsByScript = [];
$caseOrdinal = 0;
foreach ($scenarios as $scenario) {
    foreach ($scenario['operations'] as $operation) {
        foreach (range(1, 12) as $failpoint) {
            $caseOrdinal++;
            $name = sprintf(
                'real upstream corpus vfs ioerr extended dynamic %04d %s operation %s failpoint %02d',
                $caseOrdinal,
                $scenario['name'],
                $operation,
                $failpoint
            );

            $tests[$name] = static function (TestRunner $t) use ($scenario, $operation, $failpoint, $expectedRc, $expectedRecovery): void {
                $plan = SQLiteVfsIoTransactionSequencePlan::ioErrorOutcome($scenario, $operation, $failpoint);

                $t->same('ok', $plan['status']);
                $t->same($scenario['script'], $plan['script']);
                $t->same($scenario['name'], $plan['scenario']);
                $t->same($operation, $plan['operation']);
                $t->same($failpoint, $plan['failpoint']);
                $t->same($scenario['phase'], $plan['phase']);
                $t->same($expectedRc($scenario, $operation, $failpoint), $plan['expected_rc']);
                $t->same($expectedRecovery($scenario, $operation, $failpoint), $plan['recovery_action']);
                $t->same(in_array($failpoint, $scenario['exclude'] ?? [], true), $plan['excluded']);
                $t->same(true, $plan['database_image_stable']);
                $t->same((bool) ($scenario['cksum'] ?? false), $plan['checksum_check']);
                $t->same(0, $plan['open_file_count']);
                $t->same(true, in_array('vfs-io-error-injection', $plan['dependencies'], true));
                $t->same(true, in_array('pager-error-state-recovery', $plan['dependencies'], true));
                $t->same(true, in_array('real-upstream-corpus-ioerr-test', $plan['dependencies'], true));
                $t->same([$scenario['script'] . ' ' . $scenario['name']], $plan['upstream']);
            };
        }
    }

    $operationsByScript[$scenario['script']] ??= [];
    $operationsByScript[$scenario['script']][] = $scenario['name'];
}

foreach ($operationsByScript as $script => $scenarioNames) {
    $tests['real upstream corpus vfs ioerr extended dynamic covers upstream script ' . $script] = static function (TestRunner $t) use ($script, $scenarioNames): void {
        $t->same(true, in_array($script, ['ioerr3.test', 'ioerr4.test', 'ioerr5.test', 'ioerr6.test'], true));
        $t->same($scenarioNames, array_values(array_unique($scenarioNames)));
        $t->same(true, count($scenarioNames) >= 2);
    };
}

$guardCases = [
    'rejects missing extended scenario name' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::ioErrorOutcome(['script' => 'ioerr4.test', 'phase' => 'shared-cache-incremental-vacuum'], 'write', 1),
    'rejects empty extended scenario script' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::ioErrorOutcome(['name' => 'ioerr4-2 shared-cache incremental vacuum', 'script' => ''], 'write', 1),
    'rejects unsupported extended operation' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::ioErrorOutcome(['name' => 'ioerr6 atomic full fault', 'script' => 'ioerr6.test'], 'rename', 1),
    'rejects zero extended failpoint' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::ioErrorOutcome(['name' => 'ioerr5 pager reclaim', 'script' => 'ioerr5.test'], 'write', 0),
    'rejects negative extended failpoint' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::ioErrorOutcome(['name' => 'ioerr3 soft heap', 'script' => 'ioerr3.test'], 'write', -2),
];

foreach ($guardCases as $name => $callable) {
    $tests['real upstream corpus vfs ioerr extended dynamic ' . $name] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, $callable);
}

return $tests;
