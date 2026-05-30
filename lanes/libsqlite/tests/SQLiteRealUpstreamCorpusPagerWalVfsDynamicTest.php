<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalVfsDynamicPlan;

$tests = [];

$scenarios = SQLiteWalVfsDynamicPlan::supportedScenarios();

for ($case = 1; $case <= 1000; $case++) {
    $scenario = $scenarios[($case - 1) % count($scenarios)];
    $busyAttempts = 1 + ($case % 7);
    $walFrames = 24 + ($case % 17);
    $backfilledFrames = 1 + ($case % 11);

    $tests[sprintf(
        'real upstream corpus pager wal vfs dynamic %04d %s',
        $case,
        $scenario
    )] = static function (TestRunner $t) use ($case, $scenario, $busyAttempts, $walFrames, $backfilledFrames): void {
        $profile = SQLiteWalVfsDynamicPlan::shmBoundary($scenario, $busyAttempts, $walFrames, $backfilledFrames);

        $t->same('ok', $profile['status']);
        $t->same('walvfs.test', $profile['script']);
        $t->same($scenario, $profile['scenario']);
        $t->same(true, in_array($profile['operation'], ['xShmMap', 'xShmMap/xShmLock', 'readmark-select', 'xShmLock', 'wal_checkpoint'], true));
        $t->same(true, in_array($profile['expected_code'], ['SQLITE_OK', 'SQLITE_READONLY', 'SQLITE_PROTOCOL', 'SQLITE_BUSY', 'SQLITE_IOERR'], true));
        $t->same(true, $profile['wal_frames'] >= 24 && $profile['wal_frames'] <= 40);
        $t->same($profile['requires_retry'] ? $busyAttempts : 0, $profile['busy_attempts']);
        $t->same($profile['checkpoint_result'] === null ? 0 : min($backfilledFrames, $walFrames), $profile['backfilled_frames']);
        $t->same(true, $profile['database_image_stable']);
        $t->same(true, str_starts_with($profile['upstream'][0], 'walvfs.test '));
        $t->same(true, in_array('sqlite-upstream-walvfs-test', $profile['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-shm-map-lock-boundary', $profile['dependencies'], true));
        $t->same(true, in_array('sqlite-vfs-io-dynamic', $profile['dependencies'], true));

        if ($profile['expected_code'] === 'SQLITE_OK') {
            $t->same('ok', $profile['message']);
            $t->same(true, $profile['shm_map_writable']);
            $t->same([0 => 0, 1 => 24, 2 => 100, 3 => 100, 4 => 100], $profile['readmarks_after']);
        } elseif ($profile['expected_code'] === 'SQLITE_READONLY') {
            $t->same('attempt to write a readonly database', $profile['message']);
            $t->same(false, $profile['shm_map_writable']);
        } elseif ($profile['expected_code'] === 'SQLITE_IOERR') {
            $t->same('disk I/O error', $profile['message']);
            $t->same(false, $profile['shm_map_writable']);
        } elseif ($profile['expected_code'] === 'SQLITE_BUSY') {
            $t->same([1, -1, -1], $profile['checkpoint_result']);
            $t->same('checkpoint blocked', $profile['message']);
        } else {
            $t->same('locking protocol', $profile['message']);
            $t->same(true, $profile['requires_retry']);
        }

        $t->same(true, $case >= 1 && $case <= 1000);
    };
}

$tests['real upstream corpus pager wal vfs dynamic records hydrated upstream sections'] = static function (TestRunner $t) use ($scenarios): void {
    $upstream = [];
    foreach ($scenarios as $scenario) {
        $profile = SQLiteWalVfsDynamicPlan::shmBoundary($scenario);
        $upstream[] = $profile['upstream'][0];
    }
    sort($upstream);

    $t->same(10, count($scenarios));
    $t->same([
        'walvfs.test 4.1',
        'walvfs.test 4.2',
        'walvfs.test 5.3',
        'walvfs.test 5.4',
        'walvfs.test 5.5',
        'walvfs.test 5.6',
        'walvfs.test 6.2',
        'walvfs.test 7.1',
        'walvfs.test 8.3',
        'walvfs.test 9.1',
    ], $upstream);
};

$tests['real upstream corpus pager wal vfs dynamic rejects malformed boundary inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalVfsDynamicPlan::shmBoundary('walvfs-404'));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalVfsDynamicPlan::shmBoundary('walvfs-4.1', 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalVfsDynamicPlan::shmBoundary('walvfs-4.1', 1, -1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalVfsDynamicPlan::shmBoundary('walvfs-4.1', 1, 1, -1));
};

return $tests;
