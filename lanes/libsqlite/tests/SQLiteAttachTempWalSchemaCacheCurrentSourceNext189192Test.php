<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas189192 = [
    'main' => [
        'schema_cookie' => 189,
        'tables' => ['wp_options', 'wp_posts', 'wp_termmeta'],
        'indexes' => ['wp_options_name', 'wp_posts_date', 'wp_termmeta_key'],
    ],
    'temp' => [
        'schema_cookie' => 92,
        'tables' => ['wp_options', 'wp_import_stage'],
        'indexes' => ['wp_temp_options_name', 'wp_import_stage_token'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 188,
        'tables' => ['wp_comments', 'wp_commentmeta'],
        'indexes' => ['wp_comments_post_id', 'wp_commentmeta_key'],
        'file' => '/srv/wp/archive-next189.sqlite',
    ],
];

$statements189192 = [
    ['name' => 'stage-reader', 'sql' => 'SELECT rowid FROM temp.wp_import_stage INDEXED BY wp_import_stage_token WHERE token = ?', 'active' => true],
    ['name' => 'archive-meta-reader', 'sql' => 'SELECT meta_value FROM archive.wp_commentmeta INDEXED BY wp_commentmeta_key WHERE meta_key = ?'],
    ['name' => 'termmeta-reader', 'sql' => 'SELECT meta_value FROM main.wp_termmeta INDEXED BY wp_termmeta_key WHERE term_id = ?'],
    ['name' => 'unqualified-options-reader', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?'],
    ['name' => 'archive-comments-writer', 'sql' => 'UPDATE archive.wp_comments SET comment_approved = ? WHERE comment_ID = ?'],
];

$plan189192 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext189192(
    $schemas ?? $schemas189192,
    $statements ?? $statements189192,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next189 temp rename holds active snapshot'] = static function (TestRunner $t) use ($plan189192): void {
    $result = $plan189192([
        ['op' => 'rename_table', 'schema' => 'temp', 'from' => 'wp_import_stage', 'to' => 'wp_import_stage_next189'],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next189-192', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next189', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next185', $result['dependencies'][4]);
    $t->same(['temp'], $result['changed_schemas']);
    $t->same(false, $result['statements']['stage-reader']['schema_transitions'][0]['next_found']);
    $t->same('finish_current_source_then_sqlite_schema_on_reset', $result['statements']['stage-reader']['next_step_action']);
    $t->same(['stage-reader'], $result['active_current_snapshot_statements']);
};

$tests['attach temp wal schema cache current source next190 archive drop index keeps table retryable'] = static function (TestRunner $t) use ($plan189192): void {
    $result = $plan189192([
        ['op' => 'drop_index', 'schema' => 'archive', 'index' => 'wp_commentmeta_key'],
    ]);

    $t->same(['archive'], $result['changed_schemas']);
    $t->same(true, $result['statements']['archive-meta-reader']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['archive-meta-reader']['index_transitions'][0]['next_found']);
    $t->same(['archive-meta-reader'], $result['retryable_read_statements']);
    $t->same(['archive-comments-writer'], $result['write_statements_blocked_before_retry']);
};

$tests['attach temp wal schema cache current source next191 main wal cookie expires qualified reader'] = static function (TestRunner $t) use ($plan189192): void {
    $result = $plan189192([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 191, 'table' => 'wp_termmeta'],
    ]);

    $t->same(['main'], $result['changed_schemas']);
    $t->same(191, $result['schema_cookies_next']['main']);
    $t->same(['termmeta-reader'], $result['expired_statements']);
    $t->same(['termmeta-reader'], $result['retryable_read_statements']);
    $t->same(['stage-reader', 'archive-meta-reader', 'unqualified-options-reader', 'archive-comments-writer'], $result['stable_statements']);
};

$tests['attach temp wal schema cache current source next192 detach archive blocks writer before retry'] = static function (TestRunner $t) use ($plan189192): void {
    $result = $plan189192([
        ['op' => 'detach', 'schema' => 'archive'],
    ]);

    $t->same(['archive'], $result['changed_schemas']);
    $t->same('__detached__', $result['statements']['archive-comments-writer']['schema_transitions'][0]['next_schema']);
    $t->same(['archive-meta-reader'], $result['retryable_read_statements']);
    $t->same(['archive-comments-writer'], $result['write_statements_blocked_before_retry']);
};

$tests['attach temp wal schema cache current source next189 192 duplicate committed wal events consolidate'] = static function (TestRunner $t) use ($plan189192): void {
    $result = $plan189192([
        ['op' => 'wal_commit', 'schema' => 'temp', 'schema_cookie' => 192, 'table' => 'wp_import_stage'],
        ['op' => 'wal_commit', 'schema' => 'temp', 'schema_cookie' => 192, 'table' => 'wp_import_stage'],
    ]);

    $t->same(1, $result['event_count']);
    $t->same(['temp'], $result['changed_schemas']);
    $t->same(192, $result['schema_cookies_next']['temp']);
};

return $tests;
