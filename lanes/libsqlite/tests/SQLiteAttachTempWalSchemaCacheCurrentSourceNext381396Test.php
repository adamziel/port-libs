<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas381396 = [
    'main' => [
        'schema_cookie' => 366,
        'tables' => ['wp_options', 'wp_posts', 'wp_postmeta', 'wp_users', 'wp_block_patterns', 'wp_global_styles', 'wp_navigation_menus', 'wp_navigation_locations'],
        'indexes' => ['wp_options_name', 'wp_posts_type_status_next293', 'wp_postmeta_key', 'wp_users_login_next308', 'wp_block_patterns_slug', 'wp_global_styles_slug', 'wp_navigation_menus_slug', 'wp_navigation_locations_menu'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 366, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 380,
        'tables' => ['wp_theme_stage', 'wp_theme_stage_preview', 'wp_theme_stage_publish'],
        'indexes' => ['wp_theme_stage_stylesheet_next350', 'wp_theme_stage_preview_key_next366', 'wp_theme_stage_publish_key'],
        'temp' => true,
    ],
    'analytics' => [
        'schema_cookie' => 378,
        'tables' => ['wp_eventmeta', 'wp_event_archive', 'wp_event_rollup_next362', 'wp_event_forecast_next378'],
        'indexes' => ['wp_eventmeta_key_next313', 'wp_event_forecast_day_next378'],
        'file' => '/srv/wp/analytics-next381.sqlite',
    ],
    'campaign' => [
        'schema_cookie' => 346,
        'tables' => ['wp_campaignmeta'],
        'indexes' => ['wp_campaigns_slug', 'wp_campaignmeta_key_next329'],
        'file' => '/srv/wp/campaign-next381.sqlite',
    ],
    'media' => [
        'schema_cookie' => 339,
        'tables' => ['wp_media', 'wp_media_metadata_next371'],
        'indexes' => ['wp_media_mime', 'wp_media_meta_key'],
        'file' => '/srv/wp/media-next381.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 373,
        'tables' => ['wp_job_queue', 'wp_job_claims', 'wp_job_history', 'wp_job_deadletter', 'wp_job_retries', 'wp_job_retry_notes'],
        'indexes' => ['wp_job_queue_token', 'wp_job_claims_token_next323', 'wp_job_history_finished_next339', 'wp_job_deadletter_reason_next360', 'wp_job_retries_job_next376', 'wp_job_retry_notes_job'],
        'file' => '/srv/wp/queue-next381.sqlite',
    ],
];

$statements381396 = [
    ['name' => 'location-reader', 'sql' => 'SELECT menu_id FROM main.wp_navigation_locations INDEXED BY wp_navigation_locations_menu WHERE location = ?', 'active' => true],
    ['name' => 'publish-reader', 'sql' => 'SELECT publish_id FROM temp.wp_theme_stage_publish INDEXED BY wp_theme_stage_publish_key WHERE cache_key = ?', 'active' => true],
    ['name' => 'forecast-reader', 'sql' => 'SELECT day FROM analytics.wp_event_forecast_next378 INDEXED BY wp_event_forecast_day_next378 WHERE day >= ?'],
    ['name' => 'campaign-meta-reader', 'sql' => 'SELECT meta_id FROM campaign.wp_campaignmeta INDEXED BY wp_campaignmeta_key_next329 WHERE meta_key = ?', 'active' => true],
    ['name' => 'media-metadata-writer', 'sql' => 'UPDATE media.wp_media_metadata_next371 INDEXED BY wp_media_meta_key SET meta_value = ? WHERE meta_key = ?'],
    ['name' => 'queue-note-reader', 'sql' => 'SELECT note_id FROM queue.wp_job_retry_notes INDEXED BY wp_job_retry_notes_job WHERE job_id = ?'],
    ['name' => 'audience-reader', 'sql' => 'SELECT audience_id FROM audience.wp_audience_map INDEXED BY wp_audience_map_slug WHERE slug = ?'],
    ['name' => 'exports-writer', 'sql' => 'UPDATE exports.wp_export_items INDEXED BY wp_export_items_status SET status = ? WHERE status = ?'],
];

$plan381396 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext381396(
    $schemas ?? $schemas381396,
    $statements ?? $statements381396,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next381-396 extends next365-380'] = static function (TestRunner $t) use ($plan381396): void {
    $result = $plan381396([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 381, 'table' => 'wp_navigation_aliases', 'indexes' => ['wp_navigation_aliases_slug'], 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'temp', 'from' => 'wp_theme_stage_publish_key', 'to' => 'wp_theme_stage_publish_key_next382'],
        ['op' => 'drop_index', 'schema' => 'analytics', 'index' => 'wp_event_forecast_day_next378'],
        ['op' => 'attach', 'schema' => 'audience', 'schema_cookie' => 84, 'tables' => ['wp_audience_map'], 'indexes' => ['wp_audience_map_slug'], 'file' => '/srv/wp/audience-next384.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'audience', 'schema_cookie' => 385, 'table' => 'wp_audience_meta', 'indexes' => ['wp_audience_meta_key'], 'commit' => true],
        ['op' => 'drop_table', 'schema' => 'campaign', 'table' => 'wp_campaignmeta'],
        ['op' => 'rename_table', 'schema' => 'media', 'from' => 'wp_media_metadata_next371', 'to' => 'wp_media_metadata_next387'],
        ['op' => 'wal_commit', 'schema' => 'queue', 'schema_cookie' => 388, 'table' => 'wp_job_retry_audit', 'indexes' => ['wp_job_retry_audit_job'], 'commit' => true],
        ['op' => 'attach', 'schema' => 'exports', 'schema_cookie' => 89, 'tables' => ['wp_export_items'], 'indexes' => ['wp_export_items_status'], 'file' => '/srv/wp/exports-next389.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'exports', 'schema_cookie' => 390, 'table' => 'wp_export_itemmeta', 'indexes' => ['wp_export_itemmeta_key'], 'commit' => false],
        ['op' => 'drop_table', 'schema' => 'main', 'table' => 'wp_block_patterns'],
        ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retry_notes_job', 'to' => 'wp_job_retry_notes_job_next392'],
        ['op' => 'detach', 'schema' => 'audience'],
        ['op' => 'wal_commit', 'schema' => 'analytics', 'schema_cookie' => 394, 'table' => 'wp_event_capacity_next394', 'indexes' => ['wp_event_capacity_day_next394'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'exports'],
        ['op' => 'wal_commit', 'schema' => 'temp', 'schema_cookie' => 396, 'table' => 'wp_theme_stage_archive', 'indexes' => ['wp_theme_stage_archive_key'], 'commit' => true],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next381-396', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next381', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next396', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next380', $result['dependencies'][31]);
    $t->same(15, $result['event_count']);
    $t->same(['temp', 'main', 'analytics', 'campaign', 'media', 'queue'], $result['changed_schemas']);
    $t->same(382, $result['schema_cookies_next']['main']);
    $t->same(396, $result['schema_cookies_next']['temp']);
    $t->same(394, $result['schema_cookies_next']['analytics']);
    $t->same(347, $result['schema_cookies_next']['campaign']);
    $t->same(340, $result['schema_cookies_next']['media']);
    $t->same(389, $result['schema_cookies_next']['queue']);
    $t->same(['temp', 'main', 'analytics', 'campaign', 'media', 'queue'], $result['search_order_next']);
    $t->same(['location-reader', 'publish-reader', 'campaign-meta-reader'], $result['active_current_snapshot_statements']);
    $t->same(['media-metadata-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['publish-reader']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['forecast-reader']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['campaign-meta-reader']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['media-metadata-writer']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['queue-note-reader']['index_transitions'][0]['next_found']);
    $t->same('__detached__', $result['statements']['audience-reader']['schema_transitions'][0]['next_schema']);
    $t->same('__detached__', $result['statements']['exports-writer']['schema_transitions'][0]['next_schema']);
};

$tests['attach temp wal schema cache current source next381-396 duplicate uncommitted churn stays stable'] = static function (TestRunner $t) use ($plan381396): void {
    $result = $plan381396([
        ['op' => 'attach', 'schema' => 'preview', 'schema_cookie' => 9, 'tables' => ['wp_preview'], 'indexes' => ['wp_preview_key'], 'file' => '/srv/wp/preview-next381.sqlite'],
        ['op' => 'detach', 'schema' => 'preview'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 383, 'table' => 'wp_unpublished_next381', 'indexes' => ['wp_unpublished_next381_key'], 'commit' => false],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 383, 'table' => 'wp_unpublished_next381', 'indexes' => ['wp_unpublished_next381_key'], 'commit' => false],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'analytics', 'campaign', 'media', 'queue'], $result['search_order_next']);
};

return $tests;
