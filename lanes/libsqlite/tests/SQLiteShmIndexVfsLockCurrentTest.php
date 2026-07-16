<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteShmIndex;

$makeIndex = static function (array $readMarks, array $embeddedLocks = []): SQLiteShmIndex {
    $pageSize = 512;
    $encodedPageSize = (1 << 24) | $pageSize;
    $header = pack(
        'V*',
        3007000,
        2,
        44,
        $encodedPageSize,
        12,
        6,
        0x01010101,
        0x02020202,
        0x03030303,
        0x04040404,
        0x05050505,
        0x06060606
    );

    $marks = array_replace([0, 4, 7, 12, 14], $readMarks);
    $locks = array_replace([0, 0, 0, 0, 0, 0, 0, 0], $embeddedLocks);
    $checkpoint = pack('V*', 6, $marks[0], $marks[1], $marks[2], $marks[3], $marks[4])
        . pack('C*', $locks[0], $locks[1], $locks[2], $locks[3], $locks[4], $locks[5], $locks[6], $locks[7])
        . pack('V*', 10, 0);

    return SQLiteShmIndex::parse($header . $header . $checkpoint);
};

$fixture = static fn (): SQLiteShmIndex => $makeIndex([]);
$vfsLocks = [
    'read0' => [],
    'read1' => ['wp-reader-a' => 'shared'],
    'read2' => ['wp-reader-b' => 'shared'],
    'read3' => ['wp-reader-latest' => 'shared'],
    'read4' => [],
    'write' => ['wp-import' => 'exclusive'],
    'checkpoint' => ['wp-cron' => 'exclusive'],
];
$vfsPlan = static fn (): array => $fixture()->checkpointPlanWithVfsLocks($vfsLocks);
$releasedPlan = static fn (): array => $fixture()->checkpointPlanWithVfsLocks([
    'read0' => [],
    'read1' => [],
    'read2' => [],
    'read3' => [],
    'read4' => [],
]);
$embeddedPlan = static fn (): array => $makeIndex([], [0, 1, 0, 1])->checkpointPlan();
$overridePlan = static fn (): array => $makeIndex([], [0, 1, 0, 1])->checkpointPlanWithVfsLocks([
    'read1' => [],
    'read2' => ['wp-active' => 'shared'],
    'read3' => [],
]);
$stringHolderPlan = static fn (): array => $fixture()->checkpointPlanWithVfsLocks([
    'read1' => 'wp-string-reader',
    'read2' => ['wp-array-reader'],
]);
$invalidMarkPlan = static fn (): array => $makeIndex([4 => 99])->checkpointPlanWithVfsLocks([
    'read4' => ['wp-bad-reader' => 'shared'],
]);

return [
    'shm index vfs locks adds dependency' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-wal-shm-lock-byte', $vfsPlan()['dependencies'], true)),
    'shm index vfs locks reports lock source' => static fn (TestRunner $t) => $t->same('vfs-shm-lock-table', $vfsPlan()['lock_source']),
    'shm index vfs locks current read locks' => static fn (TestRunner $t) => $t->same([false, true, true, true, false], $vfsPlan()['read_locks']),
    'shm index vfs locks holder map read one' => static fn (TestRunner $t) => $t->same(['wp-reader-a'], $vfsPlan()['lock_holders']['read1']),
    'shm index vfs locks holder map read two' => static fn (TestRunner $t) => $t->same(['wp-reader-b'], $vfsPlan()['lock_holders']['read2']),
    'shm index vfs locks ignores write holder for readers' => static fn (TestRunner $t) => $t->same(false, in_array('wp-import', $vfsPlan()['lock_holders']['read1'], true)),
    'shm index vfs locks pins first stale active reader' => static fn (TestRunner $t) => $t->same(7, $vfsPlan()['checkpoint_pinned_frame']),
    'shm index vfs locks reset blocked by active reader' => static fn (TestRunner $t) => $t->same(true, $vfsPlan()['reset_blocked']),
    'shm index vfs locks checkpoint cannot finish' => static fn (TestRunner $t) => $t->same(false, $vfsPlan()['checkpoint_can_finish']),
    'shm index vfs locks slot zero reusable database reader' => static fn (TestRunner $t) => $t->same(true, in_array(0, $vfsPlan()['reusable_slots'], true)),
    'shm index vfs locks slot four reusable invalid reader' => static fn (TestRunner $t) => $t->same(true, in_array(4, $vfsPlan()['reusable_slots'], true)),
    'shm index vfs locks slot one stale without checkpoint pin' => static fn (TestRunner $t) => $t->same('stale_reader_snapshot', $vfsPlan()['read_marks'][1]['reason']),
    'shm index vfs locks slot one held' => static fn (TestRunner $t) => $t->same(true, $vfsPlan()['read_marks'][1]['read_lock_held']),
    'shm index vfs locks slot one not pinning backfilled frame' => static fn (TestRunner $t) => $t->same(false, $vfsPlan()['read_marks'][1]['pins_checkpoint']),
    'shm index vfs locks slot two pins checkpoint' => static fn (TestRunner $t) => $t->same('reader_pins_checkpoint_backfill', $vfsPlan()['read_marks'][2]['reason']),
    'shm index vfs locks slot two frame' => static fn (TestRunner $t) => $t->same(7, $vfsPlan()['read_marks'][2]['frame']),
    'shm index vfs locks slot three latest commit' => static fn (TestRunner $t) => $t->same('pins_latest_commit', $vfsPlan()['read_marks'][3]['reason']),
    'shm index vfs locks latest does not pin checkpoint reset' => static fn (TestRunner $t) => $t->same(false, $vfsPlan()['read_marks'][3]['pins_checkpoint']),
    'shm index vfs locks invalid read mark remains invalid' => static fn (TestRunner $t) => $t->same('beyond_wal_mx_frame', $vfsPlan()['read_marks'][4]['reason']),
    'shm index vfs locks invalid read mark held false' => static fn (TestRunner $t) => $t->same(false, $vfsPlan()['read_marks'][4]['read_lock_held']),

    'shm index vfs locks released read locks all false' => static fn (TestRunner $t) => $t->same([false, false, false, false, false], $releasedPlan()['read_locks']),
    'shm index vfs locks released checkpoint can finish' => static fn (TestRunner $t) => $t->same(true, $releasedPlan()['checkpoint_can_finish']),
    'shm index vfs locks released reset unblocked' => static fn (TestRunner $t) => $t->same(false, $releasedPlan()['reset_blocked']),
    'shm index vfs locks released pinned frame null' => static fn (TestRunner $t) => $t->same(null, $releasedPlan()['checkpoint_pinned_frame']),
    'shm index vfs locks released reusable all slots' => static fn (TestRunner $t) => $t->same([0, 1, 2, 3, 4], $releasedPlan()['reusable_slots']),
    'shm index vfs locks released read one abandons stale mark' => static fn (TestRunner $t) => $t->same('read_mark_without_read_lock', $releasedPlan()['read_marks'][1]['reason']),
    'shm index vfs locks released read two abandons pin' => static fn (TestRunner $t) => $t->same('read_mark_without_read_lock', $releasedPlan()['read_marks'][2]['reason']),
    'shm index vfs locks released read three abandons latest' => static fn (TestRunner $t) => $t->same('read_mark_without_read_lock', $releasedPlan()['read_marks'][3]['reason']),

    'shm index embedded fixture still parses locks' => static fn (TestRunner $t) => $t->same([false, true, false, true, false], $embeddedPlan()['read_locks']),
    'shm index embedded fixture pinned frame from parsed bytes' => static fn (TestRunner $t) => $t->same(null, $embeddedPlan()['checkpoint_pinned_frame']),
    'shm index vfs locks override embedded read locks' => static fn (TestRunner $t) => $t->same([false, false, true, false, false], $overridePlan()['read_locks']),
    'shm index vfs locks override embedded holder' => static fn (TestRunner $t) => $t->same(['wp-active'], $overridePlan()['lock_holders']['read2']),
    'shm index vfs locks override pins from live lock only' => static fn (TestRunner $t) => $t->same(7, $overridePlan()['checkpoint_pinned_frame']),
    'shm index vfs locks override releases embedded latest lock' => static fn (TestRunner $t) => $t->same(false, $overridePlan()['read_marks'][3]['read_lock_held']),

    'shm index vfs locks accepts string holder' => static fn (TestRunner $t) => $t->same(['wp-string-reader'], $stringHolderPlan()['lock_holders']['read1']),
    'shm index vfs locks accepts list holder' => static fn (TestRunner $t) => $t->same(['wp-array-reader'], $stringHolderPlan()['lock_holders']['read2']),
    'shm index vfs locks sorts unique holders' => static fn (TestRunner $t) => $t->same(['a', 'b'], $fixture()->checkpointPlanWithVfsLocks(['read1' => ['b', 'a', 'b']])['lock_holders']['read1']),
    'shm index vfs locks ignores false keyed holder' => static fn (TestRunner $t) => $t->same([], $fixture()->checkpointPlanWithVfsLocks(['read1' => ['wp-reader' => false]])['lock_holders']['read1']),
    'shm index vfs locks ignores null keyed holder' => static fn (TestRunner $t) => $t->same([], $fixture()->checkpointPlanWithVfsLocks(['read1' => ['wp-reader' => null]])['lock_holders']['read1']),
    'shm index vfs locks missing lock defaults false' => static fn (TestRunner $t) => $t->same([false, false, false, false, false], $fixture()->checkpointPlanWithVfsLocks([])['read_locks']),

    'shm index vfs locks invalid held mark still reusable' => static fn (TestRunner $t) => $t->same(true, in_array(4, $invalidMarkPlan()['reusable_slots'], true)),
    'shm index vfs locks invalid held mark not pinning' => static fn (TestRunner $t) => $t->same(false, $invalidMarkPlan()['read_marks'][4]['pins_checkpoint']),
    'shm index vfs locks invalid held mark records live lock' => static fn (TestRunner $t) => $t->same(true, $invalidMarkPlan()['read_marks'][4]['read_lock_held']),
    'shm index vfs locks invalid held mark reason remains invalid' => static fn (TestRunner $t) => $t->same('beyond_wal_mx_frame', $invalidMarkPlan()['read_marks'][4]['reason']),
];
