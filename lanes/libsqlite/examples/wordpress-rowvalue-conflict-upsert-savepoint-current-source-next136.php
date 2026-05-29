<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteRowValueSavepointUpsertCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteRowValueConflictUpsertSavepointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteRowValueConflictUpsertSavepointCurrentSourceNextPlan;

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
        ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
        ['option_id' => 3, 'blog_id' => 1, 'option_name' => 'theme_mods', 'autoload' => 'no', 'status' => 'draft', 'bytes' => 10, 'option_value' => 'a:0:{}'],
    ],
];

$moveSiteurl = "INSERT INTO wp_options (option_id, blog_id, option_name, move_name, autoload, status, bytes, option_value) VALUES (10, 1, 'siteurl', 'plugin_cache', 'no', 'incoming', 5, 'cache-a') ON CONFLICT (blog_id, option_name) DO UPDATE SET (option_name, autoload, status, bytes, option_value) = (excluded.move_name, excluded.autoload, 'moved', bytes + excluded.bytes, option_value || ':' || excluded.option_value) RETURNING option_id, blog_id, option_name, autoload, status, bytes, option_value";
$hitMovedKey = "INSERT INTO wp_options (option_id, blog_id, option_name, move_name, autoload, status, bytes, option_value) VALUES (11, 1, 'plugin_cache', 'plugin_cache', 'yes', 'incoming', 3, 'cache-b') ON CONFLICT (blog_id, option_name) DO UPDATE SET (autoload, status, bytes, option_value) = (excluded.autoload, 'refreshed', bytes + excluded.bytes, option_value || ':' || excluded.option_value) RETURNING option_id, blog_id, option_name, autoload, status, bytes, option_value";
$duplicateTheme = "INSERT INTO wp_options (option_id, blog_id, option_name, move_name, autoload, status, bytes, option_value) VALUES (12, 1, 'home', 'theme_mods', 'no', 'incoming', 4, 'bad') ON CONFLICT (blog_id, option_name) DO UPDATE SET (option_name, status, bytes, option_value) = (excluded.move_name, 'duplicate-key', bytes + excluded.bytes, excluded.option_value) RETURNING option_id, blog_id, option_name, status, bytes";

$plan = SQLiteRowValueConflictUpsertSavepointCurrentSourceNextPlan::execute(
    $tables,
    [$moveSiteurl, $hitMovedKey, $duplicateTheme],
    [['blog_id', 'option_name'], ['option_id']],
    ['blog_id', 'option_name'],
    'wp_import_conflict_move'
);

$summary = [
    'scenario' => 'wordpress-rowvalue-conflict-upsert-savepoint-current-source-next136',
    'wordpressUse' => 'Preview copied wp_options imports where an UPSERT row-value assignment moves a composite conflict key, later rows must resolve against that moved current-source key, and a later duplicate composite key rolls the savepoint back to the original source.',
    'status' => $plan['status'],
    'rolledBack' => $plan['rolled_back'],
    'rollbackReason' => $plan['rollback_reason'],
    'movedConflictKeys' => $plan['moved_conflict_keys'],
    'matchedMovedConflictKeys' => $plan['matched_moved_conflict_keys'],
    'currentOptionNames' => array_column($plan['current_source_tables']['wp_options'], 'option_name', 'option_id'),
    'attemptedOptionNames' => array_column($plan['next_source_tables']['wp_options'], 'option_name', 'option_id'),
    'dependencies' => $plan['dependencies'],
    'dependencyClosure' => 'no new support component needed; this composes native PHP row-value UPSERT savepoint execution with current-source conflict-key movement diagnostics',
];

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    assert($summary['rolledBack'] === true);
    assert($summary['currentOptionNames'][1] === 'siteurl');
    assert($summary['attemptedOptionNames'][1] === 'plugin_cache');
    assert($summary['matchedMovedConflictKeys'][0]['key'] === '1|plugin_cache');
    echo "wordpress-rowvalue-conflict-upsert-savepoint-current-source-next136 self-test passed\n";
}

return $summary;
