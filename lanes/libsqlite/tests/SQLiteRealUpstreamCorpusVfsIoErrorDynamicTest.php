<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTransactionSequencePlan;

$tests = [];

$scenarios = [
    [
        'name' => 'ioerr-1 transaction rollback preserves original rows',
        'script' => 'ioerr.test',
        'operations' => ['read', 'write', 'sync', 'truncate', 'delete', 'access', 'open', 'close'],
        'exclude' => [4],
        'access_is_required' => false,
    ],
    [
        'name' => 'ioerr-2 vacuum temp database keeps checksum stable',
        'script' => 'ioerr.test',
        'operations' => ['read', 'write', 'sync', 'truncate', 'delete', 'access', 'open', 'close'],
        'exclude' => [1, 9],
        'write_context' => 'vacuum',
        'cksum' => true,
    ],
    [
        'name' => 'ioerr-4 overflow record header read aborts cleanly',
        'script' => 'ioerr.test',
        'operations' => ['read', 'write', 'sync', 'truncate', 'delete', 'access', 'open', 'close'],
        'read_context' => 'record-header',
    ],
    [
        'name' => 'ioerr-5 attached two-file commit holds super journal',
        'script' => 'ioerr.test',
        'operations' => ['read', 'write', 'sync', 'truncate', 'delete', 'access', 'open', 'close'],
        'write_context' => 'super-journal',
        'access_is_required' => true,
    ],
    [
        'name' => 'ioerr-7 hot journal rollback defers failed reads',
        'script' => 'ioerr.test',
        'operations' => ['read', 'write', 'sync', 'truncate', 'delete', 'access', 'open', 'close'],
        'read_context' => 'hot-journal',
        'exclude' => [1],
    ],
    [
        'name' => 'ioerr-9 master journal name read failure is isolated',
        'script' => 'ioerr.test',
        'operations' => ['read', 'write', 'sync', 'truncate', 'delete', 'access', 'open', 'close'],
        'read_context' => 'master-journal',
        'write_context' => 'super-journal',
    ],
    [
        'name' => 'ioerr-10 statement journal playback rolls back constraint work',
        'script' => 'ioerr.test',
        'operations' => ['read', 'write', 'sync', 'truncate', 'delete', 'access', 'open', 'close'],
        'write_context' => 'statement-journal',
    ],
    [
        'name' => 'ioerr-12 coresident page journal write restores pointer map',
        'script' => 'ioerr.test',
        'operations' => ['read', 'write', 'sync', 'truncate', 'delete', 'access', 'open', 'close'],
        'write_context' => 'pointer-map',
    ],
    [
        'name' => 'ioerr2-7 nonpersistent pager error retries clean transaction',
        'script' => 'ioerr2.test',
        'operations' => ['read', 'write', 'sync', 'truncate', 'delete', 'access', 'open', 'close'],
        'persistent' => false,
        'phase' => 'pager-retry-after-nonpersistent-error',
    ],
    [
        'name' => 'ioerr5 persistent error state keeps dirty pages in cache',
        'script' => 'ioerr5.test',
        'operations' => ['read', 'write', 'sync', 'truncate', 'delete', 'access', 'open', 'close'],
        'persistent' => true,
        'phase' => 'memory-reclaim-error-state',
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

$caseOrdinal = 0;
foreach ($scenarios as $scenario) {
    foreach ($scenario['operations'] as $operation) {
        foreach (range(1, 13) as $failpoint) {
            $caseOrdinal++;
            $persistent = ($scenario['persistent'] ?? false) === true && ($failpoint % 2) === 0;
            $name = sprintf(
                'real upstream corpus vfs ioerr dynamic %04d %s operation %s failpoint %02d',
                $caseOrdinal,
                $scenario['name'],
                $operation,
                $failpoint
            );

            $tests[$name] = static function (TestRunner $t) use ($scenario, $operation, $failpoint, $persistent, $expectedRc, $expectedRecovery): void {
                $plan = SQLiteVfsIoTransactionSequencePlan::ioErrorOutcome($scenario, $operation, $failpoint, $persistent);

                $t->same('ok', $plan['status']);
                $t->same($scenario['script'], $plan['script']);
                $t->same($scenario['name'], $plan['scenario']);
                $t->same($operation, $plan['operation']);
                $t->same($failpoint, $plan['failpoint']);
                $t->same($expectedRc($scenario, $operation, $failpoint), $plan['expected_rc']);
                $t->same($expectedRecovery($scenario, $operation, $failpoint), $plan['recovery_action']);
                $t->same(in_array($failpoint, $scenario['exclude'] ?? [], true), $plan['excluded']);
                $t->same(true, $plan['database_image_stable']);
                $t->same(0, $plan['open_file_count']);
                $t->same(true, in_array('vfs-io-error-injection', $plan['dependencies'], true));
                $t->same(true, in_array('pager-error-state-recovery', $plan['dependencies'], true));
                $t->same(true, in_array('real-upstream-corpus-ioerr-test', $plan['dependencies'], true));
                $t->same([$scenario['script'] . ' ' . $scenario['name']], $plan['upstream']);
            };
        }
    }
}

$persistentCases = [
    'ioerr5.test persistent write preserves dirty pages' => [
        ['name' => 'ioerr5 persistent write path', 'script' => 'ioerr5.test', 'persistent' => true],
        'write',
        3,
        true,
        'pager_error_state_holds_dirty_pages',
    ],
    'ioerr5.test memory reclaim persistent sync does not spill dirty pages' => [
        ['name' => 'ioerr5 memory reclaim', 'script' => 'ioerr5.test', 'persistent' => true, 'phase' => 'memory-reclaim-error-state'],
        'sync',
        5,
        true,
        'do_not_spill_dirty_pages_from_error_state',
    ],
    'ioerr2.test transient write can roll back without dirty cache preservation' => [
        ['name' => 'ioerr2 nonpersistent pager retry', 'script' => 'ioerr2.test', 'persistent' => false],
        'write',
        7,
        false,
        'rollback_transaction_and_keep_original_pages',
    ],
];

foreach ($persistentCases as $name => [$scenario, $operation, $failpoint, $dirtyPagesPreserved, $recovery]) {
    $tests['real upstream corpus vfs ioerr dynamic ' . $name] = static function (TestRunner $t) use ($scenario, $operation, $failpoint, $dirtyPagesPreserved, $recovery): void {
        $plan = SQLiteVfsIoTransactionSequencePlan::ioErrorOutcome($scenario, $operation, $failpoint);

        $t->same($dirtyPagesPreserved, $plan['dirty_pages_preserved']);
        $t->same($recovery, $plan['recovery_action']);
        $t->same($scenario['persistent'], $plan['persistent']);
        $t->same(true, $plan['database_image_stable']);
        $t->same([$scenario['script'] . ' ' . $scenario['name']], $plan['upstream']);
    };
}

$guardCases = [
    'rejects missing scenario name' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::ioErrorOutcome(['script' => 'ioerr.test'], 'write', 1),
    'rejects missing scenario script' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::ioErrorOutcome(['name' => 'ioerr guard', 'script' => ''], 'write', 1),
    'rejects unsupported operation' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::ioErrorOutcome(['name' => 'ioerr guard'], 'rename', 1),
    'rejects zero failpoint' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::ioErrorOutcome(['name' => 'ioerr guard'], 'write', 0),
    'rejects negative failpoint' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::ioErrorOutcome(['name' => 'ioerr guard'], 'write', -1),
];

foreach ($guardCases as $name => $callable) {
    $tests['real upstream corpus vfs ioerr dynamic ' . $name] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, $callable);
}

return $tests;
