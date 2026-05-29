<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas845860 = [
    'main' => [
        'schema_cookie' => 844,
        'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_receipt_next860', 'wp_navigation_rule_locale_publish_final_next860'],
        'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_receipt_key_next860', 'wp_navigation_rule_locale_publish_final_key_next860'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 844, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 848,
        'tables' => ['wp_theme_stage_publish_review_next848', 'wp_theme_stage_publish_notice_next848'],
        'indexes' => [],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 845,
        'tables' => ['wp_schema_archive_done_next845'],
        'indexes' => ['wp_schema_archive_done_key_next845'],
        'file' => '/srv/wp/archive-next845.sqlite',
    ],
    'audit' => [
        'schema_cookie' => 853,
        'tables' => ['wp_schema_audit_receipt_next853'],
        'indexes' => ['wp_schema_audit_receipt_key_next853'],
        'file' => '/srv/wp/audit-next845.sqlite',
    ],
    'handoff' => [
        'schema_cookie' => 825,
        'tables' => ['wp_schema_handoff_receipt_next809'],
        'indexes' => ['wp_schema_handoff_receipt_key_next809'],
        'file' => '/srv/wp/handoff-next845.sqlite',
    ],
    'publish' => [
        'schema_cookie' => 858,
        'tables' => ['wp_schema_publish_next845', 'wp_schema_publish_done_next858'],
        'indexes' => ['wp_schema_publish_key_next850', 'wp_schema_publish_done_key_next858'],
        'file' => '/srv/wp/publish-next845.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 850,
        'tables' => ['wp_job_retry_checkpoint_delivered_next846', 'wp_job_retry_checkpoint_archive_next850'],
        'indexes' => ['wp_job_retry_checkpoint_delivered_key_next846', 'wp_job_retry_checkpoint_archive_key_next850'],
        'file' => '/srv/wp/queue-next845.sqlite',
    ],
    'report' => [
        'schema_cookie' => 827,
        'tables' => ['wp_schema_report_next746', 'wp_schema_report_meta_next811'],
        'indexes' => ['wp_schema_report_key_next709', 'wp_schema_report_meta_key_next811'],
        'file' => '/srv/wp/report-next845.sqlite',
    ],
];

$statements845860 = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final_next860 INDEXED BY wp_navigation_rule_locale_publish_final_key_next860 WHERE nav_key = ?', 'active' => true],
    ['name' => 'main-receipt-reader', 'sql' => 'SELECT receipt_id FROM main.wp_navigation_rule_locale_publish_receipt_next860 INDEXED BY wp_navigation_rule_locale_publish_receipt_key_next860 WHERE receipt_key = ?'],
    ['name' => 'publish-done-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_done_next858 INDEXED BY wp_schema_publish_done_key_next858 WHERE publish_key = ?'],
    ['name' => 'queue-archive-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_archive_next850 INDEXED BY wp_job_retry_checkpoint_archive_key_next850 SET delivered = 1 WHERE job_id = ?', 'active' => true],
    ['name' => 'audit-reader', 'sql' => 'SELECT audit_id FROM audit.wp_schema_audit_receipt_next853 INDEXED BY wp_schema_audit_receipt_key_next853 WHERE audit_key = ?'],
    ['name' => 'handoff-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_receipt_next809 INDEXED BY wp_schema_handoff_receipt_key_next809 WHERE handoff_key = ?'],
    ['name' => 'report-meta-reader', 'sql' => 'SELECT meta_id FROM report.wp_schema_report_meta_next811 INDEXED BY wp_schema_report_meta_key_next811 WHERE meta_key = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt_next853 INDEXED BY wp_schema_review_receipt_key_next853 WHERE review_key = ?'],
    ['name' => 'temp-notice-reader', 'sql' => 'SELECT notice_id FROM temp.wp_theme_stage_publish_notice_next848 WHERE cache_key = ?'],
];

$plan845860 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext845860(
    $schemas ?? $schemas845860,
    $statements ?? $statements845860,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next845-860 extends next829-844 handoff'] = static function (TestRunner $t) use ($plan845860): void {
    $result = $plan845860([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 846, 'table' => 'wp_navigation_rule_locale_publish_delta_next846', 'indexes' => ['wp_navigation_rule_locale_publish_delta_key_next846'], 'commit' => true],
        ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 848, 'table' => 'wp_theme_stage_publish_notice_next848', 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retry_checkpoint_archive_key_next850', 'to' => 'wp_job_retry_checkpoint_archive_key_next854'],
        ['op' => 'attach', 'schema' => 'review', 'schema_cookie' => 853, 'tables' => ['wp_schema_review_receipt_next853'], 'indexes' => ['wp_schema_review_receipt_key_next853'], 'file' => '/srv/wp/review-next853.sqlite'],
        ['op' => 'drop_table', 'schema' => 'audit', 'table' => 'wp_schema_audit_receipt_next853'],
        ['op' => 'rename_table', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_receipt_next809', 'to' => 'wp_schema_handoff_receipt_next856'],
        ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 858, 'table' => 'wp_schema_publish_receipt_next858', 'indexes' => ['wp_schema_publish_receipt_key_next858'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'archive'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 860, 'table' => 'wp_navigation_rule_locale_publish_final_next860', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next860'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 845, 'table' => 'wp_navigation_rule_locale_publish_uncommitted_next845', 'indexes' => ['wp_navigation_rule_locale_publish_uncommitted_key_next845'], 'commit' => false],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next845-860', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next845', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next860', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next829', $result['dependencies'][16]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next844', $result['dependencies'][31]);
    $t->same(9, $result['event_count']);
    $t->same(860, $result['schema_cookies_next']['main']);
    $t->same(848, $result['schema_cookies_next']['temp']);
    $t->same(854, $result['schema_cookies_next']['audit']);
    $t->same(826, $result['schema_cookies_next']['handoff']);
    $t->same(858, $result['schema_cookies_next']['publish']);
    $t->same(851, $result['schema_cookies_next']['queue']);
    $t->same(853, $result['schema_cookies_next']['review']);
    $t->same(false, isset($result['schema_cookies_next']['archive']));
    $t->same(['main-final-reader', 'queue-archive-writer'], $result['active_current_snapshot_statements']);
    $t->same(['queue-archive-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['queue-archive-writer']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['audit-reader']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['handoff-reader']['schema_transitions'][0]['next_found']);
    $t->same('review', $result['statements']['review-reader']['schema_transitions'][0]['next_schema']);
    $t->same(['publish-done-reader', 'report-meta-reader', 'temp-notice-reader'], $result['stable_statements']);
};

$tests['attach temp wal schema cache current source next845-860 ignores detached scratch handoff'] = static function (TestRunner $t) use ($plan845860): void {
    $result = $plan845860([
        ['op' => 'attach', 'schema' => 'scratch', 'schema_cookie' => 845, 'tables' => ['wp_scratch_next845'], 'indexes' => ['wp_scratch_key_next845'], 'file' => '/srv/wp/scratch-next845.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'scratch', 'schema_cookie' => 846, 'table' => 'wp_scratch_meta_next846', 'indexes' => ['wp_scratch_meta_key_next846'], 'commit' => false],
        ['op' => 'detach', 'schema' => 'scratch'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'archive', 'audit', 'handoff', 'publish', 'queue', 'report'], $result['search_order_next']);
};

return $tests;
