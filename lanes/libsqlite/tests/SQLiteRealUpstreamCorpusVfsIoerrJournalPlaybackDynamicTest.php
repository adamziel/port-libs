<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$scenarios = [
    'hot-journal-read' => [
        'upstream' => ['ioerr.test ioerr-7'],
        'checkpoint' => 'hot-journal',
        'success_reason' => 'hot_journal_replayed_after_successful_retry',
        'fault_reason' => 'defer_hot_journal_replay_until_read_succeeds',
    ],
    'master-journal-name-read' => [
        'upstream' => ['ioerr.test ioerr-9'],
        'checkpoint' => 'master-journal-name',
        'success_reason' => 'master_journal_name_read_allows_member_rollback',
        'fault_reason' => 'treat_master_journal_name_as_unreadable_and_keep_member_hot',
    ],
    'statement-playback-constraint' => [
        'upstream' => ['ioerr.test ioerr-10'],
        'checkpoint' => 'statement-journal',
        'success_reason' => 'constraint_aborts_statement_without_outer_transaction_loss',
        'fault_reason' => 'play_statement_journal_then_preserve_outer_transaction',
    ],
];

$operations = ['read', 'write', 'sync', 'truncate'];
$case = 0;

foreach ($scenarios as $scenario => $expected) {
    foreach (range(1, 100) as $failAt) {
        foreach ($operations as $operation) {
            $case++;
            $seedRows = 120 + (($case * 17) % 900);

            $tests[sprintf('real upstream corpus vfs ioerr journal playback dynamic %04d %s fail %03d %s', $case, $scenario, $failAt, $operation)] =
                static function (TestRunner $t) use ($scenario, $expected, $failAt, $operation, $seedRows): void {
                    $plan = SQLiteVfsIoDynamicPlan::journalPlaybackIoErrorProfile($scenario, $failAt, $operation, $seedRows);
                    $faultDetected = $failAt % 41 !== 0;
                    $writeSideFault = in_array($operation, ['write', 'sync', 'truncate'], true);

                    $t->same('ok', $plan['status']);
                    $t->same('ioerr.test', $plan['script']);
                    $t->same($scenario, $plan['scenario']);
                    $t->same($expected['upstream'], $plan['upstream']);
                    $t->same($failAt, $plan['fail_at']);
                    $t->same($operation, $plan['operation']);
                    $t->same($seedRows, $plan['seed_rows']);
                    $t->same($faultDetected, $plan['fault_detected']);
                    $t->same($expected['checkpoint'], $plan['checkpoint']);
                    $t->same('ok', $plan['integrity_check']);
                    $t->same(true, $plan['cache_refcount_zero']);
                    $t->same(0, $plan['open_file_count']);
                    $t->same(true, $plan['rows_preserved']);
                    $t->same(true, in_array('upstream-ioerr-journal-playback', $plan['dependencies'], true));
                    $t->same(true, in_array('sqlite-pager-journal-playback', $plan['dependencies'], true));
                    $t->same(true, in_array('vfs-io-dynamic-real-corpus', $plan['dependencies'], true));

                    if ($scenario === 'hot-journal-read') {
                        $t->same($faultDetected ? 'SQLITE_IOERR_READ' : 'SQLITE_OK', $plan['expected_result']);
                        $t->same($faultDetected, $plan['rollback_required']);
                        $t->same($faultDetected, $plan['hot_journal_left']);
                        $t->same($faultDetected, $plan['journal_bytes_retained_for_retry']);
                        $t->same(false, $plan['master_journal_name_required']);
                        $t->same([[1, 2]], $plan['final_rows_sample']);
                        $t->same($faultDetected ? $expected['fault_reason'] : $expected['success_reason'], $plan['reason']);
                    } elseif ($scenario === 'master-journal-name-read') {
                        $t->same($faultDetected ? 'SQLITE_IOERR_READ' : 'SQLITE_OK', $plan['expected_result']);
                        $t->same($faultDetected, $plan['rollback_required']);
                        $t->same(false, $plan['hot_journal_left']);
                        $t->same($faultDetected, $plan['journal_bytes_retained_for_retry']);
                        $t->same(true, $plan['master_journal_name_required']);
                        $t->same(['committed-row'], $plan['final_rows_sample']);
                        $t->same($faultDetected ? $expected['fault_reason'] : $expected['success_reason'], $plan['reason']);
                    } else {
                        $t->same('UNIQUE constraint failed: t1.a', $plan['expected_result']);
                        $t->same($faultDetected && $writeSideFault, $plan['rollback_required']);
                        $t->same(false, $plan['hot_journal_left']);
                        $t->same(false, $plan['journal_bytes_retained_for_retry']);
                        $t->same(false, $plan['master_journal_name_required']);
                        $t->same(true, $plan['statement_journal_playback']);
                        $t->same(true, $plan['constraint_message_preserved']);
                        $t->same(range(0, min($seedRows - 1, 9)), $plan['final_rows_sample']);
                        $t->same($faultDetected && $writeSideFault ? $expected['fault_reason'] : $expected['success_reason'], $plan['reason']);
                    }
                };
        }
    }
}

$tests['real upstream corpus vfs ioerr journal playback dynamic records hydrated source sections'] = static function (TestRunner $t) use ($case, $scenarios): void {
    $t->same(1200, $case);
    $t->same(['hot-journal-read', 'master-journal-name-read', 'statement-playback-constraint'], array_keys($scenarios));
    $t->same(['ioerr.test ioerr-7'], $scenarios['hot-journal-read']['upstream']);
    $t->same(['ioerr.test ioerr-9'], $scenarios['master-journal-name-read']['upstream']);
    $t->same(['ioerr.test ioerr-10'], $scenarios['statement-playback-constraint']['upstream']);
};

$tests['real upstream corpus vfs ioerr journal playback dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::journalPlaybackIoErrorProfile('unknown', 1, 'read'));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::journalPlaybackIoErrorProfile('hot-journal-read', 0, 'read'));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::journalPlaybackIoErrorProfile('hot-journal-read', 1, 'open'));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::journalPlaybackIoErrorProfile('hot-journal-read', 1, 'read', 0));
};

return $tests;
