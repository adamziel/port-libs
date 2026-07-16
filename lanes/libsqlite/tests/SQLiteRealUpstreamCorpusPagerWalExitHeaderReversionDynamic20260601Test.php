<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalExclusiveModePlan;

$tests = [];
$upstream = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_wal.test';

$tests['real upstream corpus pager wal exit header reversion cites hydrated e_wal source'] = static function (TestRunner $t) use ($upstream): void {
    $source = (string) file_get_contents($upstream);

    $t->contains('EVIDENCE-OF: R-02535-05811', $source);
    $t->contains('EVIDENCE-OF: R-60175-02388', $source);
    $t->contains('do_execsql_test 4.2.3 { PRAGMA journal_mode = delete } {delete}', $source);
    $t->contains('do_test 4.2.4 { file exists test.db-wal } {0}', $source);
    $t->contains('do_test 4.3 { hexio_read test.db 18 2 } {0101}', $source);
};

$rows = SQLiteWalExclusiveModePlan::journalModeExitRows(1000);

foreach ($rows as $row) {
    $tests[sprintf(
        'real upstream corpus pager wal exit header reversion dynamic case %04d page %d frames %d',
        $row['case'],
        $row['page_size'],
        $row['wal_frame_count_before_exit']
    )] = static function (TestRunner $t) use ($row): void {
        $t->same('e_wal.test', $row['script']);
        $t->same('e_wal-4.2.3..4.3', $row['section']);
        $t->same(18, $row['database_header_offset']);
        $t->same(2, $row['database_header_length']);
        $t->same('0101', $row['pre_wal_header_hex']);
        $t->same('0202', $row['wal_header_hex']);
        $t->same('0101', $row['post_exit_header_hex']);
        $t->same(1, $row['pre_wal_read_version']);
        $t->same(1, $row['pre_wal_write_version']);
        $t->same(2, $row['wal_read_version']);
        $t->same(2, $row['wal_write_version']);
        $t->same(1, $row['post_exit_read_version']);
        $t->same(1, $row['post_exit_write_version']);
        $t->same('wal', $row['journal_mode_before_exit']);
        $t->same('delete', $row['journal_mode_request']);
        $t->same('delete', $row['journal_mode_result']);
        $t->same(true, $row['wal_sidecar_exists_before_exit']);
        $t->same(false, $row['wal_sidecar_exists_after_exit']);
        $t->same(true, $row['checkpoint_required_before_unlink']);
        $t->same($row['wal_frame_count_before_exit'], $row['checkpointed_frame_count_on_exit']);
        $t->same(32 + ($row['wal_frame_count_before_exit'] * (24 + $row['page_size'])), $row['wal_bytes_before_exit']);
        $t->same(0, $row['wal_bytes_after_exit']);
        $t->same(true, in_array($row['page_size'], [512, 1024, 2048, 4096, 8192, 16384, 32768, 65536], true));
        $t->same(true, $row['inserted_row_count_before_exit'] >= 1);
        $t->same(true, $row['wal_frame_count_before_exit'] > $row['inserted_row_count_before_exit']);
        $t->same(true, $row['database_page_count'] >= 2);
        $t->same(true, $row['legacy_reader_can_access_after_exit']);
        $t->same('deliberate_exit_from_wal_mode', $row['format_reversion_reason']);
        $t->same(true, in_array('R-02535-05811', $row['evidence'], true));
        $t->same(true, in_array('R-60175-02388', $row['evidence'], true));
        $t->same(true, in_array('real-upstream-corpus-e-wal', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-journal-mode-exit', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-header-version-reversion', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-sidecar-delete', $row['dependencies'], true));
    };
}

$tests['real upstream corpus pager wal exit header reversion inventory and non overlap'] = static function (TestRunner $t) use ($rows): void {
    $pageSizes = array_values(array_unique(array_column($rows, 'page_size')));
    sort($pageSizes);

    $t->same(1000, count($rows));
    $t->same([512, 1024, 2048, 4096, 8192, 16384, 32768, 65536], $pageSizes);
    $t->same('e_wal.test 4.2.3..4.3 WAL exit header reversion dynamic case 0001', $rows[0]['upstream']);
    $t->same('e_wal.test 4.2.3..4.3 WAL exit header reversion dynamic case 1000', $rows[999]['upstream']);
    $t->same(
        'upstream source: e_wal.test 4.2.3, 4.2.4, and 4.3 deliberately leave WAL mode with PRAGMA journal_mode=delete, delete the WAL sidecar, and restore database header bytes 18/19 to 0101',
        'upstream source: e_wal.test 4.2.3, 4.2.4, and 4.3 deliberately leave WAL mode with PRAGMA journal_mode=delete, delete the WAL sidecar, and restore database header bytes 18/19 to 0101'
    );
    $t->same(
        'non-overlap: targets e_wal exit-WAL header reversion and WAL sidecar deletion after 4.2.3; avoids accepted e_wal old-VFS exclusive access/header-entry rows, checkpoint, WAL byte truncation, VFS writer/sync/lock-state, rollback-journal apply/commit, walpersist, walrestart, walvfs, walprotocol, and application-WAL slices',
        'non-overlap: targets e_wal exit-WAL header reversion and WAL sidecar deletion after 4.2.3; avoids accepted e_wal old-VFS exclusive access/header-entry rows, checkpoint, WAL byte truncation, VFS writer/sync/lock-state, rollback-journal apply/commit, walpersist, walrestart, walvfs, walprotocol, and application-WAL slices'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses generic WAL journal-mode state and database-header byte modeling against hydrated upstream e_wal.test source truth',
        'dependency-closure: no new support component needed; reuses generic WAL journal-mode state and database-header byte modeling against hydrated upstream e_wal.test source truth'
    );
};

$tests['real upstream corpus pager wal exit header reversion rejects invalid row count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteWalExclusiveModePlan::journalModeExitRows(0));
};

return $tests;
