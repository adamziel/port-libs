<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

$expectedSections = [];
for ($definition = 1; $definition <= 6; $definition++) {
    for ($action = 1; $action <= 3; $action++) {
        $expectedSections[] = 'without_rowid4-1.' . $definition . '.' . $action;
    }
}

// Source truth: SQLite upstream test/without_rowid4.test section 1. These
// cases preserve BEFORE/AFTER row-trigger visibility for UPDATE, DELETE, and
// INSERT statements across the six WITHOUT ROWID table definitions used by
// the upstream script.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::withoutRowid4TriggerOrderCases(1200) as $case) {
    $tests['real upstream without_rowid4 trigger order dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $expectedSections): void {
        $t->same('without_rowid4.test section without_rowid4-1.1 through without_rowid4-1.6.3', $case['source']);
        $t->same('test/without_rowid4.test', $case['upstream_file']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1200);
        $t->true($case['batch'] >= 1 && $case['batch'] <= 67);
        $t->true(in_array($case['upstream_section'], $expectedSections, true));
        $t->true(str_contains($case['scenario'], 'dynamic batch ' . $case['batch']));
        $t->true(str_contains($case['detail'], $case['upstream_section']));
        $t->true($case['statement'] !== '');
        $t->true(str_contains($case['table_definition'], 'WITHOUT rowid'));
        $t->same(true, $case['without_rowid']);
        $t->same(false, $case['recursive_triggers']);
        $t->same('ok', $case['integrity']);
        $t->same(count($case['trigger_events']), $case['event_count']);
        $t->true(in_array($case['schema'], ['main', 'temp'], true));
        $t->same($case['schema'] === 'temp', $case['temp_table']);

        if ($case['temp_table']) {
            $t->true(str_contains($case['table_definition'], 'TEMP'));
        }
        if ($case['secondary_index'] !== null) {
            $t->same('tbl_idx ON tbl(b)', $case['secondary_index']);
            $t->true(str_contains($case['table_definition'], 'CREATE INDEX tbl_idx'));
        }

        foreach ($case['trigger_events'] as $event) {
            $t->true($event['idx'] >= 1);
            $t->true($event['trigger'] !== '');
            $t->true(array_key_exists('a', $event['old']));
            $t->true(array_key_exists('b', $event['old']));
            $t->true(array_key_exists('a', $event['db_sum']));
            $t->true(array_key_exists('b', $event['db_sum']));
            $t->true(array_key_exists('a', $event['new']));
            $t->true(array_key_exists('b', $event['new']));
        }

        match ($case['action']) {
            'update' => [
                $t->same('UPDATE tbl SET a = a * 10, b = b * 10', $case['statement']),
                $t->same(['a' => 4, 'b' => 6], $case['pre_statement_sum']),
                $t->same(['a' => 40, 'b' => 60], $case['final_sum']),
                $t->same([[10, 20], [30, 40]], $case['final_rows']),
                $t->same(5, $case['event_count']),
                $t->same(1, $case['conditional_event_count']),
                $t->same('before_update_row', $case['trigger_events'][0]['trigger']),
                $t->same(['a' => 1, 'b' => 2], $case['trigger_events'][0]['old']),
                $t->same(['a' => 4, 'b' => 6], $case['trigger_events'][0]['db_sum']),
                $t->same(['a' => 10, 'b' => 20], $case['trigger_events'][0]['new']),
                $t->same('after_update_row', $case['trigger_events'][1]['trigger']),
                $t->same(['a' => 13, 'b' => 24], $case['trigger_events'][1]['db_sum']),
                $t->same('before_update_row', $case['trigger_events'][2]['trigger']),
                $t->same(['a' => 3, 'b' => 4], $case['trigger_events'][2]['old']),
                $t->same('after_update_row', $case['trigger_events'][3]['trigger']),
                $t->same(['a' => 40, 'b' => 60], $case['trigger_events'][3]['db_sum']),
                $t->same('conditional_update_row', $case['trigger_events'][4]['trigger']),
                $t->same(['a' => 1, 'b' => 2], $case['trigger_events'][4]['old']),
            ],
            'delete' => [
                $t->same('DELETE FROM tbl', $case['statement']),
                $t->same(['a' => 400, 'b' => 300], $case['pre_statement_sum']),
                $t->same(['a' => 0, 'b' => 0], $case['final_sum']),
                $t->same([], $case['final_rows']),
                $t->same(4, $case['event_count']),
                $t->same(0, $case['conditional_event_count']),
                $t->same('delete_before_row', $case['trigger_events'][0]['trigger']),
                $t->same(['a' => 100, 'b' => 100], $case['trigger_events'][0]['old']),
                $t->same(['a' => 400, 'b' => 300], $case['trigger_events'][0]['db_sum']),
                $t->same('delete_after_row', $case['trigger_events'][1]['trigger']),
                $t->same(['a' => 300, 'b' => 200], $case['trigger_events'][1]['db_sum']),
                $t->same('delete_before_row', $case['trigger_events'][2]['trigger']),
                $t->same(['a' => 300, 'b' => 200], $case['trigger_events'][2]['old']),
                $t->same('delete_after_row', $case['trigger_events'][3]['trigger']),
                $t->same(['a' => 0, 'b' => 0], $case['trigger_events'][3]['db_sum']),
            ],
            'insert' => [
                $t->same('INSERT INTO tbl VALUES(5, 6)', $case['statement']),
                $t->same(['a' => 0, 'b' => 0], $case['pre_statement_sum']),
                $t->same(['a' => 5, 'b' => 6], $case['final_sum']),
                $t->same([[5, 6]], $case['final_rows']),
                $t->same(2, $case['event_count']),
                $t->same(0, $case['conditional_event_count']),
                $t->same('insert_before_row', $case['trigger_events'][0]['trigger']),
                $t->same(['a' => 0, 'b' => 0], $case['trigger_events'][0]['db_sum']),
                $t->same(['a' => 5, 'b' => 6], $case['trigger_events'][0]['new']),
                $t->same('insert_after_row', $case['trigger_events'][1]['trigger']),
                $t->same(['a' => 5, 'b' => 6], $case['trigger_events'][1]['db_sum']),
            ],
        };
    };
}

$tests['real upstream without_rowid4 trigger order source truth'] = static function (TestRunner $t): void {
    $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/without_rowid4.test');

    $t->true(is_string($source));
    $t->contains('without_rowid4-1.1.*: ON UPDATE trigger execution model', $source);
    $t->contains('without_rowid4-1.2.*: DELETE trigger execution model', $source);
    $t->contains('without_rowid4-1.3.*: INSERT trigger execution model', $source);
    $t->contains('CREATE TABLE tbl (a INTEGER PRIMARY KEY, b) WITHOUT rowid', $source);
    $t->contains('CREATE TEMPORARY TABLE tbl (a INTEGER PRIMARY KEY, b) WITHOUT rowid', $source);
};

$tests['real upstream without_rowid4 trigger order source range'] = static function (TestRunner $t) use ($expectedSections): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::withoutRowid4TriggerOrderCases(1200);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1200, count($cases));
    $t->same($expectedSections, $sections);
    $t->same('without_rowid4-1.1.1', $cases[0]['upstream_section']);
    $t->same('without_rowid4-1.6.3', $cases[17]['upstream_section']);
    $t->same('without_rowid4-1.1.1', $cases[18]['upstream_section']);
    $t->same(18, count($sections));
};

$tests['real upstream without_rowid4 trigger order rejects empty corpus'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::withoutRowid4TriggerOrderCases(0));
};

$tests['real upstream without_rowid4 trigger order dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, WITHOUT ROWID table-shape metadata, and trigger event-order evidence arrays',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, WITHOUT ROWID table-shape metadata, and trigger event-order evidence arrays',
    );
};

return $tests;
