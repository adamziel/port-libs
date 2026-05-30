<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsSyncPlan;
use PortLibs\LibSqlite\SQLiteWalSyncMatrix;

$tests = [];

$matrix = [
    '15.1' => [[false, false, 'off'], [0, 0], [0, 0], [0, 0]],
    '15.2' => [[false, false, 'normal'], [1, 0], [0, 0], [2, 0]],
    '15.3' => [[false, false, 'full'], [2, 0], [1, 0], [2, 0]],
    '15.4' => [[false, true, 'off'], [0, 0], [0, 0], [0, 0]],
    '15.5' => [[false, true, 'normal'], [0, 1], [0, 0], [0, 2]],
    '15.6' => [[false, true, 'full'], [0, 2], [0, 1], [0, 2]],
    '15.7' => [[true, false, 'off'], [0, 0], [0, 0], [0, 0]],
    '15.8' => [[true, false, 'normal'], [0, 1], [0, 0], [0, 2]],
    '15.9' => [[true, false, 'full'], [1, 1], [1, 0], [0, 2]],
    '15.10' => [[true, true, 'off'], [0, 0], [0, 0], [0, 0]],
    '15.11' => [[true, true, 'normal'], [0, 1], [0, 0], [0, 2]],
    '15.12' => [[true, true, 'full'], [0, 2], [0, 1], [0, 2]],
];

foreach ($matrix as $upstream => [$settings, $restart, $commit, $checkpoint]) {
    [$checkpointFullfsync, $fullfsync, $synchronous] = $settings;
    foreach (['restart' => $restart, 'commit' => $commit, 'checkpoint' => $checkpoint] as $phase => $expected) {
        $plan = static fn (): array => SQLiteWalSyncMatrix::syncCounts($checkpointFullfsync, $fullfsync, $synchronous, $phase);
        $prefix = "real upstream wal2.test {$upstream} {$phase}";

        $tests["{$prefix} normal sync count"] = static fn (TestRunner $t) => $t->same($expected[0], $plan()['normal']);
        $tests["{$prefix} full sync count"] = static fn (TestRunner $t) => $t->same($expected[1], $plan()['full']);
        $tests["{$prefix} total sync count"] = static fn (TestRunner $t) => $t->same($expected[0] + $expected[1], $plan()['total']);
        $tests["{$prefix} phase"] = static fn (TestRunner $t) => $t->same($phase, $plan()['phase']);
        $tests["{$prefix} synchronous mode"] = static fn (TestRunner $t) => $t->same($synchronous, $plan()['synchronous']);
        $tests["{$prefix} checkpoint fullfsync setting"] = static fn (TestRunner $t) => $t->same($checkpointFullfsync, $plan()['checkpoint_fullfsync']);
        $tests["{$prefix} fullfsync setting"] = static fn (TestRunner $t) => $t->same($fullfsync, $plan()['fullfsync']);
        $tests["{$prefix} flags use vfs constants"] = static fn (TestRunner $t) => $t->same(
            array_merge(
                array_fill(0, $expected[0], SQLiteVfsSyncPlan::SQLITE_SYNC_NORMAL),
                array_fill(0, $expected[1], SQLiteVfsSyncPlan::SQLITE_SYNC_FULL)
            ),
            $plan()['flags']
        );
        $tests["{$prefix} flag names"] = static fn (TestRunner $t) => $t->same(
            array_merge(array_fill(0, $expected[0], 'normal'), array_fill(0, $expected[1], 'full')),
            $plan()['flag_names']
        );
        $tests["{$prefix} cites upstream source"] = static fn (TestRunner $t) => $t->same('upstream wal2.test 15.*', $plan()['source']);
    }
}

$autoCheckpoint = [
    'wal2-14.1 default checkpoint_fullfsync off' => [null, [10, 0], [4, 0], [6, 0]],
    'wal2-14.2 checkpoint_fullfsync on' => [true, [10, 6], [4, 3], [6, 3]],
    'wal2-14.3 checkpoint_fullfsync off' => [false, [10, 0], [4, 0], [6, 0]],
];

foreach ($autoCheckpoint as $upstream => [$checkpointFullfsync, $initial, $overflow, $close]) {
    $plan = static fn (): array => SQLiteWalSyncMatrix::autoCheckpointCounts($checkpointFullfsync);
    $tests["real upstream {$upstream} initial normal sync count"] = static fn (TestRunner $t) => $t->same($initial[0], $plan()['initial']['normal']);
    $tests["real upstream {$upstream} initial full sync count"] = static fn (TestRunner $t) => $t->same($initial[1], $plan()['initial']['full']);
    $tests["real upstream {$upstream} initial total sync count"] = static fn (TestRunner $t) => $t->same($initial[0] + $initial[1], $plan()['initial']['total']);
    $tests["real upstream {$upstream} overflow normal sync count"] = static fn (TestRunner $t) => $t->same($overflow[0], $plan()['overflow_insert']['normal']);
    $tests["real upstream {$upstream} overflow full sync count"] = static fn (TestRunner $t) => $t->same($overflow[1], $plan()['overflow_insert']['full']);
    $tests["real upstream {$upstream} overflow total sync count"] = static fn (TestRunner $t) => $t->same($overflow[0] + $overflow[1], $plan()['overflow_insert']['total']);
    $tests["real upstream {$upstream} close normal sync count"] = static fn (TestRunner $t) => $t->same($close[0], $plan()['close_after_autocheckpoint_off']['normal']);
    $tests["real upstream {$upstream} close full sync count"] = static fn (TestRunner $t) => $t->same($close[1], $plan()['close_after_autocheckpoint_off']['full']);
    $tests["real upstream {$upstream} close total sync count"] = static fn (TestRunner $t) => $t->same($close[0] + $close[1], $plan()['close_after_autocheckpoint_off']['total']);
    $tests["real upstream {$upstream} cites source"] = static fn (TestRunner $t) => $t->same('upstream wal2.test wal2-14.*', $plan()['source']);
}

$noop = [
    'walckptnoop 1.1 first noop leaves all frames uncheckpointed' => [298, 0, true, false, [0, 298, 0, 298]],
    'walckptnoop 1.2 repeated noop leaves all frames uncheckpointed' => [298, 0, true, false, [0, 298, 0, 298]],
    'walckptnoop 1.4 noop after passive reports checkpointed frames' => [298, 298, true, false, [0, 298, 298, 0]],
    'walckptnoop 1.5 reopened noop reports no checkpoint progress' => [298, 0, true, false, [0, 298, 0, 298]],
    'walckptnoop 1.6 empty wal noop reports zero frames' => [0, 0, true, false, [0, 0, 0, 0]],
    'walckptnoop 1.7 writer transaction blocks noop checkpoint' => [5, 0, true, true, [1, 5, 0, 5]],
    'walckptnoop 1.8 committed delete leaves five log frames' => [5, 0, true, false, [0, 5, 0, 5]],
    'walckptnoop 1.10 rollback journal mode reports no wal' => [5, 0, false, false, [0, -1, -1, 0]],
];

foreach ($noop as $upstream => [$logFrames, $checkpointedFrames, $journalModeWal, $writerOpen, $expected]) {
    $plan = static fn (): array => SQLiteWalSyncMatrix::noopCheckpoint($logFrames, $checkpointedFrames, $journalModeWal, $writerOpen);
    $tests["real upstream {$upstream} busy field"] = static fn (TestRunner $t) => $t->same($expected[0], $plan()['busy']);
    $tests["real upstream {$upstream} log field"] = static fn (TestRunner $t) => $t->same($expected[1], $plan()['log']);
    $tests["real upstream {$upstream} checkpointed field"] = static fn (TestRunner $t) => $t->same($expected[2], $plan()['checkpointed']);
    $tests["real upstream {$upstream} remaining checkpoint frames"] = static fn (TestRunner $t) => $t->same($expected[3], $plan()['checkpoint_frames_remaining']);
    $tests["real upstream {$upstream} does not apply checkpoint"] = static fn (TestRunner $t) => $t->same(false, $plan()['checkpoint_applied']);
    $tests["real upstream {$upstream} cites source"] = static fn (TestRunner $t) => $t->same(true, str_starts_with($plan()['source'], 'upstream walckptnoop.test'));
}

$tests['real upstream wal sync matrix rejects bad synchronous mode'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalSyncMatrix::syncCounts(false, false, 'extra', 'restart'));
$tests['real upstream wal sync matrix rejects bad phase'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalSyncMatrix::syncCounts(false, false, 'normal', 'vacuum'));
$tests['real upstream wal noop checkpoint rejects negative log frame count'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalSyncMatrix::noopCheckpoint(-1, 0));
$tests['real upstream wal noop checkpoint rejects negative checkpoint frame count'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalSyncMatrix::noopCheckpoint(1, -1));
$tests['real upstream wal noop checkpoint rejects checkpoint beyond log'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalSyncMatrix::noopCheckpoint(1, 2));

return $tests;
