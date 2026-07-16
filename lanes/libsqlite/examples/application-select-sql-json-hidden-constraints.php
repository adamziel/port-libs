<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$settingsJson = '{"plugin":{"rules":[{"name":"seo","priority":2,"autoload":true},{"name":"cache","priority":7,"autoload":false},{"name":"forms","priority":4,"autoload":true}]}}';

$priorityRows = SQLiteSelectSql::execute(
    "SELECT key, atom AS priority, fullkey FROM json_tree WHERE json = '{$settingsJson}' AND root = '$.plugin.rules' AND type = 'integer' ORDER BY priority DESC LIMIT 2",
    [],
);

$summaryRows = SQLiteSelectSql::execute(
    "SELECT type, count(*) AS rows, sum(atom) AS atom_sum FROM json_tree WHERE json = '{$settingsJson}' AND root = '$.plugin.rules' GROUP BY type HAVING count(*) >= 2 ORDER BY rows DESC, type ASC LIMIT 2",
    [],
);

echo json_encode([
    'scenario' => 'application-select-sql-json-hidden-constraints',
    'priorityRows' => $priorityRows,
    'summaryRows' => $summaryRows,
], JSON_PRETTY_PRINT) . PHP_EOL;
