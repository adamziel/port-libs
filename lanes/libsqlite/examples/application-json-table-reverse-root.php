<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteBlobValue.php';
require __DIR__ . '/../src/SQLiteJson5Parser.php';
require __DIR__ . '/../src/SQLiteJsonB.php';
require __DIR__ . '/../src/SQLiteJsonCanonical.php';
require __DIR__ . '/../src/SQLiteJsonInspection.php';
require __DIR__ . '/../src/SQLiteJsonPath.php';
require __DIR__ . '/../src/SQLiteJsonQuote.php';
require __DIR__ . '/../src/SQLiteJsonEach.php';
require __DIR__ . '/../src/SQLiteJsonTree.php';
require __DIR__ . '/../src/SQLiteDatabase.php';
require __DIR__ . '/../src/SQLiteJsonValidity.php';
require __DIR__ . '/../src/SQLiteJsonTablePlan.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$optionValue = '{"plugin":{"rules":[{"name":"seo"},{"name":"cache"}],"enabled":true}}';

$rows = SQLiteJsonTablePlan::projectedRows('json_tree', [
    ['column' => 'json', 'operator' => '=', 'value' => $optionValue],
    ['column' => 'root', 'operator' => '=', 'value' => '$.plugin.rules[#-1]'],
], ['rowid', 'key', 'parent', 'type', 'atom', 'path', 'root']);

echo json_encode([
    'scenario' => 'application-json-table-reverse-root',
    'rowCount' => count($rows),
    'rootKey' => $rows[0]['key'] ?? null,
    'rootPath' => $rows[0]['path'] ?? null,
    'leafAtom' => $rows[1]['atom'] ?? null,
    'rows' => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
