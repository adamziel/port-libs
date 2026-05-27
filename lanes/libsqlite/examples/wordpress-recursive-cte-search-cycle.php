<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteSelectSql.php';
require_once __DIR__ . '/../src/SQLiteSelectQuery.php';
require_once __DIR__ . '/../src/SQLiteSelectProjection.php';
require_once __DIR__ . '/../src/SQLiteSelectPredicate.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteSelectCompound.php';
require_once __DIR__ . '/../src/SQLiteSelectExpression.php';
require_once __DIR__ . '/../src/SQLiteGroupedAggregate.php';
require_once __DIR__ . '/../src/SQLiteNumericAggregate.php';
require_once __DIR__ . '/../src/SQLiteNumericAggregateState.php';
require_once __DIR__ . '/../src/SQLiteTextAggregate.php';
require_once __DIR__ . '/../src/SQLiteTextAggregateState.php';
require_once __DIR__ . '/../src/SQLiteJsonAggregate.php';
require_once __DIR__ . '/../src/SQLiteJsonAggregateState.php';
require_once __DIR__ . '/../src/SQLiteJsonSubtypeValue.php';
require_once __DIR__ . '/../src/SQLiteJsonCanonical.php';
require_once __DIR__ . '/../src/SQLiteJsonInspection.php';
require_once __DIR__ . '/../src/SQLiteJsonExtract.php';
require_once __DIR__ . '/../src/SQLiteJsonPath.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteJsonTablePlan.php';
require_once __DIR__ . '/../src/SQLiteJsonTableCursor.php';
require_once __DIR__ . '/../src/SQLiteJsonEach.php';
require_once __DIR__ . '/../src/SQLiteJsonTree.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$tables = [
    'option_edges' => [
        ['parent' => 'plugin_settings', 'child' => 'plugin_cache'],
        ['parent' => 'plugin_settings', 'child' => 'plugin_theme'],
        ['parent' => 'plugin_cache', 'child' => 'plugin_cache_ttl'],
        ['parent' => 'plugin_theme', 'child' => 'plugin_theme_css'],
        ['parent' => 'plugin_theme_css', 'child' => 'plugin_theme'],
    ],
    'wp_options' => [
        ['option_name' => 'plugin_settings', 'autoload' => 'yes'],
        ['option_name' => 'plugin_cache', 'autoload' => 'no'],
        ['option_name' => 'plugin_cache_ttl', 'autoload' => 'no'],
        ['option_name' => 'plugin_theme', 'autoload' => 'yes'],
        ['option_name' => 'plugin_theme_css', 'autoload' => 'no'],
    ],
];

$rows = SQLiteSelectSql::execute(
    "WITH RECURSIVE dependency(name, depth) AS (
        VALUES ('plugin_settings', 0)
        UNION
        SELECT option_edges.child, dependency.depth + 1
        FROM option_edges JOIN dependency ON option_edges.parent = dependency.name
        WHERE dependency.depth < 4
        ORDER BY 2 DESC
    )
    SELECT dependency.name AS option_name, wp_options.autoload AS autoload, dependency.depth AS depth
    FROM dependency JOIN wp_options ON wp_options.option_name = dependency.name
    ORDER BY depth, option_name",
    $tables,
);

foreach ($rows as $row) {
    echo $row['depth'] . ' ' . $row['option_name'] . ' autoload=' . $row['autoload'] . PHP_EOL;
}
