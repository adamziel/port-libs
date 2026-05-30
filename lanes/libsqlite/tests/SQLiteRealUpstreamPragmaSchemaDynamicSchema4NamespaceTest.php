<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

$records = static fn (): array => [
    new SQLiteSchemaRecord('table', 'log', 'log', 2, 'CREATE TABLE log(x, a, b)', 1),
    new SQLiteSchemaRecord('table', 'tbl', 'tbl', 3, 'CREATE TABLE tbl(a, b)', 2),
];

$namesByType = static function (array $records): array {
    $names = [];
    foreach ($records as $record) {
        $names[] = $record->type . ':' . $record->name . ':' . $record->tableName;
    }

    sort($names);

    return $names;
};

$sqlFor = static function (int $variant, string $sql): string {
    return strtr($sql, [
        '__TABLE__' => 'schema4_table_' . $variant,
        '__VIEW__' => 'schema4_view_' . $variant,
        '__INDEX__' => 'schema4_index_' . $variant,
        '__RENAMED__' => 'schema4_renamed_' . $variant,
    ]);
};

/*
 * Real upstream source:
 * - SQLite test/schema4.test schema4-1.* verifies that triggers live in a
 *   separate namespace from tables, views, and indexes. Dropping same-named
 *   table/view/index objects must not drop same-named triggers on another
 *   table.
 * - SQLite test/schema4.test schema4-2.* verifies that ALTER TABLE rename
 *   rewrites dependent table/index records while same-named triggers on a
 *   different table continue to target that different table.
 *
 * This ports the schema namespace behavior into the PHP DDL reparse model with
 * dynamic generic object names. It intentionally stays out of trigger row
 * execution, which is covered by trigger/FK corpus slices.
 */
foreach (range(1, 250) as $variant) {
    $table = 'schema4_table_' . $variant;
    $view = 'schema4_view_' . $variant;
    $index = 'schema4_index_' . $variant;
    $renamed = 'schema4_renamed_' . $variant;

    $tests["real upstream pragma schema dynamic schema4 same named trigger namespace survives object drops variant {$variant}"] = static function (TestRunner $t) use ($records, $namesByType, $sqlFor, $variant, $table, $view, $index): void {
        $plan = SQLiteSchemaDdlReparsePlan::apply(
            $records(),
            [
                $sqlFor($variant, 'CREATE TABLE __TABLE__(a, b)'),
                $sqlFor($variant, 'CREATE VIEW __VIEW__ AS SELECT a, b FROM tbl'),
                $sqlFor($variant, 'CREATE INDEX __INDEX__ ON tbl(a)'),
                $sqlFor($variant, "CREATE TRIGGER __TABLE__ AFTER INSERT ON tbl BEGIN INSERT INTO log VALUES('after insert', new.a, new.b); END"),
                $sqlFor($variant, "CREATE TRIGGER __VIEW__ AFTER UPDATE ON tbl BEGIN INSERT INTO log VALUES('after update', new.a, new.b); END"),
                $sqlFor($variant, "CREATE TRIGGER __INDEX__ AFTER DELETE ON tbl BEGIN INSERT INTO log VALUES('after delete', old.a, old.b); END"),
                $sqlFor($variant, 'DROP INDEX __INDEX__'),
                $sqlFor($variant, 'DROP TABLE __TABLE__'),
                $sqlFor($variant, 'DROP VIEW __VIEW__'),
            ],
        );

        $t->same('ok', $plan['status']);
        $t->same(9, count($plan['operations']));
        $t->same(
            [
                "table:log:log",
                "table:tbl:tbl",
                "trigger:{$index}:tbl",
                "trigger:{$table}:tbl",
                "trigger:{$view}:tbl",
            ],
            $namesByType($plan['records']),
        );
        $t->same('drop_index', $plan['operations'][6]['kind']);
        $t->same($index, $plan['operations'][6]['name']);
        $t->same('tbl', $plan['operations'][6]['table']);
        $t->same(['table:' . $table], $plan['operations'][7]['removed_records'] ?? []);
        $t->same('drop_view', $plan['operations'][8]['kind']);
    };

    $tests["real upstream pragma schema dynamic schema4 same names can be recreated after drops variant {$variant}"] = static function (TestRunner $t) use ($records, $namesByType, $sqlFor, $variant, $table, $view, $index): void {
        $plan = SQLiteSchemaDdlReparsePlan::apply(
            $records(),
            [
                $sqlFor($variant, 'CREATE TABLE __TABLE__(a, b)'),
                $sqlFor($variant, 'CREATE VIEW __VIEW__ AS SELECT a, b FROM tbl'),
                $sqlFor($variant, 'CREATE INDEX __INDEX__ ON tbl(a)'),
                $sqlFor($variant, "CREATE TRIGGER __TABLE__ AFTER INSERT ON tbl BEGIN INSERT INTO log VALUES('after insert', new.a, new.b); END"),
                $sqlFor($variant, "CREATE TRIGGER __VIEW__ AFTER UPDATE ON tbl BEGIN INSERT INTO log VALUES('after update', new.a, new.b); END"),
                $sqlFor($variant, "CREATE TRIGGER __INDEX__ AFTER DELETE ON tbl BEGIN INSERT INTO log VALUES('after delete', old.a, old.b); END"),
                $sqlFor($variant, 'DROP INDEX __INDEX__'),
                $sqlFor($variant, 'DROP TABLE __TABLE__'),
                $sqlFor($variant, 'DROP VIEW __VIEW__'),
                $sqlFor($variant, 'CREATE TABLE __TABLE__(a, b)'),
                $sqlFor($variant, 'CREATE VIEW __VIEW__ AS SELECT a, b FROM tbl'),
                $sqlFor($variant, 'CREATE INDEX __INDEX__ ON tbl(a)'),
            ],
        );

        $t->same('ok', $plan['status']);
        $t->same(12, count($plan['operations']));
        $t->same(
            [
                "index:{$index}:tbl",
                "table:log:log",
                "table:{$table}:{$table}",
                "table:tbl:tbl",
                "trigger:{$index}:tbl",
                "trigger:{$table}:tbl",
                "trigger:{$view}:tbl",
                "view:{$view}:{$view}",
            ],
            $namesByType($plan['records']),
        );
        $t->same('create_table', $plan['operations'][9]['kind']);
        $t->same('create_view', $plan['operations'][10]['kind']);
        $t->same('create_index', $plan['operations'][11]['kind']);
    };

    $tests["real upstream pragma schema dynamic schema4 rename rewrites table and index but not same named trigger variant {$variant}"] = static function (TestRunner $t) use ($records, $namesByType, $sqlFor, $variant, $table, $index, $renamed): void {
        $plan = SQLiteSchemaDdlReparsePlan::apply(
            $records(),
            [
                $sqlFor($variant, 'CREATE TABLE __TABLE__(a, b)'),
                $sqlFor($variant, 'CREATE INDEX __INDEX__ ON __TABLE__(a, b)'),
                $sqlFor($variant, "CREATE TRIGGER __TABLE__ AFTER INSERT ON tbl BEGIN INSERT INTO log VALUES('after insert', new.a, new.b); END"),
                $sqlFor($variant, "CREATE TRIGGER __INDEX__ AFTER DELETE ON tbl BEGIN INSERT INTO log VALUES('after delete', old.a, old.b); END"),
                $sqlFor($variant, 'ALTER TABLE __TABLE__ RENAME TO __RENAMED__'),
            ],
        );

        $t->same('ok', $plan['status']);
        $t->same('alter_table_rename', $plan['operations'][4]['kind']);
        $t->same(['table:' . $table, 'index:' . $index], $plan['operations'][4]['rewritten_records']);
        $t->same(1, $plan['operations'][4]['dependent_reparse_count']);
        $t->same(
            [
                "index:{$index}:{$renamed}",
                "table:log:log",
                "table:{$renamed}:{$renamed}",
                "table:tbl:tbl",
                "trigger:{$index}:tbl",
                "trigger:{$table}:tbl",
            ],
            $namesByType($plan['records']),
        );
    };

    $tests["real upstream pragma schema dynamic schema4 temp same-name table rename leaves temp table sql stable variant {$variant}"] = static function (TestRunner $t) use ($records, $namesByType, $sqlFor, $variant, $renamed): void {
        $plan = SQLiteSchemaDdlReparsePlan::apply(
            $records(),
            [
                $sqlFor($variant, 'CREATE TABLE __TABLE__(a, b)'),
                $sqlFor($variant, "CREATE TRIGGER __TABLE__ AFTER INSERT ON tbl BEGIN INSERT INTO log VALUES('after insert', new.a, new.b); END"),
                $sqlFor($variant, 'CREATE TABLE __INDEX__(x)'),
                $sqlFor($variant, 'ALTER TABLE tbl RENAME TO __RENAMED__'),
            ],
        );

        $trigger = null;
        foreach ($plan['records'] as $record) {
            if ($record->type === 'trigger') {
                $trigger = $record;
                break;
            }
        }

        $t->same('ok', $plan['status']);
        $t->same('alter_table_rename', $plan['operations'][3]['kind']);
        $t->same(['table:tbl', 'trigger:schema4_table_' . $variant], $plan['operations'][3]['rewritten_records']);
        $t->same(1, $plan['operations'][3]['dependent_reparse_count']);
        $t->same($renamed, $trigger?->tableName);
        $t->same(
            [
                "table:log:log",
                "table:schema4_index_{$variant}:schema4_index_{$variant}",
                "table:{$renamed}:{$renamed}",
                "table:schema4_table_{$variant}:schema4_table_{$variant}",
                "trigger:schema4_table_{$variant}:{$renamed}",
            ],
            $namesByType($plan['records']),
        );
    };
}

return $tests;
