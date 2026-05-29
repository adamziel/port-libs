<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas = [
    'main' => [
        'schema_cookie' => 173,
        'tables' => ['wp_options', 'wp_posts'],
        'indexes' => ['wp_options_name', 'wp_posts_date'],
    ],
    'temp' => [
        'schema_cookie' => 73,
        'tables' => ['wp_options'],
        'indexes' => [],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 101,
        'tables' => ['wp_posts_archive', 'wp_terms_archive'],
        'indexes' => ['wp_archive_posts_date', 'wp_terms_archive_slug'],
        'file' => '/srv/wp/archive-next173.sqlite',
    ],
];

$statements = [
    ['name' => 'pending-archive-comments-reader', 'sql' => 'SELECT comment_ID FROM archive.wp_comments WHERE comment_post_ID = ?'],
    ['name' => 'temp-options-index-reader', 'sql' => 'SELECT option_value FROM temp.wp_options INDEXED BY wp_temp_options_name WHERE option_name = ?'],
    ['name' => 'active-archive-terms-reader', 'sql' => 'SELECT term_id FROM archive.wp_terms_archive WHERE slug = ?', 'active' => true],
    ['name' => 'options-writer', 'sql' => 'UPDATE wp_options SET option_value = ? WHERE option_name = ?'],
];

$events = [
    ['op' => 'wal_commit', 'schema' => 'archive', 'schema_cookie' => 173, 'table' => 'wp_comments'],
    ['op' => 'create_index', 'schema' => 'temp', 'index' => 'wp_temp_options_name'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext173176($schemas, $statements, $events);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next173-176');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next173');
    assert($plan['event_count'] === 2);
    assert($plan['changed_schemas'] === ['temp', 'archive']);
    assert($plan['schema_cookies_next']['archive'] === 173);
    assert($plan['schema_cookies_next']['temp'] === 74);
    assert($plan['statements']['pending-archive-comments-reader']['schema_transitions'][0]['next_found'] === true);
    assert($plan['statements']['temp-options-index-reader']['index_transitions'][0]['next_found'] === true);
    assert(in_array('options-writer', $plan['write_statements_blocked_before_retry'], true));

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next173-176 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
