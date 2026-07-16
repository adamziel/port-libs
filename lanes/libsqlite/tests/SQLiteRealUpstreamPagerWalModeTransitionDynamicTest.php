<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamPagerWalDynamicCorpusPlan;

$tests = [];

foreach (SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walModeTransitionRows() as $row) {
    $tests['real upstream pager wal mode transition dynamic ' . $row['upstream']] = static function (TestRunner $t) use ($row): void {
        $t->same('walmode.test', $row['source_file']);
        $t->same(true, str_starts_with($row['upstream'], 'walmode.test walmode-'));
        $t->same(true, str_contains($row['behavior'], 'wal') || str_contains($row['behavior'], 'journal'));
        $t->same(true, in_array($row['schema'], ['main', 'temp', 'two'], true));
        $t->same(true, $row['case'] >= 1 && $row['case'] <= 1200);
        $t->same(true, in_array($row['page_size'], [1024, 2048, 4096, 8192], true));
        $t->same($row['page_size'], $row['database_size_after_mode_change']);
        $t->same(true, in_array($row['before_mode'], ['off', 'memory', 'persist', 'delete', 'truncate', 'wal'], true));
        $t->same(true, in_array($row['requested_mode'], ['delete', 'persist', 'wal'], true));
        $t->same(true, in_array($row['after_mode'], ['memory', 'persist', 'delete', 'truncate', 'wal'], true));
        $t->same($row['blocks_transition'] ? $row['before_mode'] : $row['after_mode'], $row['reported_mode']);
        $t->same($row['after_mode'] === 'wal' && !$row['blocks_transition'], $row['wal_sidecar_after_pragma']);
        $t->same(in_array($row['after_mode'], ['persist', 'delete', 'truncate'], true), $row['journal_sidecar_after_pragma']);
        $t->same($row['refuses_wal'], !$row['requires_file_backed_database']);
        $t->same($row['schema'] === 'two', $row['schema_independent_mode']);
        $t->same(2, $row['committed_row_count']);
        $t->same(300 + ($row['case'] * 2), $row['committed_value_sum']);
        $t->same(true, in_array('real-upstream-corpus-walmode', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-pager-journal-mode-transition', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-sidecar-lifecycle', $row['dependencies'], true));
    };

    $tests['real upstream pager wal mode transition dynamic committed rows ' . $row['case']] = static function (TestRunner $t) use ($row): void {
        $rows = $row['committed_rows'];

        $t->same(2, count($rows));
        $t->same(1, $rows[0]['setting_id']);
        $t->same(2, $rows[1]['setting_id']);
        $t->same('alpha-' . $row['case'], $rows[0]['key_name']);
        $t->same('beta-' . $row['case'], $rows[1]['key_name']);
        $t->same((string) (100 + $row['case']), $rows[0]['key_value']);
        $t->same((string) (200 + $row['case']), $rows[1]['key_value']);
        $t->same((int) $rows[0]['key_value'] + (int) $rows[1]['key_value'], $row['committed_value_sum']);
        $t->same(true, $row['reported_mode'] !== '' && $row['after_mode'] !== '');
        $t->same(true, $row['connection_count'] === 1 || $row['connection_count'] === 2);
        $t->same(str_contains($row['upstream'], 'walmode-4.6'), $row['blocks_transition']);
        $t->same(str_contains($row['upstream'], 'walmode-5.'), $row['refuses_wal']);
    };
}

$tests['real upstream pager wal mode transition dynamic records hydrated upstream source sections'] = static function (TestRunner $t): void {
    $rows = SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walModeTransitionRows();
    $sections = array_values(array_unique(array_map(
        static fn (array $row): string => preg_replace('/ dynamic transition \\d+$/', '', (string) $row['upstream']),
        $rows
    )));

    $t->same(1200, count($rows));
    $t->same([
        'walmode.test walmode-1.1..1.7',
        'walmode.test walmode-2.1..2.3',
        'walmode.test walmode-3.1..3.2',
        'walmode.test walmode-4.1..4.5',
        'walmode.test walmode-4.6..4.18',
        'walmode.test walmode-5.1.*',
        'walmode.test walmode-5.2.*',
        'walmode.test walmode-5.3.*',
        'walmode.test walmode-6.1..6.5',
        'walmode.test walmode-7.1..7.16',
        'walmode.test walmode-8.1..8.12',
        'walmode.test walmode-8.13..8.22',
    ], $sections);
};

return $tests;
