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
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 10],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 10],
        ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 20],
        ['option_id' => 4, 'option_name' => 'cron', 'autoload' => 'no', 'bytes' => 30],
        ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'bytes' => 30],
    ],
];

$summary = SQLiteSelectSql::execute(
    "SELECT option_id,
            option_name,
            sum(bytes) OVER (
                ORDER BY option_id
                ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING
                EXCLUDE CURRENT ROW
            ) AS following_bytes,
            group_concat(option_name) OVER (
                ORDER BY bytes
                GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING
                EXCLUDE CURRENT ROW
            ) AS peer_next_option_names
       FROM wp_options
      ORDER BY option_id",
    $tables
);

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
