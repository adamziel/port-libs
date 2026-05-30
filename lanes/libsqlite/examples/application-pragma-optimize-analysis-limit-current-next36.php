<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaOptimizePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$tables = [
    ['schema' => 'main', 'name' => 'wp_options', 'rowCount' => 12000, 'statRowCount' => 8000, 'touched' => true],
    ['schema' => 'main', 'name' => 'wp_postmeta', 'rowCount' => 240000, 'statRowCount' => 240000, 'touched' => false],
    ['schema' => 'main', 'name' => 'wp_posts', 'rowCount' => 20000, 'hasStat' => false, 'touched' => false],
    ['schema' => 'aux', 'name' => 'wp_options', 'rowCount' => 90, 'statRowCount' => 10, 'touched' => true],
];

$pragma = new SQLitePragmaOptimizePlan(['main' => 128]);

echo json_encode([
    'analysisLimit' => $pragma->execute('PRAGMA analysis_limit'),
    'mainOptimize' => $pragma->execute('PRAGMA optimize', $tables),
    'auxOptimize' => $pragma->execute('PRAGMA aux.optimize', $tables),
    'applicationUse' => 'Copied Application SQLite databases can run bounded PRAGMA optimize planning with temporary analysis_limit application, stale sqlite_stat1 detection, attached-schema targeting, and restored connection state without requiring ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
