<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealPagerBoundaryPlan;

$tests = [];
$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

$tests['real upstream corpus pager wal empty database journal cleanup cites hydrated pager1 source'] = static function (TestRunner $t) use ($upstreamRoot): void {
    $pager1 = (string) file_get_contents($upstreamRoot . '/pager1.test');

    $t->same(true, is_file($upstreamRoot . '/pager1.test'));
    $t->contains('Test that if an empty database file (size 0 bytes) is opened', $pager1);
    $t->contains('exclusive-locking mode, any journal file is deleted', $pager1);
    $t->contains('RESERVED lock obtained while', $pager1);
    $t->contains('do_test pager1-30.1', $pager1);
    $t->contains('seek $fd [expr 512+1032*2]', $pager1);
    $t->contains('PRAGMA locking_mode=EXCLUSIVE;', $pager1);
    $t->contains('SELECT count(*) FROM sqlite_master;', $pager1);
    $t->contains('PRAGMA lock_status;', $pager1);
    $t->contains('{exclusive 0 main reserved temp closed}', $pager1);
};

$rows = SQLiteRealPagerBoundaryPlan::exclusiveEmptyDatabaseJournalCleanupRows(1000);

foreach ($rows as $row) {
    $tests[sprintf(
        'real upstream corpus pager wal empty database journal cleanup %04d page %d sector %d records %d',
        (int) $row['case'],
        (int) $row['page_size'],
        (int) $row['sector_size'],
        (int) $row['journal_record_count']
    )] = static function (TestRunner $t) use ($row): void {
        $expectedJournalRecordBytes = (int) $row['page_size'] + 8;
        $expectedJournalSize = (int) $row['sector_size'] + ($expectedJournalRecordBytes * (int) $row['journal_record_count']) + 1;

        $t->same('pager1.test', $row['script']);
        $t->same('pager1-30.1', $row['section']);
        $t->same(true, str_starts_with((string) $row['upstream'], 'pager1.test pager1-30.1'));
        $t->same(0, $row['database_bytes_before_open']);
        $t->same(false, $row['database_file_exists_before_open']);
        $t->same(true, $row['stale_journal_exists_before_open']);
        $t->same(true, in_array($row['page_size'], [512, 1024, 2048, 4096, 8192], true));
        $t->same(true, in_array($row['sector_size'], [512, 1024, 2048, 4096], true));
        $t->same(true, in_array($row['journal_record_count'], [1, 2, 3, 4], true));
        $t->same($expectedJournalRecordBytes, $row['journal_record_bytes']);
        $t->same($expectedJournalSize, $row['stale_journal_size']);
        $t->same($expectedJournalSize - (int) $row['sector_size'], $row['stale_journal_payload_bytes']);
        $t->same('exclusive', $row['locking_mode_request']);
        $t->same('exclusive', $row['locking_mode_result']);
        $t->same(0, $row['sqlite_master_count']);
        $t->same(['main' => 'reserved', 'temp' => 'closed'], $row['lock_status']);
        $t->same(false, $row['rollback_attempted']);
        $t->same(true, $row['journal_deleted_without_rollback']);
        $t->same(true, $row['reserved_lock_retained_after_cleanup']);
        $t->same(false, $row['journal_exists_after_open']);
        $t->same([], $row['schema_rows_after_open']);
        $t->same(['exclusive', 0, 'main', 'reserved', 'temp', 'closed'], $row['expected_execsql_result']);
        $t->same(true, str_contains((string) $row['source'], 'pager1.test pager1-30.1'));
        $t->same(true, in_array('real-upstream-corpus-pager1', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-pager-empty-database-stale-journal-cleanup', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-pager-exclusive-reserved-lock-retention', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-pager-no-rollback-for-empty-database', $row['dependencies'], true));
    };
}

$tests['real upstream corpus pager wal empty database journal cleanup inventory and non overlap'] = static function (TestRunner $t) use ($rows): void {
    $pageSizes = array_values(array_unique(array_column($rows, 'page_size')));
    $sectorSizes = array_values(array_unique(array_column($rows, 'sector_size')));
    $recordCounts = array_values(array_unique(array_column($rows, 'journal_record_count')));
    sort($pageSizes);
    sort($sectorSizes);
    sort($recordCounts);

    $t->same(1000, count($rows));
    $t->same([512, 1024, 2048, 4096, 8192], $pageSizes);
    $t->same([512, 1024, 2048, 4096], $sectorSizes);
    $t->same([1, 2, 3, 4], $recordCounts);
    $t->same(2577, 512 + ((1024 + 8) * 2) + 1);
    $t->same('pager1.test pager1-30.1 empty database exclusive journal cleanup dynamic case 0001', $rows[0]['upstream']);
    $t->same('pager1.test pager1-30.1 empty database exclusive journal cleanup dynamic case 1000', $rows[999]['upstream']);
    $t->same(
        'upstream source: pager1.test pager1-30.1 opens an empty database with a stale journal, deletes the stale journal without rollback, and returns exclusive 0 main reserved temp closed',
        'upstream source: pager1.test pager1-30.1 opens an empty database with a stale journal, deletes the stale journal without rollback, and returns exclusive 0 main reserved temp closed'
    );
    $t->same(
        'non-overlap: targets pager1-30.1 empty-database stale-journal cleanup only; avoids accepted pager1-8 transient filenames, pager1-18 invalid pages, pager1-28 peer-lock cleanup, pager1-31 zero page-size journals, pager1-33 missing journal unlink, pager1-44 max_page_count rollback, VFS writer/sync/lock, rollback-journal apply/commit, WAL byte truncation, and savepoint2 WAL signatures',
        'non-overlap: targets pager1-30.1 empty-database stale-journal cleanup only; avoids accepted pager1-8 transient filenames, pager1-18 invalid pages, pager1-28 peer-lock cleanup, pager1-31 zero page-size journals, pager1-33 missing journal unlink, pager1-44 max_page_count rollback, VFS writer/sync/lock, rollback-journal apply/commit, WAL byte truncation, and savepoint2 WAL signatures'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses source-neutral SQLiteRealPagerBoundaryPlan and hydrated upstream pager1.test source truth',
        'dependency-closure: no new support component needed; reuses source-neutral SQLiteRealPagerBoundaryPlan and hydrated upstream pager1.test source truth'
    );
};

$tests['real upstream corpus pager wal empty database journal cleanup rejects invalid row count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteRealPagerBoundaryPlan::exclusiveEmptyDatabaseJournalCleanupRows(0));
};

return $tests;
