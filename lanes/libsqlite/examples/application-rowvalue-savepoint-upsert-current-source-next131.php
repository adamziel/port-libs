<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteRowValueSavepointUpsertCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteRowValueSavepointUpsertCurrentSourceNextPlan;

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
        ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
        ['option_id' => 3, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ],
];

$statements = [
    "INSERT INTO wp_options (option_id, blog_id, option_name, autoload, status, bytes, option_value) VALUES (4, 2, 'blogdescription', 'no', 'inserted', 8, 'Network Tagline') ON CONFLICT (blog_id, option_name) DO UPDATE SET (autoload, status, bytes, option_value) = (excluded.autoload, 'updated', bytes + excluded.bytes, option_value || ':' || excluded.option_value) RETURNING option_id, blog_id, option_name, status, bytes, option_value",
    "INSERT INTO wp_options (option_id, blog_id, option_name, autoload, status, bytes, option_value) VALUES (5, 1, 'siteurl', 'no', 'incoming', 5, 'https://new.test') ON CONFLICT (blog_id, option_name) DO UPDATE SET (autoload, status, bytes, option_value) = (excluded.autoload, 'updated', bytes + excluded.bytes, option_value || ':' || excluded.option_value) RETURNING option_id, blog_id, option_name, status, bytes, option_value",
];

$plan = SQLiteRowValueSavepointUpsertCurrentSourceNextPlan::execute(
    $tables,
    $statements,
    [['blog_id', 'option_name'], ['option_id']],
    'app_settings_upsert_import',
    'option_id',
);

$summary = [
    'scenario' => 'application-rowvalue-savepoint-upsert-current-source-next131',
    'applicationUse' => 'Preview copied wp_options imports where INSERT ON CONFLICT DO UPDATE uses row-value assignments inside an import savepoint, yielding inserted/updated RETURNING rows while preserving current-source rollback evidence for later unique failures.',
    'status' => $plan['status'],
    'actions' => array_column($plan['executed_statements'], 'action'),
    'returningRows' => array_merge(...array_column($plan['yielded_returning'], 'rows')),
    'currentOptionNames' => array_column($plan['current_source_tables']['wp_options'], 'option_name', 'option_id'),
    'dependencies' => $plan['dependencies'],
    'dependencyClosure' => 'no new support component needed; this composes native PHP INSERT ON CONFLICT DO UPDATE row-value assignment parsing with existing savepoint current-source reporting',
];

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    assert($summary['status'] === 'released');
    assert($summary['actions'] === ['insert', 'update']);
    assert($summary['currentOptionNames'][4] === 'blogdescription');
    assert($summary['returningRows'][1]['status'] === 'updated');
    echo "application-rowvalue-savepoint-upsert-current-source-next131 self-test passed\n";
}

return $summary;
