<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteCoreScalarFunction.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteJsonCanonical.php';
require_once __DIR__ . '/../src/SQLiteJsonExtract.php';
require_once __DIR__ . '/../src/SQLiteJsonInspection.php';
require_once __DIR__ . '/../src/SQLiteJsonSubtypeValue.php';
require_once __DIR__ . '/../src/SQLiteSelectCompound.php';
require_once __DIR__ . '/../src/SQLiteSelectExpression.php';
require_once __DIR__ . '/../src/SQLiteSelectPredicate.php';
require_once __DIR__ . '/../src/SQLiteSelectProjection.php';
require_once __DIR__ . '/../src/SQLiteSelectQuery.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteSelectSql.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$singleSiteOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'HOME ', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => null, 'autoload' => 'yes'],
];
$networkOptions = [
    ['option_id' => 10, 'option_name' => 'SiteURL', 'autoload' => 'yes'],
    ['option_id' => 11, 'option_name' => 'home', 'autoload' => 'yes'],
    ['option_id' => 12, 'option_name' => 'blogname ', 'autoload' => 'yes'],
    ['option_id' => 13, 'option_name' => null, 'autoload' => 'yes'],
];

$rows = SQLiteSelectSql::execute(
    "SELECT option_name COLLATE NOCASE AS name FROM wp_options
     UNION
     SELECT option_name AS name FROM network_options
     ORDER BY name COLLATE NOCASE NULLS LAST
     LIMIT 1, 3",
    ['wp_options' => $singleSiteOptions, 'network_options' => $networkOptions],
);

$result = [
    'scenario' => 'compound SELECT projected COLLATE duplicate comparison',
    'names' => array_column($rows, 'name'),
    'rows' => $rows,
];

if (($argv[1] ?? null) === '--self-test') {
    assert($result['names'] === ['blogname ', 'home', 'HOME ']);
    echo "application-select-compound-collate-set self-test passed\n";
    return;
}

echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
