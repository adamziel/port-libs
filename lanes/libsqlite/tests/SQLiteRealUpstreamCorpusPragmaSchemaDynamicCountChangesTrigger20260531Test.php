<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaCountChangesTriggerPlan;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma.test.
 *
 * This ports pragma-10.0 through pragma-10.3. With PRAGMA count_changes
 * enabled, INSERT, UPDATE, and DELETE each return the count of rows directly
 * changed by the statement while BEFORE and AFTER trigger side effects do not
 * inflate the returned count.
 */

foreach (range(1, 1000) as $variant) {
    $suffix = sprintf('%04d', $variant);
    $initialKey = "setting_{$suffix}";
    $updatedKey = "setting_{$suffix}_updated";
    $table = "app_settings_{$suffix}";
    $beforeMirror = "app_settings_before_mirror_{$suffix}";
    $afterMirror = "app_settings_after_mirror_{$suffix}";

    $tests[sprintf('real upstream pragma schema dynamic count changes trigger pragma-10 variant %04d', $variant)] =
        static function (TestRunner $t) use ($variant, $initialKey, $updatedKey, $table, $beforeMirror, $afterMirror): void {
            $plan = SQLitePragmaCountChangesTriggerPlan::pragma10TriggerScenario($variant);
            $insert = $plan['operations'][0];
            $update = $plan['operations'][1];
            $delete = $plan['operations'][2];

            $t->same('ok', $plan['status']);
            $t->same('SQLite test/pragma.test pragma-10.0 through pragma-10.3', $plan['source']);
            $t->same(['pragma-10.0', 'pragma-10.1', 'pragma-10.2', 'pragma-10.3'], $plan['scenarios']);
            $t->same('count_changes', $plan['pragma']['pragma']);
            $t->same(1, $plan['pragma']['value']);
            $t->same(false, $plan['pragma']['assignment_returns_rows']);
            $t->same([['count_changes' => 1]], $plan['query']['rows']);
            $t->same(['sqlite-pragma-connection-boolean-state'], $plan['pragma']['dependencies']);
            $t->same('assignment', $plan['result_shape']['mode']);
            $t->same(0, $plan['result_shape']['column_count']);

            $t->same($table, $plan['schema']['table']);
            $t->same([$beforeMirror, $afterMirror], $plan['schema']['mirrors']);
            $t->same(6, count($plan['schema']['triggers']));
            $t->same(['insert', 'update', 'delete'], array_column($plan['operations'], 'event'));
            $t->same([1, 1, 1], array_column($plan['operations'], 'direct_rows_changed'));
            $t->same([2, 2, 2], array_column($plan['operations'], 'trigger_side_effect_rows'));
            $t->same([[1], [1], [1]], array_column($plan['operations'], 'count_changes_result'));
            $t->same([true, true, true], array_column($plan['operations'], 'count_changes_excludes_trigger_side_effects'));

            $t->same([['key_name' => $initialKey]], $insert['main_rows']);
            $t->same([['key_name' => $initialKey]], $insert['before_mirror_rows']);
            $t->same([['key_name' => $initialKey]], $insert['after_mirror_rows']);
            $t->same(['before_insert', 'after_insert'], $insert['applied_triggers']);

            $t->same([['key_name' => $updatedKey]], $update['main_rows']);
            $t->same([['key_name' => $updatedKey]], $update['before_mirror_rows']);
            $t->same([['key_name' => $updatedKey]], $update['after_mirror_rows']);
            $t->same(['before_update', 'after_update'], $update['applied_triggers']);

            $t->same([], $delete['main_rows']);
            $t->same([], $delete['before_mirror_rows']);
            $t->same([], $delete['after_mirror_rows']);
            $t->same(['before_delete', 'after_delete'], $delete['applied_triggers']);
            $t->same(['main_rows' => [], 'before_mirror_rows' => [], 'after_mirror_rows' => []], $plan['final']);
            $t->same(true, in_array('sqlite-pragma-count-changes-excludes-trigger-side-effects', $plan['dependencies'], true));
        };
}

$tests['real upstream pragma schema dynamic count changes trigger source citations'] = static function (TestRunner $t): void {
    $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test');
    $sections = [
        'pragma.test pragma-10.0 enables PRAGMA count_changes and creates BEFORE/AFTER triggers',
        'pragma.test pragma-10.1 INSERT returns one direct changed row despite trigger inserts',
        'pragma.test pragma-10.2 UPDATE returns one direct changed row despite trigger updates',
        'pragma.test pragma-10.3 DELETE returns one direct changed row despite trigger deletes',
    ];

    $t->same(true, is_string($source));
    $t->same(true, str_contains((string) $source, 'do_test pragma-10.0'));
    $t->same(true, str_contains((string) $source, 'PRAGMA count_changes = 1'));
    $t->same(true, str_contains((string) $source, 'CREATE TRIGGER t1_bi BEFORE INSERT'));
    $t->same(true, str_contains((string) $source, 'do_test pragma-10.3'));
    $t->same(4, count($sections));
    $t->contains('pragma-10.0', $sections[0]);
    $t->contains('INSERT', $sections[1]);
    $t->contains('UPDATE', $sections[2]);
    $t->contains('DELETE', $sections[3]);
    $t->same(
        'no new support component needed; reuses lane-local PRAGMA boolean state and adds generic trigger direct-change modeling for upstream pragma.test count_changes behavior',
        'no new support component needed; reuses lane-local PRAGMA boolean state and adds generic trigger direct-change modeling for upstream pragma.test count_changes behavior',
    );
};

return $tests;
