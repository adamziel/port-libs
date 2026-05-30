<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteBlobValue.php';
require __DIR__ . '/../src/SQLiteJsonSubtypeValue.php';
require __DIR__ . '/../src/SQLiteJson5Parser.php';
require __DIR__ . '/../src/SQLiteJsonB.php';
require __DIR__ . '/../src/SQLiteJsonCanonical.php';
require __DIR__ . '/../src/SQLiteJsonQuote.php';
require __DIR__ . '/../src/SQLiteJsonConstructor.php';
require __DIR__ . '/../src/SQLiteJsonInspection.php';
require __DIR__ . '/../src/SQLiteJsonPath.php';
require __DIR__ . '/../src/SQLiteJsonEach.php';
require __DIR__ . '/../src/SQLiteJsonTree.php';
require __DIR__ . '/../src/SQLiteDatabase.php';
require __DIR__ . '/../src/SQLiteJsonValidity.php';
require __DIR__ . '/../src/SQLiteJsonTablePlan.php';

use PortLibs\LibSqlite\SQLiteJsonConstructor;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$optionValue = new SQLiteJsonSubtypeValue(SQLiteJsonConstructor::jsonObject(
    'plugin',
    new SQLiteJsonSubtypeValue(SQLiteJsonConstructor::jsonObject(
        'rules',
        new SQLiteJsonSubtypeValue(SQLiteJsonConstructor::jsonArray(
            new SQLiteJsonSubtypeValue(SQLiteJsonConstructor::jsonObject('name', 'seo', 'priority', 2)),
            new SQLiteJsonSubtypeValue(SQLiteJsonConstructor::jsonObject('name', 'cache', 'priority', 7)),
        )),
        'enabled',
        true,
    )),
));

$plan = SQLiteJsonTablePlan::validatedPlan('json_tree', [
    ['column' => 'json', 'operator' => '=', 'value' => $optionValue],
    ['column' => 'root', 'operator' => '=', 'value' => '$.plugin.rules'],
    ['column' => 'atom', 'operator' => '>=', 'value' => 7],
]);

$rows = SQLiteJsonTablePlan::projectedRows('json_tree', [
    ['column' => 'json', 'operator' => '=', 'value' => $optionValue],
    ['column' => 'root', 'operator' => '=', 'value' => '$.plugin.rules'],
    ['column' => 'atom', 'operator' => '>=', 'value' => 7],
], ['key', 'atom', 'type', 'fullkey']);

echo json_encode([
    'scenario' => 'application-json-table-subtype-handoff',
    'inputKind' => $plan['jsonInputKind'],
    'jsonValid' => $plan['jsonValid'],
    'rowCount' => count($rows),
    'matchingFullkeys' => array_column($rows, 'fullkey'),
    'rows' => $rows,
    'applicationUse' => 'Local-only wp_options diagnostics that pass JSON constructor/subtype results directly into json_tree hidden json constraints, preserving SQLite JSON subtype handoff before copied plugin settings are imported without requiring ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
