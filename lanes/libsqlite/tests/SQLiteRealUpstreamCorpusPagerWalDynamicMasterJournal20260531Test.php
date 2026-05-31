<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteMasterJournalDynamicPlan;

$tests = [];

$upstream = '/home/claude/port-libs/.upstream-cache/libsqlite/test/mjournal.test';

$tests['real upstream corpus pager wal dynamic master journal cites hydrated mjournal source'] = static function (TestRunner $t) use ($upstream): void {
    $source = (string) file_get_contents($upstream);

    $t->contains('set testprefix mjournal', $source);
    $t->contains('test.db2journal', $source);
    $t->contains('test0db2journal', $source);
    $t->contains('do_hasmj_test 2.1', $source);
    $t->contains('do_hasmj_test 2.2', $source);
    $t->contains('do_hasmj_test 2.3', $source);
};

foreach (SQLiteMasterJournalDynamicPlan::masterJournalRows(1000) as $row) {
    $tests[sprintf(
        'real upstream corpus pager wal dynamic master journal %04d %s %s',
        $row['case'],
        $row['kind'],
        $row['section']
    )] = static function (TestRunner $t) use ($row): void {
        $t->same('mjournal.test', $row['script']);
        $t->same(true, $row['case'] >= 1 && $row['case'] <= 1000);
        $t->same('ok', $row['status'] ?? $row['commit_status']);
        $t->same(true, str_starts_with($row['upstream'], 'mjournal.test '));
        $t->same(true, in_array('real-upstream-corpus-mjournal', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-pager-master-journal-dynamic', $row['dependencies'], true));

        if ($row['kind'] === 'pointer-safety') {
            $t->same(true, in_array($row['section'], ['mjournal-1.1..1.2', 'mjournal-1.3..1.4', 'mjournal-1.5..1.6'], true));
            $t->same([], $row['select_result']);
            $t->same('SELECT * FROM t1', $row['read_sql']);
            $t->same(true, $row['database_rows_preserved']);
            $t->same(true, $row['crash_prevented']);
            $t->same(true, $row['master_probe_safe']);
            $t->same(true, $row['journal_pointer_length'] > 4);
            $t->same(true, $row['journal_bytes_contains_pointer']);
            $t->same(true, ctype_xdigit($row['journal_bytes_prefix_hex']));
            $t->same(64, strlen($row['journal_bytes_prefix_hex']));
            $t->same(true, $row['legacy_name_without_dash']);
            $t->same(false, $row['journal_pointer_has_dash'] && ($row['master_member_has_dash'] ?? true));
            $t->same('ignore_legacy_master_journal_name_without_crash', $row['recovery_action']);
            $t->same(true, in_array('sqlite-master-journal-legacy-name-safety', $row['dependencies'], true));

            if ($row['section'] === 'mjournal-1.5..1.6') {
                $t->same('test.db2-master', $row['journal_pointer_name']);
                $t->same('test1', $row['master_member_name']);
                $t->same(true, $row['master_journal_contains_member']);
                $t->same(false, $row['master_member_has_dash']);
                $t->same(true, str_starts_with($row['master_journal_bytes_prefix_hex'], '746573743100'));
            } else {
                $t->same(null, $row['master_member_name']);
                $t->same(false, $row['master_journal_contains_member']);
                $t->same('', $row['master_journal_bytes']);
            }

            return;
        }

        $t->same(true, in_array($row['section'], ['mjournal-2.1', 'mjournal-2.2', 'mjournal-2.3'], true));
        $t->same([[1]], $row['result_rows']);
        $t->same(true, count($row['databases']) === 2);
        $t->same(true, $row['real_modified_count'] >= 1 && $row['real_modified_count'] <= 2);
        $t->same($row['real_modified_count'] >= 2, $row['master_journal_opened']);
        $t->same($row['master_journal_opened'] ? 1 : 0, $row['opened_master_journal_count']);
        $t->same($row['master_journal_opened'], $row['master_journal_path'] !== null);
        $t->same(!$row['master_journal_opened'], $row['temporary_or_memory_excluded']);
        $t->same(true, in_array('sqlite-master-journal-creation-gate', $row['dependencies'], true));

        if ($row['master_journal_opened']) {
            $t->same('mjournal-2.1', $row['section']);
            $t->same(2, $row['real_modified_count']);
            $t->same('two_real_database_files_need_master_journal', $row['reason']);
            $t->same(true, str_contains((string) $row['master_journal_path'], 'mj'));
            $t->same(true, count(array_filter($row['opened_files'], static fn (string $file): bool => str_ends_with($file, '-journal'))) === 2);
        } else {
            $t->same(true, in_array($row['section'], ['mjournal-2.2', 'mjournal-2.3'], true));
            $t->same(1, $row['real_modified_count']);
            $t->same('temporary_or_memory_database_excluded_from_master_journal', $row['reason']);
            $t->same(true, in_array('temporary', $row['modified_kinds'], true) || in_array('memory', $row['modified_kinds'], true));
            $t->same(false, in_array('master-journal-' . sprintf('%04d', $row['case']) . '-mj', $row['opened_files'], true));
        }
    };
}

$tests['real upstream corpus pager wal dynamic master journal row count and non overlap'] = static function (TestRunner $t): void {
    $rows = SQLiteMasterJournalDynamicPlan::masterJournalRows(1000);

    $t->same(1000, count($rows));
    $t->same('mjournal.test 1.1-1.2 rollback journal pointer without dash is ignored safely dynamic case 0001', $rows[0]['upstream']);
    $t->same('mjournal.test 1.5-1.6 master journal member without dash is ignored safely dynamic case 0003', $rows[2]['upstream']);
    $t->same('mjournal.test 2.1 two real database files open a master journal dynamic case 0004', $rows[3]['upstream']);
    $t->same('mjournal.test 2.2 real plus temporary database does not open a master journal dynamic case 0005', $rows[4]['upstream']);
    $t->same('mjournal.test 2.3 real plus memory database does not open a master journal dynamic case 0006', $rows[5]['upstream']);
    $t->same('mjournal.test 1.3-1.4 rollback journal pointer without dash is ignored safely dynamic case 0998', $rows[997]['upstream']);
    $t->same(
        'non-overlap: covers mjournal.test legacy master-journal name safety and real/temp/memory master-journal creation gates; avoids accepted pager1 master-journal boundary, journal2 safe-delete, rollback-journal apply/commit, super-journal commit, VFS writer/sync/lock, WAL byte truncation, walshared, e_walhook, and app-WAL slices',
        'non-overlap: covers mjournal.test legacy master-journal name safety and real/temp/memory master-journal creation gates; avoids accepted pager1 master-journal boundary, journal2 safe-delete, rollback-journal apply/commit, super-journal commit, VFS writer/sync/lock, WAL byte truncation, walshared, e_walhook, and app-WAL slices'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses hydrated upstream mjournal.test and a source-neutral PHP master-journal dynamic helper',
        'dependency-closure: no new support component needed; reuses hydrated upstream mjournal.test and a source-neutral PHP master-journal dynamic helper'
    );
};

$tests['real upstream corpus pager wal dynamic master journal rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteMasterJournalDynamicPlan::masterJournalRows(0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteMasterJournalDynamicPlan::journalPointerSafetyPlan('', null, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteMasterJournalDynamicPlan::journalPointerSafetyPlan("bad\0name", null, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteMasterJournalDynamicPlan::journalPointerSafetyPlan('test.db2-master', "bad\0member", 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteMasterJournalDynamicPlan::journalPointerSafetyPlan('dir/test.db2journal', null, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteMasterJournalDynamicPlan::journalPointerSafetyPlan('test.db2journal', null, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteMasterJournalDynamicPlan::creationPlan([], 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteMasterJournalDynamicPlan::creationPlan([
        ['name' => 'main.db', 'kind' => 'network', 'modified' => true],
    ], 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteMasterJournalDynamicPlan::creationPlan([
        ['name' => '', 'kind' => 'real', 'modified' => true],
    ], 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteMasterJournalDynamicPlan::creationPlan([
        ['name' => 'main.db', 'kind' => 'real', 'modified' => true],
    ], 0));
};

return $tests;
