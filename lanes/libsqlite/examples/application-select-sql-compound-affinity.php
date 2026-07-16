<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteCoreScalarFunction.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteGroupedAggregate.php';
require_once __DIR__ . '/../src/SQLiteJson5Parser.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteJsonEach.php';
require_once __DIR__ . '/../src/SQLiteJsonPath.php';
require_once __DIR__ . '/../src/SQLiteJsonTablePlan.php';
require_once __DIR__ . '/../src/SQLiteJsonTree.php';
require_once __DIR__ . '/../src/SQLiteJsonValidity.php';
require_once __DIR__ . '/../src/SQLiteSelectCompound.php';
require_once __DIR__ . '/../src/SQLiteSelectExpression.php';
require_once __DIR__ . '/../src/SQLiteSelectPredicate.php';
require_once __DIR__ . '/../src/SQLiteSelectProjection.php';
require_once __DIR__ . '/../src/SQLiteSelectQuery.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteSelectSql.php';

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => '1', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 1, 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'active_plugins', 'option_value' => new SQLiteBlobValue('1'), 'autoload' => 'no'],
    ['option_id' => 4, 'option_name' => 'blog_public', 'option_value' => 1.0, 'autoload' => 'yes'],
    ['option_id' => 5, 'option_name' => 'orphaned', 'option_value' => null, 'autoload' => null],
];

$staged = [
    ['option_id' => 10, 'option_name' => 'network_siteurl', 'option_value' => 1, 'autoload' => 'yes'],
    ['option_id' => 11, 'option_name' => 'network_plugins', 'option_value' => new SQLiteBlobValue('1'), 'autoload' => 'no'],
    ['option_id' => 12, 'option_name' => 'network_null', 'option_value' => null, 'autoload' => null],
];

$localTextOnly = SQLiteSelectSql::execute(
    "SELECT option_value AS value FROM wp_options WHERE option_name = 'siteurl' EXCEPT SELECT option_value AS value FROM staged_options WHERE option_name = 'network_siteurl'",
    ['wp_options' => $options, 'staged_options' => $staged],
);

$numericIntersection = SQLiteSelectSql::execute(
    "SELECT option_value AS value FROM wp_options WHERE option_name IN ('home','blog_public') INTERSECT SELECT option_value AS value FROM staged_options WHERE option_name = 'network_siteurl'",
    ['wp_options' => $options, 'staged_options' => $staged],
);

$blobLocalOnly = SQLiteSelectSql::execute(
    "SELECT option_value AS value FROM wp_options WHERE option_name = 'active_plugins' EXCEPT SELECT '1' AS value",
    ['wp_options' => $options],
);

$nullIntersection = SQLiteSelectSql::execute(
    "SELECT option_value AS value FROM wp_options WHERE option_name = 'orphaned' INTERSECT SELECT option_value AS value FROM staged_options WHERE option_name = 'network_null'",
    ['wp_options' => $options, 'staged_options' => $staged],
);

$format = static function (array $rows): array {
    return array_map(static function (array $row): mixed {
        $value = $row['value'];
        if ($value instanceof SQLiteBlobValue) {
            return ['blobHex' => bin2hex($value->bytes)];
        }

        return $value;
    }, $rows);
};

return [
    'applicationUse' => 'Preview copied wp_options compound INTERSECT/EXCEPT comparisons where SQLite does not apply affinity between text, numeric, and BLOB option_value storage classes before migration/import tooling deduplicates rows.',
    'textOneExceptNumericOne' => $format($localTextOnly),
    'numericOneIntersection' => $format($numericIntersection),
    'blobOneExceptTextOne' => $format($blobLocalOnly),
    'nullIntersection' => $format($nullIntersection),
];
