<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteSchemaRecord.php';
require_once __DIR__ . '/../src/SQLiteCreateTable.php';
require_once __DIR__ . '/../src/SQLiteIndexColumn.php';
require_once __DIR__ . '/../src/SQLitePragmaSchemaCatalog.php';
require_once __DIR__ . '/../src/SQLitePragmaForeignKeyCheck.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$current = [
    $record('table', 'wp_option_defaults', 'wp_option_defaults', 2, 'CREATE TABLE wp_option_defaults(option_name TEXT PRIMARY KEY, locale TEXT NOT NULL, UNIQUE(option_name, locale))', 1),
    $record('index', 'sqlite_autoindex_wp_option_defaults_1', 'wp_option_defaults', 3, null, 2),
    $record('index', 'sqlite_autoindex_wp_option_defaults_2', 'wp_option_defaults', 4, null, 3),
    $record('table', 'wp_options_stage', 'wp_options_stage', 5, 'CREATE TABLE wp_options_stage(option_name TEXT NOT NULL, locale TEXT NOT NULL, FOREIGN KEY(option_name, locale) REFERENCES wp_option_defaults(option_name, locale) ON DELETE CASCADE)', 4),
    $record('index', 'wp_options_stage_expr_lookup', 'wp_options_stage', 6, 'CREATE INDEX wp_options_stage_expr_lookup ON wp_options_stage(option_name, lower(locale))', 5),
];

$next = [
    $current[0],
    $current[1],
    $current[2],
    $current[3],
    $record('index', 'wp_options_stage_fk_lookup', 'wp_options_stage', 6, 'CREATE INDEX wp_options_stage_fk_lookup ON wp_options_stage(option_name, locale)', 5),
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page251(
    $current,
    $next,
    'PRAGMA main.index_xinfo(wp_options_stage_expr_lookup)',
    'PRAGMA main.foreign_key_list(wp_options_stage)',
    limit: 400,
);

$payload = [
    'scenario' => 'copied wp_options FK action lookup expression-index repair',
    'operation' => $page['operation'],
    'currentBlocked' => $page['current']['foreign_key_child_action_expression_index']['blocked'],
    'nextBlocked' => $page['next_counts']['foreign_key_child_action_expression_index']['blocked'],
    'repaired' => $page['delta']['foreign_key_child_action_expression_index_repaired'],
    'firstSummary' => $page['current_source']['foreign_key_child_action_expression_index'][0] ?? null,
    'applicationUse' => 'Copied wp_options staging tables can avoid treating an expression index as the child-side lookup index for cascading FK actions before publishing a repaired plain child-key index.',
];

if ($payload['currentBlocked'] !== 2 || $payload['nextBlocked'] !== 0 || $payload['repaired'] !== true) {
    fwrite(STDERR, 'application-pragma-index-xinfo-foreignkey-current-source-next251 self-test failed' . PHP_EOL);
    exit(1);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo 'application-pragma-index-xinfo-foreignkey-current-source-next251 self-test passed' . PHP_EOL;
