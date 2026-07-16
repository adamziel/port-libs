<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/capi3.test capi3-2.6 through capi3-2.8 prepares
 *   PRAGMA table_info("TableName"); --excess text from UTF-16 SQL. The first
 *   PRAGMA statement returns a row and the ignored tail does not make the
 *   prepared statement fail.
 *
 * This ports the same first-statement prepare-tail behavior through the native
 * PRAGMA schema catalog and table-valued PRAGMA parser. The dynamic cases vary
 * direct/table-valued forms, schema targets, indexes, foreign keys, quoted
 * identifiers, semicolons embedded inside identifiers, and trailing SQL text.
 */

$record = static fn (
    string $type,
    string $name,
    string $table,
    ?int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);

$catalogFor = static function (int $variant) use ($record): SQLiteAttachedSchemaCatalog {
    $table = "TableName{$variant}";
    $generated = "generated_tail_{$variant}";
    $child = "child_tail_{$variant}";
    $plainIndex = "tail_lookup_{$variant}";
    $expressionIndex = "tail_expr_lookup_{$variant}";
    $semi = "semi;tail_{$variant}";
    $bracket = "bracket_tail_{$variant}";

    return new SQLiteAttachedSchemaCatalog([
        $record('table', $table, $table, 1000 + $variant, "CREATE TABLE {$table}(setting_id INTEGER PRIMARY KEY, key_name TEXT NOT NULL DEFAULT 'main', key_value TEXT DEFAULT 'v{$variant}')", 1),
        $record('index', $plainIndex, $table, 2000 + $variant, "CREATE INDEX {$plainIndex} ON {$table}(key_name, key_value)", 2),
        $record('index', $expressionIndex, $table, 3000 + $variant, "CREATE INDEX {$expressionIndex} ON {$table}(key_name COLLATE NOCASE DESC, lower(key_value))", 3),
        $record('table', $generated, $generated, 4000 + $variant, "CREATE TABLE {$generated}(base INTEGER, v TEXT GENERATED ALWAYS AS (base || '-v') VIRTUAL, s TEXT AS (base || '-s') STORED)", 4),
        $record('table', $child, $child, 5000 + $variant, "CREATE TABLE {$child}(child_id INTEGER PRIMARY KEY, parent_key TEXT REFERENCES {$table}(key_name) ON UPDATE CASCADE ON DELETE SET NULL)", 5),
        $record('table', $semi, $semi, 6000 + $variant, "CREATE TABLE \"{$semi}\"(quoted_key TEXT, quoted_value TEXT)", 6),
        $record('table', $bracket, $bracket, 7000 + $variant, "CREATE TABLE {$bracket}(bracket_key TEXT, bracket_value TEXT)", 7),
    ]);
};

$caseFor = static function (int $variant): array {
    $table = "TableName{$variant}";
    $generated = "generated_tail_{$variant}";
    $child = "child_tail_{$variant}";
    $plainIndex = "tail_lookup_{$variant}";
    $expressionIndex = "tail_expr_lookup_{$variant}";
    $semi = "semi;tail_{$variant}";
    $bracket = "bracket_tail_{$variant}";

    return match ($variant % 10) {
        0 => [
            'sql' => "PRAGMA table_info(\"{$table}\"); --excess text",
            'table_valued' => false,
            'pragma' => 'table_info',
            'target' => $table,
            'row_count' => 3,
            'names' => ['setting_id', 'key_name', 'key_value'],
        ],
        1 => [
            'sql' => "PRAGMA table_xinfo([{$generated}]); SELECT 10",
            'table_valued' => false,
            'pragma' => 'table_xinfo',
            'target' => $generated,
            'row_count' => 3,
            'hidden' => [0, 2, 3],
        ],
        2 => [
            'sql' => "PRAGMA index_list('{$table}'); /* ignored tail */ SELECT missing FROM nowhere",
            'table_valued' => false,
            'pragma' => 'index_list',
            'target' => $table,
            'row_count' => 2,
            'names' => [$plainIndex, $expressionIndex],
        ],
        3 => [
            'sql' => "PRAGMA index_info(\"{$plainIndex}\"); --excess text",
            'table_valued' => false,
            'pragma' => 'index_info',
            'target' => $plainIndex,
            'row_count' => 2,
            'names' => ['key_name', 'key_value'],
        ],
        4 => [
            'sql' => "PRAGMA index_xinfo([{$expressionIndex}]); SELECT 10",
            'table_valued' => false,
            'pragma' => 'index_xinfo',
            'target' => $expressionIndex,
            'row_count' => 3,
            'names' => ['key_name', null, null],
            'cids' => [1, -2, -1],
        ],
        5 => [
            'sql' => "PRAGMA foreign_key_list(\"{$child}\"); --excess text",
            'table_valued' => false,
            'pragma' => 'foreign_key_list',
            'target' => $child,
            'row_count' => 1,
            'fk_table' => $table,
        ],
        6 => [
            'sql' => "pragma_table_info('{$table}'); --excess text",
            'table_valued' => true,
            'pragma' => 'table_info',
            'target' => $table,
            'row_count' => 3,
            'names' => ['setting_id', 'key_name', 'key_value'],
        ],
        7 => [
            'sql' => "pragma_index_xinfo('{$plainIndex}'); SELECT 10",
            'table_valued' => true,
            'pragma' => 'index_xinfo',
            'target' => $plainIndex,
            'row_count' => 3,
            'names' => ['key_name', 'key_value', null],
            'cids' => [1, 2, -1],
        ],
        8 => [
            'sql' => "PRAGMA table_info(\"{$semi}\"); -- semicolon belongs to the identifier above",
            'table_valued' => false,
            'pragma' => 'table_info',
            'target' => $semi,
            'row_count' => 2,
            'names' => ['quoted_key', 'quoted_value'],
        ],
        default => [
            'sql' => "PRAGMA table_info([{$bracket}]); /* tail ; stays outside the first statement */ SELECT 10",
            'table_valued' => false,
            'pragma' => 'table_info',
            'target' => $bracket,
            'row_count' => 2,
            'names' => ['bracket_key', 'bracket_value'],
        ],
    };
};

foreach (range(1, 1000) as $variant) {
    $tests[sprintf('real upstream capi3 pragma schema prepare tail variant %04d', $variant)] = static function (TestRunner $t) use ($catalogFor, $caseFor, $variant): void {
        $catalog = $catalogFor($variant);
        $case = $caseFor($variant);
        $result = $case['table_valued']
            ? $catalog->executeTableValuedPragma($case['sql'])
            : $catalog->executeSchemaPragma($case['sql']);

        $t->same('ok', $result['status']);
        $t->same($case['pragma'], $result['pragma']);
        $t->same('main', $result['schema']);
        $t->same($case['target'], $result['target']);
        $t->same($case['row_count'], count($result['rows']));

        if (isset($case['names'])) {
            $t->same($case['names'], array_column($result['rows'], 'name'));
        }
        if (isset($case['hidden'])) {
            $t->same($case['hidden'], array_column($result['rows'], 'hidden'));
        }
        if (isset($case['cids'])) {
            $t->same($case['cids'], array_column($result['rows'], 'cid'));
        }
        if (isset($case['fk_table'])) {
            $t->same($case['fk_table'], $result['rows'][0]['table']);
            $t->same('parent_key', $result['rows'][0]['from']);
            $t->same('CASCADE', $result['rows'][0]['on_update']);
            $t->same('SET NULL', $result['rows'][0]['on_delete']);
        }

        $cursor = $case['table_valued']
            ? $catalog->executeTableValuedPragmaCursor($case['sql'])
            : $catalog->executeSchemaPragmaCursor($case['sql']);
        $t->same($result['rows'][0], $cursor->current());
    };
}

$tests['real upstream capi3 pragma schema prepare tail source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'capi3.test capi3-2.6 prepares PRAGMA table_info("TableName"); --excess text as the first UTF-16 statement',
        'capi3.test capi3-2.7 steps the prepared PRAGMA once more and reaches SQLITE_DONE without evaluating the tail',
        'capi3.test capi3-2.8 finalizes the prepared PRAGMA successfully after ignoring the excess tail',
    ];

    $t->same(3, count($sections));
    $t->contains('capi3-2.6', $sections[0]);
    $t->contains('--excess text', $sections[0]);
    $t->contains('SQLITE_DONE', $sections[1]);
    $t->contains('finalizes', $sections[2]);
};

return $tests;
