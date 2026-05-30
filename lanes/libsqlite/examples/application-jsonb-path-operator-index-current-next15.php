<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLiteBlobValue.php';
require_once dirname(__DIR__) . '/src/SQLiteJson5Parser.php';
require_once dirname(__DIR__) . '/src/SQLiteJsonB.php';
require_once dirname(__DIR__) . '/src/SQLiteJsonCanonical.php';
require_once dirname(__DIR__) . '/src/SQLiteJsonInspection.php';
require_once dirname(__DIR__) . '/src/SQLiteJsonPath.php';
require_once dirname(__DIR__) . '/src/SQLiteJsonExtract.php';
require_once dirname(__DIR__) . '/src/SQLiteJsonSubtypeValue.php';
require_once dirname(__DIR__) . '/src/SQLiteCoreScalarFunction.php';
require_once dirname(__DIR__) . '/src/SQLiteSelectExpression.php';
require_once dirname(__DIR__) . '/src/SQLiteSelectPredicate.php';
require_once dirname(__DIR__) . '/src/SQLiteSelectProjection.php';
require_once dirname(__DIR__) . '/src/SQLiteSelectResult.php';
require_once dirname(__DIR__) . '/src/SQLiteSelectQuery.php';
require_once dirname(__DIR__) . '/src/SQLiteSelectCompound.php';
require_once dirname(__DIR__) . '/src/SQLiteGroupedAggregate.php';
require_once dirname(__DIR__) . '/src/SQLiteNumericAggregateState.php';
require_once dirname(__DIR__) . '/src/SQLiteNumericAggregate.php';
require_once dirname(__DIR__) . '/src/SQLiteTextAggregateState.php';
require_once dirname(__DIR__) . '/src/SQLiteTextAggregate.php';
require_once dirname(__DIR__) . '/src/SQLiteJsonAggregateState.php';
require_once dirname(__DIR__) . '/src/SQLiteJsonAggregate.php';
require_once dirname(__DIR__) . '/src/SQLiteSelectSql.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

$rows = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_cache_settings',
        'option_value' => '{"plugin":{"name":"cache","enabled":true,"priority":7,"channel":"stable"}}',
        'autoload' => 'yes',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_forms_settings',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'plugin' => ['name' => 'forms', 'enabled' => false, 'priority' => 3, 'channel' => 'beta'],
        ])),
        'autoload' => 'no',
    ],
];

echo json_encode(
    SQLiteSelectSql::execute(
        "SELECT option_name, option_value ->> '$.plugin.name' AS plugin, option_value -> '$.plugin.channel' AS channel_json FROM wp_options WHERE option_value ->> '$.plugin.priority' >= 3 ORDER BY option_value ->> '$.plugin.priority' DESC",
        ['wp_options' => $rows],
    ),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
) . PHP_EOL;
