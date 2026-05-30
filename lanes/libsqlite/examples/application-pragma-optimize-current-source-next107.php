<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaOptimizePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$tables = [
    [
        'schema' => 'main',
        'name' => 'wp_options',
        'rowCount' => 12000,
        'statRowCount' => 8000,
        'touched' => true,
        'schemaCookie' => 41,
        'expectedSchemaCookie' => 41,
        'statCookie' => 7,
        'expectedStatCookie' => 7,
        'sourceId' => 'main-schema-v41',
        'expectedSourceId' => 'main-schema-v41',
    ],
    [
        'schema' => 'main',
        'name' => 'wp_usermeta',
        'rowCount' => 50000,
        'statRowCount' => 10000,
        'touched' => true,
        'schemaCookie' => 42,
        'expectedSchemaCookie' => 41,
        'statCookie' => 7,
        'expectedStatCookie' => 7,
        'sourceId' => 'main-schema-v42',
        'expectedSourceId' => 'main-schema-v41',
    ],
    [
        'schema' => 'network',
        'name' => 'wp_sitemeta',
        'rowCount' => 1000,
        'statRowCount' => 10,
        'touched' => true,
        'schemaCookie' => 9,
        'expectedSchemaCookie' => 9,
        'statCookie' => 3,
        'expectedStatCookie' => 3,
        'sourceId' => 'network-schema-v9',
        'expectedSourceId' => 'network-schema-v9',
    ],
];

$pragma = new SQLitePragmaOptimizePlan(['main' => 128, 'network' => 64]);
$main = $pragma->execute('PRAGMA optimize', $tables);
$network = $pragma->execute('PRAGMA network.optimize', $tables);

echo json_encode([
    'mainAnalyze' => array_column($main['analyze'], 'reason', 'table'),
    'mainSkipped' => array_column($main['skipped'], 'reason', 'table'),
    'mainCurrentSource' => $main['currentSource'],
    'networkAnalyze' => array_column($network['analyze'], 'reason', 'table'),
    'networkCurrentSource' => $network['currentSource'],
    'applicationUse' => 'Copied Application SQLite databases can reuse PRAGMA optimize preflight metadata only while schema cookies, sqlite_stat1 cookies, and source ids match the current schema snapshot; stale rows are skipped instead of scheduling ANALYZE from obsolete catalog state.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
