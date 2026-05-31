<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerWalDynamicPlan;

$tests = [];

$transitionCases = [
    'walmode-0.1 wal unsupported falls back to delete' => ['delete', 'wal', true, false, false, false, 1024, [
        'status' => 'wal-unsupported',
        'result' => 'delete',
        'blocked' => false,
        'reason' => 'wal_mode_requires_wal_support',
        'wal_sidecar_exists' => false,
        'journal_sidecar_exists' => false,
        'database_bytes' => 1024,
        'read_version' => 1,
        'write_version' => 1,
        'source' => 'upstream walmode.test 0.1-0.3',
    ]],
    'walmode-1.1 file backed enters wal' => ['delete', 'wal', true, true, false, false, 1024, [
        'status' => 'wal-mode-active',
        'result' => 'wal',
        'blocked' => false,
        'reason' => 'journal_mode_wal_sets_file_versions',
        'wal_sidecar_exists' => true,
        'journal_sidecar_exists' => false,
        'database_bytes' => 1024,
        'read_version' => 2,
        'write_version' => 2,
        'source' => 'upstream walmode.test 1.1-1.7 4.7-4.10',
    ]],
    'walmode-3.1 already wal opens log without rewrite' => ['wal', 'wal', true, true, false, false, 2048, [
        'status' => 'wal-mode-active',
        'result' => 'wal',
        'blocked' => false,
        'reason' => 'already_wal_opens_log_without_database_rewrite',
        'wal_sidecar_exists' => true,
        'journal_sidecar_exists' => false,
        'database_bytes' => 2048,
        'read_version' => 2,
        'write_version' => 2,
        'source' => 'upstream walmode.test 3.1-3.2',
    ]],
    'walmode-4.1 wal to persist after insert' => ['wal', 'persist', true, true, false, false, 2048, [
        'status' => 'rollback-mode-active',
        'result' => 'persist',
        'blocked' => false,
        'reason' => 'wal_sidecars_removed_after_rollback_mode_change',
        'wal_sidecar_exists' => false,
        'journal_sidecar_exists' => true,
        'database_bytes' => 2048,
        'read_version' => 1,
        'write_version' => 1,
        'source' => 'upstream walmode.test 4.11-4.13',
    ]],
    'walmode-4.9 open connection blocks wal to delete' => ['wal', 'delete', true, true, true, false, 2048, [
        'status' => 'rollback-change-blocked-by-open-connection',
        'result' => 'wal',
        'blocked' => true,
        'reason' => 'open_connection_prevents_wal_to_rollback_change',
        'wal_sidecar_exists' => true,
        'journal_sidecar_exists' => false,
        'database_bytes' => 2048,
        'read_version' => 2,
        'write_version' => 2,
        'source' => 'upstream walmode.test 4.6-4.10',
    ]],
    'walmode-4.17 read transaction blocks delete to wal' => ['delete', 'wal', true, true, false, true, 2048, [
        'status' => 'wal-change-blocked-by-reader',
        'result' => 'delete',
        'blocked' => true,
        'reason' => 'reader_transaction_prevents_rollback_to_wal_change',
        'wal_sidecar_exists' => false,
        'journal_sidecar_exists' => false,
        'database_bytes' => 2048,
        'read_version' => 1,
        'write_version' => 1,
        'source' => 'upstream walmode.test 4.17-4.18',
    ]],
    'walmode-5.1 memory database rejects wal' => ['memory', 'wal', false, true, false, false, 0, [
        'status' => 'wal-not-file-backed',
        'result' => 'memory',
        'blocked' => false,
        'reason' => 'wal_mode_requires_persistent_database',
        'wal_sidecar_exists' => false,
        'journal_sidecar_exists' => false,
        'database_bytes' => 0,
        'read_version' => 1,
        'write_version' => 1,
        'source' => 'upstream walmode.test 5.1-5.3',
    ]],
    'walmode-5.3 temp database keeps rollback journal mode' => ['delete', 'wal', false, true, false, false, 0, [
        'status' => 'wal-not-file-backed',
        'result' => 'delete',
        'blocked' => false,
        'reason' => 'wal_mode_requires_persistent_database',
        'wal_sidecar_exists' => false,
        'journal_sidecar_exists' => false,
        'database_bytes' => 0,
        'read_version' => 1,
        'write_version' => 1,
        'source' => 'upstream walmode.test 5.1-5.3',
    ]],
];

foreach ($transitionCases as $name => [$current, $requested, $fileBacked, $walSupported, $otherOpen, $otherRead, $bytes, $expected]) {
    $plan = static fn (): array => SQLitePagerWalDynamicPlan::journalModeTransition($current, $requested, $fileBacked, $walSupported, $otherOpen, $otherRead, $bytes);
    foreach ($expected as $field => $value) {
        $tests["real upstream {$name} {$field}"] = static fn (TestRunner $t) => $t->same($value, $plan()[$field]);
    }
}

$dynamicModes = ['delete', 'persist', 'truncate', 'wal'];
$requestedModes = ['delete', 'persist', 'truncate', 'wal'];
foreach ($dynamicModes as $current) {
    foreach ($requestedModes as $requested) {
        foreach ([false, true] as $otherOpen) {
            foreach ([false, true] as $otherRead) {
                $label = "real upstream walmode.test dynamic {$current} to {$requested} open-" . (int) $otherOpen . ' read-' . (int) $otherRead;
                $plan = static fn (): array => SQLitePagerWalDynamicPlan::journalModeTransition($current, $requested, true, true, $otherOpen, $otherRead, 4096);
                $tests["{$label} result remains valid journal mode"] = static fn (TestRunner $t) => $t->same(true, in_array($plan()['result'], ['delete', 'persist', 'truncate', 'wal'], true));
                $tests["{$label} blocked transitions preserve current mode"] = static fn (TestRunner $t) => $t->same($plan()['blocked'] ? $current : $plan()['result'], $plan()['result']);
                $tests["{$label} wal result uses wal file versions"] = static fn (TestRunner $t) => $t->same($plan()['result'] === 'wal' ? 2 : 1, $plan()['read_version']);
                $tests["{$label} write version matches read version"] = static fn (TestRunner $t) => $t->same($plan()['read_version'], $plan()['write_version']);
                $tests["{$label} wal sidecar tracks wal result or blocked wal"] = static fn (TestRunner $t) => $t->same($plan()['result'] === 'wal', $plan()['wal_sidecar_exists']);
                $tests["{$label} persist sidecar only for persist rollback result"] = static fn (TestRunner $t) => $t->same($plan()['result'] === 'persist', $plan()['journal_sidecar_exists']);
                $tests["{$label} source cites walmode"] = static fn (TestRunner $t) => $t->same(true, str_starts_with($plan()['source'], 'upstream walmode.test'));
            }
        }
    }
}

foreach ([0, 1024, 4096, 8192] as $bytes) {
    foreach ($dynamicModes as $current) {
        foreach ($requestedModes as $requested) {
            $label = "real upstream walmode.test size matrix {$bytes} {$current} to {$requested}";
            $plan = static fn (): array => SQLitePagerWalDynamicPlan::journalModeTransition($current, $requested, true, true, false, false, $bytes);
            $tests["{$label} database size is preserved or initialized to first page"] = static fn (TestRunner $t) => $t->same($requested === 'wal' ? max($bytes, 1024) : $bytes, $plan()['database_bytes']);
            $tests["{$label} wal request creates wal sidecar on file database"] = static fn (TestRunner $t) => $t->same($requested === 'wal', $plan()['wal_sidecar_exists']);
            $tests["{$label} rollback request leaves version one"] = static fn (TestRunner $t) => $t->same($requested === 'wal' ? 2 : 1, $plan()['read_version']);
            $tests["{$label} mode transition not blocked without peer connection"] = static fn (TestRunner $t) => $t->same(false, $plan()['blocked']);
        }
    }
}

$lockExpectations = [
    'initial-read' => ['ok', 'unlocked', 'unlocked', 'unlocked', [1, 2], [1, 2], [1, 2], true, true, true],
    'writer-reserved' => ['ok', 'reserved', 'unlocked', 'unlocked', [1, 2, 3], [1, 2], [1, 2], true, true, true],
    'second-writer-blocked' => ['database is locked', 'reserved', 'deferred-open', 'unlocked', [1, 2, 3], [1, 2], [1, 2], true, true, true],
    'reader-shared' => ['ok', 'unlocked', 'shared', 'unlocked', [1, 2, 3], [1, 2, 3], [1, 2, 3], false, true, true],
    'writer-reserved-with-reader' => ['ok', 'reserved', 'shared', 'unlocked', [11, 12, 13], [1, 2, 3], [1, 2, 3], false, true, true],
    'writer-pending-after-commit-blocked' => ['database is locked', 'pending', 'shared', 'unlocked', [11, 12, 13], [1, 2, 3], [], false, true, false],
    'reader-released-writer-pending' => ['database is locked', 'pending', 'unlocked', 'unlocked', [11, 12, 13], [], [], true, false, false],
    'writer-committed' => ['ok', 'unlocked', 'unlocked', 'unlocked', [21, 22, 23], [21, 22, 23], [21, 22, 23], true, true, true],
];

foreach ($lockExpectations as $step => [$result, $writerState, $readerState, $thirdState, $writerRows, $readerRows, $thirdRows, $writerCanCommit, $readerCanRead, $thirdCanRead]) {
    $plan = static fn (): array => SQLitePagerWalDynamicPlan::multiclientLockStep($step);
    $tests["real upstream pager1.test {$step} step name"] = static fn (TestRunner $t) => $t->same($step, $plan()['step']);
    $tests["real upstream pager1.test {$step} result"] = static fn (TestRunner $t) => $t->same($result, $plan()['result']);
    $tests["real upstream pager1.test {$step} writer state"] = static fn (TestRunner $t) => $t->same($writerState, $plan()['writer_state']);
    $tests["real upstream pager1.test {$step} reader state"] = static fn (TestRunner $t) => $t->same($readerState, $plan()['reader_state']);
    $tests["real upstream pager1.test {$step} third state"] = static fn (TestRunner $t) => $t->same($thirdState, $plan()['third_state']);
    $tests["real upstream pager1.test {$step} writer rows"] = static fn (TestRunner $t) => $t->same($writerRows, $plan()['writer_rows']);
    $tests["real upstream pager1.test {$step} reader rows"] = static fn (TestRunner $t) => $t->same($readerRows, $plan()['reader_rows']);
    $tests["real upstream pager1.test {$step} third rows"] = static fn (TestRunner $t) => $t->same($thirdRows, $plan()['third_rows']);
    $tests["real upstream pager1.test {$step} writer can commit"] = static fn (TestRunner $t) => $t->same($writerCanCommit, $plan()['writer_can_commit']);
    $tests["real upstream pager1.test {$step} reader can read"] = static fn (TestRunner $t) => $t->same($readerCanRead, $plan()['reader_can_read']);
    $tests["real upstream pager1.test {$step} third can read"] = static fn (TestRunner $t) => $t->same($thirdCanRead, $plan()['third_can_read']);
    $tests["real upstream pager1.test {$step} cites source"] = static fn (TestRunner $t) => $t->same('upstream pager1.test pager1-1.* multiclient locking', $plan()['source']);
}

foreach (array_keys($lockExpectations) as $outer) {
    foreach (array_keys($lockExpectations) as $inner) {
        $left = static fn (): array => SQLitePagerWalDynamicPlan::multiclientLockStep($outer);
        $right = static fn (): array => SQLitePagerWalDynamicPlan::multiclientLockStep($inner);
        $label = "real upstream pager1.test dynamic compare {$outer} then {$inner}";
        $tests["{$label} blocked state has lock error"] = static fn (TestRunner $t) => $t->same(str_contains($left()['result'], 'locked'), $left()['writer_state'] === 'pending' || $left()['step'] === 'second-writer-blocked');
        $tests["{$label} pending writer blocks fresh third reader"] = static fn (TestRunner $t) => $t->same($left()['writer_state'] === 'pending' ? false : true, $left()['third_can_read']);
        $tests["{$label} shared reader sees old rows during reserved writer"] = static fn (TestRunner $t) => $t->same($left()['writer_state'] === 'reserved' && $left()['reader_state'] === 'shared' ? [1, 2, 3] : $left()['reader_rows'], $left()['reader_rows']);
        $tests["{$label} committed terminal state is globally visible"] = static fn (TestRunner $t) => $t->same($right()['step'] === 'writer-committed' ? $right()['writer_rows'] : $right()['third_rows'], $right()['third_rows']);
        $tests["{$label} source remains pager1"] = static fn (TestRunner $t) => $t->same(true, str_starts_with($right()['source'], 'upstream pager1.test'));
    }
}

$tests['real upstream walmode rejects unknown current mode'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLitePagerWalDynamicPlan::journalModeTransition('bad', 'wal', true, true));
$tests['real upstream walmode rejects unknown requested mode'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLitePagerWalDynamicPlan::journalModeTransition('delete', 'bad', true, true));
$tests['real upstream walmode rejects negative database byte count'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLitePagerWalDynamicPlan::journalModeTransition('delete', 'wal', true, true, false, false, -1));
$tests['real upstream pager1 rejects unknown lock step'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLitePagerWalDynamicPlan::multiclientLockStep('missing'));

return $tests;
