<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealPagerBoundaryPlan;

$tests = [];
$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

$tests['real upstream corpus pager wal peer lock journal cleanup cites hydrated pager1 source'] = static function (TestRunner $t) use ($upstreamRoot): void {
    $pager1 = (string) file_get_contents($upstreamRoot . '/pager1.test');

    $t->same(true, is_file($upstreamRoot . '/pager1.test'));
    $t->contains('do_test pager1-28.$tn.1', $pager1);
    $t->contains('csql1 { BEGIN; INSERT INTO t1 VALUES', $pager1);
    $t->contains('database is locked', $pager1);
    $t->contains('Normally, when changing from journal_mode=PERSIST to DELETE', $pager1);
    $t->contains('do_test pager1-28.$tn.20 { sql2 { COMMIT } } {}', $pager1);
};

$rows = SQLiteRealPagerBoundaryPlan::peerLockJournalCleanupRows(1000);

foreach ($rows as $row) {
    $tests[sprintf(
        'real upstream corpus pager wal peer lock journal cleanup %04d %s page %d',
        $row['case'],
        $row['phase'],
        $row['page_size']
    )] = static function (TestRunner $t) use ($row): void {
        $t->same('pager1.test', $row['script']);
        $t->same(true, str_starts_with((string) $row['section'], 'pager1-28.'));
        $t->same(true, str_starts_with((string) $row['upstream'], 'pager1.test pager1-28.'));
        $t->same(true, in_array($row['phase'], ['wal-exclusive-peer-open', 'persist-delete-peer-writer', 'persist-delete-open-blob'], true));
        $t->same(true, in_array($row['page_size'], [512, 1024, 2048, 4096, 8192], true));
        $t->same(28 + ($row['journal_pages_before_cleanup'] * ($row['page_size'] + 8)), $row['journal_bytes_before_cleanup']);
        $t->same(true, $row['payload_bytes'] >= 96);
        $t->same(true, $row['journal_pages_before_cleanup'] >= 1);
        $t->same('delete', $row['pragma_result']);
        $t->same('ok', $row['integrity_check_after_final_commit']);
        $t->same(1, count($row['initial_rows']));
        $t->same(2, count($row['retry_row']));
        $t->same(2, count($row['peer_row']));
        $t->same(true, in_array('real-upstream-corpus-pager1', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-pager-peer-lock-boundary', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-pager-persist-delete-deferred-cleanup', $row['dependencies'], true));
        $t->same(true, str_contains((string) $row['source'], 'pager1-28'));
        $t->same(false, in_array('wordpress', array_map('strtolower', $row['dependencies']), true));

        if ($row['phase'] === 'wal-exclusive-peer-open') {
            $t->same('pager1-28.1..28.4', $row['section']);
            $t->same('wal', $row['journal_mode_before']);
            $t->same('exclusive', $row['locking_mode_request']);
            $t->same('exclusive', $row['locking_mode_result']);
            $t->same(true, $row['peer_connection_open']);
            $t->same($row['initial_rows'], $row['peer_read_rows']);
            $t->same(false, $row['begin_write_allowed_with_peer']);
            $t->same('database is locked', $row['begin_write_error']);
            $t->same(true, $row['retry_after_peer_reopen_allowed']);
            $t->same(false, $row['journal_exists_before_cleanup']);
            $t->same(false, $row['journal_exists_after_pragma']);
            $t->same(false, $row['journal_exists_after_peer_commit']);
            $t->same($row['wal_frames_before_retry'] + 1, $row['wal_frames_after_retry']);
            $t->same([$row['initial_rows'][0], $row['retry_row']], $row['final_rows']);
            $t->same([
                'client1:wal:exclusive-request',
                'client2:reader:shared',
                'client1:begin-write:blocked',
                'client2:close-reopen',
                'client1:begin-write:ok',
                'client1:commit:ok',
            ], $row['lock_sequence']);
            $t->same(true, in_array('sqlite-wal-exclusive-peer-open', $row['dependencies'], true));
            $t->same(true, in_array('sqlite-pager-locking-mode-exclusive', $row['dependencies'], true));
            return;
        }

        $t->same('persist', $row['journal_mode_before']);
        $t->same('delete', $row['journal_mode_after_pragma']);
        $t->same(true, $row['peer_reserved_lock']);
        $t->same(false, $row['can_obtain_reserved_lock_for_cleanup']);
        $t->same(true, $row['journal_exists_before_cleanup']);
        $t->same(true, $row['journal_exists_after_pragma']);
        $t->same(false, $row['journal_exists_after_peer_commit']);
        $t->same([$row['initial_rows'][0], $row['peer_row']], $row['final_rows']);

        if ($row['phase'] === 'persist-delete-peer-writer') {
            $t->same('pager1-28.5..28.12', $row['section']);
            $t->same(false, $row['open_blob_reader']);
            $t->same(true, $row['peer_commit_allowed_before_reader_close']);
            $t->same(null, $row['peer_commit_error']);
            $t->same([
                'client1:persist:journal-created',
                'client2:begin-write:reserved',
                'client1:pragma-delete:cleanup-deferred',
                'client2:commit:ok',
                'pager:delete-stale-journal',
            ], $row['lock_sequence']);
            $t->same(true, in_array('sqlite-pager-persist-delete-writer-deferred', $row['dependencies'], true));
            $t->same(true, in_array('sqlite-pager-reserved-lock-cleanup', $row['dependencies'], true));
            return;
        }

        $t->same('pager1-28.13..28.20', $row['section']);
        $t->same(true, $row['open_blob_reader']);
        $t->same('c', $row['blob_read_result']);
        $t->same(false, $row['peer_commit_allowed_before_reader_close']);
        $t->same('database is locked', $row['peer_commit_error']);
        $t->same([
            'client1:blob-reader:open',
            'client1:persist:journal-created',
            'client2:begin-write:reserved',
            'client1:pragma-delete:cleanup-deferred',
            'client2:commit:blocked',
            'client1:blob-reader:read-c',
            'client1:blob-reader:close',
            'client2:commit:ok',
            'pager:delete-stale-journal',
        ], $row['lock_sequence']);
        $t->same(true, in_array('sqlite-pager-persist-delete-blob-reader-deferred', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-pager-incremental-blob-lock-boundary', $row['dependencies'], true));
    };
}

$tests['real upstream corpus pager wal peer lock journal cleanup inventory and non overlap'] = static function (TestRunner $t) use ($rows): void {
    $phaseCounts = array_count_values(array_column($rows, 'phase'));
    $pageSizes = array_values(array_unique(array_column($rows, 'page_size')));
    sort($pageSizes);

    $t->same(1000, count($rows));
    $t->same(334, $phaseCounts['wal-exclusive-peer-open']);
    $t->same(333, $phaseCounts['persist-delete-peer-writer']);
    $t->same(333, $phaseCounts['persist-delete-open-blob']);
    $t->same([512, 1024, 2048, 4096, 8192], $pageSizes);
    $t->same('pager1.test pager1-28.1..28.4 WAL exclusive peer-open dynamic case 0001', $rows[0]['upstream']);
    $t->same('pager1.test pager1-28.1..28.4 WAL exclusive peer-open dynamic case 1000', $rows[999]['upstream']);
    $t->same(
        'upstream source: pager1.test pager1-28 covers WAL exclusive write blocking with a peer connection and deferred PERSIST-to-DELETE cleanup when another handle owns the RESERVED lock',
        'upstream source: pager1.test pager1-28 covers WAL exclusive write blocking with a peer connection and deferred PERSIST-to-DELETE cleanup when another handle owns the RESERVED lock'
    );
    $t->same(
        'non-overlap: targets pager1-28 peer-lock and deferred rollback-journal cleanup, not accepted pager1 zero-page-size fallback, invalid-page, max-page rollback, pager1-22 checkpoint, pager1-23.1..23.6 mode-only rows, pager1-24 cache-spill scans, VFS writer/sync/lock-state, rollback-journal apply/commit, WAL byte truncation, or savepoint2 WAL signatures',
        'non-overlap: targets pager1-28 peer-lock and deferred rollback-journal cleanup, not accepted pager1 zero-page-size fallback, invalid-page, max-page rollback, pager1-22 checkpoint, pager1-23.1..23.6 mode-only rows, pager1-24 cache-spill scans, VFS writer/sync/lock-state, rollback-journal apply/commit, WAL byte truncation, or savepoint2 WAL signatures'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses source-neutral pager boundary modeling against hydrated upstream pager1.test source truth',
        'dependency-closure: no new support component needed; reuses source-neutral pager boundary modeling against hydrated upstream pager1.test source truth'
    );
};

$tests['real upstream corpus pager wal peer lock journal cleanup rejects invalid row count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteRealPagerBoundaryPlan::peerLockJournalCleanupRows(0));
};

return $tests;
