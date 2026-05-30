<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteShmIndex;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$encodedPageSize = (1 << 24) | $pageSize;
$header = pack(
    'V*',
    3007000,
    1,
    42,
    $encodedPageSize,
    8,
    4,
    0x10101010,
    0x20202020,
    0x30303030,
    0x40404040,
    0x50505050,
    0x60606060
);
$checkpointInfo = pack('V*', 3, 0, 4, 8, 0xffffffff, 10)
    . "\x00\x01\x01\x00\x00\x00\x00\x00"
    . pack('V*', 6, 0);

$index = SQLiteShmIndex::parse($header . $header . $checkpointInfo);
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
            'valid' => $mark['valid'],
            'pinsCheckpoint' => $mark['pins_checkpoint'],
            'reason' => $mark['reason'],
        ],
        $plan['read_marks']
    ),
], JSON_PRETTY_PRINT) . "\n";
