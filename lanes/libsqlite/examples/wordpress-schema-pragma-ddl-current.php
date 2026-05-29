<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteSchemaRecord.php';
require_once __DIR__ . '/../src/SQLitePragmaRowCursor.php';
require_once __DIR__ . '/../src/SQLitePragmaSchemaCatalog.php';
require_once __DIR__ . '/../src/SQLitePragmaSchemaDataVersion.php';
require_once __DIR__ . '/../src/SQLiteAlterTableRenamePlan.php';
require_once __DIR__ . '/../src/SQLiteAlterTableColumnCorpus.php';
require_once __DIR__ . '/../src/SQLiteViewTriggerNameResolution.php';
require_once __DIR__ . '/../src/SQLiteSchemaDdlReparsePlan.php';
require_once __DIR__ . '/../src/SQLiteSchemaPragmaDdlCurrent.php';

use PortLibs\LibSqlite\SQLiteSchemaPragmaDdlCurrent;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$records = [
    new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, "CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT 'yes')", 1),
    new SQLiteSchemaRecord('index', 'wp_options_autoload', 'wp_options', 3, "CREATE INDEX wp_options_autoload ON wp_options(autoload) WHERE autoload = 'yes'", 2),
    new SQLiteSchemaRecord('view', 'wp_autoloaded_options', 'wp_autoloaded_options', 0, "CREATE VIEW wp_autoloaded_options AS SELECT option_id, option_name FROM wp_options WHERE autoload = 'yes'", 3),
];

$plan = SQLiteSchemaPragmaDdlCurrent::apply(
    $records,
    [
        'ALTER TABLE wp_options RENAME TO wp_site_options',
        "CREATE INDEX wp_site_options_name ON wp_site_options(option_name) WHERE autoload = 'yes'",
    ],
    ['main' => ['schema_version' => 29, 'data_version' => 7, 'change_counter' => 7]],
    'main',
    [
        ['id' => 'autoload-options-reader', 'schema_cookie' => 29, 'sql' => 'SELECT option_name FROM wp_options WHERE autoload = ?'],
    ],
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'ok');
    assert($plan['schema_delta'] === 2);
    assert($plan['pragma_after']['schema_version']['value'] === 31);
    assert($plan['pragma_after']['data_version']['value'] === 7);
    assert($plan['local_data_version_changed'] === false);
    assert($plan['header_after']['schema_cookie'] === 31);
    assert($plan['header_after']['file_change_counter'] === 9);
    assert($plan['invalidated_prepared'] === ['autoload-options-reader']);
    assert($plan['pragma_samples']['table_xinfo:wp_site_options']['rows'][1]['name'] === 'option_name');
    assert($plan['pragma_samples']['index_list:wp_site_options']['rows'][1]['name'] === 'wp_site_options_name');
    fwrite(STDOUT, "wordpress-schema-pragma-ddl-current self-test passed\n");
    return;
}

echo json_encode([
    'scenario' => 'wordpress-schema-pragma-ddl-current',
    'wordpressUse' => 'Copied WordPress schema migrations can apply DDL, refresh PRAGMA table/index metadata, bump schema_version/header state, and keep same-connection data_version stable before reusing prepared wp_options readers.',
    'schemaDelta' => $plan['schema_delta'],
    'schemaVersionAfter' => $plan['pragma_after']['schema_version']['value'],
    'dataVersionAfter' => $plan['pragma_after']['data_version']['value'],
    'invalidatedPrepared' => $plan['invalidated_prepared'],
    'indexNames' => array_column($plan['pragma_samples']['index_list:wp_site_options']['rows'], 'name'),
], JSON_PRETTY_PRINT) . PHP_EOL;
