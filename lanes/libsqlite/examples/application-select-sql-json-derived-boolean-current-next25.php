<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteJsonPath.php';
require_once __DIR__ . '/../src/SQLiteJsonCanonical.php';
require_once __DIR__ . '/../src/SQLiteJsonInspection.php';
require_once __DIR__ . '/../src/SQLiteJsonExtract.php';
require_once __DIR__ . '/../src/SQLiteJsonSubtypeValue.php';
require_once __DIR__ . '/../src/SQLiteJson5Parser.php';
require_once __DIR__ . '/../src/SQLiteJsonValidity.php';
require_once __DIR__ . '/../src/SQLiteJsonErrorPosition.php';
require_once __DIR__ . '/../src/SQLiteJsonPretty.php';
require_once __DIR__ . '/../src/SQLiteJsonPatch.php';
require_once __DIR__ . '/../src/SQLiteJsonArrayInsert.php';
require_once __DIR__ . '/../src/SQLiteJsonMutation.php';
require_once __DIR__ . '/../src/SQLiteJsonRemove.php';
require_once __DIR__ . '/../src/SQLiteJsonQuote.php';
require_once __DIR__ . '/../src/SQLiteJsonConstructor.php';
require_once __DIR__ . '/../src/SQLiteJsonEach.php';
require_once __DIR__ . '/../src/SQLiteJsonTree.php';
require_once __DIR__ . '/../src/SQLiteCoreScalarFunction.php';
require_once __DIR__ . '/../src/SQLiteNumericAggregateState.php';
require_once __DIR__ . '/../src/SQLiteNumericAggregate.php';
require_once __DIR__ . '/../src/SQLiteTextAggregateState.php';
require_once __DIR__ . '/../src/SQLiteTextAggregate.php';
require_once __DIR__ . '/../src/SQLiteGroupedAggregate.php';
require_once __DIR__ . '/../src/SQLiteWindowFunction.php';
require_once __DIR__ . '/../src/SQLiteSelectExpression.php';
require_once __DIR__ . '/../src/SQLiteSelectPredicate.php';
require_once __DIR__ . '/../src/SQLiteSelectProjection.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteSelectCompound.php';
require_once __DIR__ . '/../src/SQLiteSelectQuery.php';
require_once __DIR__ . '/../src/SQLiteSelectSql.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

$rows = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_cache',
        'option_value' => '{"enabled":true,"network":false,"label":"cache"}',
        'autoload' => 'yes',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_forms',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'enabled' => false,
            'network' => true,
            'label' => 'forms',
        ])),
        'autoload' => 'no',
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_mu',
        'option_value' => '{"enabled":true,"network":true,"label":"mu"}',
        'autoload' => 'yes',
    ],
];

$enabled = SQLiteSelectSql::execute(
    "SELECT option_name, option_value ->> '$.label' AS label FROM wp_options WHERE json_extract(option_value, '$.enabled') ORDER BY option_id",
    ['wp_options' => $rows],
);
$network = SQLiteSelectSql::execute(
    "SELECT option_name FROM wp_options WHERE jsonb_extract(option_value, '$.network') IS TRUE ORDER BY option_id",
    ['wp_options' => $rows],
);
$inactive = SQLiteSelectSql::execute(
    "SELECT option_name FROM wp_options WHERE NOT json_extract(option_value, '$.enabled') ORDER BY option_id",
    ['wp_options' => $rows],
);

return [
    'scenario' => 'application-select-sql-json-derived-boolean-current-next25',
    'applicationUse' => 'Local-only wp_options plugin-setting preview that mirrors SQLite JSON boolean truth predicates for json_extract(), jsonb_extract(), ->>, TRUE/FALSE literals, NOT, and IS TRUE/FALSE without requiring ext/sqlite.',
    'enabledPlugins' => array_column($enabled, 'option_name'),
    'enabledLabels' => array_column($enabled, 'label'),
    'networkPlugins' => array_column($network, 'option_name'),
    'inactivePlugins' => array_column($inactive, 'option_name'),
];
