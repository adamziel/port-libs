<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteSelectCompound.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectCompound;

$autoloaded = [
    ['option_name' => 'siteurl', 'source' => 'autoload', 'payload' => new SQLiteBlobValue('url')],
    ['option_name' => 'home', 'source' => 'autoload', 'payload' => new SQLiteBlobValue('url')],
    ['option_name' => 'home', 'source' => 'autoload', 'payload' => new SQLiteBlobValue('url')],
    ['option_name' => 'blogname', 'source' => 'autoload', 'payload' => new SQLiteBlobValue('text')],
    ['option_name' => 'maybe-null', 'source' => null, 'payload' => null],
];

$network = [
    ['option_name' => 'home', 'source' => 'autoload', 'payload' => new SQLiteBlobValue('url')],
    ['option_name' => 'network_home', 'source' => 'network', 'payload' => new SQLiteBlobValue('url')],
    ['option_name' => 'maybe-null', 'source' => null, 'payload' => null],
    ['option_name' => 'siteurl', 'source' => 'network', 'payload' => new SQLiteBlobValue('url')],
];

$union = SQLiteSelectCompound::execute(
    $autoloaded,
    $network,
    'UNION',
    [['column' => 'option_name']]
);
$unionAll = SQLiteSelectCompound::union($autoloaded, $network, true);
$intersect = SQLiteSelectCompound::intersect($autoloaded, $network);
$except = SQLiteSelectCompound::execute(
    $autoloaded,
    $network,
    'EXCEPT',
    [['column' => 'option_name']]
);

$report = [
    'unionOptions' => array_column($union, 'option_name'),
    'unionAllCount' => count($unionAll),
    'intersectOptions' => array_column($intersect, 'option_name'),
    'exceptOptions' => array_column($except, 'option_name'),
    'nullRowsIntersect' => count(array_filter($intersect, static fn (array $row): bool => $row['source'] === null)),
    'applicationUse' => 'Preview copied wp_options rows composed from multiple SELECT arms with SQLite UNION, UNION ALL, INTERSECT, and EXCEPT semantics before migration/import tooling applies final ordering.',
];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
