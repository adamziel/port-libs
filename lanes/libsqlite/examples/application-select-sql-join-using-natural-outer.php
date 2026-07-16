<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteCoreScalarFunction.php';
require_once __DIR__ . '/../src/SQLiteNumericAggregateState.php';
require_once __DIR__ . '/../src/SQLiteNumericAggregate.php';
require_once __DIR__ . '/../src/SQLiteTextAggregateState.php';
require_once __DIR__ . '/../src/SQLiteTextAggregate.php';
require_once __DIR__ . '/../src/SQLiteGroupedAggregate.php';
require_once __DIR__ . '/../src/SQLiteSelectExpression.php';
require_once __DIR__ . '/../src/SQLiteSelectPredicate.php';
require_once __DIR__ . '/../src/SQLiteSelectProjection.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteSelectQuery.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteJsonPath.php';
require_once __DIR__ . '/../src/SQLiteJsonTree.php';
require_once __DIR__ . '/../src/SQLiteJsonTablePlan.php';
require_once __DIR__ . '/../src/SQLiteSelectSql.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'site_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes'],
        ['option_id' => 2, 'site_id' => 1, 'option_name' => 'home', 'autoload' => 'yes'],
        ['option_id' => 3, 'site_id' => 1, 'option_name' => 'blogname', 'autoload' => 'yes'],
        ['option_id' => 5, 'site_id' => 2, 'option_name' => 'network_admin_email', 'autoload' => 'yes'],
    ],
    'option_meta' => [
        ['option_id' => 1, 'site_id' => 1, 'source' => 'core', 'priority' => 10],
        ['option_id' => 2, 'site_id' => 1, 'source' => 'core', 'priority' => 20],
        ['option_id' => 3, 'site_id' => 1, 'source' => 'theme', 'priority' => 30],
        ['option_id' => 6, 'site_id' => 2, 'source' => 'orphan_meta', 'priority' => 1],
    ],
];

$rows = SQLiteSelectSql::execute(
    'SELECT o.option_name AS option_name, m.source AS source, m.priority AS priority FROM wp_options AS o FULL OUTER JOIN option_meta AS m USING (option_id) ORDER BY option_name, priority',
    $tables,
);

echo json_encode([
    'scenario' => 'application-select-sql-join-using-natural-outer',
    'rowCount' => count($rows),
    'optionNames' => array_column($rows, 'option_name'),
    'sources' => array_column($rows, 'source'),
], JSON_PRETTY_PRINT) . PHP_EOL;
