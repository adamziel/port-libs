<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamPagerWalDynamicCorpusPlan;

$tests = [];

$tests['real upstream corpus pager wal readonly shm cache spill cites hydrated walro section'] = static function (TestRunner $t): void {
    $upstream = '/home/claude/port-libs/.upstream-cache/libsqlite/test/walro.test';

    $t->same(true, is_file($upstream));
    $t->same(true, str_contains((string) file_get_contents($upstream), 'do_test 1.4.4.1'));
    $t->same(true, str_contains((string) file_get_contents($upstream), 'PRAGMA cache_size = 10'));
    $t->same(true, str_contains((string) file_get_contents($upstream), 'file size test.db-wal'));
};

$rows = SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walReadonlyShmCacheSpillRows();

$tests['real upstream corpus pager wal readonly shm cache spill row count'] = static function (TestRunner $t) use ($rows): void {
    $t->same(1024, count($rows));
    $t->same(512, count(array_filter($rows, static fn (array $row): bool => $row['phase'] === 'uncommitted-cache-spill-hidden')));
    $t->same(512, count(array_filter($rows, static fn (array $row): bool => $row['phase'] === 'committed-cache-spill-visible')));
};

foreach ($rows as $case => $row) {
    $tests[sprintf('real upstream corpus pager wal readonly shm cache spill %04d %s', $case, $row['phase'])] = static function (TestRunner $t) use ($row): void {
        $t->same('walro.test', $row['script']);
        $t->same('walro-1.4.4.1..1.4.4.2', $row['section']);
        $t->same(true, str_starts_with($row['upstream'], 'walro.test 1.4.4 cache-spill generated row '));
        $t->same(true, $row['rowid'] >= 0 && $row['rowid'] < 512);
        $t->same(1024, $row['page_size']);
        $t->same(10, $row['cache_size_pages']);
        $t->same(9, $row['doubling_rounds']);
        $t->same(512, $row['generated_row_count']);
        $t->same('db2', $row['writer_connection']);
        $t->same('db', $row['readonly_connection']);
        $t->same(true, $row['readonly_shm']);
        $t->same([0, 3, 3], $row['checkpoint_before_writer']);
        $t->same(true, in_array($row['wal_size_during_writer'], [147800, 148848], true));
        $t->same(9, $row['reader_snapshot_rows_before_commit']);
        $t->same(521, $row['reader_snapshot_rows_after_commit']);
        $t->same(true, $row['commit_required_for_visibility']);
        $t->same(true, in_array('real-upstream-corpus-walro', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-readonly-shm-cache-spill', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-log-wrap-snapshot-stability', $row['dependencies'], true));

        if ($row['phase'] === 'uncommitted-cache-spill-hidden') {
            $t->same(false, $row['visible_to_readonly_reader']);
            $t->same(true, $row['writer_transaction_open']);
            $t->same(['1', '2', '3', '4', '5', '6'], $row['reader_sees_t1_tail']);
        } else {
            $t->same(true, $row['visible_to_readonly_reader']);
            $t->same(false, $row['writer_transaction_open']);
            $t->same($row['left_length'], strlen($row['reader_sees_t2_row'][0]));
            $t->same($row['right_length'], strlen($row['reader_sees_t2_row'][1]));
            $t->same($row['left_prefix'], substr($row['reader_sees_t2_row'][0], 0, 12));
            $t->same($row['right_prefix'], substr($row['reader_sees_t2_row'][1], 0, 12));
            $t->same($row['payload_digest'], hash('sha256', $row['reader_sees_t2_row'][0] . "\0" . $row['reader_sees_t2_row'][1]));
        }
    };
}

return $tests;
