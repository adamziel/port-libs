<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
        ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
        ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
        ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
        ['option_id' => 5, 'blog_id' => 2, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 14, 'option_value' => 'network-feed'],
        ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ],
];

$delete = SQLiteUpdateDeleteReturningSql::execute(
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (2, '_transient_feed')) RETURNING option_id, blog_id, option_name ORDER BY blog_id DESC LIMIT 2",
    $tables,
);

$update = SQLiteUpdateDeleteReturningSql::execute(
    "UPDATE wp_options SET (autoload, status, option_value) = ('yes', 'migrated', option_name || ':migrated'), bytes = bytes + 100 WHERE (blog_id, option_name) = (1, 'siteurl') RETURNING option_id, autoload, status, option_value, bytes",
    $tables,
);

$payload = [
    'applicationUse' => 'Preview copied multisite wp_options cleanup/import SQL that relies on SQLite row-value predicates and row-value UPDATE assignments while preserving DELETE old-row RETURNING values and UPDATE new-row RETURNING values without ext/sqlite.',
    'deleteSelectedIds' => $delete['plan']->selectedIds,
    'deleteMutationIds' => $delete['plan']->mutationIds,
    'deleteReturning' => $delete['returning'],
    'updateSelectedIds' => $update['plan']->selectedIds,
    'updateReturning' => $update['returning'],
    'dependencies' => [
        'sqlite-update-delete-returning-sql',
        'sqlite-row-value-predicate-current-source-next110',
        'application-wp-options-copy-import-cleanup',
    ],
];

if (($argv[1] ?? null) === '--self-test') {
    assert($payload['deleteSelectedIds'] === [5, 3]);
    assert($payload['deleteMutationIds'] === [3, 5]);
    assert($payload['deleteReturning'][0]['option_id'] === 3);
    assert($payload['updateSelectedIds'] === [1]);
    assert($payload['updateReturning'][0]['status'] === 'migrated');
    assert($payload['updateReturning'][0]['option_value'] === 'siteurl:migrated');
    echo "application-row-value-update-delete-current-source-next110 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
