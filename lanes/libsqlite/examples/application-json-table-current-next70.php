<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteJson5Parser.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteJsonCanonical.php';
require_once __DIR__ . '/../src/SQLiteJsonConstructor.php';
require_once __DIR__ . '/../src/SQLiteJsonEach.php';
require_once __DIR__ . '/../src/SQLiteJsonInspection.php';
require_once __DIR__ . '/../src/SQLiteJsonPath.php';
require_once __DIR__ . '/../src/SQLiteJsonQuote.php';
require_once __DIR__ . '/../src/SQLiteJsonSubtypeValue.php';
require_once __DIR__ . '/../src/SQLiteJsonTablePlan.php';
require_once __DIR__ . '/../src/SQLiteJsonTree.php';
require_once __DIR__ . '/../src/SQLiteJsonValidity.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$settings = json_encode([
    'plugins' => [
        ['slug' => 'akismet', 'enabled' => true, 'autoload' => 'yes', 'priority' => 4],
        ['slug' => 'seo-pack', 'enabled' => true, 'autoload' => 'yes', 'priority' => 2],
        ['slug' => 'cache-pro', 'enabled' => true, 'autoload' => 'no', 'priority' => 7],
        ['slug' => 'forms-lite', 'enabled' => false, 'autoload' => 'yes', 'priority' => 5],
    ],
], JSON_THROW_ON_ERROR);

$plan = SQLiteJsonTablePlan::adjacentConstraintPlan('json_tree', [
    ['column' => 'json', 'operator' => '=', 'value' => $settings],
    ['column' => 'root', 'operator' => '=', 'value' => '$.plugins'],
    ['column' => 'key', 'operator' => '=', 'value' => 'slug'],
    ['column' => 'type', 'operator' => '=', 'value' => 'text'],
    ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugins[%].slug'],
    ['column' => 'atom', 'operator' => 'GLOB', 'value' => '*-*'],
], [['column' => 'id']]);

echo json_encode([
    'scenario' => 'application-json-table-current-next70',
    'runnable' => $plan['runnable'],
    'filterArguments' => count($plan['filterArguments']),
    'rowPairs' => array_map(
        static fn (array $pair): array => [
            'current' => $pair['current']['atom'],
            'next' => $pair['next']['atom'] ?? null,
        ],
        $plan['rowCurrentNext'],
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
