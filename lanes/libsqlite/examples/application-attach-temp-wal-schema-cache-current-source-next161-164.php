<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCachePlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas = [
    'main' => [
        'schema_cookie' => 161,
        'tables' => ['wp_options', 'wp_posts'],
        'indexes' => ['wp_options_name'],
    ],
    'temp' => [
        'schema_cookie' => 61,
        'tables' => ['wp_import_queue'],
        'indexes' => ['wp_import_queue_slug'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 91,
        'tables' => ['wp_posts_archive', 'wp_terms_archive'],
        'indexes' => ['wp_archive_posts_date', 'wp_archive_terms_slug'],
        'file' => '/srv/wp/archive-next161.sqlite',
    ],
];

$statements = [
    ['name' => 'options-reader', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?'],
    ['name' => 'archive-terms-reader', 'sql' => 'SELECT name FROM archive.wp_terms_archive WHERE slug = ?'],
    ['name' => 'main-comments-reader', 'sql' => 'SELECT comment_ID FROM main.wp_comments WHERE comment_post_ID = ?'],
    ['name' => 'active-archive-index-reader', 'sql' => 'SELECT ID FROM archive.wp_posts_archive INDEXED BY wp_archive_posts_date WHERE post_date > ?', 'active' => true],
];

$events = [
    ['op' => 'schema_write', 'schema' => 'temp', 'table' => 'wp_options'],
    ['op' => 'detach', 'schema' => 'archive'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 163, 'table' => 'wp_comments'],
];

$plan = SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan($schemas, $statements, $events);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-consolidated');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert($plan['event_count'] === 3);
    assert($plan['changed_schemas'] === ['temp', 'main', 'archive']);
    assert($plan['statements']['options-reader']['schema_transitions'][0]['next_schema'] === 'temp');
    assert($plan['statements']['archive-terms-reader']['schema_transitions'][0]['next_schema'] === '__detached__');
    assert($plan['statements']['main-comments-reader']['schema_transitions'][0]['next_schema'] === 'main');

    echo "application-attach-temp-wal-schema-cache-current-source-next161-164 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
