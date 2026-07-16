<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$next248Rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::externalParentKeyRows248([
    $record('table', 'wp_terms_248', 'wp_terms_248', 2, 'CREATE TABLE wp_terms_248(taxonomy TEXT NOT NULL, slug TEXT NOT NULL)', 1),
    $record('index', 'wp_terms_248_taxonomy_slug_unique', 'wp_terms_248', 3, 'CREATE UNIQUE INDEX wp_terms_248_taxonomy_slug_unique ON wp_terms_248(taxonomy, slug)', 2),
    $record('table', 'wp_relationships_248', 'wp_relationships_248', 4, 'CREATE TABLE wp_relationships_248(taxonomy TEXT, slug TEXT, FOREIGN KEY(taxonomy, slug) REFERENCES wp_terms_248(taxonomy, slug) ON DELETE CASCADE)', 3),
]);

$next249Rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::generatedChildColumnRows249([
    $record('table', 'wp_terms_249', 'wp_terms_249', 5, 'CREATE TABLE wp_terms_249(slug TEXT PRIMARY KEY, taxonomy TEXT UNIQUE)', 4),
    $record('index', 'sqlite_autoindex_wp_terms_249_1', 'wp_terms_249', 6, null, 5),
    $record('index', 'sqlite_autoindex_wp_terms_249_2', 'wp_terms_249', 7, null, 6),
    $record('table', 'wp_termmeta_249', 'wp_termmeta_249', 8, 'CREATE TABLE wp_termmeta_249(raw_slug TEXT, raw_taxonomy TEXT, slug_key TEXT AS (lower(raw_slug)) VIRTUAL REFERENCES wp_terms_249(slug), taxonomy_key TEXT AS (lower(raw_taxonomy)) STORED, FOREIGN KEY(slug_key, taxonomy_key) REFERENCES wp_terms_249(slug, taxonomy))', 7),
]);

$current250 = [
    $record('table', 'wp_terms_250', 'wp_terms_250', 9, 'CREATE TABLE wp_terms_250(slug TEXT PRIMARY KEY, taxonomy TEXT UNIQUE)', 8),
    $record('index', 'sqlite_autoindex_wp_terms_250_1', 'wp_terms_250', 10, null, 9),
    $record('index', 'sqlite_autoindex_wp_terms_250_2', 'wp_terms_250', 11, null, 10),
    $record('table', 'wp_termmeta_250', 'wp_termmeta_250', 12, 'CREATE TABLE wp_termmeta_250(raw_slug TEXT, raw_taxonomy TEXT, slug_ref TEXT AS (lower(raw_slug)) VIRTUAL REFERENCES wp_terms_250(slug), taxonomy_ref TEXT AS (lower(raw_taxonomy)) STORED, FOREIGN KEY(slug_ref, taxonomy_ref) REFERENCES wp_terms_250(slug, taxonomy))', 11),
];
$next250 = [
    $current250[0],
    $current250[1],
    $current250[2],
    $record('table', 'wp_termmeta_250', 'wp_termmeta_250', 12, 'CREATE TABLE wp_termmeta_250(raw_slug TEXT, raw_taxonomy TEXT, slug_ref TEXT REFERENCES wp_terms_250(slug), taxonomy_ref TEXT, FOREIGN KEY(slug_ref, taxonomy_ref) REFERENCES wp_terms_250(slug, taxonomy))', 11),
];
$next250Page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page250($current250, $next250, 'PRAGMA main.index_xinfo(sqlite_autoindex_wp_terms_250_1)', 'PRAGMA main.foreign_key_list(wp_termmeta_250)');

$next251Rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::expressionChildActionRows251([
    $record('table', 'wp_option_defaults_251', 'wp_option_defaults_251', 13, 'CREATE TABLE wp_option_defaults_251(option_name TEXT PRIMARY KEY, locale TEXT UNIQUE)', 12),
    $record('index', 'sqlite_autoindex_wp_option_defaults_251_1', 'wp_option_defaults_251', 14, null, 13),
    $record('index', 'sqlite_autoindex_wp_option_defaults_251_2', 'wp_option_defaults_251', 15, null, 14),
    $record('table', 'wp_options_stage_251', 'wp_options_stage_251', 16, 'CREATE TABLE wp_options_stage_251(option_name TEXT, locale TEXT, FOREIGN KEY(option_name, locale) REFERENCES wp_option_defaults_251(option_name, locale) ON UPDATE SET NULL)', 15),
    $record('index', 'wp_options_stage_251_expr_lookup', 'wp_options_stage_251', 17, 'CREATE INDEX wp_options_stage_251_expr_lookup ON wp_options_stage_251(option_name, lower(locale))', 16),
]);

$summary = [
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next248-251',
    'applicationUse' => 'Application schema import checks can keep external parent UNIQUE indexes, generated child columns, generated-child repair, and expression child action indexes visible in one PRAGMA/FK handoff.',
    'next248_status' => $next248Rows[0]['status'],
    'next248_drop_index_mismatch_risk' => $next248Rows[0]['drop_index_mismatch_risk'],
    'next249_generated_rows' => count($next249Rows),
    'next249_storage' => array_values(array_unique(array_column($next249Rows, 'child_generated_storage'))),
    'next250_current_rows' => $next250Page['current']['foreign_key_generated_child_columns']['rows'],
    'next250_next_rows' => $next250Page['next_counts']['foreign_key_generated_child_columns']['rows'],
    'next250_repaired' => $next250Page['delta']['foreign_key_generated_child_repaired'],
    'next251_status' => $next251Rows[0]['status'],
    'next251_expression_key_positions' => $next251Rows[0]['expression_key_positions'],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['next248_status'] !== 'external_unique_parent_key'
        || $summary['next248_drop_index_mismatch_risk'] !== true
        || $summary['next249_generated_rows'] !== 3
        || $summary['next249_storage'] !== ['virtual', 'stored']
        || $summary['next250_current_rows'] !== 3
        || $summary['next250_next_rows'] !== 0
        || $summary['next250_repaired'] !== true
        || $summary['next251_status'] !== 'expression_child_action_index'
        || $summary['next251_expression_key_positions'] !== [1]
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next248-251 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next248-251 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
