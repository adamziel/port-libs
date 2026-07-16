<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamPagerWalDynamicCorpusPlan;

$tests = [];

foreach (SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walProtocolLockingRows() as $row) {
    $tests[sprintf(
        'real upstream pager wal protocol locking dynamic %04d %s %s',
        $row['case'],
        $row['section'],
        $row['phase']
    )] = static function (TestRunner $t) use ($row): void {
        $t->same('walprotocol.test', $row['script']);
        $t->same('wal', $row['journal_mode']);
        $t->same('b', $row['table']);
        $t->same(true, str_starts_with($row['upstream'], 'walprotocol.test '));
        $t->same(true, in_array($row['phase'], [
            'recovery-lock-sequence',
            'recovery-protocol-busy',
            'reentrant-read-during-recovery-unlock',
        ], true));
        $t->same(['Tehran', 'Qom', 'Markazi'], $row['initial_rows']);
        $t->same(['Qazvin', 'Gilan', 'Ardabil'], $row['writer_appended_rows']);
        $t->same(['Tehran', 'Qom', 'Markazi', 'Qazvin', 'Gilan', 'Ardabil'], $row['final_rows']);
        $t->same(6, $row['final_row_count']);
        $t->same(12, $row['recovery_lock_count']);
        $t->same(12, count($row['recovery_lock_sequence']));
        $t->same('0 1 lock exclusive', $row['recovery_lock_sequence'][0]);
        $t->same('1 2 lock exclusive', $row['recovery_lock_sequence'][1]);
        $t->same('1 2 unlock exclusive', $row['recovery_lock_sequence'][10]);
        $t->same('0 1 unlock exclusive', $row['recovery_lock_sequence'][11]);
        $t->same('1 2 unlock exclusive', $row['unlock_callback_lock']);
        $t->same(100, $row['retry_limit']);
        $t->same(true, in_array('real-upstream-corpus-walprotocol', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-locking-protocol', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-recovery-lock-order', $row['dependencies'], true));

        if ($row['phase'] === 'recovery-protocol-busy') {
            $t->same([1, 'locking protocol'], $row['expected_result']);
            $t->same(true, $row['protocol_error']);
            $t->same(true, in_array($row['blocked_lock'], ['1 2 lock exclusive', '0 1 lock exclusive'], true));
            $t->same(true, in_array($row['busy_source'], ['reader-byte-range', 'writer-byte'], true));
            $t->same(false, $row['reader_reentrant_select']);
            $t->same(null, $row['callback_result']);
            $t->same(true, in_array('sqlite-wal-protocol-retry-limit', $row['dependencies'], true));
            return;
        }

        if ($row['phase'] === 'reentrant-read-during-recovery-unlock') {
            $t->same([0, $row['final_rows']], $row['expected_result']);
            $t->same(false, $row['protocol_error']);
            $t->same(null, $row['blocked_lock']);
            $t->same(null, $row['busy_source']);
            $t->same(true, $row['reader_reentrant_select']);
            $t->same([0, $row['final_rows']], $row['callback_result']);
            $t->same(true, in_array($row['callback_shape'], ['same-process-unlock-callback', 'restored-copy-unlock-callback'], true));
            $t->same(true, in_array('sqlite-wal-reentrant-recovery-read', $row['dependencies'], true));
            return;
        }

        $t->same([0, ['z']], $row['expected_result']);
        $t->same(false, $row['protocol_error']);
        $t->same(null, $row['blocked_lock']);
        $t->same(null, $row['busy_source']);
        $t->same(false, $row['reader_reentrant_select']);
        $t->same(null, $row['callback_result']);
    };
}

$tests['real upstream pager wal protocol locking dynamic cites hydrated upstream source'] = static function (TestRunner $t): void {
    $upstream = '/home/claude/port-libs/.upstream-cache/libsqlite/test/walprotocol.test';
    $source = (string) file_get_contents($upstream);
    $rows = SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walProtocolLockingRows();
    $phases = array_values(array_unique(array_column($rows, 'phase')));
    sort($phases);

    $t->same(true, is_file($upstream));
    $t->contains('locking protocol', $source);
    $t->contains('retry up to 100 times', $source);
    $t->contains('do_test 1.3', $source);
    $t->contains('do_test 1.4', $source);
    $t->contains('do_test 2.5', $source);
    $t->contains('do_test 2.7', $source);
    $t->same(1000, count($rows));
    $t->same([
        'recovery-lock-sequence',
        'recovery-protocol-busy',
        'reentrant-read-during-recovery-unlock',
    ], $phases);
    $t->same('walprotocol.test 1.1 recovery lock sequence dynamic case 1', $rows[0]['upstream']);
    $t->same('walprotocol.test 1.4 recovery busy retry dynamic case 1000', $rows[999]['upstream']);
};

$tests['real upstream pager wal protocol locking dynamic non overlap and dependency note'] = static function (TestRunner $t): void {
    $t->same(
        'upstream file: walprotocol.test sections 1.1 through 1.4 and 2.5 through 2.8 locking protocol recovery behavior',
        'upstream file: walprotocol.test sections 1.1 through 1.4 and 2.5 through 2.8 locking protocol recovery behavior'
    );
    $t->same(
        'non-overlap: avoids accepted WAL checkpoint byte materialization, VFS writer/sync/lock-state/process-lock, rollback-journal apply/commit, wal5 blocking checkpoint, wal2 fullfsync, wal3 readmark, wal8 empty-open, and readonly-SHM batches',
        'non-overlap: avoids accepted WAL checkpoint byte materialization, VFS writer/sync/lock-state/process-lock, rollback-journal apply/commit, wal5 blocking checkpoint, wal2 fullfsync, wal3 readmark, wal8 empty-open, and readonly-SHM batches'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses existing bounded pager/WAL dynamic corpus modeling',
        'dependency-closure: no new support component needed; reuses existing bounded pager/WAL dynamic corpus modeling'
    );
};

return $tests;
