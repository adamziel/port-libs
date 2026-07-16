<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCachePlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas = [
    'main' => ['schema_cookie' => 185, 'tables' => ['wp_options', 'wp_posts'], 'indexes' => ['wp_options_name', 'wp_posts_date']],
    'temp' => ['schema_cookie' => 88, 'tables' => ['wp_options', 'wp_uploads'], 'indexes' => ['wp_temp_options_name', 'wp_uploads_token'], 'temp' => true],
    'archive' => ['schema_cookie' => 184, 'tables' => ['wp_comments'], 'indexes' => ['wp_comments_post_id'], 'file' => '/srv/wp/archive-next185.sqlite'],
];

$statements = [
    ['name' => 'temp-upload-reader', 'sql' => 'SELECT file FROM temp.wp_uploads INDEXED BY wp_uploads_token WHERE token = ?', 'active' => true],
    ['name' => 'archive-comments-reader', 'sql' => 'SELECT comment_ID FROM archive.wp_comments INDEXED BY wp_comments_post_id WHERE comment_post_ID = ?'],
    ['name' => 'main-posts-writer', 'sql' => 'UPDATE wp_posts SET post_modified = ? WHERE ID = ?'],
];

$plan = SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan($schemas, $statements, [
    ['op' => 'drop_index', 'schema' => 'temp', 'index' => 'wp_uploads_token'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 187, 'table' => 'wp_posts'],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-consolidated');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-consolidated', $plan['dependencies'], true));
    assert($plan['changed_schemas'] === ['temp', 'main']);
    assert($plan['statements']['temp-upload-reader']['index_transitions'][0]['next_found'] === false);
    assert(in_array('main-posts-writer', $plan['write_statements_blocked_before_retry'], true));

    echo "application-attach-temp-wal-schema-cache-current-source-next185-188 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
