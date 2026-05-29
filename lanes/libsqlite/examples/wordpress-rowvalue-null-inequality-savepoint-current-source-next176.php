<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteLimitPlan.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteReturningSql.php';
require_once __DIR__ . '/../src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'core', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'active_plugins', 'autoload' => 'yes', 'status' => null, 'bucket' => 'plugins', 'bytes' => 31, 'option_value' => 'a:0:{}'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => null, 'bucket' => 'rules', 'bytes' => 32, 'option_value' => 'rules'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => null, 'bucket' => 'transient', 'bytes' => 9, 'option_value' => 'feed'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'active_plugins', 'autoload' => 'yes', 'status' => null, 'bucket' => 'plugins', 'bytes' => 41, 'option_value' => 'network'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => null, 'bucket' => 'rules', 'bytes' => 42, 'option_value' => 'network-rules'],
    ['option_id' => 7, 'blog_id' => 3, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'pending', 'bucket' => null, 'bytes' => 7, 'option_value' => 'theme'],
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNullInequalityRetrySavepointBatch(
    ['wp_options' => $rows],
    [
        "UPDATE wp_options SET (option_name, status, option_value, bytes) = (option_name || ':stage176', status, option_value || ':stage176', bytes + 1) WHERE (blog_id, status, option_name) <> (1, NULL, 'active_plugins') RETURNING option_id, option_name, status, (blog_id, status, option_name) <> (1, NULL, 'active_plugins') AS tuple_ne ORDER BY option_id",
        "UPDATE OR ROLLBACK wp_options SET (blog_id, option_name) = (1, 'active_plugins') WHERE option_id = 7 RETURNING option_id, blog_id, option_name",
    ],
    [
        "DELETE FROM wp_options WHERE (blog_id, status, option_name) <> (1, NULL, 'active_plugins') RETURNING option_id, option_name, status, (blog_id, status, option_name) = (1, NULL, 'active_plugins') AS tuple_eq, (blog_id, status, option_name) <> (1, NULL, 'active_plugins') AS tuple_ne ORDER BY option_id",
        "UPDATE wp_options SET (status, option_value) = ('kept176', option_value || ':kept') WHERE option_id = 2 RETURNING option_id, option_name, status, option_value",
    ],
    [['blog_id', 'option_name']],
    'wp_options_rowvalue_null_inequality_next176',
);

$summary = [
    'scenario' => 'wordpress-rowvalue-null-inequality-savepoint-current-source-next176',
    'wordpressUse' => 'Copied wp_options cleanup can delete rows that are deterministically not equal to a nullable row-value key while preserving rows whose tuple comparison is still UNKNOWN, then retry from the rollback source after an OR ROLLBACK conflict.',
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'discardedReturningCount' => $plan['discarded_returning_count'],
    'yieldedReturningCount' => $plan['yielded_returning_count'],
    'deletedIds' => array_column($plan['yielded_returning'][0]['rows'], 'option_id'),
    'currentRows' => $plan['current_source_tables']['wp_options'],
    'dependencyClosure' => 'no new support component needed; this reuses native PHP UPDATE/DELETE RETURNING row-value execution and savepoint current-source retry planning',
];

if (($argv[1] ?? null) === '--self-test') {
    assert($summary['status'] === 'transaction-rolled-back-retried-current-source-next164');
    assert($summary['discardedReturningCount'] === 6);
    assert($summary['yieldedReturningCount'] === 7);
    assert($summary['deletedIds'] === [1, 3, 4, 5, 6, 7]);
    assert(array_column($summary['currentRows'], 'option_id') === [2]);
    assert($summary['currentRows'][0]['status'] === 'kept176');
    echo "wordpress-rowvalue-null-inequality-savepoint-current-source-next176 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
