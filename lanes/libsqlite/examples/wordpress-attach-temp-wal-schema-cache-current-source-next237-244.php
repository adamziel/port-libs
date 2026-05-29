<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas = [
    'main' => [
        'schema_cookie' => 237,
        'tables' => ['wp_options', 'wp_posts', 'wp_usermeta', 'wp_terms', 'wp_term_relationships'],
        'indexes' => ['wp_options_name', 'wp_posts_type_status', 'wp_usermeta_key', 'wp_terms_slug', 'wp_term_relationships_object'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 237, 'commit' => true],
            ['page' => 1, 'schema_cookie' => 245, 'commit' => false],
        ],
    ],
    'temp' => ['schema_cookie' => 144, 'tables' => ['wp_options', 'wp_import_batch', 'wp_rewrite_stage', 'wp_user_stage'], 'indexes' => ['wp_temp_options_name', 'wp_import_batch_token', 'wp_rewrite_stage_name', 'wp_user_stage_login'], 'temp' => true],
    'analytics' => ['schema_cookie' => 31, 'tables' => ['wp_events', 'wp_eventmeta', 'wp_event_rollup'], 'indexes' => ['wp_events_name', 'wp_eventmeta_key', 'wp_event_rollup_day'], 'file' => '/srv/wp/analytics-next237.sqlite'],
    'audit' => ['schema_cookie' => 17, 'tables' => ['wp_audit_log', 'wp_auditmeta'], 'indexes' => ['wp_audit_log_action', 'wp_auditmeta_key'], 'file' => '/srv/wp/audit-next237.sqlite'],
    'search' => ['schema_cookie' => 9, 'tables' => ['wp_search_cache'], 'indexes' => ['wp_search_cache_key'], 'file' => '/srv/wp/search-next237.sqlite'],
];

$statements = [
    ['name' => 'temp-options-reader', 'sql' => 'SELECT option_value FROM wp_options INDEXED BY wp_temp_options_name WHERE option_name = ?'],
    ['name' => 'main-options-reader', 'sql' => 'SELECT option_value FROM main.wp_options INDEXED BY wp_options_name WHERE option_name = ?'],
    ['name' => 'posts-reader', 'sql' => 'SELECT ID FROM wp_posts INDEXED BY wp_posts_type_status WHERE post_status = ?', 'active' => true],
    ['name' => 'usermeta-writer', 'sql' => 'UPDATE main.wp_usermeta SET meta_value = ? WHERE meta_key = ?'],
    ['name' => 'terms-reader', 'sql' => 'SELECT term_id FROM main.wp_terms INDEXED BY wp_terms_slug WHERE slug = ?'],
    ['name' => 'import-batch-reader', 'sql' => 'SELECT rowid FROM temp.wp_import_batch INDEXED BY wp_import_batch_token WHERE token = ?', 'active' => true],
    ['name' => 'rewrite-stage-reader', 'sql' => 'SELECT rowid FROM temp.wp_rewrite_stage INDEXED BY wp_rewrite_stage_name WHERE option_name = ?'],
    ['name' => 'analytics-events-reader', 'sql' => 'SELECT event_id FROM analytics.wp_events INDEXED BY wp_events_name WHERE event_name = ?'],
    ['name' => 'analytics-rollup-reader', 'sql' => 'SELECT day_key FROM analytics.wp_event_rollup INDEXED BY wp_event_rollup_day WHERE day_key = ?'],
    ['name' => 'audit-reader', 'sql' => 'SELECT log_id FROM audit.wp_audit_log INDEXED BY wp_audit_log_action WHERE action = ?'],
    ['name' => 'auditmeta-writer', 'sql' => 'UPDATE audit.wp_auditmeta INDEXED BY wp_auditmeta_key SET meta_value = ? WHERE meta_key = ?'],
    ['name' => 'search-reader', 'sql' => 'SELECT cache_value FROM search.wp_search_cache INDEXED BY wp_search_cache_key WHERE cache_key = ?'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext237244($schemas, $statements, [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 238, 'table' => 'wp_postmeta', 'indexes' => ['wp_postmeta_key'], 'commit' => true],
    ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_options'],
    ['op' => 'rename_index', 'schema' => 'audit', 'from' => 'wp_auditmeta_key', 'to' => 'wp_auditmeta_key_next239'],
    ['op' => 'attach', 'schema' => 'queue', 'schema_cookie' => 3, 'tables' => ['wp_job_queue'], 'indexes' => ['wp_job_queue_token'], 'file' => '/srv/wp/queue-next240.sqlite'],
    ['op' => 'detach', 'schema' => 'search'],
    ['op' => 'drop_table', 'schema' => 'analytics', 'table' => 'wp_event_rollup'],
    ['op' => 'wal_commit', 'schema' => 'audit', 'schema_cookie' => 243, 'table' => 'wp_audit_queue', 'commit' => false],
    ['op' => 'rename_index', 'schema' => 'main', 'from' => 'wp_posts_type_status', 'to' => 'wp_posts_type_status_next244'],
    ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_import_batch'],
    ['op' => 'wal_commit', 'schema' => 'analytics', 'schema_cookie' => 244, 'table' => 'wp_event_archive', 'commit' => true],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next237-244');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next237');
    assert($plan['dependencies'][7] === 'sqlite-attach-temp-wal-schema-cache-current-source-next244');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-current-source-next236', $plan['dependencies'], true));
    assert($plan['event_count'] === 9);
    assert($plan['changed_schemas'] === ['temp', 'main', 'analytics', 'audit', 'queue', 'search']);
    assert($plan['schema_cookies_next']['main'] === 239);
    assert($plan['schema_cookies_next']['temp'] === 146);
    assert($plan['schema_cookies_next']['analytics'] === 244);
    assert($plan['search_order_next'] === ['temp', 'main', 'analytics', 'audit', 'queue']);
    assert(in_array('posts-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('import-batch-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('usermeta-writer', $plan['write_statements_blocked_before_retry'], true));
    assert(in_array('auditmeta-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['search-reader']['schema_transitions'][0]['next_schema'] === '__detached__');
    assert($plan['statements']['temp-options-reader']['schema_transitions'][0]['next_schema'] === 'main');

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next237-244 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
