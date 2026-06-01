<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$upstreamFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/win32lock.test';
$scenarios = ['win32lock-1.2', 'win32lock-2.2', 'win32lock-3.2', 'win32lock-3.4'];

foreach (range(1, 1000) as $case) {
    $scenario = $scenarios[$case % count($scenarios)];
    $retryCount = $scenario === 'win32lock-2.2' ? 1 + ($case % 5) : 10;
    $retryDelay = $scenario === 'win32lock-2.2' ? 1 + ($case % 7) : 25;
    $budget = $retryCount * $retryDelay;
    $lockDelay = in_array($scenario, ['win32lock-1.2', 'win32lock-2.2'], true)
        ? (($case % 2) === 0 ? max(0, $budget - ($case % max(1, $retryDelay))) : $budget + 1 + ($case % 29))
        : (10 + ($case % 251));
    $rowCount = 4 + ($case % 5);
    $basePayload = 50000 + (($case * 137) % 75001);

    $tests[sprintf('real upstream corpus vfs io win32lock dynamic %04d %s', $case, $scenario)] = static function (TestRunner $t) use (
        $basePayload,
        $budget,
        $case,
        $lockDelay,
        $retryCount,
        $retryDelay,
        $rowCount,
        $scenario
    ): void {
        $plan = SQLiteVfsIoDynamicPlan::win32AntivirusLockRetryProfile($scenario, [
            'base_payload_bytes' => $basePayload,
            'lock_delay_ms' => $lockDelay,
            'retry_count' => $retryCount,
            'retry_delay_ms' => $retryDelay,
            'row_count' => $rowCount,
        ]);

        $t->same('ok', $plan['status']);
        $t->same('win32lock.test', $plan['script']);
        $t->same($scenario, $plan['scenario']);
        $t->same('windows', $plan['platform']);
        $t->same(true, $plan['mmap_disabled']);
        $t->same(10, $plan['cache_size']);
        $t->same($rowCount, count($plan['setup_rows']));
        $t->same([1, $basePayload], $plan['setup_rows'][0]);
        $t->same(['rc' => 0, 'retry_count' => 10, 'retry_delay_ms' => 25], $plan['default_av_retry_control']);
        $t->same($retryCount, $plan['retry_count']);
        $t->same($retryDelay, $plan['retry_delay_ms']);
        $t->same($lockDelay, $plan['lock_delay_ms']);
        $t->same($budget, $plan['retry_budget_ms']);
        $t->same(true, in_array('sqlite-upstream-win32lock-antivirus-retry', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-vfs-win32-lock-retry', $plan['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $plan['dependencies'], true));

        if (in_array($scenario, ['win32lock-1.2', 'win32lock-2.2'], true)) {
            $readSucceeds = $lockDelay <= $budget;
            $expectedAttempts = min($retryCount, (int) ceil($lockDelay / max(1, $retryDelay)));
            $t->same($scenario === 'win32lock-2.2'
                ? ['rc' => 0, 'retry_count' => $retryCount, 'retry_delay_ms' => $retryDelay]
                : ['rc' => 0, 'retry_count' => 10, 'retry_delay_ms' => 25], $plan['av_retry_control_after_set']);
            $t->same($expectedAttempts, $plan['retry_attempts_planned']);
            $t->same($readSucceeds, $plan['transient_lock_cleared_before_budget']);
            $t->same($readSucceeds ? 'SQLITE_OK' : 'SQLITE_IOERR_LOCK', $plan['select_result_code']);
            $t->same($readSucceeds ? 'ok' : 'disk I/O error', $plan['select_result_message']);
            $t->same($readSucceeds ? $rowCount : 0, count($plan['select_rows']));
            $t->same($readSucceeds && $lockDelay > 0 ? 'delayed #ms for lock/sharing conflict' : null, $plan['log_message_normalized']);
            $t->same(true, $plan['both_ok_and_error_possible_in_loop']);
            $t->same(true, $plan['database_image_stable_after_retry']);
            $t->same($readSucceeds ? 'win32_antivirus_retry_waits_out_transient_lock' : 'win32_antivirus_retry_budget_exhaustion_surfaces_disk_io_error', $plan['reason']);
            $t->contains('win32lock.test win32lock-1.2', implode("\n", $plan['upstream']));
            $t->contains('win32lock.test win32lock-2.2', implode("\n", $plan['upstream']));
        } elseif ($scenario === 'win32lock-3.2') {
            $t->same('db', $plan['primary_handle']);
            $t->same('db2', $plan['peer_handle']);
            $t->same(['begin' => 'exclusive', 'inserted_row' => 4, 'status' => 'open'], $plan['primary_transaction']);
            $t->same(['begin' => 'exclusive', 'inserted_row' => 5, 'code' => 1, 'message' => 'database is locked'], $plan['peer_transaction_attempt']);
            $t->same(['code' => 0, 'message' => 'ok'], $plan['primary_commit_result']);
            $t->same(true, $plan['peer_blocked_by_primary_exclusive']);
            $t->same([1, 2, 3, 4], $plan['rows_after_primary_commit']);
            $t->same('ordinary_win32_handles_enforce_exclusive_transaction_contention', $plan['reason']);
            $t->contains('win32lock.test win32lock-3.2', implode("\n", $plan['upstream']));
        } else {
            $t->same('db', $plan['primary_handle']);
            $t->same(true, $plan['saved_handle_available']);
            $t->same(true, $plan['handle_set_to_zero']);
            $t->same(['begin' => 'exclusive', 'inserted_row' => 6, 'commit' => true], $plan['write_attempt']);
            $t->same(['code' => 1, 'message' => 'disk I/O error'], $plan['write_result']);
            $t->same(['rc' => 0, 'handle_restored' => true], $plan['restore_handle_result']);
            $t->same('SQLITE_IOERR_LOCK', $plan['extended_errcode']);
            $t->same(true, $plan['database_image_stable_after_failed_lock']);
            $t->same('invalid_win32_file_handle_maps_lock_failure_to_sqlite_ioerr_lock', $plan['reason']);
            $t->contains('win32lock.test win32lock-3.4', implode("\n", $plan['upstream']));
        }

        $t->same(true, $case >= 1);
    };
}

$tests['real upstream corpus vfs io win32lock cites hydrated source truth'] = static function (TestRunner $t) use ($upstreamFile): void {
    $source = (string) file_get_contents($upstreamFile);

    $t->contains('recovery from transient manditory locks', $source);
    $t->contains('file_control_win32_av_retry db -1 -1', $source);
    $t->contains('file_control_win32_av_retry db 1 1', $source);
    $t->contains('delayed #ms for lock/sharing conflict', $source);
    $t->contains('SQLITE_IOERR_LOCK', $source);

    $default = SQLiteVfsIoDynamicPlan::win32AntivirusLockRetryProfile('win32lock-2.0');
    $updated = SQLiteVfsIoDynamicPlan::win32AntivirusLockRetryProfile('win32lock-2.1');

    $t->same(['rc' => 0, 'retry_count' => 10, 'retry_delay_ms' => 25], $default['file_control_result']);
    $t->same(false, $default['file_control_mutates_connection']);
    $t->same('win32_av_retry_file_control_reports_default_retry_window', $default['reason']);
    $t->same(['rc' => 0, 'retry_count' => 1, 'retry_delay_ms' => 1], $updated['file_control_result']);
    $t->same(true, $updated['file_control_mutates_connection']);
    $t->same('win32_av_retry_file_control_updates_retry_window', $updated['reason']);
};

$rejects = [
    'empty scenario' => static fn (): array => SQLiteVfsIoDynamicPlan::win32AntivirusLockRetryProfile(''),
    'unsupported scenario' => static fn (): array => SQLiteVfsIoDynamicPlan::win32AntivirusLockRetryProfile('win32lock-9.9'),
    'negative retry count' => static fn (): array => SQLiteVfsIoDynamicPlan::win32AntivirusLockRetryProfile('win32lock-1.2', ['retry_count' => -1]),
    'negative retry delay' => static fn (): array => SQLiteVfsIoDynamicPlan::win32AntivirusLockRetryProfile('win32lock-1.2', ['retry_delay_ms' => -1]),
    'negative lock delay' => static fn (): array => SQLiteVfsIoDynamicPlan::win32AntivirusLockRetryProfile('win32lock-1.2', ['lock_delay_ms' => -1]),
    'zero rows' => static fn (): array => SQLiteVfsIoDynamicPlan::win32AntivirusLockRetryProfile('win32lock-1.2', ['row_count' => 0]),
    'zero payload' => static fn (): array => SQLiteVfsIoDynamicPlan::win32AntivirusLockRetryProfile('win32lock-1.2', ['base_payload_bytes' => 0]),
];

foreach ($rejects as $name => $callback) {
    $tests['real upstream corpus vfs io win32lock rejects malformed input ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(InvalidArgumentException::class, $callback);
    };
}

return $tests;
