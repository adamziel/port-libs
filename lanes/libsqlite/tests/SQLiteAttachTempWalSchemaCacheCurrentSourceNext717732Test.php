<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas717732 = [
    'main' => [
        'schema_cookie' => 716,
        'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_final_next700', 'wp_navigation_rule_locale_publish_receipt_next716'],
        'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_final_key_next700', 'wp_navigation_rule_locale_publish_receipt_key_next716'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 716, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 704,
        'tables' => ['wp_theme_stage_publish_preview_next704', 'wp_theme_stage_publish_review_next720'],
        'indexes' => [],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 691,
        'tables' => ['wp_schema_archive_done_next718'],
        'indexes' => ['wp_schema_archive_done_key_next718'],
        'file' => '/srv/wp/archive-next717.sqlite',
    ],
    'metrics' => [
        'schema_cookie' => 713,
        'tables' => ['wp_schema_metrics_next713'],
        'indexes' => ['wp_schema_metrics_key_next713'],
        'file' => '/srv/wp/metrics-next717.sqlite',
    ],
    'publish' => [
        'schema_cookie' => 706,
        'tables' => ['wp_schema_publish_next679', 'wp_schema_publish_done_next706'],
        'indexes' => ['wp_schema_publish_key_next686', 'wp_schema_publish_done_key_next706'],
        'file' => '/srv/wp/publish-next717.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 689,
        'tables' => ['wp_job_retry_checkpoint_delivered_next688', 'wp_job_retry_checkpoint_preview_next620'],
        'indexes' => ['wp_job_retry_checkpoint_delivered_key_next702'],
        'file' => '/srv/wp/queue-next717.sqlite',
    ],
    'report' => [
        'schema_cookie' => 695,
        'tables' => ['wp_schema_report_next711', 'wp_schema_report_meta_next694'],
        'indexes' => ['wp_schema_report_key_next693', 'wp_schema_report_meta_key_next694'],
        'file' => '/srv/wp/report-next717.sqlite',
    ],
    'review' => [
        'schema_cookie' => 709,
        'tables' => ['wp_schema_review_receipt_next709'],
        'indexes' => ['wp_schema_review_receipt_key_next709'],
        'file' => '/srv/wp/review-next717.sqlite',
    ],
];

$statements717732 = [
    ['name' => 'main-receipt-reader', 'sql' => 'SELECT receipt_id FROM main.wp_navigation_rule_locale_publish_receipt_next716 INDEXED BY wp_navigation_rule_locale_publish_receipt_key_next716 WHERE receipt_key = ?', 'active' => true],
    ['name' => 'publish-done-writer', 'sql' => 'UPDATE publish.wp_schema_publish_done_next706 INDEXED BY wp_schema_publish_done_key_next706 SET delivered = 1 WHERE publish_key = ?'],
    ['name' => 'queue-delivery-reader', 'sql' => 'SELECT job_id FROM queue.wp_job_retry_checkpoint_delivered_next688 INDEXED BY wp_job_retry_checkpoint_delivered_key_next702 WHERE job_id = ?', 'active' => true],
    ['name' => 'archive-done-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_done_next718 INDEXED BY wp_schema_archive_done_key_next718 WHERE archive_key = ?'],
    ['name' => 'report-reader', 'sql' => 'SELECT report_id FROM report.wp_schema_report_next711 INDEXED BY wp_schema_report_key_next693 WHERE report_key = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt_next709 INDEXED BY wp_schema_review_receipt_key_next709 WHERE review_key = ?'],
    ['name' => 'audit-reader', 'sql' => 'SELECT audit_id FROM audit.wp_schema_audit_receipt_next725 INDEXED BY wp_schema_audit_receipt_key_next725 WHERE audit_key = ?'],
    ['name' => 'handoff-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_receipt_next729 INDEXED BY wp_schema_handoff_receipt_key_next729 WHERE handoff_key = ?'],
    ['name' => 'temp-review-reader', 'sql' => 'SELECT review_id FROM temp.wp_theme_stage_publish_review_next720 WHERE cache_key = ?'],
];

$plan717732 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext717732(
    $schemas ?? $schemas717732,
    $statements ?? $statements717732,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next717-732 extends next701-716 handoff'] = static function (TestRunner $t) use ($plan717732): void {
    $result = $plan717732([
        ['op' => 'schema_write', 'schema' => 'archive', 'schema_cookie' => 718, 'table' => 'wp_schema_archive_done_next718', 'indexes' => ['wp_schema_archive_done_key_next718'], 'commit' => true],
        ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 720, 'table' => 'wp_theme_stage_publish_review_next720', 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'publish', 'from' => 'wp_schema_publish_done_key_next706', 'to' => 'wp_schema_publish_done_key_next722'],
        ['op' => 'wal_commit', 'schema' => 'queue', 'schema_cookie' => 724, 'table' => 'wp_job_retry_checkpoint_archive_next724', 'indexes' => ['wp_job_retry_checkpoint_archive_key_next724'], 'commit' => true],
        ['op' => 'attach', 'schema' => 'audit', 'schema_cookie' => 725, 'tables' => ['wp_schema_audit_receipt_next725'], 'indexes' => ['wp_schema_audit_receipt_key_next725'], 'file' => '/srv/wp/audit-next725.sqlite'],
        ['op' => 'drop_table', 'schema' => 'review', 'table' => 'wp_schema_review_receipt_next709'],
        ['op' => 'attach', 'schema' => 'handoff', 'schema_cookie' => 729, 'tables' => ['wp_schema_handoff_receipt_next729'], 'indexes' => ['wp_schema_handoff_receipt_key_next729'], 'file' => '/srv/wp/handoff-next729.sqlite'],
        ['op' => 'rename_table', 'schema' => 'report', 'from' => 'wp_schema_report_next711', 'to' => 'wp_schema_report_next730'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 732, 'table' => 'wp_navigation_rule_locale_publish_final_next732', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next732'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 733, 'table' => 'wp_navigation_rule_locale_publish_uncommitted_next733', 'indexes' => ['wp_navigation_rule_locale_publish_uncommitted_key_next733'], 'commit' => false],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next717-732', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next717', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next732', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next701', $result['dependencies'][16]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next716', $result['dependencies'][31]);
    $t->same(9, $result['event_count']);
    $t->same(732, $result['schema_cookies_next']['main']);
    $t->same(720, $result['schema_cookies_next']['temp']);
    $t->same(718, $result['schema_cookies_next']['archive']);
    $t->same(725, $result['schema_cookies_next']['audit']);
    $t->same(729, $result['schema_cookies_next']['handoff']);
    $t->same(707, $result['schema_cookies_next']['publish']);
    $t->same(724, $result['schema_cookies_next']['queue']);
    $t->same(696, $result['schema_cookies_next']['report']);
    $t->same(710, $result['schema_cookies_next']['review']);
    $t->same(['main-receipt-reader', 'queue-delivery-reader'], $result['active_current_snapshot_statements']);
    $t->same(['publish-done-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['publish-done-writer']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['review-reader']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['report-reader']['schema_transitions'][0]['next_found']);
    $t->same('audit', $result['statements']['audit-reader']['schema_transitions'][0]['next_schema']);
    $t->same('handoff', $result['statements']['handoff-reader']['schema_transitions'][0]['next_schema']);
    $t->same([], $result['stable_statements']);
};

$tests['attach temp wal schema cache current source next717-732 ignores detached scratch receipt'] = static function (TestRunner $t) use ($plan717732): void {
    $result = $plan717732([
        ['op' => 'attach', 'schema' => 'scratch', 'schema_cookie' => 717, 'tables' => ['wp_scratch_next717'], 'indexes' => ['wp_scratch_key_next717'], 'file' => '/srv/wp/scratch-next717.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'scratch', 'schema_cookie' => 718, 'table' => 'wp_scratch_meta_next718', 'indexes' => ['wp_scratch_meta_key_next718'], 'commit' => false],
        ['op' => 'detach', 'schema' => 'scratch'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'archive', 'metrics', 'publish', 'queue', 'report', 'review'], $result['search_order_next']);
};

return $tests;
