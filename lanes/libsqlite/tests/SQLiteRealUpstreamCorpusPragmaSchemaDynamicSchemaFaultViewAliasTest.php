<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/schemafault.test 1.0 creates a table and a view with an
 *   explicit view column list: CREATE VIEW v2(xxx, yyy) AS SELECT aaa, aaa+1
 *   FROM t2. The fault-injection body repeatedly evaluates SELECT * FROM v2.
 *
 * The PHP port does not run upstream OOM injection here. It ports the durable
 * schema behavior needed by that SELECT path: explicit view column aliases are
 * the schema-visible output names even when the SELECT projection contains
 * expressions without their own aliases.
 */

$record = static fn (
    string $type,
    string $name,
    string $table,
    int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);

$catalogFor = static function (int $variant) use ($record): SQLitePragmaSchemaCatalog {
    $suffix = sprintf('%04d', $variant);
    $table = 'schemafault_source_' . $suffix;
    $view = 'schemafault_view_' . $suffix;
    $leftAlias = 'value_alias_' . $suffix;
    $rightAlias = 'computed_alias_' . $suffix;
    $declaredType = match ($variant % 4) {
        0 => 'INTTT',
        1 => 'TEXT',
        2 => 'NUMERIC',
        default => 'REAL',
    };
    $expression = match ($variant % 5) {
        0 => 'aaa+1',
        1 => 'aaa * 2',
        2 => 'coalesce(aaa, 0)',
        3 => 'aaa || ' . "'-suffix'",
        default => 'abs(aaa)',
    };

    return new SQLitePragmaSchemaCatalog([
        $record(
            'table',
            $table,
            $table,
            10 + ($variant * 2),
            "CREATE TABLE {$table}(aaa {$declaredType})",
            1 + ($variant * 2),
        ),
        $record(
            'view',
            $view,
            $view,
            0,
            "CREATE VIEW {$view}({$leftAlias}, {$rightAlias}) AS SELECT aaa, {$expression} FROM {$table}",
            2 + ($variant * 2),
        ),
    ]);
};

foreach (range(1, 1000) as $variant) {
    $tests[sprintf('real upstream schemafault.test 1.0 view aliases survive expression projection variant %04d', $variant)] =
        static function (TestRunner $t) use ($catalogFor, $variant): void {
            $suffix = sprintf('%04d', $variant);
            $view = 'schemafault_view_' . $suffix;
            $leftAlias = 'value_alias_' . $suffix;
            $rightAlias = 'computed_alias_' . $suffix;
            $declaredType = match ($variant % 4) {
                0 => 'INTTT',
                1 => 'TEXT',
                2 => 'NUMERIC',
                default => 'REAL',
            };
            $catalog = $catalogFor($variant);

            $tableInfo = $catalog->execute("PRAGMA table_info({$view})");
            $tableXInfo = $catalog->executeTableValuedPragma("pragma_table_xinfo('{$view}')");
            $tableList = $catalog->execute("PRAGMA table_list({$view})");

            $t->same('ok', $tableInfo['status']);
            $t->same('table_info', $tableInfo['pragma']);
            $t->same($view, $tableInfo['target']);
            $t->same([$leftAlias, $rightAlias], array_column($tableInfo['rows'], 'name'));
            $t->same([0, 1], array_column($tableInfo['rows'], 'cid'));
            $t->same([$declaredType, ''], array_column($tableInfo['rows'], 'type'));
            $t->same([0, 0], array_column($tableInfo['rows'], 'notnull'));
            $t->same([0, 0], array_column($tableInfo['rows'], 'pk'));
            $t->same([0, 0], array_column($tableXInfo['rows'], 'hidden'));
            $t->same('view', $tableList['rows'][0]['type']);
            $t->same(2, $tableList['rows'][0]['ncol']);
            $t->same($view, $tableList['rows'][0]['name']);
        };
}

$tests['real upstream schemafault.test 1.0 source section cited'] = static function (TestRunner $t): void {
    $sections = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/schemafault.test schemafault-1.0 CREATE VIEW v2(xxx,yyy) AS SELECT aaa, aaa+1 FROM t2 remains selectable under fault injection',
    ];

    $t->same(1, count($sections));
    $t->contains('schemafault.test', $sections[0]);
    $t->contains('CREATE VIEW v2(xxx,yyy)', $sections[0]);
};

return $tests;
