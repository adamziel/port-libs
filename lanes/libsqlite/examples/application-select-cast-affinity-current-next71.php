<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteCoreScalarFunction.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteJsonCanonical.php';
require_once __DIR__ . '/../src/SQLiteJsonExtract.php';
require_once __DIR__ . '/../src/SQLiteJsonInspection.php';
require_once __DIR__ . '/../src/SQLiteJsonPath.php';
require_once __DIR__ . '/../src/SQLiteJsonSubtypeValue.php';
require_once __DIR__ . '/../src/SQLiteSelectExpression.php';
require_once __DIR__ . '/../src/SQLiteSelectPredicate.php';
require_once __DIR__ . '/../src/SQLiteSelectProjection.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteGroupedAggregate.php';
require_once __DIR__ . '/../src/SQLiteSelectQuery.php';
require_once __DIR__ . '/../src/SQLiteSelectSql.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => '42.9', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => '042', 'autoload' => 'no'],
    ['option_id' => 3, 'option_name' => 'active_plugins', 'option_value' => new SQLiteBlobValue('07plugin'), 'autoload' => 'no'],
];

$diagnostics = [
    'integerAffinity' => SQLiteSelectSql::execute(
        'SELECT option_name, CAST(option_value AS UNSIGNED BIG INT) AS numeric_value FROM wp_options ORDER BY option_id',
        ['wp_options' => $rows],
    ),
    'realAffinity' => SQLiteSelectSql::execute(
        'SELECT option_name, CAST(option_value AS DOUBLE PRECISION) AS real_value FROM wp_options ORDER BY real_value DESC, option_id',
        ['wp_options' => $rows],
    ),
    'textAffinity' => SQLiteSelectSql::execute(
        'SELECT option_name, CAST(option_id AS VARCHAR(20)) AS id_text FROM wp_options ORDER BY option_id',
        ['wp_options' => $rows],
    ),
    'numericFallback' => SQLiteSelectSql::execute(
        'SELECT option_name FROM wp_options WHERE CAST(option_value AS DECIMAL(10,2)) IN (7, 42) ORDER BY option_id',
        ['wp_options' => $rows],
    ),
    'blobAffinityBytes' => array_map(
        static fn (array $row): array => [
            'option_name' => $row['option_name'],
            'hex' => bin2hex($row['blob_value']->bytes),
        ],
        SQLiteSelectSql::execute(
            'SELECT option_name, CAST(option_value AS NONE) AS blob_value FROM wp_options ORDER BY option_id',
            ['wp_options' => $rows],
        ),
    ),
];

echo json_encode([
    'scenario' => 'application-select-cast-affinity-current-next71',
    'diagnostics' => $diagnostics,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
