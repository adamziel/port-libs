<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteGeneratedColumnDependencyPlan;

$schema = <<<'SQL'
CREATE TABLE wp_options(
    option_id INTEGER PRIMARY KEY,
    option_name TEXT NOT NULL,
    option_value TEXT NOT NULL,
    option_name_fold TEXT AS (lower(option_name)) VIRTUAL,
    option_cache_key TEXT AS (option_name_fold || ':' || option_value_len) STORED,
    option_value_len INTEGER AS (length(option_value)) VIRTUAL
)
SQL;

$cycleSchema = <<<'SQL'
CREATE TABLE wp_options(
    option_id INTEGER PRIMARY KEY,
    option_name TEXT NOT NULL,
    option_value TEXT NOT NULL,
    option_name_fold TEXT AS (option_cache_key || ':' || option_name) VIRTUAL,
    option_cache_key TEXT AS (option_name_fold || ':' || option_value) STORED
)
SQL;

echo json_encode([
    'applicationUse' => 'Preflight copied wp_options generated-column expressions for SQLite-style dependency loops before native PHP import or repair without ext/sqlite.',
    'acyclic' => SQLiteGeneratedColumnDependencyPlan::analyze($schema),
    'cycle' => SQLiteGeneratedColumnDependencyPlan::analyze($cycleSchema),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
