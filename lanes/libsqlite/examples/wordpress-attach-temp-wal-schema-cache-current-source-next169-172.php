<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCachePlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas = [
    'main' => [
        'schema_cookie' => 169,
        'tables' => ['wp_options', 'wp_posts'],
        'indexes' => ['wp_options_name', 'wp_posts_date'],
    ],
    'temp' => [
        'schema_cookie' => 69,
        'tables' => ['wp_plugin_stage'],
        'indexes' => ['wp_plugin_stage_slug'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 99,
        'tables' => ['wp_options_archive', 'wp_posts_archive'],
        'indexes' => ['wp_archive_options_name', 'wp_archive_posts_date'],
        'file' => '/srv/wp/archive-next169.sqlite',
    ],
];

$statements = [
    ['name' => 'active-archive-posts-index-reader', 'sql' => 'SELECT ID FROM archive.wp_posts_archive INDEXED BY wp_archive_posts_date WHERE post_date > ?', 'active' => true],
    ['name' => 'archive-comments-reader', 'sql' => 'SELECT comment_ID FROM archive.wp_comments WHERE comment_post_ID = ?'],
    ['name' => 'archive-options-writer', 'sql' => 'UPDATE archive.wp_options_archive SET option_value = ? WHERE option_name = ?'],
    ['name' => 'stage-reader', 'sql' => 'SELECT option_name FROM temp.wp_plugin_stage WHERE option_name LIKE ?'],
];

$events = [
    ['op' => 'drop_index', 'schema' => 'archive', 'index' => 'wp_archive_posts_date'],
    ['op' => 'wal_commit', 'schema' => 'archive', 'schema_cookie' => 170, 'table' => 'wp_comments'],
];

$plan = SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan($schemas, $statements, $events);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-consolidated');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert($plan['event_count'] === 2);
    assert($plan['changed_schemas'] === ['archive']);
    assert($plan['schema_cookies_next']['archive'] === 170);
    assert($plan['statements']['active-archive-posts-index-reader']['index_transitions'][0]['next_found'] === false);
    assert($plan['statements']['archive-comments-reader']['schema_transitions'][0]['next_found'] === true);
    assert(in_array('archive-options-writer', $plan['write_statements_blocked_before_retry'], true));

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next169-172 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
