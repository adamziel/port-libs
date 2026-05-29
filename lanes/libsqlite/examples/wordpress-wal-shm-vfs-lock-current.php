<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteShmIndex;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

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

$checkpoint = pack('V*', 6, 0, 4, 7, 12, 14)
    . str_repeat("\0", 8)
    . pack('V*', 10, 0);

$index = SQLiteShmIndex::parse($header . $header . $checkpoint);
$plan = $index->checkpointPlanWithVfsLocks([
    'read1' => ['wp-cron-reader' => 'shared'],
    'read2' => ['wp-import-reader' => 'shared'],
    'read3' => ['wp-latest-reader' => 'shared'],
]);

$summary = [
    'scenario' => 'wordpress-wal-shm-vfs-lock-current',
    'database' => '/srv/www/wp-content/database/.ht.sqlite',
    'shm' => '/srv/www/wp-content/database/.ht.sqlite-shm',
    'mxFrame' => $plan['mx_frame'],
    'backfilledFrameCount' => $plan['backfilled_frame_count'],
    'checkpointPinnedFrame' => $plan['checkpoint_pinned_frame'],
    'checkpointCanFinish' => $plan['checkpoint_can_finish'],
    'resetBlocked' => $plan['reset_blocked'],
    'readLocks' => $plan['read_locks'],
    'reusableSlots' => $plan['reusable_slots'],
    'lockSource' => $plan['lock_source'],
    'lockHolders' => $plan['lock_holders'],
    'dependencies' => $plan['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['checkpointPinnedFrame'] === 7);
    assert($summary['checkpointCanFinish'] === false);
    assert($summary['resetBlocked'] === true);
    assert($summary['readLocks'] === [false, true, true, true, false]);
    assert(in_array('vfs-wal-shm-lock-byte-current-source', $summary['dependencies'], true));
    echo "wordpress-wal-shm-vfs-lock-current self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
