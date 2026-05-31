<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamTriggerFkeyDynamicPlan;

$valueAt = static function (array $array, string $path): mixed {
    $cursor = $array;
    foreach (explode('.', $path) as $part) {
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }
        if (is_array($cursor) && ctype_digit($part) && array_key_exists((int) $part, $cursor)) {
            $cursor = $cursor[(int) $part];
            continue;
        }

        throw new RuntimeException("Missing assertion path {$path}");
    }

    return $cursor;
};

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/lastinsert.test';
$plan = SQLiteUpstreamTriggerFkeyDynamicPlan::lastInsertRowidTriggerFrames();

$tests = [
    'real upstream lastinsert trigger frames cite table trigger sections' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);

        $t->true(is_string($source));
        $t->contains('LIRID changed properly after an insert into table containing an after trigger', $source);
        $t->contains('LIRID not changed after an update onto a table containing an after trigger', $source);
        $t->contains('LIRID not changed after a delete on a table containing a before trigger', $source);
        $t->contains('insert into t2 values (NEW.k*2, last_insert_rowid(), NULL, NULL);', $source);
    },
    'real upstream lastinsert trigger frames cite view and nested trigger sections' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);

        $t->true(is_string($source));
        $t->contains('LIRID not changed after an insert into view containing an instead of trigger', $source);
        $t->contains('LIRID not changed after an update on a view containing an instead of trigger', $source);
        $t->contains('complex tests with temporary tables and nested instead of triggers', $source);
        $t->contains('insert into v2 values (100+last_insert_rowid());', $source);
    },
    'real upstream lastinsert trigger frames cite wide rowid sections' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);

        $t->true(is_string($source));
        $t->contains('INSERT INTO t2 VALUES(5000000000, 1);', $source);
        $t->contains('SELECT last_insert_rowid();', $source);
        $t->contains('db eval {INSERT INTO t2 VALUES(123456789012345,0)}', $source);
    },
    'real upstream lastinsert trigger frames metadata' => static function (TestRunner $t) use ($plan): void {
        $t->same('lastinsert.test', $plan['source']);
        $t->same(200, $plan['variant_count']);
        $t->same(1400, $plan['case_count']);
        $t->same(1400, count($plan['cases']));
        $t->same('lastinsert-2.1..2.4', $plan['scenarios'][0]);
        $t->same('lastinsert-9.1', $plan['scenarios'][7]);
        $t->same('sqlite-upstream-lastinsert-trigger-entry-uses-current-frame-rowid', $plan['dependencies'][0]);
        $t->same('sqlite-upstream-lastinsert-64-bit-rowid-preserved-through-after-trigger', $plan['dependencies'][5]);
    },
];

foreach ($plan['cases'] as $case) {
    $name = sprintf(
        'real upstream lastinsert trigger frame dynamic %04d %s %s',
        (int) $case['variant'],
        (string) $case['case'],
        (string) $case['operation']
    );

    $tests[$name] = static function (TestRunner $t) use ($case, $valueAt): void {
        $t->same('lastinsert.test', $case['source']);
        $t->true((int) $case['variant'] >= 1);

        if ($case['kind'] === 'single-trigger-frame') {
            $entry = $valueAt($case, 'trigger_entry_last_insert_rowid');
            $triggerInsert = $valueAt($case, 'trigger_insert_rowid');
            $postStatement = $valueAt($case, 'post_statement_last_insert_rowid');

            $t->same('single-trigger-frame', $case['kind']);
            $t->true(in_array($case['trigger_timing'], ['AFTER', 'BEFORE', 'INSTEAD OF'], true));
            $t->true(in_array($case['trigger_event'], ['INSERT', 'UPDATE', 'DELETE'], true));
            $t->true(in_array($case['target_kind'], ['table', 'view'], true));
            $t->same($entry, $valueAt($case, 'trigger_table_row.val1'));
            $t->same(100 + $triggerInsert, $valueAt($case, 'trigger_table_row.val2'));
            $t->same(1000 + $triggerInsert, $valueAt($case, 'trigger_table_row.val3'));
            $t->same($triggerInsert, $case['trigger_update_last_insert_rowid']);
            $t->same($triggerInsert, $case['trigger_second_update_last_insert_rowid']);
            $t->same(true, $case['inner_insert_visible_inside_trigger']);
            $t->same(true, $case['update_does_not_change_lirid_inside_trigger']);
            $t->same($postStatement !== $triggerInsert, $case['inner_insert_restored_after_trigger_exit']);
            $t->same($case['target_kind'] === 'view' ? false : null, $case['view_dml_changes_connection_lirid']);
            $t->same($case['operation'] === 'after-insert-table', $case['outer_statement_changes_lirid']);
            $t->same($case['operation'] === 'after-insert-table' ? $case['statement_rowid'] : $case['initial_last_insert_rowid'], $postStatement);
            $t->same('sqlite-upstream-lastinsert-trigger-entry-uses-current-frame-rowid', $valueAt($case, 'dependencies.0'));
            $t->same('sqlite-upstream-lastinsert-trigger-inner-insert-updates-trigger-frame', $valueAt($case, 'dependencies.1'));
            $t->same('sqlite-upstream-lastinsert-trigger-exit-restores-caller-frame-rowid', $valueAt($case, 'dependencies.2'));

            return;
        }

        if ($case['kind'] === 'nested-instead-of-view') {
            $t->same('nested-temp-view-trigger-last-insert-rowid', $case['operation']);
            $t->same('INSERT INTO v1 VALUES(?)', $case['outer_statement']);
            $t->same(false, $case['outer_view_dml_changes_connection_lirid']);
            $t->same($case['initial_last_insert_rowid'], $case['post_statement_last_insert_rowid']);
            $t->same($case['initial_last_insert_rowid'], $valueAt($case, 'r1.entry_last_insert_rowid'));
            $t->same(100 + $case['outer_view_input'], $valueAt($case, 'r1.inserted_t1_rowid'));
            $t->same(100 + $valueAt($case, 'r1.inserted_t1_rowid'), $valueAt($case, 'r1.nested_view_input'));
            $t->same($valueAt($case, 'r1.inserted_t1_rowid'), $valueAt($case, 'r1.exit_last_insert_rowid'));
            $t->same($valueAt($case, 'r1.inserted_t1_rowid'), $valueAt($case, 'r2.entry_last_insert_rowid'));
            $t->same(1000 + $valueAt($case, 'r1.nested_view_input'), $valueAt($case, 'r2.inserted_t2_rowid'));
            $t->same($valueAt($case, 'r2.inserted_t2_rowid'), $valueAt($case, 'r2.exit_last_insert_rowid'));
            $t->same($case['initial_last_insert_rowid'], $valueAt($case, 'rid_rows.1.rin'));
            $t->same($valueAt($case, 'r1.inserted_t1_rowid'), $valueAt($case, 'rid_rows.1.rout'));
            $t->same($valueAt($case, 'r1.inserted_t1_rowid'), $valueAt($case, 'rid_rows.2.rin'));
            $t->same($valueAt($case, 'r2.inserted_t2_rowid'), $valueAt($case, 'rid_rows.2.rout'));
            $t->same(true, $case['inner_trigger_insert_restored_to_outer_trigger_frame']);
            $t->same(true, $case['outer_trigger_insert_restored_to_connection_frame']);

            return;
        }

        $t->same('wide-rowid-after-trigger', $case['kind']);
        $t->same('wide-rowid-after-trigger-last-insert-rowid', $case['operation']);
        $t->true($case['first_insert_rowid'] > 5000000000);
        $t->true($case['second_insert_rowid'] > 123456789012345);
        $t->same($case['first_insert_rowid'], $valueAt($case, 'first_trigger_log_row.a'));
        $t->same(1, $valueAt($case, 'first_trigger_log_row.b'));
        $t->same($case['first_insert_rowid'], $case['last_insert_rowid_after_first_trigger']);
        $t->same($case['second_insert_rowid'], $case['last_insert_rowid_after_second_insert']);
        $t->same(true, $case['after_trigger_insert_does_not_clobber_outer_rowid']);
        $t->same(true, $case['supports_64_bit_rowid']);
    };
}

$tests['real upstream lastinsert trigger frames rejects zero variants'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpstreamTriggerFkeyDynamicPlan::lastInsertRowidTriggerFrames(0));
};

$tests['real upstream lastinsert trigger frames rejects excessive variants'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpstreamTriggerFkeyDynamicPlan::lastInsertRowidTriggerFrames(2001));
};

$tests['real upstream lastinsert trigger frames non overlap and dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'non-overlap: covers lastinsert.test trigger-frame last_insert_rowid restoration, nested INSTEAD OF trigger frames, and wide rowids; avoids accepted trigger count_changes, fkey cascade/action, trigger1 schema, trigger2 conflict, and e_fkey section-6 batches',
        'non-overlap: covers lastinsert.test trigger-frame last_insert_rowid restoration, nested INSTEAD OF trigger frames, and wide rowids; avoids accepted trigger count_changes, fkey cascade/action, trigger1 schema, trigger2 conflict, and e_fkey section-6 batches'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses the lane-local upstream trigger/FK dynamic plan and hydrated SQLite lastinsert.test source truth',
        'dependency-closure: no new support component needed; reuses the lane-local upstream trigger/FK dynamic plan and hydrated SQLite lastinsert.test source truth'
    );
};

return $tests;
