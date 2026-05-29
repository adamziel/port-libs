<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaImportExecutor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$schema = <<<'SQL'
CREATE TABLE wp_options(
    option_id INTEGER PRIMARY KEY,
    option_name TEXT NOT NULL UNIQUE,
    option_value TEXT NOT NULL DEFAULT '',
    autoload TEXT NOT NULL DEFAULT 'yes',
    CHECK(autoload IN ('yes','no'))
);
CREATE INDEX wp_options_autoload_name ON wp_options(autoload, option_name);
CREATE TEMP TABLE IF NOT EXISTS wp_options_stage(
    option_name TEXT PRIMARY KEY,
    option_value TEXT,
    source TEXT UNIQUE
);
CREATE INDEX IF NOT EXISTS temp.wp_options_stage_source ON wp_options_stage(source);
CREATE TABLE archive.wp_options_archive(
    option_id INTEGER,
    option_name TEXT,
    option_value TEXT,
    PRIMARY KEY(option_id, option_name),
    UNIQUE(option_name)
);
SQL;

$executor = new SQLiteSchemaImportExecutor();
$result = $executor->executeScript($schema, 'main');
$catalog = $executor->catalog();

echo json_encode([
    'scenario' => 'wordpress-schema-import-exec-current-next20',
    'wordpressUse' => 'Import copied WordPress schema DDL into bounded sqlite_schema records, including temp staging tables, attached archive tables, autoindexes, rootpages, and PRAGMA catalog handoff without requiring ext/sqlite.',
    'statementCount' => count($result['statements']),
    'mainObjects' => array_map(static fn ($record): string => $record->name, $executor->schemaRecords('main')),
    'tempObjects' => array_map(static fn ($record): string => $record->name, $executor->schemaRecords('temp')),
    'archiveObjects' => array_map(static fn ($record): string => $record->name, $executor->schemaRecords('archive')),
    'wpOptionsColumns' => array_column($catalog->executeSchemaPragma('PRAGMA table_info(wp_options)')['rows'], 'name'),
    'wpOptionsIndexes' => array_column($catalog->executeSchemaPragma('PRAGMA index_list(wp_options)')['rows'], 'name'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
