<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$settingsJson = '{"plugin":{"rules":[{"name":"seo","priority":2},{"name":"cache","priority":7},{"name":"forms","priority":4}],"flags":{"network":true,"beta":false}}}';
$settingsSql = str_replace("'", "''", $settingsJson);

$priorityRows = SQLiteSelectSql::execute(
    "SELECT key, atom AS priority, fullkey FROM json_tree WHERE '{$settingsSql}' = json AND '$.plugin.rules' = root AND key = 'priority' ORDER BY priority DESC",
    [],
);
$flagRows = SQLiteSelectSql::execute(
    "SELECT key, atom FROM json_each WHERE '$.plugin.flags' = root AND '{$settingsSql}' = json ORDER BY key",
    [],
);

echo json_encode([
    'scenario' => 'application-json-table-commuted-hidden-constraints',
    'priority_count' => count($priorityRows),
    'priorities' => array_column($priorityRows, 'priority'),
    'top_priority_path' => $priorityRows[0]['fullkey'] ?? null,
    'flags' => array_column($flagRows, 'key'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
