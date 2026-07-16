<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$records = [
    new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT "yes")', 1),
    new SQLiteSchemaRecord('index', 'wp_options_autoload', 'wp_options', 3, 'CREATE INDEX wp_options_autoload ON wp_options(autoload)', 2),
    new SQLiteSchemaRecord('view', 'wp_options_export', 'wp_options_export', 0, 'CREATE VIEW wp_options_export AS SELECT * FROM wp_options WHERE autoload = "yes"', 3),
];

$plan = SQLiteSchemaDdlReparsePlan::apply(
    $records,
    [
        'ALTER TABLE wp_options ADD COLUMN option_name_lc TEXT AS (lower(option_name)) VIRTUAL CHECK(option_name_lc <> "")',
        'CREATE INDEX wp_options_generated_lookup ON wp_options(option_name_lc, lower(autoload)) WHERE option_name_lc >= "a"',
    ],
    130,
    'main',
    [
        ['id' => 'wp-options-by-name', 'schema_cookie' => 130, 'sql' => 'SELECT option_name FROM wp_options WHERE option_name_lc >= ?'],
        ['id' => 'wp-options-generated-index', 'schema_cookie' => 131, 'sql' => 'SELECT option_name FROM wp_options INDEXED BY wp_options_generated_lookup'],
    ],
    [
        'wp_options' => [
            ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
            ['option_id' => 2, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'yes'],
        ],
    ],
);

$addColumn = $plan['operations'][0];
$createIndex = $plan['operations'][1];
$summary = [
    'scenario' => 'application-schema-alter-generated-index-current-source-next130',
    'applicationUse' => 'Preview copied Application option-table migrations that add a generated lookup column and immediately create an index over that current sqlite_schema image before prepared option queries are reused.',
    'schemaCookie' => [
        'before' => $plan['before_schema_cookie'],
        'after' => $plan['after_schema_cookie'],
    ],
    'addedColumn' => $addColumn['column'],
    'checkedRows' => $addColumn['checked_rows'],
    'createdIndex' => $createIndex['name'],
    'indexTerms' => $createIndex['terms'],
    'expressionTerms' => $createIndex['expression_terms'],
    'generatedColumnReferences' => $createIndex['generated_column_references'],
    'currentSourceReparse' => $createIndex['current_source_reparse'],
    'invalidatedPrepared' => $plan['invalidated_prepared'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['schemaCookie']['after'] === 132);
    assert($summary['checkedRows'] === 2);
    assert($summary['createdIndex'] === 'wp_options_generated_lookup');
    assert($summary['indexTerms'] === ['option_name_lc', 'lower(autoload)']);
    assert($summary['expressionTerms'] === ['lower(autoload)']);
    assert($summary['generatedColumnReferences'] === ['option_name_lc']);
    assert($summary['currentSourceReparse'] === true);
    assert($summary['invalidatedPrepared'] === ['wp-options-by-name', 'wp-options-generated-index']);
    echo "application-schema-alter-generated-index-current-source-next130 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
