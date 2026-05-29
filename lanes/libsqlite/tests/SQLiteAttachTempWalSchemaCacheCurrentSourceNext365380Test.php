<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas365380 = [
    'main' => [
        'schema_cookie' => 350,
        'tables' => ['wp_options', 'wp_posts', 'wp_postmeta', 'wp_users', 'wp_term_taxonomy', 'wp_block_patterns', 'wp_global_styles', 'wp_navigation_menus'],
        'indexes' => ['wp_options_name', 'wp_posts_type_status_next293', 'wp_postmeta_key', 'wp_users_login_next308', 'wp_term_taxonomy_taxonomy', 'wp_block_patterns_slug', 'wp_global_styles_slug', 'wp_navigation_menus_slug'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 350, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 364,
        'tables' => ['wp_theme_stage', 'wp_theme_stage_preview'],
        'indexes' => ['wp_theme_stage_stylesheet_next350', 'wp_theme_stage_preview_key'],
        'temp' => true,
    ],
    'analytics' => [
        'schema_cookie' => 362,
        'tables' => ['wp_eventmeta', 'wp_event_archive', 'wp_event_rollup_next362'],
        'indexes' => ['wp_eventmeta_key_next313', 'wp_event_rollup_day_next362'],
        'file' => '/srv/wp/analytics-next365.sqlite',
    ],
    'campaign' => [
        'schema_cookie' => 345,
        'tables' => ['wp_campaignmeta', 'wp_campaign_audit'],
        'indexes' => ['wp_campaigns_slug', 'wp_campaignmeta_key_next329'],
        'file' => '/srv/wp/campaign-next365.sqlite',
    ],
    'media' => [
        'schema_cookie' => 338,
        'tables' => ['wp_media', 'wp_media_metadata_next355'],
        'indexes' => ['wp_media_mime', 'wp_media_meta_key'],
        'file' => '/srv/wp/media-next365.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 357,
        'tables' => ['wp_job_queue', 'wp_job_claims', 'wp_job_history', 'wp_job_deadletter', 'wp_job_retries'],
        'indexes' => ['wp_job_queue_token', 'wp_job_claims_token_next323', 'wp_job_history_finished_next339', 'wp_job_deadletter_reason_next360', 'wp_job_retries_job'],
        'file' => '/srv/wp/queue-next365.sqlite',
    ],
];

$statements365380 = [
    ['name' => 'menu-reader', 'sql' => 'SELECT menu_id FROM main.wp_navigation_menus INDEXED BY wp_navigation_menus_slug WHERE slug = ?', 'active' => true],
    ['name' => 'preview-reader', 'sql' => 'SELECT preview_id FROM temp.wp_theme_stage_preview INDEXED BY wp_theme_stage_preview_key WHERE cache_key = ?', 'active' => true],
    ['name' => 'analytics-rollup-reader', 'sql' => 'SELECT day FROM analytics.wp_event_rollup_next362 INDEXED BY wp_event_rollup_day_next362 WHERE day >= ?'],
    ['name' => 'campaign-audit-reader', 'sql' => 'SELECT audit_id FROM campaign.wp_campaign_audit INDEXED BY wp_campaigns_slug WHERE campaign = ?', 'active' => true],
    ['name' => 'media-metadata-writer', 'sql' => 'UPDATE media.wp_media_metadata_next355 INDEXED BY wp_media_meta_key SET meta_value = ? WHERE meta_key = ?'],
    ['name' => 'queue-retry-reader', 'sql' => 'SELECT job_id FROM queue.wp_job_retries INDEXED BY wp_job_retries_job WHERE job_id = ?'],
    ['name' => 'segments-reader', 'sql' => 'SELECT segment_id FROM segments.wp_segment_map INDEXED BY wp_segment_map_slug WHERE slug = ?'],
    ['name' => 'import-writer', 'sql' => 'UPDATE imports.wp_import_items INDEXED BY wp_import_items_status SET status = ? WHERE status = ?'],
];

$plan365380 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext365380(
    $schemas ?? $schemas365380,
    $statements ?? $statements365380,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next365-380 extends next349-364'] = static function (TestRunner $t) use ($plan365380): void {
    $result = $plan365380([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 365, 'table' => 'wp_navigation_locations', 'indexes' => ['wp_navigation_locations_menu'], 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'temp', 'from' => 'wp_theme_stage_preview_key', 'to' => 'wp_theme_stage_preview_key_next366'],
        ['op' => 'drop_index', 'schema' => 'analytics', 'index' => 'wp_event_rollup_day_next362'],
        ['op' => 'attach', 'schema' => 'segments', 'schema_cookie' => 68, 'tables' => ['wp_segment_map'], 'indexes' => ['wp_segment_map_slug'], 'file' => '/srv/wp/segments-next368.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'segments', 'schema_cookie' => 369, 'table' => 'wp_segment_meta', 'indexes' => ['wp_segment_meta_key'], 'commit' => true],
        ['op' => 'drop_table', 'schema' => 'campaign', 'table' => 'wp_campaign_audit'],
        ['op' => 'rename_table', 'schema' => 'media', 'from' => 'wp_media_metadata_next355', 'to' => 'wp_media_metadata_next371'],
        ['op' => 'wal_commit', 'schema' => 'queue', 'schema_cookie' => 372, 'table' => 'wp_job_retry_notes', 'indexes' => ['wp_job_retry_notes_job'], 'commit' => true],
        ['op' => 'attach', 'schema' => 'imports', 'schema_cookie' => 73, 'tables' => ['wp_import_items'], 'indexes' => ['wp_import_items_status'], 'file' => '/srv/wp/imports-next373.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'imports', 'schema_cookie' => 374, 'table' => 'wp_import_itemmeta', 'indexes' => ['wp_import_itemmeta_key'], 'commit' => false],
        ['op' => 'drop_table', 'schema' => 'main', 'table' => 'wp_term_taxonomy'],
        ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retries_job', 'to' => 'wp_job_retries_job_next376'],
        ['op' => 'detach', 'schema' => 'segments'],
        ['op' => 'wal_commit', 'schema' => 'analytics', 'schema_cookie' => 378, 'table' => 'wp_event_forecast_next378', 'indexes' => ['wp_event_forecast_day_next378'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'imports'],
        ['op' => 'wal_commit', 'schema' => 'temp', 'schema_cookie' => 380, 'table' => 'wp_theme_stage_publish', 'indexes' => ['wp_theme_stage_publish_key'], 'commit' => true],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next365-380', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next365', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next380', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next364', $result['dependencies'][31]);
    $t->same(15, $result['event_count']);
    $t->same(['temp', 'main', 'analytics', 'campaign', 'media', 'queue'], $result['changed_schemas']);
    $t->same(366, $result['schema_cookies_next']['main']);
    $t->same(380, $result['schema_cookies_next']['temp']);
    $t->same(378, $result['schema_cookies_next']['analytics']);
    $t->same(346, $result['schema_cookies_next']['campaign']);
    $t->same(339, $result['schema_cookies_next']['media']);
    $t->same(373, $result['schema_cookies_next']['queue']);
    $t->same(['temp', 'main', 'analytics', 'campaign', 'media', 'queue'], $result['search_order_next']);
    $t->same(['menu-reader', 'preview-reader', 'campaign-audit-reader'], $result['active_current_snapshot_statements']);
    $t->same(['media-metadata-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['preview-reader']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['analytics-rollup-reader']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['campaign-audit-reader']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['media-metadata-writer']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['queue-retry-reader']['index_transitions'][0]['next_found']);
    $t->same('__detached__', $result['statements']['segments-reader']['schema_transitions'][0]['next_schema']);
    $t->same('__detached__', $result['statements']['import-writer']['schema_transitions'][0]['next_schema']);
};

$tests['attach temp wal schema cache current source next365-380 duplicate uncommitted churn stays stable'] = static function (TestRunner $t) use ($plan365380): void {
    $result = $plan365380([
        ['op' => 'attach', 'schema' => 'preview', 'schema_cookie' => 8, 'tables' => ['wp_preview'], 'indexes' => ['wp_preview_key'], 'file' => '/srv/wp/preview-next365.sqlite'],
        ['op' => 'detach', 'schema' => 'preview'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 367, 'table' => 'wp_unpublished_next365', 'indexes' => ['wp_unpublished_next365_key'], 'commit' => false],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 367, 'table' => 'wp_unpublished_next365', 'indexes' => ['wp_unpublished_next365_key'], 'commit' => false],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'analytics', 'campaign', 'media', 'queue'], $result['search_order_next']);
};

return $tests;
