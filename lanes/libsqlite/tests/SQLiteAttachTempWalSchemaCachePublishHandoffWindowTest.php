<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas957972 = [
    'main' => [
        'schema_cookie' => 956,
        'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_final_next956', 'wp_navigation_rule_locale_publish_gate_next960'],
        'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_final_key_next956', 'wp_navigation_rule_locale_publish_gate_key_next960'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 956, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 960,
        'tables' => ['wp_theme_stage_publish_notice_next944', 'wp_theme_stage_publish_token_next960'],
        'indexes' => [],
        'temp' => true,
    ],
    'audit' => [
        'schema_cookie' => 950,
        'tables' => ['wp_schema_audit_replay_next917', 'wp_schema_audit_seal_next949'],
        'indexes' => ['wp_schema_audit_replay_key_next917', 'wp_schema_audit_seal_key_next950'],
        'file' => '/srv/wp/audit-next957.sqlite',
    ],
    'archive' => [
        'schema_cookie' => 952,
        'tables' => ['wp_schema_archive_receipt_next952'],
        'indexes' => ['wp_schema_archive_receipt_key_next952'],
        'file' => '/srv/wp/archive-next952.sqlite',
    ],
    'handoff' => [
        'schema_cookie' => 954,
        'tables' => ['wp_schema_handoff_receipt_next954'],
        'indexes' => ['wp_schema_handoff_receipt_key_next920'],
        'file' => '/srv/wp/handoff-next957.sqlite',
    ],
    'publish' => [
        'schema_cookie' => 955,
        'tables' => ['wp_schema_publish_final_next955'],
        'indexes' => ['wp_schema_publish_final_key_next955'],
        'file' => '/srv/wp/publish-next957.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 919,
        'tables' => ['wp_job_retry_checkpoint_ready_next919'],
        'indexes' => ['wp_job_retry_checkpoint_ready_key_next919'],
        'file' => '/srv/wp/queue-next957.sqlite',
    ],
    'report' => [
        'schema_cookie' => 957,
        'tables' => ['wp_schema_report_receipt_next957'],
        'indexes' => ['wp_schema_report_receipt_key_next957'],
        'file' => '/srv/wp/report-next957.sqlite',
    ],
];

$statements957972 = [
    ['name' => 'main-gate-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_gate_next960 INDEXED BY wp_navigation_rule_locale_publish_gate_key_next960 WHERE nav_key = ?', 'active' => true],
    ['name' => 'audit-seal-reader', 'sql' => 'SELECT seal_id FROM audit.wp_schema_audit_seal_next949 INDEXED BY wp_schema_audit_seal_key_next950 WHERE seal_key = ?'],
    ['name' => 'archive-receipt-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_receipt_next952 INDEXED BY wp_schema_archive_receipt_key_next952 WHERE archive_key = ?'],
    ['name' => 'handoff-writer', 'sql' => 'UPDATE handoff.wp_schema_handoff_receipt_next954 INDEXED BY wp_schema_handoff_receipt_key_next920 SET accepted = 1 WHERE handoff_key = ?', 'active' => true],
    ['name' => 'publish-final-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_final_next955 INDEXED BY wp_schema_publish_final_key_next955 WHERE publish_key = ?'],
    ['name' => 'queue-ready-reader', 'sql' => 'SELECT job_id FROM queue.wp_job_retry_checkpoint_ready_next919 INDEXED BY wp_job_retry_checkpoint_ready_key_next919 WHERE job_id = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt_next968 INDEXED BY wp_schema_review_receipt_key_next968 WHERE review_key = ?'],
    ['name' => 'report-reader', 'sql' => 'SELECT report_id FROM report.wp_schema_report_receipt_next957 INDEXED BY wp_schema_report_receipt_key_next957 WHERE report_key = ?'],
];

$plan957972 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::schemaCachePublishHandoffWindow(
    $schemas ?? $schemas957972,
    $statements ?? $statements957972,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next957-972 extends next941-956 handoff'] = static function (TestRunner $t) use ($plan957972): void {
    $result = $plan957972([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 962, 'table' => 'wp_navigation_rule_locale_publish_batch_next962', 'indexes' => ['wp_navigation_rule_locale_publish_batch_key_next962'], 'commit' => true],
        ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 964, 'table' => 'wp_theme_stage_publish_token_next964', 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'archive', 'from' => 'wp_schema_archive_receipt_key_next952', 'to' => 'wp_schema_archive_receipt_key_next966'],
        ['op' => 'attach', 'schema' => 'review', 'schema_cookie' => 968, 'tables' => ['wp_schema_review_receipt_next968'], 'indexes' => ['wp_schema_review_receipt_key_next968'], 'file' => '/srv/wp/review-next968.sqlite'],
        ['op' => 'drop_table', 'schema' => 'queue', 'table' => 'wp_job_retry_checkpoint_ready_next919'],
        ['op' => 'rename_table', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_receipt_next954', 'to' => 'wp_schema_handoff_receipt_next970'],
        ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 971, 'table' => 'wp_schema_publish_done_next971', 'indexes' => ['wp_schema_publish_done_key_next971'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'audit'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 972, 'table' => 'wp_navigation_rule_locale_publish_final_next972', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next972'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 957, 'table' => 'wp_navigation_rule_locale_publish_uncommitted_next957', 'indexes' => ['wp_navigation_rule_locale_publish_uncommitted_key_next957'], 'commit' => false],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next957-972', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next957', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next972', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next941', $result['dependencies'][16]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next956', $result['dependencies'][31]);
    $t->same(9, $result['event_count']);
    $t->same(972, $result['schema_cookies_next']['main']);
    $t->same(964, $result['schema_cookies_next']['temp']);
    $t->same(false, isset($result['schema_cookies_next']['audit']));
    $t->same(953, $result['schema_cookies_next']['archive']);
    $t->same(955, $result['schema_cookies_next']['handoff']);
    $t->same(971, $result['schema_cookies_next']['publish']);
    $t->same(920, $result['schema_cookies_next']['queue']);
    $t->same(968, $result['schema_cookies_next']['review']);
    $t->same(['main-gate-reader', 'handoff-writer'], $result['active_current_snapshot_statements']);
    $t->same(['handoff-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['audit-seal-reader']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['archive-receipt-reader']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['queue-ready-reader']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['handoff-writer']['schema_transitions'][0]['next_found']);
    $t->same('review', $result['statements']['review-reader']['schema_transitions'][0]['next_schema']);
    $t->same(['report-reader'], $result['stable_statements']);
};

$tests['attach temp wal schema cache current source next957-972 ignores detached staging review'] = static function (TestRunner $t) use ($plan957972): void {
    $result = $plan957972([
        ['op' => 'attach', 'schema' => 'staging', 'schema_cookie' => 957, 'tables' => ['wp_staging_review_next957'], 'indexes' => ['wp_staging_review_key_next957'], 'file' => '/srv/wp/staging-next957.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'staging', 'schema_cookie' => 958, 'table' => 'wp_staging_review_meta_next958', 'indexes' => ['wp_staging_review_meta_key_next958'], 'commit' => false],
        ['op' => 'detach', 'schema' => 'staging'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'archive', 'audit', 'handoff', 'publish', 'queue', 'report'], $result['search_order_next']);
};

return $tests;
