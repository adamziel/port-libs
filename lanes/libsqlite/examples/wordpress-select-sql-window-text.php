<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteCoreScalarFunction.php';
require_once __DIR__ . '/../src/SQLiteJson5Parser.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteJsonPath.php';
require_once __DIR__ . '/../src/SQLiteJsonExtract.php';
require_once __DIR__ . '/../src/SQLiteJsonInspection.php';
require_once __DIR__ . '/../src/SQLiteJsonEach.php';
require_once __DIR__ . '/../src/SQLiteJsonTree.php';
require_once __DIR__ . '/../src/SQLiteJsonTablePlan.php';
require_once __DIR__ . '/../src/SQLiteSelectExpression.php';
require_once __DIR__ . '/../src/SQLiteSelectPredicate.php';
require_once __DIR__ . '/../src/SQLiteSelectProjection.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteSelectCompound.php';
require_once __DIR__ . '/../src/SQLiteGroupedAggregate.php';
require_once __DIR__ . '/../src/SQLiteWindowFunction.php';
require_once __DIR__ . '/../src/SQLiteSelectQuery.php';
require_once __DIR__ . '/../src/SQLiteSelectSql.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 9],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 9],
        ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 5],
        ['option_id' => 4, 'option_name' => 'cron', 'autoload' => 'no', 'weight' => 4],
        ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'weight' => 4],
        ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'no', 'weight' => 1],
    ],
];

$summary = SQLiteSelectSql::execute(
    "SELECT option_name,
            autoload,
            row_number() OVER (PARTITION BY autoload ORDER BY weight DESC, option_id ASC) AS autoload_rank,
            lag(option_name, 1, 'none') OVER (PARTITION BY autoload ORDER BY option_id ASC) AS previous_autoload_option
       FROM wp_options
      ORDER BY autoload ASC, autoload_rank ASC",
    $tables
);

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
