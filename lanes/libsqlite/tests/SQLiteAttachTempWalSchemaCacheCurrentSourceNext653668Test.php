<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas653668 = [
    'main' => [
        'schema_cookie' => 652,
        'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_final_next652'],
        'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_final_key_next652'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 652, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 526,
        'tables' => ['wp_theme_stage_publish_retries_next558'],
        'indexes' => [],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 574,
        'tables' => ['wp_schema_archive_preview_next628', 'wp_schema_archive_receipt_next644'],
        'indexes' => ['wp_schema_archive_receipt_key_next644'],
        'file' => '/srv/wp/archive-next653.sqlite',
    ],
    'handoff' => [
        'schema_cookie' => 587,
        'tables' => ['wp_schema_handoff_meta_next621'],
        'indexes' => [],
        'file' => '/srv/wp/handoff-next653.sqlite',
    ],
    'publish' => [
        'schema_cookie' => 643,
        'tables' => ['wp_schema_publish_next642', 'wp_schema_publish_meta_next643'],
        'indexes' => ['wp_schema_publish_key_next642', 'wp_schema_publish_meta_key_next643'],
        'file' => '/srv/wp/publish-next653.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 595,
        'tables' => ['wp_job_retry_checkpoint_sealed_next623', 'wp_job_retry_checkpoint_preview_next620'],
        'indexes' => ['wp_job_retry_checkpoint_preview_job_next639'],
        'file' => '/srv/wp/queue-next653.sqlite',
    ],
];

$statements653668 = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT final_id FROM main.wp_navigation_rule_locale_publish_final_next652 INDEXED BY wp_navigation_rule_locale_publish_final_key_next652 WHERE final_key = ?', 'active' => true],
    ['name' => 'queue-preview-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_preview_next620 INDEXED BY wp_job_retry_checkpoint_preview_job_next639 SET acked = 1 WHERE job_id = ?'],
    ['name' => 'publish-meta-reader', 'sql' => 'SELECT meta_id FROM publish.wp_schema_publish_meta_next643 INDEXED BY wp_schema_publish_meta_key_next643 WHERE meta_key = ?', 'active' => true],
    ['name' => 'archive-receipt-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_receipt_next644 INDEXED BY wp_schema_archive_receipt_key_next644 WHERE archive_key = ?'],
    ['name' => 'handoff-meta-reader', 'sql' => 'SELECT meta_id FROM handoff.wp_schema_handoff_meta_next621 WHERE meta_key = ?'],
    ['name' => 'audit-reader', 'sql' => 'SELECT audit_id FROM audit.wp_schema_audit_next660 INDEXED BY wp_schema_audit_key_next660 WHERE audit_key = ?'],
    ['name' => 'temp-retry-reader', 'sql' => 'SELECT tries FROM temp.wp_theme_stage_publish_retries_next558 WHERE cache_key = ?'],
];

$plan653668 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext653668(
    $schemas ?? $schemas653668,
    $statements ?? $statements653668,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next653-668 extends next637-652 handoff'] = static function (TestRunner $t) use ($plan653668): void {
    $result = $plan653668([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 653, 'table' => 'wp_navigation_rule_locale_publish_receipt_next653', 'indexes' => ['wp_navigation_rule_locale_publish_receipt_key_next653'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 654, 'table' => 'wp_schema_publish_audit_next654', 'indexes' => ['wp_schema_publish_audit_key_next654'], 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retry_checkpoint_preview_job_next639', 'to' => 'wp_job_retry_checkpoint_preview_job_next655'],
        ['op' => 'drop_table', 'schema' => 'handoff', 'table' => 'wp_schema_handoff_meta_next621'],
        ['op' => 'attach', 'schema' => 'audit', 'schema_cookie' => 660, 'tables' => ['wp_schema_audit_next660'], 'indexes' => ['wp_schema_audit_key_next660'], 'file' => '/srv/wp/audit-next660.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'audit', 'schema_cookie' => 661, 'table' => 'wp_schema_audit_meta_next661', 'indexes' => ['wp_schema_audit_meta_key_next661'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'archive', 'schema_cookie' => 662, 'table' => 'wp_schema_archive_receipt_next662', 'indexes' => ['wp_schema_archive_receipt_key_next662'], 'commit' => false],
        ['op' => 'drop_index', 'schema' => 'archive', 'index' => 'wp_schema_archive_receipt_key_next644'],
        ['op' => 'detach', 'schema' => 'publish'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 668, 'table' => 'wp_navigation_rule_locale_publish_final_next668', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next668'], 'commit' => true],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next653-668', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next653', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next668', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next637', $result['dependencies'][16]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next652', $result['dependencies'][31]);
    $t->same(9, $result['event_count']);
    $t->same(668, $result['schema_cookies_next']['main']);
    $t->same(526, $result['schema_cookies_next']['temp']);
    $t->same(596, $result['schema_cookies_next']['queue']);
    $t->same(588, $result['schema_cookies_next']['handoff']);
    $t->same(575, $result['schema_cookies_next']['archive']);
    $t->same(661, $result['schema_cookies_next']['audit']);
    $t->same(['main-final-reader', 'publish-meta-reader'], $result['active_current_snapshot_statements']);
    $t->same(['queue-preview-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['queue-preview-writer']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['handoff-meta-reader']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['archive-receipt-reader']['index_transitions'][0]['next_found']);
    $t->same('__detached__', $result['statements']['publish-meta-reader']['schema_transitions'][0]['next_schema']);
    $t->same('audit', $result['statements']['audit-reader']['schema_transitions'][0]['next_schema']);
    $t->same(['temp-retry-reader'], $result['stable_statements']);
};

$tests['attach temp wal schema cache current source next653-668 keeps transient audit preview stable'] = static function (TestRunner $t) use ($plan653668): void {
    $result = $plan653668([
        ['op' => 'attach', 'schema' => 'scratch', 'schema_cookie' => 653, 'tables' => ['wp_scratch_next653'], 'indexes' => ['wp_scratch_key_next653'], 'file' => '/srv/wp/scratch-next653.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'scratch', 'schema_cookie' => 654, 'table' => 'wp_scratch_meta_next654', 'indexes' => ['wp_scratch_meta_key_next654'], 'commit' => false],
        ['op' => 'detach', 'schema' => 'scratch'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'archive', 'handoff', 'publish', 'queue'], $result['search_order_next']);
};

return $tests;
