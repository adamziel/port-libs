<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$tests = [];

$tests['pragma index xinfo foreignkey next248 external parent unique index stays visible'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent248', 'parent248', 2, 'CREATE TABLE parent248(taxonomy TEXT NOT NULL, slug TEXT NOT NULL)', 1),
        $record('index', 'parent248_taxonomy_slug_unique', 'parent248', 3, 'CREATE UNIQUE INDEX parent248_taxonomy_slug_unique ON parent248(taxonomy, slug)', 2),
        $record('table', 'child248', 'child248', 4, 'CREATE TABLE child248(taxonomy TEXT NOT NULL, slug TEXT NOT NULL, FOREIGN KEY(taxonomy, slug) REFERENCES parent248(taxonomy, slug) ON DELETE CASCADE)', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::externalParentKeyRows248($records);

    $t->same(1, count($rows));
    $t->same('external_unique_parent_key', $rows[0]['status']);
    $t->same('parent248_taxonomy_slug_unique', $rows[0]['parent_index']);
    $t->same(['taxonomy', 'slug'], $rows[0]['parent_index_columns']);
    $t->same(true, $rows[0]['drop_index_mismatch_risk']);
};

$tests['pragma index xinfo foreignkey next249 generated child columns are reported from table_xinfo'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent249', 'parent249', 2, 'CREATE TABLE parent249(slug TEXT PRIMARY KEY, taxonomy TEXT UNIQUE)', 1),
        $record('index', 'sqlite_autoindex_parent249_1', 'parent249', 3, null, 2),
        $record('index', 'sqlite_autoindex_parent249_2', 'parent249', 4, null, 3),
        $record('table', 'child249', 'child249', 5, 'CREATE TABLE child249(raw_slug TEXT, raw_taxonomy TEXT, slug_key TEXT AS (lower(raw_slug)) VIRTUAL REFERENCES parent249(slug), taxonomy_key TEXT AS (lower(raw_taxonomy)) STORED, FOREIGN KEY(slug_key, taxonomy_key) REFERENCES parent249(slug, taxonomy))', 4),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::generatedChildColumnRows249($records);

    $t->same(3, count($rows));
    $t->same('foreign_key_generated_child_column', $rows[0]['kind']);
    $t->same('generated_child_column', $rows[0]['status']);
    $t->same('virtual', $rows[0]['child_generated_storage']);
    $t->same('stored', $rows[2]['child_generated_storage']);
};

$tests['pragma index xinfo foreignkey next250 generated child page clears after plain child columns'] = static function (TestRunner $t) use ($record): void {
    $current = [
        $record('table', 'parent250', 'parent250', 2, 'CREATE TABLE parent250(slug TEXT PRIMARY KEY, taxonomy TEXT UNIQUE)', 1),
        $record('index', 'sqlite_autoindex_parent250_1', 'parent250', 3, null, 2),
        $record('index', 'sqlite_autoindex_parent250_2', 'parent250', 4, null, 3),
        $record('table', 'child250', 'child250', 5, 'CREATE TABLE child250(raw_slug TEXT, raw_taxonomy TEXT, slug_key TEXT AS (lower(raw_slug)) VIRTUAL REFERENCES parent250(slug), taxonomy_key TEXT AS (lower(raw_taxonomy)) STORED, FOREIGN KEY(slug_key, taxonomy_key) REFERENCES parent250(slug, taxonomy))', 4),
    ];
    $next = [
        $current[0],
        $current[1],
        $current[2],
        $record('table', 'child250', 'child250', 5, 'CREATE TABLE child250(raw_slug TEXT, raw_taxonomy TEXT, slug_key TEXT REFERENCES parent250(slug), taxonomy_key TEXT, FOREIGN KEY(slug_key, taxonomy_key) REFERENCES parent250(slug, taxonomy))', 4),
    ];

    $page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page250($current, $next, 'PRAGMA main.index_xinfo(sqlite_autoindex_parent250_1)', 'PRAGMA main.foreign_key_list(child250)');

    $t->same('ok', $page['status']);
    $t->same(3, $page['current']['foreign_key_generated_child_columns']['rows']);
    $t->same(0, $page['next_counts']['foreign_key_generated_child_columns']['rows']);
    $t->same(true, $page['delta']['foreign_key_generated_child_repaired']);
};

$tests['pragma index xinfo foreignkey next251 expression child action index is blocked'] = static function (TestRunner $t) use ($record): void {
    $records = [
        $record('table', 'parent251', 'parent251', 2, 'CREATE TABLE parent251(option_name TEXT PRIMARY KEY, locale TEXT UNIQUE)', 1),
        $record('index', 'sqlite_autoindex_parent251_1', 'parent251', 3, null, 2),
        $record('index', 'sqlite_autoindex_parent251_2', 'parent251', 4, null, 3),
        $record('table', 'child251', 'child251', 5, 'CREATE TABLE child251(option_name TEXT, locale TEXT, FOREIGN KEY(option_name, locale) REFERENCES parent251(option_name, locale) ON UPDATE SET NULL)', 4),
        $record('index', 'child251_expr_lookup', 'child251', 6, 'CREATE INDEX child251_expr_lookup ON child251(option_name, lower(locale))', 5),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::expressionChildActionRows251($records);

    $t->same(2, count($rows));
    $t->same('foreign_key_child_action_expression_index', $rows[0]['kind']);
    $t->same('expression_child_action_index', $rows[0]['status']);
    $t->same(['option_name', null], $rows[0]['child_index_columns']);
    $t->same([1], $rows[0]['expression_key_positions']);
};

return $tests;
