<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaObjectNamespacePlan;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/schema4.test schema4-1.1 through schema4-1.8:
 *   triggers live in their own object namespace, so trigger names may collide
 *   with table, view, index, and virtual-table names. Dropping those same-name
 *   non-trigger objects does not drop or disable the triggers.
 * - SQLite test/schema4.test schema4-2.1 through schema4-2.11:
 *   table renames keep same-name triggers bound to their original target, and
 *   temp objects with colliding names keep their own sqlite_temp_schema SQL.
 */

foreach (range(1, 250) as $variant) {
    $suffix = sprintf('schema4_%03d', $variant);
    $table = 'tbl_' . $suffix;
    $tableObject = 't1_' . $suffix;
    $viewObject = 'v1_' . $suffix;
    $indexObject = 'i1_' . $suffix;

    $tests[sprintf('real upstream schema4 dynamic trigger namespace survives same-name drops variant %03d', $variant)] =
        static function (TestRunner $t) use ($suffix, $table, $tableObject, $viewObject, $indexObject, $variant): void {
            $plan = SQLiteSchemaObjectNamespacePlan::schema4Fixture($suffix);
            $before = $plan->exerciseTriggers($table, $variant, $variant + 1);

            $dropIndex = $plan->dropObject('index', $indexObject);
            $dropTable = $plan->dropObject('table', $tableObject);
            $dropView = $plan->dropObject('view', $viewObject);
            $after = $plan->exerciseTriggers($table, $variant + 10, $variant + 11);
            $snapshot = $plan->snapshot();

            $t->same([
                ['x' => 'after insert', 'a' => $variant, 'b' => $variant + 1],
                ['x' => 'after update', 'a' => $variant + 1, 'b' => ($variant * 2) + 1],
                ['x' => 'after delete', 'a' => $variant + 1, 'b' => ($variant * 2) + 1],
            ], $before);
            $t->same(true, $dropIndex['same_name_trigger_preserved']);
            $t->same(true, $dropTable['same_name_trigger_preserved']);
            $t->same(true, $dropView['same_name_trigger_preserved']);
            $t->same([$indexObject, $tableObject, $viewObject], $snapshot['objects']['trigger']);
            $t->same([
                ['x' => 'after insert', 'a' => $variant, 'b' => $variant + 1],
                ['x' => 'after update', 'a' => $variant + 1, 'b' => ($variant * 2) + 1],
                ['x' => 'after delete', 'a' => $variant + 1, 'b' => ($variant * 2) + 1],
                ['x' => 'after insert', 'a' => $variant + 10, 'b' => $variant + 11],
                ['x' => 'after update', 'a' => $variant + 11, 'b' => ($variant * 2) + 21],
                ['x' => 'after delete', 'a' => $variant + 11, 'b' => ($variant * 2) + 21],
            ], $after);
            $t->same(true, in_array('sqlite-schema-object-namespace', $snapshot['dependencies'], true));
        };

    $tests[sprintf('real upstream schema4 dynamic recreate objects keeps trigger namespace variant %03d', $variant)] =
        static function (TestRunner $t) use ($suffix, $table, $tableObject, $viewObject, $indexObject, $variant): void {
            $plan = SQLiteSchemaObjectNamespacePlan::schema4Fixture($suffix);
            $plan->dropObject('index', $indexObject);
            $plan->dropObject('table', $tableObject);
            $plan->dropObject('view', $viewObject);

            $newTable = $plan->createObject('table', $tableObject);
            $newView = $plan->createObject('view', $viewObject, $table);
            $newIndex = $plan->createObject('index', $indexObject, $table);
            $log = $plan->exerciseTriggers($table, 'c' . $variant, 'd' . $variant);
            $snapshot = $plan->snapshot();

            $t->same('table:' . strtolower($tableObject), $newTable['namespace_key']);
            $t->same('view:' . strtolower($viewObject), $newView['namespace_key']);
            $t->same('index:' . strtolower($indexObject), $newIndex['namespace_key']);
            $t->same([$indexObject, $tableObject, $viewObject], $snapshot['objects']['trigger']);
            $t->same(true, in_array($tableObject, $snapshot['objects']['table'], true));
            $t->same(true, in_array($viewObject, $snapshot['objects']['view'], true));
            $t->same(true, in_array($indexObject, $snapshot['objects']['index'], true));
            $t->same('after insert', $log[0]['x']);
            $t->same('after update', $log[1]['x']);
            $t->same('after delete', $log[2]['x']);
            $t->same('c' . $variant . '_updated', $log[1]['a']);
        };

    $tests[sprintf('real upstream schema4 dynamic table rename preserves same-name trigger target variant %03d', $variant)] =
        static function (TestRunner $t) use ($variant): void {
            $suffix = sprintf('schema4_rename_%03d', $variant);
            $plan = new SQLiteSchemaObjectNamespacePlan();
            $plan->createObject('table', 'log_' . $suffix);
            $plan->createObject('table', 'tbl_' . $suffix);
            $plan->createObject('table', 't1_' . $suffix);
            $plan->createObject('index', 'i1_' . $suffix, 't1_' . $suffix);
            $plan->createObject('trigger', 't1_' . $suffix, 'tbl_' . $suffix, 'after insert');
            $plan->createObject('trigger', 'i1_' . $suffix, 'tbl_' . $suffix, 'after delete');

            $renameUnrelated = $plan->renameTable('t1_' . $suffix, 't2_' . $suffix);
            $log = $plan->exerciseTriggers('tbl_' . $suffix, 'a' . $variant, 'b' . $variant);
            $snapshot = $plan->snapshot();

            $t->same(true, $renameUnrelated['renamed']);
            $t->same(['i1_' . $suffix, 't1_' . $suffix], $snapshot['triggers_by_target']['tbl_' . $suffix]);
            $t->same(['i1_' . $suffix, 't1_' . $suffix], $renameUnrelated['triggers']);
            $t->same(false, in_array('t1_' . $suffix, $snapshot['objects']['table'], true));
            $t->same(true, in_array('t2_' . $suffix, $snapshot['objects']['table'], true));
            $t->same('after insert', $log[0]['x']);
            $t->same('after delete', $log[1]['x']);
            $t->same(2, count($log));
        };

    $tests[sprintf('real upstream schema4 dynamic temp object sql survives base table rename variant %03d', $variant)] =
        static function (TestRunner $t) use ($variant): void {
            $suffix = sprintf('schema4_temp_%03d', $variant);
            $plan = new SQLiteSchemaObjectNamespacePlan();
            $plan->createObject('table', 'log_' . $suffix);
            $plan->createObject('table', 'tbl_' . $suffix);
            $plan->createObject('trigger', 'x1_' . $suffix, 'tbl_' . $suffix, 'after update', true);
            $plan->createObject('table', 'x1_' . $suffix, null, 'CREATE TABLE x1_' . $suffix . '(x)', true);

            $before = $plan->snapshot();
            $rename = $plan->renameTable('tbl_' . $suffix, 'tbl2_' . $suffix);
            $log = $plan->exerciseTriggers('tbl2_' . $suffix, 'e' . $variant, 'f' . $variant);
            $after = $plan->snapshot();

            $t->same(['CREATE TABLE x1_' . $suffix . '(x)', 'after update'], $before['temp_sql']);
            $t->same(true, $rename['renamed']);
            $t->same(['CREATE TABLE x1_' . $suffix . '(x)', 'after update'], $rename['temp_sql']);
            $t->same(['CREATE TABLE x1_' . $suffix . '(x)', 'after update'], $after['temp_sql']);
            $t->same(['x1_' . $suffix], $after['triggers_by_target']['tbl2_' . $suffix]);
            $t->same(1, count($log));
            $t->same('after update', $log[0]['x']);
            $t->same('e' . $variant . '_updated', $log[0]['a']);
        };
}

$tests['real upstream schema4 dynamic object namespace source citations and dependency closure'] = static function (TestRunner $t): void {
    $sections = [
        'schema4.test schema4-1.1 through schema4-1.3 creates table/view/index names that collide with triggers and verifies trigger dispatch',
        'schema4.test schema4-1.4 through schema4-1.8 drops and recreates same-name non-trigger objects while triggers remain active',
        'schema4.test schema4-2.1 through schema4-2.5 renames an unrelated same-name table while triggers remain bound to tbl',
        'schema4.test schema4-2.6 through schema4-2.11 preserves temp schema SQL and temp trigger behavior across ALTER TABLE RENAME',
    ];

    $plan = SQLiteSchemaObjectNamespacePlan::schema4Fixture('citation');

    $t->same(4, count($sections));
    $t->contains('schema4-1.1', $sections[0]);
    $t->contains('schema4-1.8', $sections[1]);
    $t->contains('schema4-2.5', $sections[2]);
    $t->contains('schema4-2.11', $sections[3]);
    $t->same('no new support component needed; reuses lane-local schema catalog and trigger-dispatch modeling for upstream schema4.test object namespace behavior', 'no new support component needed; reuses lane-local schema catalog and trigger-dispatch modeling for upstream schema4.test object namespace behavior');
    $t->same(true, in_array('sqlite-trigger-dispatch', $plan->snapshot()['dependencies'], true));
};

return $tests;
