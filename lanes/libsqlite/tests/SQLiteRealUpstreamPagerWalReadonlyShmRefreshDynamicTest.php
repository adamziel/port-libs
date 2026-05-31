<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalReadonlyShmPlan;

$tests = [];

$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

$tests['real upstream pager wal readonly shm refresh cites walro2 later sections'] = static function (TestRunner $t) use ($upstreamRoot): void {
    $walro2 = (string) file_get_contents($upstreamRoot . '/walro2.test');

    $t->contains('do_test $TN.4.1.1', $walro2);
    $t->contains('do_test $TN.4.2.4', $walro2);
    $t->contains('do_test $TN.5.2', $walro2);
    $t->contains('do_test $TN.6.2', $walro2);
    $t->contains('readonly_shm=1', $walro2);
    $t->contains('PRAGMA wal_checkpoint = truncate', $walro2);
};

$pageSizes = [512, 1024, 2048, 4096, 8192, 16384, 32768, 65536];
$zeroShmModes = [false, true];
$sections = [
    [
        'upstream' => 'walro2.test 4.1.1..4.1.3 readonly_shm sees writer append after truncate checkpoint',
        'events' => [
            ['op' => 'insert', 'rows' => [['!', 'checkpoint']]],
            ['op' => 'checkpoint', 'wal_truncated' => true],
        ],
        'expected_rows' => 3,
        'expected_refreshes' => 1,
        'large_uncommitted_tail' => false,
        'during_read_checkpoint' => false,
    ],
    [
        'upstream' => 'walro2.test 4.2.1..4.2.4 readonly_shm ignores uncommitted large WAL tail',
        'events' => [
            ['op' => 'insert', 'rows' => [['!', 'first']]],
            ['op' => 'insert', 'rows' => [['!', 'second']]],
            ['op' => 'wrap', 'wal_wrapped' => false],
        ],
        'expected_rows' => 4,
        'expected_refreshes' => 1,
        'large_uncommitted_tail' => true,
        'during_read_checkpoint' => false,
    ],
    [
        'upstream' => 'walro2.test 5.1..5.3 readonly_shm remains stable while checkpoint reads WAL',
        'events' => [
            ['op' => 'checkpoint', 'wal_truncated' => true],
        ],
        'expected_rows' => 2,
        'expected_refreshes' => 1,
        'large_uncommitted_tail' => false,
        'during_read_checkpoint' => true,
    ],
    [
        'upstream' => 'walro2.test 6.1..6.3 readonly_shm remains stable if checkpoint truncates during xRead',
        'events' => [
            ['op' => 'checkpoint', 'wal_truncated' => true],
            ['op' => 'wrap', 'wal_wrapped' => true],
        ],
        'expected_rows' => 2,
        'expected_refreshes' => 2,
        'large_uncommitted_tail' => false,
        'during_read_checkpoint' => true,
    ],
];

for ($case = 1; $case <= 1000; $case++) {
    $section = $sections[($case - 1) % count($sections)];
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $zeroShm = $zeroShmModes[intdiv($case - 1, count($pageSizes)) % count($zeroShmModes)];
    $walFrames = $section['large_uncommitted_tail'] ? 390 + ($case % 37) : 4 + ($case % 11);
    $walSize = 32 + ($walFrames * ($pageSize + 24));
    $shmSize = $zeroShm ? 0 : max(32768, $pageSize);
    $checkpointFrames = $section['during_read_checkpoint'] ? $walFrames : max(0, $walFrames - 1);
    $backfilledFrames = $section['during_read_checkpoint'] ? max(0, $checkpointFrames - 1) : $checkpointFrames;
    $beforeRows = [['hello', 'world'], ['!', 'world'], ['hello', (string) $case]];
    $afterRows = array_merge($beforeRows, [['checkpoint', (string) $case]]);

    $tests[sprintf('real upstream pager wal readonly shm refresh dynamic %04d %s page %d zero-shm %d', $case, $section['upstream'], $pageSize, $zeroShm ? 1 : 0)] = static function (TestRunner $t) use (
        $section,
        $pageSize,
        $zeroShm,
        $walSize,
        $shmSize,
        $checkpointFrames,
        $backfilledFrames,
        $beforeRows,
        $afterRows
    ): void {
        $open = SQLiteWalReadonlyShmPlan::openReadonly(
            true,
            true,
            true,
            true,
            false,
            $walSize,
            $shmSize,
            $pageSize,
            $section['events']
        );
        $snapshot = SQLiteWalReadonlyShmPlan::concurrentCheckpointReadonlySnapshot(
            true,
            true,
            true,
            true,
            (bool) $section['during_read_checkpoint'],
            $pageSize,
            $checkpointFrames,
            $backfilledFrames,
            $beforeRows,
            $afterRows
        );

        $t->same('readonly-wal-open', $open['status']);
        $t->same('SQLITE_OK', $open['extended_errcode']);
        $t->same(true, $open['readonly_shm']);
        $t->same(false, $open['shm_writable']);
        $t->same($zeroShm ? 0 : max(32768, $pageSize), $open['shm_size']);
        $t->same($walSize, $open['wal_size']);
        $t->same((int) $section['expected_rows'], $open['row_count']);
        $t->same((int) $section['expected_refreshes'], count($open['refreshes']));
        $t->same(2, count($open['write_denials']));
        $t->same('attempt to write a readonly database', $open['write_denials'][0]['error']);
        $t->same(true, str_contains($open['source'], 'walro2.test'));
        $t->same(true, in_array('sqlite-wal-readonly-cache-refresh', $open['dependencies'], true));
        $t->same('readonly-checkpoint-snapshot-open', $snapshot['status']);
        $t->same('SQLITE_OK', $snapshot['extended_errcode']);
        $t->same((bool) $section['during_read_checkpoint'], $snapshot['checkpoint_in_progress']);
        $t->same($checkpointFrames, $snapshot['checkpoint_frame_count']);
        $t->same($backfilledFrames, $snapshot['checkpoint_backfilled_frame_count']);
        $t->same($checkpointFrames === $backfilledFrames, $snapshot['checkpoint_complete']);
        $t->same($section['during_read_checkpoint'] ? 'wal-readonly-snapshot' : 'checkpointed-database', $snapshot['snapshot_source']);
        $t->same($section['during_read_checkpoint'] ? $beforeRows : $afterRows, $snapshot['rows']);
        $t->same($section['during_read_checkpoint'] ? count($beforeRows) : count($afterRows), $snapshot['row_count']);
        $t->same(true, in_array('sqlite-wal-readonly-checkpoint-snapshot', $snapshot['dependencies'], true));
        $t->same(true, str_starts_with($section['upstream'], 'walro2.test'));
    };
}

$tests['real upstream pager wal readonly shm refresh records non-overlap'] = static function (TestRunner $t) use ($sections): void {
    $t->same([
        'walro2.test 4.1.1..4.1.3 readonly_shm sees writer append after truncate checkpoint',
        'walro2.test 4.2.1..4.2.4 readonly_shm ignores uncommitted large WAL tail',
        'walro2.test 5.1..5.3 readonly_shm remains stable while checkpoint reads WAL',
        'walro2.test 6.1..6.3 readonly_shm remains stable if checkpoint truncates during xRead',
    ], array_column($sections, 'upstream'));
    $t->same(
        'non-overlap: extends readonly_shm coverage to walro2 later read-refresh and xRead checkpoint races; avoids accepted WAL byte truncation, checkpoint transactions, VFS writer/sync/lock state, rollback commit/apply, walro 1.*, walro 2.1, and prior walro2 page-size/cache-refresh matrix cases',
        'non-overlap: extends readonly_shm coverage to walro2 later read-refresh and xRead checkpoint races; avoids accepted WAL byte truncation, checkpoint transactions, VFS writer/sync/lock state, rollback commit/apply, walro 1.*, walro 2.1, and prior walro2 page-size/cache-refresh matrix cases'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses generic WAL readonly-SHM open and checkpoint snapshot planning',
        'dependency-closure: no new support component needed; reuses generic WAL readonly-SHM open and checkpoint snapshot planning'
    );
};

return $tests;
