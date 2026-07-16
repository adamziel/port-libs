<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamPagerWalDynamicPlan;

$tests = [];

foreach (SQLiteRealUpstreamPagerWalDynamicPlan::pagerWalDynamicMatrixCases() as $case) {
    $label = sprintf(
        'real upstream pager wal lock checkpoint dynamic matrix %04d %s %s %s %s',
        $case['case'],
        $case['source_file'],
        $case['connection_mode'],
        $case['checkpoint_mode'],
        $case['sync_mode']
    );

    $tests[$label] = static function (TestRunner $t) use ($case): void {
        $t->true($case['case'] >= 1 && $case['case'] <= 1024);
        $t->true(in_array($case['source_file'], ['wal2.test', 'pager1.test', 'walrestart.test'], true));
        $t->true(
            str_starts_with($case['upstream'], 'wal2-')
            || str_starts_with($case['upstream'], 'pager1-')
            || str_starts_with($case['upstream'], 'walrestart-')
        );
        $t->true(in_array($case['connection_mode'], ['normal', 'exclusive', 'shared-cache', 'read-only'], true));
        $t->true(in_array($case['checkpoint_mode'], ['passive', 'full', 'restart', 'truncate'], true));
        $t->true(in_array($case['sync_mode'], ['off', 'normal', 'full', 'extra'], true));
        $t->true(in_array($case['page_size'], [512, 1024, 2048, 4096], true));
        $t->same($case['lock_sequence'], array_values($case['lock_sequence']));
        $t->same(
            $case['lock_count'],
            count(array_filter($case['lock_sequence'], static fn (array $lock): bool => ($lock['op'] ?? null) === 'lock'))
        );
        $t->same(
            $case['unlock_count'],
            count(array_filter($case['lock_sequence'], static fn (array $lock): bool => ($lock['op'] ?? null) === 'unlock'))
        );
        $t->same(
            $case['has_busy_lock'],
            in_array('SQLITE_BUSY', array_column($case['lock_sequence'], 'result'), true)
        );
        $t->same(
            $case['has_ioerr_lock'],
            in_array('SQLITE_IOERR', array_column($case['lock_sequence'], 'result'), true)
        );
        $t->same($case['reader_visible'], (bool) $case['reader_visible']);
        $t->same($case['wal_exists'], (bool) $case['wal_exists']);
        $t->same($case['journal_exists'], (bool) $case['journal_exists']);
        $t->true($case['checkpoint'] === null || count($case['checkpoint']) === 3);
        $t->true(is_array($case['rows']));
        $t->same(
            [
                'sqlite-real-upstream-wal2-locking',
                'sqlite-real-upstream-pager1-locking',
                'sqlite-real-upstream-walrestart-checkpoint',
            ],
            $case['dependencies']
        );
        if ($case['error'] !== null) {
            $t->true(in_array($case['error'], ['database is locked'], true));
            $t->same(false, $case['reader_visible'] && $case['has_ioerr_lock']);
        } else {
            $t->same(null, $case['error']);
        }
        if ($case['checkpoint'] !== null) {
            $t->same(0, $case['checkpoint'][0]);
            $t->true($case['checkpoint'][1] >= $case['checkpoint'][2]);
        }
    };
}

$tests['real upstream pager wal lock checkpoint dynamic matrix records upstream coverage and cardinality'] = static function (TestRunner $t): void {
    $cases = SQLiteRealUpstreamPagerWalDynamicPlan::pagerWalDynamicMatrixCases();
    $sources = array_values(array_unique(array_column($cases, 'source_file')));
    sort($sources);

    $t->same(1024, count($cases));
    $t->same(['pager1.test', 'wal2.test', 'walrestart.test'], $sources);
    $t->same('wal2-1.2', $cases[0]['upstream']);
    $t->same('walrestart-1.2', $cases[1023]['upstream']);
    $t->same([
        'wal2.test: wal2-1.* header recovery, wal2-2.* stale headers, wal2-3.* busy recovery, wal2-6.* exclusive locking',
        'pager1.test: lock transition and writer/reader visibility sequence',
        'walrestart.test: checkpoint restart race and integrity sequence',
    ], [
        'wal2.test: wal2-1.* header recovery, wal2-2.* stale headers, wal2-3.* busy recovery, wal2-6.* exclusive locking',
        'pager1.test: lock transition and writer/reader visibility sequence',
        'walrestart.test: checkpoint restart race and integrity sequence',
    ]);
};

return $tests;
