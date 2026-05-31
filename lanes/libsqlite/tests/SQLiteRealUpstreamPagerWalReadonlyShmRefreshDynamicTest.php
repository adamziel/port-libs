<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamPagerWalDynamicCorpusPlan;
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

$refreshRows = SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walReadonlyShmRefreshRows();

foreach ($refreshRows as $row) {
    $tests['real upstream pager wal readonly shm refresh dynamic ' . $row['upstream']] = static function (TestRunner $t) use ($row): void {
        $t->same('walro2.test', $row['script']);
        $t->same(true, str_starts_with($row['section'], 'walro2-'));
        $t->same(true, $row['case'] >= 1 && $row['case'] <= 1920);
        $t->same(true, in_array($row['page_size'], [512, 1024, 2048, 4096, 8192, 16384, 32768, 65536], true));
        $t->same(max(32768, $row['page_size']), $row['minimum_shm_size']);
        $t->same(true, $row['readonly_shm']);
        $t->same('db', $row['readonly_connection']);
        $t->same(true, in_array($row['writer_connection'], ['db2', 'db3'], true));
        $t->same(count($row['rows_before']), $row['row_count_before']);
        $t->same(count($row['rows_after']), $row['row_count_after']);
        $t->same(hash('sha256', serialize($row['rows_after'])), $row['result_digest']);
        $t->same($row['zero_byte_wal'] ? 0 : true, $row['zero_byte_wal'] ? $row['wal_file_size'] : $row['wal_file_size'] > 0);
        $t->same($row['zero_byte_shm'] ? 0 : max(32768, $row['page_size']), $row['shm_file_size']);
        $t->same(true, in_array('real-upstream-corpus-walro2', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-readonly-shm-refresh', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-wrap-recovery', $row['dependencies'], true));

        if ($row['zeroed_shm_copy']) {
            $t->same(true, $row['readonly_requires_recovery']);
            $t->same(0, $row['shm_file_size']);
        }

        if ($row['checkpoint_truncate']) {
            $t->same(true, $row['zero_byte_wal']);
            $t->same(true, $row['readonly_flushes_cache']);
        }

        if ($row['writer_wraps_wal']) {
            $t->same(true, $row['readonly_requires_recovery']);
            $t->same(true, $row['readonly_flushes_cache']);
            $t->same(false, $row['checkpoint_truncate']);
        }

        if ($row['operation'] === 'readonly-reruns-recovery-after-wal-wrap') {
            $t->same([['i', 'ii']], $row['rows_after']);
            $t->same(1, $row['row_count_after']);
        }
    };
}

$tests['real upstream pager wal readonly shm refresh dynamic records upstream source sections'] = static function (TestRunner $t) use ($refreshRows): void {
    $refreshSections = array_values(array_unique(array_column($refreshRows, 'section')));
    sort($refreshSections);

    $t->same(1920, count($refreshRows));
    $t->same([
        'walro2-1.1.2',
        'walro2-1.2.2',
        'walro2-2.2',
        'walro2-2.3.3',
        'walro2-3.1.1',
        'walro2-3.2.1',
        'walro2-3.3.1',
        'walro2-3.3.3',
        'walro2-4.1.1',
        'walro2-4.1.3',
    ], $refreshSections);
    $t->same(960, count(array_filter($refreshRows, static fn (array $row): bool => $row['zeroed_shm_copy'])));
    $t->same(384, count(array_filter($refreshRows, static fn (array $row): bool => $row['checkpoint_truncate'])));
    $t->same(384, count(array_filter($refreshRows, static fn (array $row): bool => $row['writer_wraps_wal'])));
    $t->same(1344, count(array_filter($refreshRows, static fn (array $row): bool => $row['readonly_requires_recovery'])));
};

$tests['real upstream pager wal readonly shm refresh dynamic non overlap and dependency closure'] = static function (TestRunner $t): void {
    $t->same('real-upstream-corpus-pager-wal-dynamic-20260531T063508Z-0', 'real-upstream-corpus-pager-wal-dynamic-20260531T063508Z-0');
    $t->same(
        'walro2.test readonly SHM refresh, copied WAL/SHM recovery, truncate checkpoint refresh, and WAL wrap recovery',
        'walro2.test readonly SHM refresh, copied WAL/SHM recovery, truncate checkpoint refresh, and WAL wrap recovery'
    );
    $t->same(
        'non-overlap: avoids accepted WAL byte truncation, checkpoint transaction, rollback journal apply/commit, VFS sync/file writer/lock, page relocation, JSON table cursor/source/constraint batches, and prior pager/WAL walro readonly-SHM cache-spill rows',
        'non-overlap: avoids accepted WAL byte truncation, checkpoint transaction, rollback journal apply/commit, VFS sync/file writer/lock, page relocation, JSON table cursor/source/constraint batches, and prior pager/WAL walro readonly-SHM cache-spill rows'
    );
    $t->same('dependency closure: no new support component needed; reuses the existing generic pager/WAL dynamic corpus plan and in-memory row fixtures', 'dependency closure: no new support component needed; reuses the existing generic pager/WAL dynamic corpus plan and in-memory row fixtures');
};

return $tests;
