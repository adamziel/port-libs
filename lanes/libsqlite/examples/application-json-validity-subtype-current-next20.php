<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

require __DIR__ . '/../src/SQLiteBlobValue.php';
require __DIR__ . '/../src/SQLiteJson5Parser.php';
require __DIR__ . '/../src/SQLiteJsonB.php';
require __DIR__ . '/../src/SQLiteJsonSubtypeValue.php';
require __DIR__ . '/../src/SQLiteJsonValidity.php';
require __DIR__ . '/../src/SQLiteJsonCanonical.php';
require __DIR__ . '/../src/SQLiteJsonQuote.php';
require __DIR__ . '/../src/SQLiteJsonConstructor.php';
require __DIR__ . '/../src/SQLiteJsonInspection.php';
require __DIR__ . '/../src/SQLiteJsonExtract.php';
require __DIR__ . '/../src/SQLiteJsonPath.php';
require __DIR__ . '/../src/SQLiteJsonPretty.php';
require __DIR__ . '/../src/SQLiteJsonPatch.php';
require __DIR__ . '/../src/SQLiteJsonMutation.php';
require __DIR__ . '/../src/SQLiteJsonArrayInsert.php';
require __DIR__ . '/../src/SQLiteJsonRemove.php';
require __DIR__ . '/../src/SQLiteCoreScalarFunction.php';
require __DIR__ . '/../src/SQLiteSelectExpression.php';
require __DIR__ . '/../src/SQLiteSelectPredicate.php';
require __DIR__ . '/../src/SQLiteSelectProjection.php';
require __DIR__ . '/../src/SQLiteSelectResult.php';
require __DIR__ . '/../src/SQLiteGroupedAggregate.php';
require __DIR__ . '/../src/SQLiteSelectQuery.php';
require __DIR__ . '/../src/SQLiteJsonEach.php';
require __DIR__ . '/../src/SQLiteJsonTree.php';
require __DIR__ . '/../src/SQLiteJsonTablePlan.php';
require __DIR__ . '/../src/SQLiteJsonTableCursor.php';
require __DIR__ . '/../src/SQLiteSelectCompound.php';
require __DIR__ . '/../src/SQLiteSelectSql.php';

$settings = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_settings',
        'option_value' => new SQLiteJsonSubtypeValue('{"plugin":{"enabled":true,"modes":["sync","cache"],"ttl":300}}'),
        'autoload' => 'yes',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_json5_text',
        'option_value' => '{plugin:{enabled:true,modes:["sync",],ttl:300}}',
        'autoload' => 'yes',
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_jsonb',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => ['enabled' => true, 'ttl' => 450]])),
        'autoload' => 'yes',
    ],
];

$rows = SQLiteSelectSql::execute(
    'SELECT option_name, json_valid(option_value) AS strict_ok, json_valid(option_value, 2) AS json5_ok, json_valid(option_value, 4) AS jsonb_ok FROM wp_options ORDER BY option_id',
    ['wp_options' => $settings],
);

echo json_encode([
    'scenario' => 'Copied wp_options JSON subtype values validated through SELECT json_valid() without ext/sqlite.',
    'rows' => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
