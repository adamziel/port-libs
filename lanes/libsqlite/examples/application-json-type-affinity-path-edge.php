<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteBlobValue.php';
require __DIR__ . '/../src/SQLiteJson5Parser.php';
require __DIR__ . '/../src/SQLiteJsonB.php';
require __DIR__ . '/../src/SQLiteJsonCanonical.php';
require __DIR__ . '/../src/SQLiteJsonInspection.php';
require __DIR__ . '/../src/SQLiteJsonPath.php';
require __DIR__ . '/../src/SQLiteJsonExtract.php';
require __DIR__ . '/../src/SQLiteCoreScalarFunction.php';
require __DIR__ . '/../src/SQLiteSelectExpression.php';
require __DIR__ . '/../src/SQLiteSelectPredicate.php';
require __DIR__ . '/../src/SQLiteSelectProjection.php';
require __DIR__ . '/../src/SQLiteSelectResult.php';
require __DIR__ . '/../src/SQLiteSelectQuery.php';
require __DIR__ . '/../src/SQLiteGroupedAggregate.php';
require __DIR__ . '/../src/SQLiteNumericAggregate.php';
require __DIR__ . '/../src/SQLiteTextAggregate.php';
require __DIR__ . '/../src/SQLiteJsonAggregate.php';
require __DIR__ . '/../src/SQLiteSelectCompound.php';
require __DIR__ . '/../src/SQLiteJsonSubtypeValue.php';
require __DIR__ . '/../src/SQLiteJsonConstructor.php';
require __DIR__ . '/../src/SQLiteJsonQuote.php';
require __DIR__ . '/../src/SQLiteJsonValidity.php';
require __DIR__ . '/../src/SQLiteJsonEach.php';
require __DIR__ . '/../src/SQLiteJsonTree.php';
require __DIR__ . '/../src/SQLiteJsonTablePlan.php';
require __DIR__ . '/../src/SQLiteJsonTableCursor.php';
require __DIR__ . '/../src/SQLiteSelectSql.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

$rows = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_seo',
        'option_value' => '{"plugin":{"enabled":true,"priority":7,"modes":["seo","cache"]}}',
        'json_path' => new SQLiteBlobValue('$.plugin.priority'),
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_forms',
        'option_value' => '{"plugin":{"enabled":false,"priority":3,"modes":["forms"]}}',
        'json_path' => new SQLiteBlobValue('$.plugin.modes'),
    ],
];

$diagnostics = SQLiteSelectSql::execute(
    'SELECT option_name, json_type(option_value, json_path) AS path_type, json_extract(option_value, json_path) AS path_value FROM wp_options ORDER BY option_id',
    ['wp_options' => $rows],
);

echo json_encode([
    'diagnostics' => $diagnostics,
    'applicationUse' => 'Copied wp_options diagnostics may store JSON paths as BLOB-affinity values; parser-level json_type/json_extract now applies SQLite text affinity before path lookup without requiring ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
