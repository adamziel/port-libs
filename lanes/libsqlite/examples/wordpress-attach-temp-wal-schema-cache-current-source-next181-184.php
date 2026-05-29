<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas = [
    'main' => [
        'schema_cookie' => 181,
        'tables' => ['wp_options', 'wp_posts'],
        'indexes' => ['wp_options_name', 'wp_posts_date'],
    ],
    'temp' => [
        'schema_cookie' => 84,
        'tables' => ['wp_options', 'wp_posts'],
        'indexes' => ['wp_temp_options_name'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 180,
        'tables' => ['wp_comments', 'wp_terms_archive'],
        'indexes' => ['wp_comments_post_id', 'wp_terms_archive_slug'],
        'file' => '/srv/wp/archive-next181.sqlite',
    ],
];

$statements = [
    ['name' => 'media-reader', 'sql' => 'SELECT post_id FROM media.wp_postmeta WHERE meta_key = ?'],
    ['name' => 'archive-comments-reader', 'sql' => 'SELECT comment_ID FROM archive.wp_comments INDEXED BY wp_comments_post_id WHERE comment_post_ID = ?'],
    ['name' => 'active-temp-posts-reader', 'sql' => 'SELECT ID FROM temp.wp_posts WHERE post_status = ?', 'active' => true],
    ['name' => 'unqualified-posts-reader', 'sql' => 'SELECT ID FROM wp_posts WHERE post_status = ?'],
];

$events = [
    ['op' => 'attach', 'schema' => 'media', 'schema_cookie' => 181, 'tables' => ['wp_postmeta'], 'indexes' => ['wp_postmeta_key'], 'file' => '/srv/wp/media-next181.sqlite'],
    ['op' => 'rename_table', 'schema' => 'temp', 'from' => 'wp_posts', 'to' => 'wp_posts_next183'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext181184($schemas, $statements, $events);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next181-184');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next181');
    assert($plan['event_count'] === 2);
    assert($plan['changed_schemas'] === ['temp', 'media']);
    assert($plan['schema_cookies_next']['media'] === 181);
    assert($plan['schema_cookies_next']['temp'] === 85);
    assert($plan['statements']['media-reader']['schema_transitions'][0]['next_found'] === true);
    assert($plan['statements']['active-temp-posts-reader']['schema_transitions'][0]['next_found'] === false);
    assert(in_array('active-temp-posts-reader', $plan['active_current_snapshot_statements'], true));

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next181-184 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
