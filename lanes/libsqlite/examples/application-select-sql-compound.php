<?php

declare(strict_types=1);

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
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 9],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 12],
];
$networkOptions = [
    ['option_id' => 10, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 30],
    ['option_id' => 11, 'option_name' => 'upload_path', 'autoload' => 'no', 'bytes' => 8],
    ['option_id' => 12, 'option_name' => 'network_admin_email', 'autoload' => 'yes', 'bytes' => 20],
];

$sql = "SELECT option_name AS name, autoload FROM wp_options WHERE autoload = 'yes' UNION SELECT option_name AS name, autoload FROM network_options WHERE autoload = 'yes' ORDER BY name ASC";
$unionRows = SQLiteSelectSql::execute($sql, ['wp_options' => $options, 'network_options' => $networkOptions]);

$exceptSql = "SELECT option_name AS name FROM wp_options WHERE autoload = 'yes' EXCEPT SELECT option_name AS name FROM network_options ORDER BY name ASC";
$localOnlyRows = SQLiteSelectSql::execute($exceptSql, ['wp_options' => $options, 'network_options' => $networkOptions]);

return [
    'applicationUse' => 'Preview copied single-site and network wp_options rows through parser-level compound SELECT text, preserving UNION duplicate removal, EXCEPT local-only rows, final ORDER BY, and no ext/sqlite dependency.',
    'unionNames' => array_column($unionRows, 'name'),
    'localOnlyNames' => array_column($localOnlyRows, 'name'),
    'compoundOperators' => SQLiteSelectSql::plan($sql, ['wp_options' => $options, 'network_options' => $networkOptions])['compound']['operators'],
];
