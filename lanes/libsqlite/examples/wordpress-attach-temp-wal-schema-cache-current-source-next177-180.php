<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCachePlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas = [
    'main' => [
        'schema_cookie' => 177,
        'tables' => ['wp_options', 'wp_posts'],
        'indexes' => ['wp_options_name', 'wp_posts_date'],
    ],
    'temp' => [
        'schema_cookie' => 80,
        'tables' => ['wp_options'],
        'indexes' => ['wp_temp_options_name'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 176,
        'tables' => ['wp_comments', 'wp_terms_archive'],
        'indexes' => ['wp_comments_post_id', 'wp_terms_archive_slug'],
        'file' => '/srv/wp/archive-next177.sqlite',
    ],
];

$statements = [
    ['name' => 'archive-comments-reader', 'sql' => 'SELECT comment_ID FROM archive.wp_comments INDEXED BY wp_comments_post_id WHERE comment_post_ID = ?'],
    ['name' => 'active-archive-terms-reader', 'sql' => 'SELECT term_id FROM archive.wp_terms_archive INDEXED BY wp_terms_archive_slug WHERE slug = ?', 'active' => true],
    ['name' => 'temp-options-reader', 'sql' => 'SELECT option_value FROM temp.wp_options INDEXED BY wp_temp_options_name WHERE option_name = ?'],
    ['name' => 'unqualified-options-reader', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?'],
];

$events = [
    ['op' => 'rename_index', 'schema' => 'archive', 'from' => 'wp_terms_archive_slug', 'to' => 'wp_terms_archive_slug_2026'],
    ['op' => 'drop_index', 'schema' => 'temp', 'index' => 'wp_temp_options_name'],
];

$plan = SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan($schemas, $statements, $events);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-consolidated');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert($plan['event_count'] === 2);
    assert($plan['changed_schemas'] === ['temp', 'archive']);
    assert($plan['schema_cookies_next']['archive'] === 177);
    assert($plan['schema_cookies_next']['temp'] === 81);
    assert($plan['statements']['active-archive-terms-reader']['index_transitions'][0]['next_found'] === false);
    assert($plan['statements']['temp-options-reader']['index_transitions'][0]['next_found'] === false);
    assert(in_array('active-archive-terms-reader', $plan['active_current_snapshot_statements'], true));

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next177-180 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
