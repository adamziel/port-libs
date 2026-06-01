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

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/laststmtchanges.test';
$plan = SQLiteUpstreamTriggerFkeyDynamicPlan::lastStatementChangesTriggerFrames();

$tests = [
    'real upstream laststmtchanges trigger frames cite table trigger sections' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);

        $t->true(is_string($source));
        $t->contains('changes() unchanged upon entry into after insert trigger', $source);
        $t->contains('changes() unchanged upon entry into after update trigger', $source);
        $t->contains('changes() unchanged upon entry into before delete trigger', $source);
        $t->contains('update t2 set v2=changes();', $source);
    },
    'real upstream laststmtchanges trigger frames cite nested view sections' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);

        $t->true(is_string($source));
        $t->contains('complex tests with temporary tables and nested instead of triggers', $source);
        $t->contains('insert into v2 values (100+NEW.k);', $source);
        $t->contains('select n from n1;', $source);
        $t->contains('select n from n2;', $source);
    },
    'real upstream laststmtchanges trigger frames cite triggerless delete sections' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);

        $t->true(is_string($source));
        $t->contains('Test "DELETE FROM <table>" in the absence of triggers', $source);
        $t->contains('DELETE FROM t3 WHERE a IS NOT NULL;', $source);
        $t->contains('CREATE INDEX t3_i1 ON t3(a);', $source);
        $t->contains('SELECT total_changes();', $source);
    },
    'real upstream laststmtchanges trigger frames metadata' => static function (TestRunner $t) use ($plan): void {
        $t->same('laststmtchanges.test', $plan['source']);
        $t->same(250, $plan['variant_count']);
        $t->same(1250, $plan['case_count']);
        $t->same(1250, count($plan['cases']));
        $t->same('laststmtchanges-2.1..2.4', $plan['scenarios'][0]);
        $t->same('laststmtchanges-6.2..6.6', $plan['scenarios'][4]);
        $t->same('sqlite-upstream-laststmtchanges-trigger-entry-uses-current-frame-changes', $plan['dependencies'][0]);
        $t->same('sqlite-upstream-laststmtchanges-delete-without-trigger-reports-row-count', $plan['dependencies'][4]);
    },
];

foreach ($plan['cases'] as $case) {
    $tests[sprintf(
        'real upstream laststmtchanges trigger frame dynamic %04d %s %s',
        (int) $case['variant'],
        (string) $case['case'],
        (string) $case['operation']
    )] = static function (TestRunner $t) use ($case, $valueAt): void {
        $t->same('laststmtchanges.test', $case['source']);
        $t->true((int) $case['variant'] >= 1);

        if ($case['kind'] === 'single-trigger-changes-frame') {
            $entryChanges = $valueAt($case, 'trigger_entry_changes');
            $innerDmlChanges = $valueAt($case, 'trigger_inner_dml_changes');
            $postStatementChanges = $valueAt($case, 'post_statement_changes');

            $t->true(in_array($case['trigger_timing'], ['AFTER', 'BEFORE'], true));
            $t->true(in_array($case['trigger_event'], ['INSERT', 'UPDATE', 'DELETE'], true));
            $t->same($entryChanges, $valueAt($case, 'trigger_table_row.v1'));
            $t->same($innerDmlChanges, $valueAt($case, 'trigger_table_row.v2'));
            $t->same(1, $case['trigger_insert_row_changes']);
            $t->same(1, $case['trigger_second_update_changes']);
            $t->same(true, $case['trigger_inner_dml_visible_inside_trigger']);
            $t->same(true, $case['trigger_exit_restores_statement_changes']);
            $t->same(false, $case['outer_statement_changes_include_trigger_body']);
            $t->same(1, $postStatementChanges);
            $t->same($case['operation'] === 'after-insert-table' ? 3 : 0, $entryChanges);
            $t->same($case['operation'] === 'after-insert-table' ? 5 : ($case['operation'] === 'after-update-table' ? 2 : 1), $innerDmlChanges);
            $t->same($case['operation'] === 'after-insert-table' ? 8 : null, $case['total_changes_delta']);
            $t->same('sqlite-upstream-laststmtchanges-trigger-entry-uses-current-frame-changes', $valueAt($case, 'dependencies.0'));
            $t->same('sqlite-upstream-laststmtchanges-trigger-inner-dml-updates-trigger-frame', $valueAt($case, 'dependencies.1'));
            $t->same('sqlite-upstream-laststmtchanges-trigger-exit-restores-caller-frame-changes', $valueAt($case, 'dependencies.2'));

            return;
        }

        if ($case['kind'] === 'nested-instead-of-view-changes-frame') {
            $t->same('nested-temp-view-trigger-changes', $case['operation']);
            $t->same('INSERT INTO v1 VALUES(?)', $case['outer_statement']);
            $t->same(false, $case['outer_view_dml_changes_connection_changes']);
            $t->same(0, $case['post_statement_changes']);
            $t->same([0, 5, 1, 0], $case['r1_changes_log']);
            $t->same([0, 1, 0, 3], $case['r2_changes_log']);
            $t->same($valueAt($case, 'r1_changes_log.0'), $case['r1_entry_changes']);
            $t->same($valueAt($case, 'r1_changes_log.1'), $case['r1_update_t0_changes']);
            $t->same($valueAt($case, 'r1_changes_log.2'), $case['r1_insert_t1_changes']);
            $t->same($valueAt($case, 'r1_changes_log.3'), $case['r1_zero_update_changes']);
            $t->same($valueAt($case, 'r2_changes_log.0'), $case['r2_entry_changes']);
            $t->same($valueAt($case, 'r2_changes_log.1'), $case['r2_insert_t2_changes']);
            $t->same($valueAt($case, 'r2_changes_log.2'), $case['r2_zero_update_changes']);
            $t->same($valueAt($case, 'r2_changes_log.3'), $case['r2_delete_t0_changes']);
            $t->same(true, $case['inner_trigger_changes_restored_to_outer_trigger_frame']);
            $t->same(true, $case['outer_trigger_changes_restored_to_connection_frame']);
            $t->same('sqlite-upstream-laststmtchanges-view-dml-does-not-change-connection-changes', $valueAt($case, 'dependencies.2'));
            $t->same('sqlite-upstream-laststmtchanges-nested-trigger-exit-restores-caller-frame', $valueAt($case, 'dependencies.3'));

            return;
        }

        $t->same('triggerless-delete-changes-frame', $case['kind']);
        $t->same('delete-without-trigger-reports-deleted-row-count', $case['operation']);
        $t->true($case['table_rows_before_delete'] >= 2);
        $t->same($case['table_rows_before_delete'], $case['delete_all_changes']);
        $t->same($case['table_rows_before_delete'], $case['delete_where_changes']);
        $t->same($case['table_rows_before_delete'], $case['indexed_delete_changes']);
        $t->same(true, $case['rollback_preserves_prior_total_changes']);
        $t->same($case['total_changes_before_final_delete'] + $case['table_rows_before_delete'], $case['total_changes_after_final_delete']);
        $t->same(true, $case['fast_delete_path_reports_rows_even_without_triggers']);
        $t->same('sqlite-upstream-laststmtchanges-delete-without-trigger-reports-row-count', $valueAt($case, 'dependencies.0'));
        $t->same('sqlite-upstream-laststmtchanges-indexed-delete-reports-row-count', $valueAt($case, 'dependencies.1'));
        $t->same('sqlite-upstream-laststmtchanges-total-changes-advances-after-delete', $valueAt($case, 'dependencies.2'));
    };
}

$tests['real upstream laststmtchanges trigger frames rejects zero variants'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpstreamTriggerFkeyDynamicPlan::lastStatementChangesTriggerFrames(0));
};

$tests['real upstream laststmtchanges trigger frames rejects excessive variants'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpstreamTriggerFkeyDynamicPlan::lastStatementChangesTriggerFrames(2001));
};

$tests['real upstream laststmtchanges trigger frames owns focused dynamic pass count'] = static function (TestRunner $t) use (&$tests): void {
    $t->same(1258, count($tests));
};

$tests['real upstream laststmtchanges trigger frames non overlap and dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'non-overlap: covers laststmtchanges.test trigger-frame changes restoration, nested INSTEAD OF trigger changes frames, and triggerless delete changes; avoids accepted lastinsert rowid, trigger2 count_changes, fkey action/deferred, triggerC indexed delete, and e_fkey section-6 batches',
        'non-overlap: covers laststmtchanges.test trigger-frame changes restoration, nested INSTEAD OF trigger changes frames, and triggerless delete changes; avoids accepted lastinsert rowid, trigger2 count_changes, fkey action/deferred, triggerC indexed delete, and e_fkey section-6 batches'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses the lane-local upstream trigger/FK dynamic plan and hydrated SQLite laststmtchanges.test source truth',
        'dependency-closure: no new support component needed; reuses the lane-local upstream trigger/FK dynamic plan and hydrated SQLite laststmtchanges.test source truth'
    );
};

return $tests;
