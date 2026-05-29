<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteUpdateDeleteLimitPlan.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteReturningSql.php';
require_once __DIR__ . '/../src/SQLiteRowValueReturningSavepointConflictCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteRowValueReturningSavepointConflictCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 4, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
];

$plan = SQLiteRowValueReturningSavepointConflictCurrentSourceNextPlan::execute(
    ['wp_options' => $rows],
    [
        "UPDATE OR IGNORE wp_options SET (blog_id, option_name, status) = (1, 'home', 'ignored') WHERE (blog_id, option_name) IN ((2, 'pending_theme')) RETURNING option_id, blog_id, option_name, status",
        "UPDATE OR REPLACE wp_options SET (blog_id, option_name, status, option_value) = (1, 'home', 'replaced', option_name || ':replaced') WHERE (blog_id, option_name) IN ((2, 'home')) RETURNING option_id, blog_id, option_name, status, option_value",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, option_name",
    ],
    [['blog_id', 'option_name']],
    'wp_options_conflict_import',
);

$summary = [
    'scenario' => 'wordpress-rowvalue-returning-savepoint-conflict-current-source-next128',
    'wordpressUse' => 'Model a copied wp_options import savepoint where row-value UPDATE RETURNING statements use OR IGNORE and OR REPLACE against the multisite option unique key before transient cleanup, so import tooling can explain yielded rows, ignored rows, replacement deletes, and savepoint state without the SQLite extension.',
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'conflictActions' => array_column($plan['executed_statements'], 'conflict_action'),
    'ignoredOptionIds' => array_map(static fn (array $entry): mixed => $entry['row']['option_id'], $plan['ignored_rows']),
    'replacementDeletedOptionIds' => array_map(static fn (array $entry): mixed => $entry['row']['option_id'], $plan['deleted_conflict_rows']),
    'yieldedRows' => array_map(static fn (array $entry): array => $entry['rows'], $plan['yielded_returning']),
    'finalOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'dependencies' => $plan['dependencies'],
    'dependencyClosure' => 'no new support component needed; this composes existing native PHP row-value UPDATE/DELETE RETURNING, unique-conflict handling, and savepoint current-source rollback bookkeeping',
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    assert($summary['status'] === 'released');
    assert($summary['ignoredOptionIds'] === [5]);
    assert($summary['replacementDeletedOptionIds'] === [2]);
    assert($summary['finalOptionIds'] === [1, 3, 4, 5]);
    echo "wordpress-rowvalue-returning-savepoint-conflict-current-source-next128 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
