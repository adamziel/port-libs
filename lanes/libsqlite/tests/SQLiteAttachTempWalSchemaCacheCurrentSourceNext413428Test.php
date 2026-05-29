<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas413428 = [
    'main' => [
        'schema_cookie' => 398,
        'tables' => ['wp_options', 'wp_posts', 'wp_postmeta', 'wp_users', 'wp_global_styles', 'wp_navigation_menus', 'wp_navigation_aliases', 'wp_navigation_redirects'],
        'indexes' => ['wp_options_name', 'wp_posts_type_status_next293', 'wp_postmeta_key', 'wp_users_login_next308', 'wp_global_styles_slug', 'wp_navigation_menus_slug', 'wp_navigation_aliases_slug', 'wp_navigation_redirects_slug'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 398, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 412,
        'tables' => ['wp_theme_stage', 'wp_theme_stage_preview', 'wp_theme_stage_publish', 'wp_theme_stage_archive', 'wp_theme_stage_diff'],
        'indexes' => ['wp_theme_stage_stylesheet_next350', 'wp_theme_stage_preview_key_next366', 'wp_theme_stage_publish_key_next382', 'wp_theme_stage_archive_key_next398', 'wp_theme_stage_diff_key'],
        'temp' => true,
    ],
    'analytics' => [
        'schema_cookie' => 410,
        'tables' => ['wp_eventmeta', 'wp_event_archive', 'wp_event_rollup_next362', 'wp_event_staff_next410'],
        'indexes' => ['wp_eventmeta_key_next313', 'wp_event_staff_day_next410'],
        'file' => '/srv/wp/analytics-next413.sqlite',
    ],
    'media' => [
        'schema_cookie' => 341,
        'tables' => ['wp_media', 'wp_media_metadata_next387'],
        'indexes' => ['wp_media_mime'],
        'file' => '/srv/wp/media-next413.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 391,
        'tables' => ['wp_job_queue', 'wp_job_claims', 'wp_job_history', 'wp_job_deadletter', 'wp_job_retries', 'wp_job_retry_notes', 'wp_job_retry_audit_next403'],
        'indexes' => ['wp_job_queue_token', 'wp_job_claims_token_next323', 'wp_job_history_finished_next339', 'wp_job_deadletter_reason_next360', 'wp_job_retries_job_next376', 'wp_job_retry_notes_job_next392', 'wp_job_retry_audit_job_next408'],
        'file' => '/srv/wp/queue-next413.sqlite',
    ],
    'campaign' => [
        'schema_cookie' => 404,
        'tables' => ['wp_campaign_archive_next404'],
        'indexes' => ['wp_campaigns_slug', 'wp_campaign_archive_slug_next404'],
        'file' => '/srv/wp/campaign-next413.sqlite',
    ],
];

$statements413428 = [
    ['name' => 'redirect-reader', 'sql' => 'SELECT redirect_id FROM main.wp_navigation_redirects INDEXED BY wp_navigation_redirects_slug WHERE slug = ?', 'active' => true],
    ['name' => 'diff-reader', 'sql' => 'SELECT stage_id FROM temp.wp_theme_stage_diff INDEXED BY wp_theme_stage_diff_key WHERE cache_key = ?', 'active' => true],
    ['name' => 'staff-reader', 'sql' => 'SELECT staff_id FROM analytics.wp_event_staff_next410 INDEXED BY wp_event_staff_day_next410 WHERE day = ?'],
    ['name' => 'media-mime-writer', 'sql' => 'UPDATE media.wp_media INDEXED BY wp_media_mime SET mime_type = ? WHERE mime_type = ?'],
    ['name' => 'queue-audit-reader', 'sql' => 'SELECT audit_id FROM queue.wp_job_retry_audit_next403 INDEXED BY wp_job_retry_audit_job_next408 WHERE job_id = ?', 'active' => true],
    ['name' => 'campaign-archive-reader', 'sql' => 'SELECT campaign_id FROM campaign.wp_campaign_archive_next404 INDEXED BY wp_campaign_archive_slug_next404 WHERE slug = ?'],
    ['name' => 'experiment-reader', 'sql' => 'SELECT experiment_id FROM experiments.wp_experiments INDEXED BY wp_experiments_slug WHERE slug = ?'],
    ['name' => 'audit-writer', 'sql' => 'UPDATE audit.wp_audit_trail INDEXED BY wp_audit_trail_type SET event_type = ? WHERE event_type = ?'],
];

$plan413428 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan(
    $schemas ?? $schemas413428,
    $statements ?? $statements413428,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next413-428 extends next397-412'] = static function (TestRunner $t) use ($plan413428): void {
    $result = $plan413428([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 413, 'table' => 'wp_navigation_rules_next413', 'indexes' => ['wp_navigation_rules_slug_next413'], 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'temp', 'from' => 'wp_theme_stage_diff_key', 'to' => 'wp_theme_stage_diff_key_next414'],
        ['op' => 'drop_table', 'schema' => 'analytics', 'table' => 'wp_event_staff_next410'],
        ['op' => 'attach', 'schema' => 'experiments', 'schema_cookie' => 97, 'tables' => ['wp_experiments'], 'indexes' => ['wp_experiments_slug'], 'file' => '/srv/wp/experiments-next416.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'experiments', 'schema_cookie' => 417, 'table' => 'wp_experimentmeta', 'indexes' => ['wp_experimentmeta_key'], 'commit' => true],
        ['op' => 'drop_index', 'schema' => 'media', 'index' => 'wp_media_mime'],
        ['op' => 'rename_table', 'schema' => 'queue', 'from' => 'wp_job_retry_audit_next403', 'to' => 'wp_job_retry_audit_next419'],
        ['op' => 'wal_commit', 'schema' => 'campaign', 'schema_cookie' => 420, 'table' => 'wp_campaign_rollup_next420', 'indexes' => ['wp_campaign_rollup_slug_next420'], 'commit' => true],
        ['op' => 'attach', 'schema' => 'audit', 'schema_cookie' => 99, 'tables' => ['wp_audit_trail'], 'indexes' => ['wp_audit_trail_type'], 'file' => '/srv/wp/audit-next421.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'audit', 'schema_cookie' => 422, 'table' => 'wp_audit_detail', 'indexes' => ['wp_audit_detail_key'], 'commit' => false],
        ['op' => 'drop_table', 'schema' => 'main', 'table' => 'wp_navigation_aliases'],
        ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retry_audit_job_next408', 'to' => 'wp_job_retry_audit_job_next424'],
        ['op' => 'detach', 'schema' => 'experiments'],
        ['op' => 'wal_commit', 'schema' => 'analytics', 'schema_cookie' => 426, 'table' => 'wp_event_capacity_next426', 'indexes' => ['wp_event_capacity_day_next426'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'audit'],
        ['op' => 'wal_commit', 'schema' => 'temp', 'schema_cookie' => 428, 'table' => 'wp_theme_stage_publish_queue', 'indexes' => ['wp_theme_stage_publish_queue_key'], 'commit' => true],
    ]);

    $t->same('attach-wal-temp-schema-cache-consolidated', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][31]);
    $t->same(15, $result['event_count']);
    $t->same(['temp', 'main', 'analytics', 'campaign', 'media', 'queue'], $result['changed_schemas']);
    $t->same(414, $result['schema_cookies_next']['main']);
    $t->same(428, $result['schema_cookies_next']['temp']);
    $t->same(426, $result['schema_cookies_next']['analytics']);
    $t->same(342, $result['schema_cookies_next']['media']);
    $t->same(393, $result['schema_cookies_next']['queue']);
    $t->same(420, $result['schema_cookies_next']['campaign']);
    $t->same(['temp', 'main', 'analytics', 'campaign', 'media', 'queue'], $result['search_order_next']);
    $t->same(['redirect-reader', 'diff-reader', 'queue-audit-reader'], $result['active_current_snapshot_statements']);
    $t->same(['media-mime-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['diff-reader']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['staff-reader']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['media-mime-writer']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['queue-audit-reader']['schema_transitions'][0]['next_found']);
    $t->same('__detached__', $result['statements']['experiment-reader']['schema_transitions'][0]['next_schema']);
    $t->same('__detached__', $result['statements']['audit-writer']['schema_transitions'][0]['next_schema']);
};

$tests['attach temp wal schema cache current source next413-428 ignores rolled back scratch schemas'] = static function (TestRunner $t) use ($plan413428): void {
    $result = $plan413428([
        ['op' => 'attach', 'schema' => 'scratch', 'schema_cookie' => 13, 'tables' => ['wp_scratch'], 'indexes' => ['wp_scratch_key'], 'file' => '/srv/wp/scratch-next413.sqlite'],
        ['op' => 'detach', 'schema' => 'scratch'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 415, 'table' => 'wp_pending_next413', 'indexes' => ['wp_pending_next413_key'], 'commit' => false],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 415, 'table' => 'wp_pending_next413', 'indexes' => ['wp_pending_next413_key'], 'commit' => false],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'analytics', 'campaign', 'media', 'queue'], $result['search_order_next']);
};

return $tests;
