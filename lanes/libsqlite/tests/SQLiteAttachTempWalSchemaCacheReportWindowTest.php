<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas941956 = [
    'main' => [
        'schema_cookie' => 940,
        'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_final_next940', 'wp_navigation_rule_locale_publish_gate_next944'],
        'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_final_key_next940', 'wp_navigation_rule_locale_publish_gate_key_next944'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 940, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 944,
        'tables' => ['wp_theme_stage_publish_notice_next928', 'wp_theme_stage_publish_token_next944'],
        'indexes' => [],
        'temp' => true,
    ],
    'audit' => [
        'schema_cookie' => 934,
        'tables' => ['wp_schema_audit_replay_next917', 'wp_schema_audit_seal_next933'],
        'indexes' => ['wp_schema_audit_replay_key_next917', 'wp_schema_audit_seal_key_next934'],
        'file' => '/srv/wp/audit-next941.sqlite',
    ],
    'archive' => [
        'schema_cookie' => 936,
        'tables' => ['wp_schema_archive_receipt_next936'],
        'indexes' => ['wp_schema_archive_receipt_key_next936'],
        'file' => '/srv/wp/archive-next936.sqlite',
    ],
    'handoff' => [
        'schema_cookie' => 938,
        'tables' => ['wp_schema_handoff_receipt_next938'],
        'indexes' => ['wp_schema_handoff_receipt_key_next920'],
        'file' => '/srv/wp/handoff-next941.sqlite',
    ],
    'publish' => [
        'schema_cookie' => 939,
        'tables' => ['wp_schema_publish_final_next939'],
        'indexes' => ['wp_schema_publish_final_key_next939'],
        'file' => '/srv/wp/publish-next941.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 919,
        'tables' => ['wp_job_retry_checkpoint_ready_next919'],
        'indexes' => ['wp_job_retry_checkpoint_ready_key_next919'],
        'file' => '/srv/wp/queue-next941.sqlite',
    ],
    'report' => [
        'schema_cookie' => 941,
        'tables' => ['wp_schema_report_receipt_next941'],
        'indexes' => ['wp_schema_report_receipt_key_next941'],
        'file' => '/srv/wp/report-next941.sqlite',
    ],
];

$statements941956 = [
    ['name' => 'main-gate-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_gate_next944 INDEXED BY wp_navigation_rule_locale_publish_gate_key_next944 WHERE nav_key = ?', 'active' => true],
    ['name' => 'audit-seal-reader', 'sql' => 'SELECT seal_id FROM audit.wp_schema_audit_seal_next933 INDEXED BY wp_schema_audit_seal_key_next934 WHERE seal_key = ?'],
    ['name' => 'archive-receipt-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_receipt_next936 INDEXED BY wp_schema_archive_receipt_key_next936 WHERE archive_key = ?'],
    ['name' => 'handoff-writer', 'sql' => 'UPDATE handoff.wp_schema_handoff_receipt_next938 INDEXED BY wp_schema_handoff_receipt_key_next920 SET accepted = 1 WHERE handoff_key = ?', 'active' => true],
    ['name' => 'publish-final-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_final_next939 INDEXED BY wp_schema_publish_final_key_next939 WHERE publish_key = ?'],
    ['name' => 'queue-ready-reader', 'sql' => 'SELECT job_id FROM queue.wp_job_retry_checkpoint_ready_next919 INDEXED BY wp_job_retry_checkpoint_ready_key_next919 WHERE job_id = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt_next952 INDEXED BY wp_schema_review_receipt_key_next952 WHERE review_key = ?'],
    ['name' => 'report-reader', 'sql' => 'SELECT report_id FROM report.wp_schema_report_receipt_next941 INDEXED BY wp_schema_report_receipt_key_next941 WHERE report_key = ?'],
];

$plan941956 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::schemaCacheReportWindow(
    $schemas ?? $schemas941956,
    $statements ?? $statements941956,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next941-956 extends next925-940 handoff'] = static function (TestRunner $t) use ($plan941956): void {
    $result = $plan941956([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 946, 'table' => 'wp_navigation_rule_locale_publish_batch_next946', 'indexes' => ['wp_navigation_rule_locale_publish_batch_key_next946'], 'commit' => true],
        ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 948, 'table' => 'wp_theme_stage_publish_token_next948', 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'archive', 'from' => 'wp_schema_archive_receipt_key_next936', 'to' => 'wp_schema_archive_receipt_key_next950'],
        ['op' => 'attach', 'schema' => 'review', 'schema_cookie' => 952, 'tables' => ['wp_schema_review_receipt_next952'], 'indexes' => ['wp_schema_review_receipt_key_next952'], 'file' => '/srv/wp/review-next952.sqlite'],
        ['op' => 'drop_table', 'schema' => 'queue', 'table' => 'wp_job_retry_checkpoint_ready_next919'],
        ['op' => 'rename_table', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_receipt_next938', 'to' => 'wp_schema_handoff_receipt_next954'],
        ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 955, 'table' => 'wp_schema_publish_done_next955', 'indexes' => ['wp_schema_publish_done_key_next955'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'audit'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 956, 'table' => 'wp_navigation_rule_locale_publish_final_next956', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next956'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 941, 'table' => 'wp_navigation_rule_locale_publish_uncommitted_next941', 'indexes' => ['wp_navigation_rule_locale_publish_uncommitted_key_next941'], 'commit' => false],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next941-956', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next941', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next956', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next925', $result['dependencies'][16]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next940', $result['dependencies'][31]);
    $t->same(9, $result['event_count']);
    $t->same(956, $result['schema_cookies_next']['main']);
    $t->same(948, $result['schema_cookies_next']['temp']);
    $t->same(false, isset($result['schema_cookies_next']['audit']));
    $t->same(937, $result['schema_cookies_next']['archive']);
    $t->same(939, $result['schema_cookies_next']['handoff']);
    $t->same(955, $result['schema_cookies_next']['publish']);
    $t->same(920, $result['schema_cookies_next']['queue']);
    $t->same(952, $result['schema_cookies_next']['review']);
    $t->same(['main-gate-reader', 'handoff-writer'], $result['active_current_snapshot_statements']);
    $t->same(['handoff-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['audit-seal-reader']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['archive-receipt-reader']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['queue-ready-reader']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['handoff-writer']['schema_transitions'][0]['next_found']);
    $t->same('review', $result['statements']['review-reader']['schema_transitions'][0]['next_schema']);
    $t->same(['report-reader'], $result['stable_statements']);
};

$tests['attach temp wal schema cache current source next941-956 ignores detached staging review'] = static function (TestRunner $t) use ($plan941956): void {
    $result = $plan941956([
        ['op' => 'attach', 'schema' => 'staging', 'schema_cookie' => 941, 'tables' => ['wp_staging_review_next941'], 'indexes' => ['wp_staging_review_key_next941'], 'file' => '/srv/wp/staging-next941.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'staging', 'schema_cookie' => 942, 'table' => 'wp_staging_review_meta_next942', 'indexes' => ['wp_staging_review_meta_key_next942'], 'commit' => false],
        ['op' => 'detach', 'schema' => 'staging'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'archive', 'audit', 'handoff', 'publish', 'queue', 'report'], $result['search_order_next']);
};

return $tests;
