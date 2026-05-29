<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_option_slug', 'wp_option_slug', 4, 'CREATE TABLE wp_option_slug(slug TEXT COLLATE NOCASE, locale TEXT COLLATE RTRIM)', 1),
    $record('table', 'wp_options', 'wp_options', 5, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, slug TEXT, locale TEXT, option_value TEXT, FOREIGN KEY(slug, locale) REFERENCES wp_option_slug(slug, locale))', 2),
    $record('index', 'wp_option_slug_expr_unique', 'wp_option_slug', 6, 'CREATE UNIQUE INDEX wp_option_slug_expr_unique ON wp_option_slug(slug COLLATE NOCASE, lower(locale) COLLATE RTRIM)', 3),
    $record('index', 'wp_options_fk_lookup', 'wp_options', 7, 'CREATE INDEX wp_options_fk_lookup ON wp_options(slug, locale)', 4),
];
$nextRecords = [
    $currentRecords[0],
    $currentRecords[1],
    $currentRecords[2],
    $record('index', 'wp_option_slug_full_unique', 'wp_option_slug', 8, 'CREATE UNIQUE INDEX wp_option_slug_full_unique ON wp_option_slug(slug COLLATE NOCASE, locale COLLATE RTRIM)', 5),
    $currentRecords[3],
];
$tables = [
    'wp_option_slug' => [['rowid' => 1, 'slug' => 'home', 'locale' => 'en_US']],
    'wp_options' => [['rowid' => 1, 'option_id' => 1, 'slug' => 'home', 'locale' => 'en_US', 'option_value' => 'https://example.test']],
];

$plan = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog190(
    $currentRecords,
    $tables,
    $nextRecords,
    $tables,
    'PRAGMA index_xinfo(wp_options_fk_lookup)',
);

if (($argv[1] ?? '') === '--self-test') {
    $expressionRows = array_values(array_filter($plan['rows'], static fn (array $row): bool => ($row['kind'] ?? null) === 'foreign_key_expression_parent_index'));
    assert($plan['status'] === 'ok');
    assert(count($expressionRows) === 4);
    assert($expressionRows[0]['status'] === 'expression_parent_key');
    assert($expressionRows[1]['index_cid'] === -2);
    assert($expressionRows[2]['status'] === 'shadowed_by_full_parent_key');
    assert($plan['delta']['foreign_key_expression_parent_index_repaired'] === true);
    echo "wordpress-pragma-index-xinfo-foreignkey-current-source-next190 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
