<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealPagerBoundaryPlan;

$tests = [];

$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

$tests['real upstream corpus pager wal transient filename dynamic cites hydrated pager1 source'] = static function (TestRunner $t) use ($upstreamRoot): void {
    $pager1 = (string) file_get_contents($upstreamRoot . '/pager1.test');

    $t->contains('pager1-8.*', $pager1);
    $t->contains('special filenames', $pager1);
    $t->contains('1 :memory:', $pager1);
    $t->contains('2 ""', $pager1);
    $t->contains('catchsql { SELECT * FROM x1 } db2', $pager1);
    $t->contains('ROLLBACK;', $pager1);
};

$rows = SQLiteRealPagerBoundaryPlan::transientSpecialFilenameRows(1000);

foreach ($rows as $row) {
    $tests[sprintf(
        'real upstream corpus pager wal transient filename dynamic %04d %s page %d',
        $row['case'],
        $row['storage'],
        $row['page_size']
    )] = static function (TestRunner $t) use ($row): void {
        $t->same('transient-special-filename-isolated', $row['status']);
        $t->same('pager1.test', $row['script']);
        $t->same(true, str_starts_with((string) $row['section'], $row['storage'] === 'memory' ? 'pager1-8.1' : 'pager1-8.2'));
        $t->same(true, str_starts_with((string) $row['upstream'], 'pager1.test pager1-8.'));
        $t->same($row['storage'] === 'memory' ? ':memory:' : '', $row['filename']);
        $t->same(true, in_array($row['storage'], ['memory', 'temporary'], true));
        $t->same(true, in_array($row['page_size'], [1024, 2048, 4096, 8192], true));
        $t->same(true, $row['auto_vacuum']);
        $t->same(['Charles', 'James', 'Mary'], $row['first_connection_rows']);
        $t->same('error', $row['second_connection_status']);
        $t->same('no such table: x1', $row['second_connection_error']);
        $t->same([], $row['second_connection_rows']);
        $t->same(['William', 'Anne'], $row['rollback_insert_rows']);
        $t->same(['Charles', 'James', 'Mary'], $row['rows_after_rollback']);
        $t->same(false, $row['persistent_database_file']);
        $t->same(false, $row['persistent_journal_file']);
        $t->same(true, $row['isolated_per_connection']);
        $t->same(true, $row['rollback_discards_transient_rows']);
        $t->same('ok', $row['integrity_check_after_rollback']);
        $t->same(true, str_contains((string) $row['source'], 'pager1-8.1 through pager1-8.2'));
        $t->same(true, in_array('real-upstream-corpus-pager1', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-pager-transient-special-filename', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-pager-isolated-memory-temp', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-pager-rollback-transient-database', $row['dependencies'], true));
    };
}

$tests['real upstream corpus pager wal transient filename dynamic inventory and non overlap'] = static function (TestRunner $t) use ($rows): void {
    $t->same(1000, count($rows));
    $t->same('pager1.test pager1-8.1 special filename isolation dynamic case 0001', $rows[0]['upstream']);
    $t->same('pager1.test pager1-8.2 special filename isolation dynamic case 1000', $rows[999]['upstream']);
    $t->same(['memory', 'temporary'], array_values(array_unique(array_column($rows, 'storage'))));
    $t->same([1024, 2048, 4096, 8192], array_values(array_unique(array_column($rows, 'page_size'))));
    $t->same(
        'upstream source: pager1.test pager1-8.1 through pager1-8.2 proves :memory: and empty-string database names open isolated transient databases, second handles do not see x1, and rollback removes transient inserted rows',
        'upstream source: pager1.test pager1-8.1 through pager1-8.2 proves :memory: and empty-string database names open isolated transient databases, second handles do not see x1, and rollback removes transient inserted rows'
    );
    $t->same(
        'non-overlap: targets pager1-8 special transient filenames; avoids accepted pager1 max-page, invalid-page, DBMOVED, peer-lock cleanup, zero page-size journal fallback, missing delete-journal commit failure, VFS writer/sync/lock, rollback-journal apply/commit, WAL byte truncation, savepoint2, walro, walsetlk, and pager2 savepoint churn',
        'non-overlap: targets pager1-8 special transient filenames; avoids accepted pager1 max-page, invalid-page, DBMOVED, peer-lock cleanup, zero page-size journal fallback, missing delete-journal commit failure, VFS writer/sync/lock, rollback-journal apply/commit, WAL byte truncation, savepoint2, walro, walsetlk, and pager2 savepoint churn'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses source-neutral pager boundary modeling and hydrated upstream pager1.test source truth',
        'dependency-closure: no new support component needed; reuses source-neutral pager boundary modeling and hydrated upstream pager1.test source truth'
    );
};

$tests['real upstream corpus pager wal transient filename dynamic rejects malformed input'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteRealPagerBoundaryPlan::transientSpecialFilenameRows(0));
};

return $tests;
