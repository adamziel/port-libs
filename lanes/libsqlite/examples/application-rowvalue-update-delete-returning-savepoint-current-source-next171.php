<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'core', 'bytes' => 20, 'option_value' => 'https://old.test'],
        ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'core', 'bytes' => 21, 'option_value' => 'https://home.test'],
        ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bucket' => 'cache', 'bytes' => 12, 'option_value' => 'feed'],
        ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bucket' => 'cache', 'bytes' => 13, 'option_value' => 'timeout'],
        ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bucket' => 'theme', 'bytes' => 7, 'option_value' => 'theme'],
        ['option_id' => 6, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bucket' => 'plugin', 'bytes' => 11, 'option_value' => 'plugin'],
        ['option_id' => 7, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bucket' => 'rewrite', 'bytes' => 9, 'option_value' => 'rules'],
    ],
];

$unique = [['blog_id', 'option_name']];
$updateSql = "UPDATE wp_options SET (status, option_value, bytes) = ('review', option_value || ':review', bytes + 5) WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (2, 'pending_theme')) OR (status, bucket) BETWEEN ('queued', 'plugin') AND ('queued', 'rewrite') AND autoload = 'no' RETURNING option_id, option_name, status, option_value, bytes ORDER BY option_id";
$deleteSql = "DELETE FROM wp_options WHERE (status, option_name) IN (('review', '_transient_feed'), ('review', 'plugin_batch')) OR option_name = 'home' RETURNING option_id, option_name, status, option_value ORDER BY option_id";

$savepointImage = $tables;
$updated = SQLiteUpdateDeleteReturningSql::execute($updateSql, $savepointImage, 'option_id', $unique);
$deleted = SQLiteUpdateDeleteReturningSql::execute($deleteSql, $updated['tables'], 'option_id', $unique);

$summary = [
    'scenario' => 'application-rowvalue-update-delete-returning-savepoint-current-source-next171',
    'applicationUse' => 'Copied wp_options cleanup/import SQL can use OR-composed row-value predicates for UPDATE/DELETE RETURNING while a savepoint current source advances through the yielded rows without ext/sqlite.',
    'dependencyClosure' => 'no new support component needed; this extends the native PHP UPDATE/DELETE RETURNING row-value predicate evaluator with top-level OR grouping',
    'savepoint' => 'wp_options_rowvalue_or_predicate_next171',
    'status' => 'released-after-rowvalue-or-predicate-cleanup',
    'updateReturningIds' => array_column($updated['returning'], 'option_id'),
    'deleteReturningIds' => array_column($deleted['returning'], 'option_id'),
    'finalOptionIds' => array_column($deleted['tables']['wp_options'], 'option_id'),
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['updateReturningIds'] !== [3, 5, 6]
        || $summary['deleteReturningIds'] !== [2, 3, 6]
        || $summary['finalOptionIds'] !== [1, 4, 5, 7]
    ) {
        fwrite(STDERR, "unexpected row-value OR current-source next171 summary\n");
        exit(1);
    }
    echo "application-rowvalue-update-delete-returning-savepoint-current-source-next171 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
