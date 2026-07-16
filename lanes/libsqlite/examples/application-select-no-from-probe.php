<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = SQLiteSelectSql::execute(
    "SELECT lower('WP_OPTIONS') AS probe, json_extract('{\"driver\":\"native\",\"ok\":true}', '$.ok') AS json_ok WHERE 1 = 1 LIMIT 1",
    []
);

echo json_encode([
    'scenario' => 'application-select-no-from-probe',
    'rows' => $rows,
    'applicationUse' => 'Run extension-free Application SQLite capability probes and scalar diagnostics with SELECT expressions that do not need a table source.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
