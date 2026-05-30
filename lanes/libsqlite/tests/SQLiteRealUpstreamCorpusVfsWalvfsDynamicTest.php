<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalVfsDynamicPlan;

$tests = [];

$scenarios = SQLiteWalVfsDynamicPlan::supportedScenarios();
$case = 0;

foreach ($scenarios as $scenario) {
    foreach ([1, 2, 3, 5, 8, 13, 21, 34, 55, 89] as $busyAttempts) {
        foreach ([5, 8, 13, 21, 24, 34, 55, 89, 144, 233] as $walFrames) {
            $case++;
            $tests[sprintf('real upstream corpus vfs walvfs dynamic %04d %s busy %d frames %d', $case, $scenario, $busyAttempts, $walFrames)] = static function (TestRunner $t) use ($scenario, $busyAttempts, $walFrames): void {
                $plan = SQLiteWalVfsDynamicPlan::shmBoundary($scenario, $busyAttempts, $walFrames, 5);
                $expectsRetry = in_array($scenario, ['walvfs-4.2', 'walvfs-5.4', 'walvfs-5.5', 'walvfs-6.2'], true);
                $expectsReadOnly = in_array($scenario, ['walvfs-4.1', 'walvfs-4.2', 'walvfs-5.5'], true);
                $expectsIoerr = $scenario === 'walvfs-9.1';
                $expectsOkRead = in_array($scenario, ['walvfs-5.3', 'walvfs-5.4', 'walvfs-5.6'], true);

                $t->same('ok', $plan['status']);
                $t->same('walvfs.test', $plan['script']);
                $t->same($scenario, $plan['scenario']);
                $t->same($walFrames, $plan['wal_frames']);
                $t->same($expectsRetry, $plan['requires_retry']);
                $t->same($expectsRetry ? $busyAttempts : 0, $plan['busy_attempts']);
                $t->same(true, $plan['database_image_stable']);
                $t->same($expectsOkRead ? 20 : ($scenario === 'walvfs-8.3' ? 21 : null), $plan['read_count']);
                $t->same($expectsReadOnly ? 'SQLITE_READONLY' : ($expectsIoerr ? 'SQLITE_IOERR' : $plan['expected_code']), $plan['expected_code']);
                $t->same(!$expectsReadOnly && !$expectsIoerr, $plan['shm_map_writable']);
                $t->same(true, in_array('sqlite-upstream-walvfs-test', $plan['dependencies'], true));
                $t->same(true, in_array('sqlite-wal-shm-map-lock-boundary', $plan['dependencies'], true));
                $t->same(true, in_array('sqlite-vfs-io-dynamic', $plan['dependencies'], true));
                $t->same(true, str_starts_with($plan['upstream'][0], 'walvfs.test '));
            };
        }
    }
}

$tests['real upstream corpus vfs walvfs dynamic cites supported upstream sections'] = static function (TestRunner $t) use ($scenarios): void {
    $t->same([
        'walvfs-4.1',
        'walvfs-4.2',
        'walvfs-5.3',
        'walvfs-5.4',
        'walvfs-5.5',
        'walvfs-5.6',
        'walvfs-6.2',
        'walvfs-7.1',
        'walvfs-8.3',
        'walvfs-9.1',
    ], $scenarios);
};

$tests['real upstream corpus vfs walvfs dynamic readmark recovery follows walvfs 5'] = static function (TestRunner $t): void {
    foreach (['walvfs-5.3', 'walvfs-5.4', 'walvfs-5.6'] as $scenario) {
        $plan = SQLiteWalVfsDynamicPlan::shmBoundary($scenario, 20, 24);

        $t->same([0 => 0, 1 => 24, 2 => 100, 3 => 100, 4 => 100], $plan['readmarks_after']);
        $t->same(20, $plan['read_count']);
        $t->same('SQLITE_OK', $plan['expected_code']);
        $t->same('ok', $plan['message']);
    }
};

$tests['real upstream corpus vfs walvfs dynamic checkpoint outcomes follow walvfs 7 and 8'] = static function (TestRunner $t): void {
    $blocked = SQLiteWalVfsDynamicPlan::shmBoundary('walvfs-7.1', 1, 5);
    $refreshed = SQLiteWalVfsDynamicPlan::shmBoundary('walvfs-8.3', 1, 5);

    $t->same([1, -1, -1], $blocked['checkpoint_result']);
    $t->same('SQLITE_BUSY', $blocked['expected_code']);
    $t->same([0, 5, 5], $refreshed['checkpoint_result']);
    $t->same(21, $refreshed['read_count']);
    $t->same(5, $refreshed['backfilled_frames']);
};

$tests['real upstream corpus vfs walvfs dynamic distinguishes readonly and ioerr boundaries'] = static function (TestRunner $t): void {
    $readonly = SQLiteWalVfsDynamicPlan::shmBoundary('walvfs-4.1');
    $readonlyAfterBusy = SQLiteWalVfsDynamicPlan::shmBoundary('walvfs-4.2', 5);
    $ioerr = SQLiteWalVfsDynamicPlan::shmBoundary('walvfs-9.1');

    $t->same('SQLITE_READONLY', $readonly['expected_code']);
    $t->same('attempt to write a readonly database', $readonly['message']);
    $t->same('SQLITE_READONLY', $readonlyAfterBusy['expected_code']);
    $t->same(5, $readonlyAfterBusy['busy_attempts']);
    $t->same('SQLITE_IOERR', $ioerr['expected_code']);
    $t->same('disk I/O error', $ioerr['message']);
};

$tests['real upstream corpus vfs walvfs dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteWalVfsDynamicPlan::shmBoundary(''));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteWalVfsDynamicPlan::shmBoundary('walvfs-3.1'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteWalVfsDynamicPlan::shmBoundary('walvfs-5.3', 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteWalVfsDynamicPlan::shmBoundary('walvfs-5.3', 1, -1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteWalVfsDynamicPlan::shmBoundary('walvfs-5.3', 1, 1, -1));
};

return $tests;
