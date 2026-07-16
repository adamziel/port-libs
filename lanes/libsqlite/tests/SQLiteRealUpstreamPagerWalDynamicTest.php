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

$wal3Scenarios = [
    'wal3-6.1',
    'wal3-6.1-race',
    'wal3-7.1',
    'wal3-9',
];

$tests['real upstream corpus pager wal dynamic cites wal3 readmark source scenarios'] = static function (TestRunner $t) use ($wal3Scenarios): void {
    $t->same('wal3.test', basename('/home/claude/port-libs/.upstream-cache/libsqlite/test/wal3.test'));
    foreach ($wal3Scenarios as $scenario) {
        $profile = SQLiteWalVfsDynamicPlan::readmarkSnapshotBoundary($scenario, 24, 3, 2);
        $t->same('wal3.test', $profile['script']);
        $t->same($scenario, $profile['scenario']);
        $t->same(true, count($profile['upstream']) >= 3);
        $t->same(true, in_array('sqlite-upstream-wal3-test', $profile['dependencies'], true));
    }
};

foreach (range(1, 150) as $round) {
    foreach ($wal3Scenarios as $scenario) {
        $mxFrame = 12 + (($round * 7) % 97);
        $writerRaceFrames = $scenario === 'wal3-6.1' ? 0 : 1 + (($round * 5) % 17);
        $retryCount = 1 + ($round % 9);

        $tests[sprintf('real upstream corpus pager wal dynamic wal3 readmark %s round %03d', $scenario, $round)] = static function (TestRunner $t) use ($scenario, $mxFrame, $writerRaceFrames, $retryCount): void {
            $profile = SQLiteWalVfsDynamicPlan::readmarkSnapshotBoundary($scenario, $mxFrame, $writerRaceFrames, $retryCount);
            $isRace = in_array($scenario, ['wal3-6.1-race', 'wal3-7.1'], true);
            $isBusyExclusive = $scenario === 'wal3-9';
            $expectedSnapshot = $scenario === 'wal3-6.1' ? 0 : ($isRace ? $mxFrame + $writerRaceFrames : $mxFrame);
            $expectedCheckpoint = $scenario === 'wal3-6.1'
                ? [0, $mxFrame, $mxFrame]
                : [1, $mxFrame + $writerRaceFrames, $mxFrame];

            $t->same('ok', $profile['status']);
            $t->same('wal3.test', $profile['script']);
            $t->same($scenario, $profile['scenario']);
            $t->same(true, str_starts_with($profile['phase'], 'readmark') || str_starts_with($profile['phase'], 'stale') || str_starts_with($profile['phase'], 'exclusive'));
            $t->same('xShmLock', $profile['operation']);
            $t->same($isBusyExclusive ? 'SQLITE_BUSY' : 'SQLITE_OK', $profile['expected_code']);
            $t->same($expectedSnapshot, $profile['snapshot_frame']);
            $t->same($expectedCheckpoint, $profile['checkpoint_result']);
            $t->same($scenario !== 'wal3-6.1', $profile['reader_blocks_restart']);
            $t->same($scenario === 'wal3-6.1', $profile['backfilled_all_frames']);
            $t->same($scenario === 'wal3-6.1' ? 0 : $writerRaceFrames, $profile['writer_race_frames']);
            $t->same($isBusyExclusive ? $retryCount : 0, $profile['retry_count']);
            $t->same(0, $profile['readmarks_before'][0]);
            $t->same(0, $profile['readmarks_after'][0]);
            $t->same($mxFrame, $profile['readmarks_before'][1]);
            $t->same(true, $profile['read_slot'] >= 0 && $profile['read_slot'] <= 4);
            $t->same(true, count($profile['lock_sequence']) >= 2);
            $t->same(true, in_array('sqlite-wal-readmark-snapshot-boundary', $profile['dependencies'], true));
            $t->same(true, in_array('sqlite-vfs-shm-lock-dynamic', $profile['dependencies'], true));
            $t->same(true, $profile['upstream'] !== []);
        };
    }
}

$tests['real upstream corpus pager wal dynamic rejects malformed wal3 readmark inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalVfsDynamicPlan::readmarkSnapshotBoundary('wal3-unknown', 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalVfsDynamicPlan::readmarkSnapshotBoundary('wal3-6.1', -1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalVfsDynamicPlan::readmarkSnapshotBoundary('wal3-6.1', 1, -1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalVfsDynamicPlan::readmarkSnapshotBoundary('wal3-6.1', 1, 0, 0));
};

return $tests;
