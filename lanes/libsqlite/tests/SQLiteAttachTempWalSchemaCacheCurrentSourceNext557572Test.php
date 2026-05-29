<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas557572 = [
    'main' => [
        'schema_cookie' => 556,
        'tables' => ['wp_options', 'wp_navigation_rule_next525', 'wp_navigation_rule_locale_next541', 'wp_navigation_rule_locale_meta_next556'],
        'indexes' => ['wp_options_name', 'wp_navigation_rule_slug_next525', 'wp_navigation_rule_locale_slug_next541', 'wp_navigation_rule_locale_meta_key_next556'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 556, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 523,
        'tables' => ['wp_theme_stage_publish_retries_next529'],
        'indexes' => ['wp_theme_stage_publish_retries_key_next542'],
        'temp' => true,
    ],
    'queue' => [
        'schema_cookie' => 544,
        'tables' => ['wp_job_retry_audit_next514', 'wp_job_retry_window_next528', 'wp_job_retry_checkpoint_next544'],
        'indexes' => ['wp_job_retry_audit_job_next520', 'wp_job_retry_window_job_next528', 'wp_job_retry_checkpoint_job_next544'],
        'file' => '/srv/wp/queue-next557.sqlite',
    ],
    'campaign' => [
        'schema_cookie' => 541,
        'tables' => ['wp_campaign_restore_next524', 'wp_campaign_restore_meta_next545'],
        'indexes' => ['wp_campaign_restore_slug_next526', 'wp_campaign_restore_meta_key_next540'],
        'file' => '/srv/wp/campaign-next557.sqlite',
    ],
    'search' => [
        'schema_cookie' => 539,
        'tables' => ['wp_search_queue_next537', 'wp_search_queue_meta_next538'],
        'indexes' => ['wp_search_queue_meta_key_next538'],
        'file' => '/srv/wp/search-next557.sqlite',
    ],
    'audit' => [
        'schema_cookie' => 554,
        'tables' => ['wp_schema_audit_next553', 'wp_schema_audit_meta_next554'],
        'indexes' => ['wp_schema_audit_key_next553', 'wp_schema_audit_meta_key_next554'],
        'file' => '/srv/wp/audit-next557.sqlite',
    ],
];

$statements557572 = [
    ['name' => 'nav-locale-reader', 'sql' => 'SELECT locale_id FROM main.wp_navigation_rule_locale_next541 INDEXED BY wp_navigation_rule_locale_slug_next541 WHERE slug = ?', 'active' => true],
    ['name' => 'temp-retry-reader', 'sql' => 'SELECT tries FROM temp.wp_theme_stage_publish_retries_next529 INDEXED BY wp_theme_stage_publish_retries_key_next542 WHERE cache_key = ?', 'active' => true],
    ['name' => 'queue-checkpoint-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_next544 INDEXED BY wp_job_retry_checkpoint_job_next544 SET attempts = attempts + 1 WHERE job_id = ?'],
    ['name' => 'campaign-meta-reader', 'sql' => 'SELECT campaign_id FROM campaign.wp_campaign_restore_meta_next545 INDEXED BY wp_campaign_restore_meta_key_next540 WHERE meta_key = ?'],
    ['name' => 'search-meta-reader', 'sql' => 'SELECT queue_id FROM search.wp_search_queue_meta_next538 INDEXED BY wp_search_queue_meta_key_next538 WHERE meta_key = ?'],
    ['name' => 'audit-meta-reader', 'sql' => 'SELECT audit_id FROM audit.wp_schema_audit_meta_next554 INDEXED BY wp_schema_audit_meta_key_next554 WHERE meta_key = ?'],
    ['name' => 'archive-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_next568 INDEXED BY wp_schema_archive_key_next568 WHERE archive_key = ?'],
];

$plan557572 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext557572(
    $schemas ?? $schemas557572,
    $statements ?? $statements557572,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next557-572 extends next541-556 handoff'] = static function (TestRunner $t) use ($plan557572): void {
    $result = $plan557572([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 557, 'table' => 'wp_navigation_rule_locale_term_next557', 'indexes' => ['wp_navigation_rule_locale_term_key_next557'], 'commit' => true],
        ['op' => 'rename_table', 'schema' => 'temp', 'from' => 'wp_theme_stage_publish_retries_next529', 'to' => 'wp_theme_stage_publish_retries_next558'],
        ['op' => 'drop_index', 'schema' => 'campaign', 'index' => 'wp_campaign_restore_meta_key_next540'],
        ['op' => 'wal_commit', 'schema' => 'queue', 'schema_cookie' => 560, 'table' => 'wp_job_retry_checkpoint_lock_next560', 'indexes' => ['wp_job_retry_checkpoint_lock_job_next560'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'search'],
        ['op' => 'attach', 'schema' => 'archive', 'schema_cookie' => 568, 'tables' => ['wp_schema_archive_next568'], 'indexes' => ['wp_schema_archive_key_next568'], 'file' => '/srv/wp/archive-next568.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'archive', 'schema_cookie' => 569, 'table' => 'wp_schema_archive_meta_next569', 'indexes' => ['wp_schema_archive_meta_key_next569'], 'commit' => true],
        ['op' => 'drop_table', 'schema' => 'audit', 'table' => 'wp_schema_audit_meta_next554'],
        ['op' => 'wal_commit', 'schema' => 'queue', 'schema_cookie' => 571, 'table' => 'wp_job_retry_checkpoint_receipt_next571', 'indexes' => ['wp_job_retry_checkpoint_receipt_job_next571'], 'commit' => false],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 572, 'table' => 'wp_navigation_rule_locale_receipt_next572', 'indexes' => ['wp_navigation_rule_locale_receipt_key_next572'], 'commit' => true],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next557-572', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next557', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next572', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next541', $result['dependencies'][16]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next556', $result['dependencies'][31]);
    $t->same(9, $result['event_count']);
    $t->same(['temp', 'main', 'archive', 'audit', 'campaign', 'queue', 'search'], $result['changed_schemas']);
    $t->same(572, $result['schema_cookies_next']['main']);
    $t->same(524, $result['schema_cookies_next']['temp']);
    $t->same(560, $result['schema_cookies_next']['queue']);
    $t->same(542, $result['schema_cookies_next']['campaign']);
    $t->same(555, $result['schema_cookies_next']['audit']);
    $t->same(569, $result['schema_cookies_next']['archive']);
    $t->same(['temp', 'main', 'archive', 'audit', 'campaign', 'queue'], $result['search_order_next']);
    $t->same(['nav-locale-reader', 'temp-retry-reader'], $result['active_current_snapshot_statements']);
    $t->same(['queue-checkpoint-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['temp-retry-reader']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['campaign-meta-reader']['index_transitions'][0]['next_found']);
    $t->same('__detached__', $result['statements']['search-meta-reader']['schema_transitions'][0]['next_schema']);
    $t->same(false, $result['statements']['audit-meta-reader']['schema_transitions'][0]['next_found']);
    $t->same('archive', $result['statements']['archive-reader']['schema_transitions'][0]['next_schema']);
};

$tests['attach temp wal schema cache current source next557-572 preserves stable transient handoff'] = static function (TestRunner $t) use ($plan557572): void {
    $result = $plan557572([
        ['op' => 'attach', 'schema' => 'transient', 'schema_cookie' => 557, 'tables' => ['wp_transient_next557'], 'indexes' => ['wp_transient_key_next557'], 'file' => '/srv/wp/transient-next557.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'transient', 'schema_cookie' => 558, 'table' => 'wp_transient_pending_next558', 'indexes' => ['wp_transient_pending_key_next558'], 'commit' => false],
        ['op' => 'detach', 'schema' => 'transient'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'audit', 'campaign', 'queue', 'search'], $result['search_order_next']);
};

return $tests;
