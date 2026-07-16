<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteShmIndex;

return [
    'ignores abandoned sqlite shm read marks whose read locks are not held' => static function (TestRunner $t): void {
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

        $t->same('ready', $plan['status']);
        $t->same(9, $plan['mx_frame']);
        $t->same(3, $plan['backfilled_frame_count']);
        $t->same(7, $plan['backfill_attempted_frame_count']);
        $t->same([false, true, false, true, false], $plan['read_locks']);
        $t->same(4, $plan['checkpoint_pinned_frame']);
        $t->same(false, $plan['checkpoint_can_finish']);
        $t->same(true, $plan['reset_blocked']);
        $t->same([0, 2, 4], $plan['reusable_slots']);
        $t->same(['sqlite-shm-index', 'wal-index-read-marks', 'wal-index-read-locks', 'checkpoint-backfill-state'], $plan['dependencies']);

        $t->same(0, $plan['read_marks'][0]['slot']);
        $t->same(0, $plan['read_marks'][0]['frame']);
        $t->same(false, $plan['read_marks'][0]['read_lock_held']);
        $t->same(false, $plan['read_marks'][0]['pins_checkpoint']);
        $t->same('database_only_reader', $plan['read_marks'][0]['reason']);

        $t->same(1, $plan['read_marks'][1]['slot']);
        $t->same(4, $plan['read_marks'][1]['frame']);
        $t->same(true, $plan['read_marks'][1]['read_lock_held']);
        $t->same(true, $plan['read_marks'][1]['valid']);
        $t->same(true, $plan['read_marks'][1]['stale']);
        $t->same(true, $plan['read_marks'][1]['pins_checkpoint']);
        $t->same('reader_pins_checkpoint_backfill', $plan['read_marks'][1]['reason']);

        $t->same(2, $plan['read_marks'][2]['slot']);
        $t->same(6, $plan['read_marks'][2]['frame']);
        $t->same(false, $plan['read_marks'][2]['read_lock_held']);
        $t->same(true, $plan['read_marks'][2]['valid']);
        $t->same(true, $plan['read_marks'][2]['stale']);
        $t->same(false, $plan['read_marks'][2]['pins_checkpoint']);
        $t->same('read_mark_without_read_lock', $plan['read_marks'][2]['reason']);

        $t->same(3, $plan['read_marks'][3]['slot']);
        $t->same(9, $plan['read_marks'][3]['frame']);
        $t->same(true, $plan['read_marks'][3]['read_lock_held']);
        $t->same(false, $plan['read_marks'][3]['stale']);
        $t->same(false, $plan['read_marks'][3]['pins_checkpoint']);
        $t->same('pins_latest_commit', $plan['read_marks'][3]['reason']);

        $t->same(4, $plan['read_marks'][4]['slot']);
        $t->same(12, $plan['read_marks'][4]['frame']);
        $t->same(false, $plan['read_marks'][4]['read_lock_held']);
        $t->same(false, $plan['read_marks'][4]['valid']);
        $t->same(false, $plan['read_marks'][4]['pins_checkpoint']);
        $t->same('beyond_wal_mx_frame', $plan['read_marks'][4]['reason']);

        $array = $index->toArray();
        $t->same([false, true, false, true, false], $array['read_locks']);
        $t->same([false, true, false, true, false], $array['checkpoint_plan']['read_locks']);
        $t->same(false, $array['read_marks'][2]['read_lock_held']);
        $t->same('read_mark_without_read_lock', $array['read_marks'][2]['reason']);

        $allAbandoned = SQLiteShmIndex::parse($header . $header . (pack('V*', 7, 0, 4, 6, 9, 0xffffffff) . str_repeat("\0", 8) . pack('V*', 7, 0)));
        $abandonedPlan = $allAbandoned->checkpointPlan();
        $t->same(null, $abandonedPlan['checkpoint_pinned_frame']);
        $t->same(true, $abandonedPlan['checkpoint_can_finish']);
        $t->same(false, $abandonedPlan['reset_blocked']);
        $t->same([0, 1, 2, 3, 4], $abandonedPlan['reusable_slots']);
        $t->same('read_mark_without_read_lock', $abandonedPlan['read_marks'][1]['reason']);
        $t->same('read_mark_without_read_lock', $abandonedPlan['read_marks'][2]['reason']);
        $t->same(false, $abandonedPlan['read_marks'][3]['pins_checkpoint']);

        $staleHeader = SQLiteShmIndex::parse($header . substr_replace($header, pack('V', 28), 8, 4) . $checkpoint);
        $t->same('stale-header-copy', $staleHeader->checkpointPlan()['status']);
        $t->same(4, $staleHeader->checkpointPlan()['checkpoint_pinned_frame']);
    },
];
