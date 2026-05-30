<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteLimitPlan.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteReturningSql.php';
require_once __DIR__ . '/../src/SQLiteRowValueUpdateDeleteSavepointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteSavepointCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'core', 'bytes' => 24, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bucket' => null, 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 3, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bucket' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 4, 'blog_id' => 3, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bucket' => 'staged', 'bytes' => 8, 'option_value' => 'theme-three'],
];

$plan = SQLiteRowValueUpdateDeleteSavepointCurrentSourceNextPlan::execute(
    ['wp_options' => $rows],
    [
        "UPDATE wp_options SET (autoload, status, option_value, bytes) = ('yes', 'null-safe', option_name || ':restored', bytes + 10) WHERE (status, bucket) IS (NULL, NULL) RETURNING option_id, status, bucket, (status, bucket) IS ('null-safe', NULL) AS tuple_is",
        "DELETE FROM wp_options WHERE (status, bucket) IS (NULL, 'staged') RETURNING option_id, option_name, (status, bucket) IS (NULL, 'staged') AS old_tuple_is",
    ],
    [['table' => 'wp_options', 'columns' => ['blog_id', 'option_name']]],
    'wp_options_rowvalue_is_batch',
);

$summary = [
    'scenario' => 'application-rowvalue-is-savepoint-current-source-next133',
    'applicationUse' => 'During copied wp_options cleanup, row-value IS/IS NOT predicates distinguish NULL=NULL from ordinary comparison UNKNOWN so nullable staged option rows update, delete, return, and roll back under a savepoint like SQLite current source.',
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'returning' => $plan['yielded_returning'],
    'currentIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'dependencies' => [
        'native-php-update-delete-returning-sql',
        'sqlite-row-value-is-null-safe-current-source-next133',
        'sqlite-savepoint-current-source-rollback',
    ],
    'dependencyClosure' => 'no new support component needed; this extends existing native PHP row-value UPDATE/DELETE RETURNING and savepoint current-source execution',
];

if (($argv[1] ?? null) === '--self-test') {
    assert($summary['status'] === 'released');
    assert($summary['returning'][0]['rows'][0]['tuple_is'] === 1);
    assert($summary['returning'][1]['rows'][0]['old_tuple_is'] === 1);
    assert($summary['currentIds'] === [1, 2, 3]);
    echo "application-rowvalue-is-savepoint-current-source-next133 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
