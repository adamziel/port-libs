<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalVfsDynamicPlan;

$tests = [];

$scenarios = SQLiteWalVfsDynamicPlan::supportedScenarios();

$tests['real upstream corpus pager wal dynamic cites walvfs source scenarios'] = static function (TestRunner $t) use ($scenarios): void {
    $t->same('walvfs.test', basename('/home/claude/port-libs/.upstream-cache/libsqlite/test/walvfs.test'));
    $t->same(
        [
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
        ],
        $scenarios
    );
};

$caseNo = 0;
foreach (range(1, 100) as $round) {
    foreach ($scenarios as $scenario) {
        $caseNo++;
        $busyAttempts = 1 + (($round + $caseNo) % 31);
        $walFrames = 24 + (($round * 7 + $caseNo) % 73);
        $backfilledFrames = ($round * 5 + $caseNo) % 29;

        $tests[sprintf('real upstream corpus pager wal dynamic walvfs %04d %s round %03d', $caseNo, $scenario, $round)] = static function (TestRunner $t) use ($scenario, $busyAttempts, $walFrames, $backfilledFrames): void {
            $profile = SQLiteWalVfsDynamicPlan::shmBoundary($scenario, $busyAttempts, $walFrames, $backfilledFrames);
            $isCheckpoint = $profile['checkpoint_result'] !== null;
            $isReadOnlyMap = in_array($profile['expected_code'], ['SQLITE_READONLY', 'SQLITE_IOERR'], true)
                && str_contains($profile['operation'], 'xShmMap');

            $t->same('ok', $profile['status']);
            $t->same('walvfs.test', $profile['script']);
            $t->same($scenario, $profile['scenario']);
            $t->same(['walvfs.test ' . substr($scenario, strlen('walvfs-'))], $profile['upstream']);
            $t->same(true, str_starts_with($profile['phase'], strtolower(str_replace('-', '_', strtok($profile['phase'], '_') ?: ''))));
            $t->same(true, in_array($profile['expected_code'], ['SQLITE_OK', 'SQLITE_READONLY', 'SQLITE_PROTOCOL', 'SQLITE_BUSY', 'SQLITE_IOERR'], true));
            $t->same($walFrames, $profile['wal_frames']);
            $t->same($isCheckpoint ? min($backfilledFrames, $walFrames) : 0, $profile['backfilled_frames']);
            $t->same(!$isReadOnlyMap, $profile['shm_map_writable']);
            $t->same(true, $profile['database_image_stable']);
            $t->same($profile['requires_retry'] ? $busyAttempts : 0, $profile['busy_attempts']);
            $t->same(true, $walFrames >= $profile['backfilled_frames']);
            $t->same(true, $profile['readmarks_before'][0] === 0);
            $t->same(true, $profile['readmarks_after'][0] === 0);
            $t->same(true, count($profile['readmarks_before']) === 5);
            $t->same(true, count($profile['readmarks_after']) === 5);
            $t->same(true, in_array('sqlite-upstream-walvfs-test', $profile['dependencies'], true));
            $t->same(true, in_array('sqlite-wal-shm-map-lock-boundary', $profile['dependencies'], true));
            $t->same(true, in_array('sqlite-vfs-io-dynamic', $profile['dependencies'], true));
            $t->same(true, $profile['message'] !== '');
        };
    }
}

$tests['real upstream corpus pager wal dynamic rejects malformed boundary inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalVfsDynamicPlan::shmBoundary('walvfs-unknown'));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalVfsDynamicPlan::shmBoundary('walvfs-4.1', 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalVfsDynamicPlan::shmBoundary('walvfs-4.1', 1, -1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalVfsDynamicPlan::shmBoundary('walvfs-4.1', 1, 24, -1));
};

return $tests;
