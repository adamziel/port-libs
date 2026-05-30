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
    27,
    $encodedPageSize,
    9,
    5,
    0x01010101,
    0x02020202,
    0x03030303,
    0x04040404,
    0x05050505,
    0x06060606
);

$checkpoint = pack('V*', 3, 0, 4, 6, 9, 12)
    . "\x00\x01\x00\x01\x00\x00\x00\x00"
    . pack('V*', 7, 0);

$index = SQLiteShmIndex::parse($header . $header . $checkpoint);
$plan = $index->checkpointPlan();

echo json_encode([
    'database' => '/srv/www/wp-content/database/.ht.sqlite',
    'shm' => '/srv/www/wp-content/database/.ht.sqlite-shm',
    'mxFrame' => $plan['mx_frame'],
    'backfilledFrameCount' => $plan['backfilled_frame_count'],
    'checkpointPinnedFrame' => $plan['checkpoint_pinned_frame'],
    'checkpointCanFinish' => $plan['checkpoint_can_finish'],
    'resetBlocked' => $plan['reset_blocked'],
    'readLocks' => $plan['read_locks'],
    'reusableSlots' => $plan['reusable_slots'],
    'dependencies' => $plan['dependencies'],
    'readMarks' => array_map(
        static fn (array $mark): array => [
            'slot' => $mark['slot'],
            'frame' => $mark['frame'],
            'readLockHeld' => $mark['read_lock_held'],
            'valid' => $mark['valid'],
            'pinsCheckpoint' => $mark['pins_checkpoint'],
            'reason' => $mark['reason'],
        ],
        $plan['read_marks']
    ),
], JSON_PRETTY_PRINT) . "\n";
