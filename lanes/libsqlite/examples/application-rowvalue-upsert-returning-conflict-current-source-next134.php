<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteRowValueSavepointUpsertCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteRowValueSavepointUpsertCurrentSourceNextPlan;

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test', 'revision' => 1],
        ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 18, 'option_value' => 'https://home.test', 'revision' => 1],
        ['option_id' => 3, 'blog_id' => 1, 'option_name' => 'blogname', 'autoload' => 'no', 'status' => 'archived', 'bytes' => 9, 'option_value' => 'Old Blog', 'revision' => 3],
    ],
];

$statements = [
    "INSERT INTO wp_options (option_id, blog_id, option_name, autoload, status, bytes, option_value, revision) VALUES (4, 1, 'siteurl', 'no', 'incoming', 5, 'https://new.test', 4) ON CONFLICT (blog_id, option_name) DO UPDATE SET (autoload, status, bytes, option_value, revision) = (excluded.autoload, 'merged', bytes + excluded.bytes, option_value || ':' || excluded.option_value, revision + excluded.revision) WHERE excluded.revision > revision RETURNING option_id, option_name, status, bytes, option_value, revision",
    "INSERT INTO wp_options (option_id, blog_id, option_name, autoload, status, bytes, option_value, revision) VALUES (5, 1, 'home', 'no', 'incoming', 5, 'https://skip.test', 1) ON CONFLICT (blog_id, option_name) DO UPDATE SET (autoload, status, bytes, option_value, revision) = (excluded.autoload, 'merged', bytes + excluded.bytes, excluded.option_value, revision + excluded.revision) WHERE excluded.revision > revision RETURNING option_id, option_name, status, revision",
    "INSERT INTO wp_options (option_id, blog_id, option_name, autoload, status, bytes, option_value, revision) VALUES (6, 1, 'blogname', 'yes', 'incoming', 3, 'New Blog', 5) ON CONFLICT (blog_id, option_name) DO NOTHING RETURNING option_id, option_name, status",
];

$plan = SQLiteRowValueSavepointUpsertCurrentSourceNextPlan::execute(
    $tables,
    $statements,
    [['blog_id', 'option_name'], ['option_id']],
    'wp_options_rowvalue_conflict_import'
);

$summary = [
    'scenario' => 'application-rowvalue-upsert-returning-conflict-current-source-next134',
    'applicationUse' => 'Preview copied wp_options imports where row-value UPSERT conflict policies decide current-source updates, skipped DO UPDATE WHERE rows, DO NOTHING conflicts, and statement-order RETURNING rows.',
    'status' => $plan['status'],
    'actions' => array_column($plan['executed_statements'], 'action'),
    'changes' => $plan['changes'],
    'returningRows' => array_merge(...array_column($plan['yielded_returning'], 'rows')),
    'skippedReasons' => array_column($plan['skipped_rows'], 'reason'),
    'currentValues' => array_column($plan['current_source_tables']['wp_options'], 'option_value', 'option_id'),
    'dependencyClosure' => 'no new support component needed; this reuses the native PHP row-value UPSERT/savepoint current-source helper and extends conflict policy evaluation',
];

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    assert($summary['status'] === 'released');
    assert($summary['actions'] === ['update', 'where-skipped', 'nothing']);
    assert($summary['changes'] === 1);
    assert($summary['returningRows'][0]['status'] === 'merged');
    assert($summary['skippedReasons'] === ['where-skipped', 'nothing']);
    echo "application-rowvalue-upsert-returning-conflict-current-source-next134 self-test passed\n";
}

return $summary;
