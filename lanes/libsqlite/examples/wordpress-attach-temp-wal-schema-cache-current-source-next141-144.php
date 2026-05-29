<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas = [
    'main' => [
        'schema_cookie' => 141,
        'tables' => ['wp_options', 'wp_posts'],
        'indexes' => ['wp_options_autoload_name', 'wp_posts_type_status'],
    ],
    'temp' => [
        'schema_cookie' => 41,
        'tables' => ['wp_import_queue'],
        'indexes' => ['wp_import_queue_key'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 71,
        'tables' => ['wp_options_archive', 'wp_terms'],
        'indexes' => ['wp_archive_option_name', 'wp_terms_slug'],
        'file' => '/srv/wp/archive-current.sqlite',
    ],
];

$statements = [
    ['name' => 'main-options-reader', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?'],
    ['name' => 'main-indexed-reader', 'sql' => 'SELECT option_value FROM main.wp_options INDEXED BY wp_options_autoload_name WHERE option_name = ?'],
    ['name' => 'archive-active-reader', 'sql' => 'SELECT name FROM archive.wp_terms WHERE slug = ?', 'active' => true],
    ['name' => 'archive-writer', 'sql' => 'UPDATE archive.wp_options_archive SET option_value = ? WHERE option_name = ?'],
];

$events = [
    ['op' => 'schema_write', 'schema' => 'temp', 'table' => 'wp_options'],
    ['op' => 'rename_index', 'schema' => 'main', 'from' => 'wp_options_autoload_name', 'to' => 'wp_options_autoload_next142'],
    ['op' => 'wal_commit', 'schema' => 'archive', 'schema_cookie' => 143, 'indexes' => ['wp_terms_name_next143']],
    ['op' => 'detach', 'schema' => 'archive'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext141144($schemas, $statements, $events);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next141-144');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next141');
    assert($plan['event_count'] === 4);
    assert($plan['changed_schemas'] === ['temp', 'main', 'archive']);
    assert($plan['statements']['main-options-reader']['schema_transitions'][0]['next_schema'] === 'temp');
    assert($plan['statements']['main-indexed-reader']['index_transitions'][0]['next_found'] === false);
    assert($plan['statements']['archive-active-reader']['next_step_action'] === 'finish_current_source_then_sqlite_schema_on_reset');
    assert($plan['statements']['archive-writer']['schema_transitions'][0]['next_schema'] === '__detached__');
    assert($plan['write_statements_blocked_before_retry'] === ['archive-writer']);

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next141-144 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
