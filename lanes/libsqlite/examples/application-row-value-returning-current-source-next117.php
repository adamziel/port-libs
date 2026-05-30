<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteLimitPlan.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteReturningSql.php';

use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 3, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 4, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
];

$tables = ['wp_options' => $rows];

$update = SQLiteUpdateDeleteReturningSql::execute(
    "UPDATE wp_options SET (autoload, status, option_value, bytes) = ('yes', 'migrated', option_name || ':migrated', bytes + blog_id + 100) WHERE (blog_id, option_name) IN ((2, 'siteurl'), (2, 'pending_theme')) RETURNING option_id, option_name || ':' || status AS next_label, bytes + blog_id AS next_weight, (autoload, status) = ('yes', 'migrated') AS migrated_tuple ORDER BY option_id DESC LIMIT 2",
    $tables,
);

$delete = SQLiteUpdateDeleteReturningSql::execute(
    "DELETE FROM wp_options WHERE (blog_id, option_name) = (1, '_transient_feed') RETURNING option_id, option_name || ':' || status AS old_label, (blog_id, status) = (1, 'stale') AS was_blog_one_stale",
    $tables,
);

$summary = [
    'scenario' => 'application-row-value-returning-current-source-next117',
    'applicationUse' => 'Preview copied multisite wp_options cleanup/import SQL that uses row-value predicates and assignments while RETURNING expression aliases inspect the old DELETE image and the new UPDATE image without ext/sqlite.',
    'updateReturning' => $update['returning'],
    'deleteReturning' => $delete['returning'],
    'dependencies' => [
        'native-php-update-delete-returning-sql',
        'sqlite-row-value-returning-current-source-next117',
    ],
];

if (($argv[1] ?? null) === '--self-test') {
    assert($summary['updateReturning'][0]['next_label'] === 'siteurl:migrated');
    assert($summary['updateReturning'][1]['migrated_tuple'] === 1);
    assert($summary['deleteReturning'][0]['old_label'] === '_transient_feed:stale');
    assert($summary['deleteReturning'][0]['was_blog_one_stale'] === 1);
    echo "application-row-value-returning-current-source-next117 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
