<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$records = [
    new SQLiteSchemaRecord(
        'table',
        'wp_options',
        'wp_options',
        2,
        'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT "yes")',
        1,
    ),
    new SQLiteSchemaRecord(
        'index',
        'wp_options_name_lc_legacy',
        'wp_options',
        3,
        'CREATE INDEX wp_options_name_lc_legacy ON wp_options(option_name_lc)',
        2,
    ),
    new SQLiteSchemaRecord(
        'index',
        'wp_options_name_lc_expr_legacy',
        'wp_options',
        4,
        'CREATE INDEX wp_options_name_lc_expr_legacy ON wp_options(substr(option_name_lc, 1, 8), option_name)',
        3,
    ),
    new SQLiteSchemaRecord(
        'index',
        'wp_options_name_lc_partial_legacy',
        'wp_options',
        5,
        'CREATE INDEX wp_options_name_lc_partial_legacy ON wp_options(option_name) WHERE option_name_lc >= "a"',
        4,
    ),
];

$plan = SQLiteSchemaDdlReparsePlan::apply(
    $records,
    ['ALTER TABLE wp_options ADD COLUMN option_name_lc TEXT AS (lower(option_name)) VIRTUAL'],
    134,
    'main',
    [
        ['id' => 'wp-options-lc-legacy-index', 'schema_cookie' => 134, 'sql' => 'SELECT option_name FROM wp_options INDEXED BY wp_options_name_lc_legacy WHERE option_name_lc = ?'],
        ['id' => 'wp-options-lc-current', 'schema_cookie' => 135, 'sql' => 'SELECT option_name_lc FROM wp_options'],
    ],
    [
        'wp_options' => [
            ['option_id' => 1, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'yes'],
            ['option_id' => 2, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
        ],
    ],
);

$operation = $plan['operations'][0];
$summary = [
    'scenario' => 'application-schema-generated-column-index-reparse-current-source-next134',
    'applicationUse' => 'After a copied Application schema admits a generated lower-case option-name column, legacy index records are classified for current-source reparse before prepared indexed statements are reused.',
    'schemaCookie' => [
        'before' => $plan['before_schema_cookie'],
        'after' => $plan['after_schema_cookie'],
    ],
    'generatedColumn' => $operation['column'],
    'indexReparseRecords' => $operation['index_reparse_records'],
    'generatedColumnIndexRecords' => $operation['generated_column_index_records'],
    'expressionIndexReparseRecords' => $operation['expression_index_reparse_records'],
    'partialIndexReparseRecords' => $operation['partial_index_reparse_records'],
    'indexGeneratedColumnReferences' => $operation['index_generated_column_references'],
    'invalidatedPrepared' => $plan['invalidated_prepared'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['schemaCookie']['after'] === 135);
    assert($summary['generatedColumn'] === 'option_name_lc');
    assert($summary['generatedColumnIndexRecords'] === [
        'index:wp_options_name_lc_legacy',
        'index:wp_options_name_lc_expr_legacy',
    ]);
    assert($summary['expressionIndexReparseRecords'] === ['index:wp_options_name_lc_expr_legacy']);
    assert($summary['partialIndexReparseRecords'] === ['index:wp_options_name_lc_partial_legacy']);
    assert($summary['invalidatedPrepared'] === ['wp-options-lc-legacy-index']);
    echo "application-schema-generated-column-index-reparse-current-source-next134 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
