<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaEncodingPageTempStoreState;

$pragma = new SQLitePragmaEncodingPageTempStoreState([
    'main' => [
        'auto_vacuum' => 0,
        'database_empty' => false,
        'page_count' => 42,
    ],
    'archive' => [
        'auto_vacuum' => 'FULL',
        'page_count' => 18,
    ],
]);

$enable = $pragma->execute('PRAGMA auto_vacuum=INCREMENTAL');
$pageCount = $pragma->execute('PRAGMA page_count');
$archive = $pragma->execute('PRAGMA archive.auto_vacuum');

$report = [
    'scenario' => 'copied wp_options pragma auto_vacuum page_count current next27',
    'applicationUse' => 'Preview a copied Application SQLite database deciding whether enabling incremental auto_vacuum is current or pending while preserving current page_count without ext/sqlite.',
    'mainAutoVacuumCurrent' => $enable['effective'],
    'mainAutoVacuumPending' => $enable['pending'],
    'requiresVacuum' => $enable['requires_vacuum'],
    'reason' => $enable['reason'],
    'pageCount' => $pageCount['effective'],
    'archiveAutoVacuum' => $archive['effective'],
    'dependencies' => array_values(array_unique(array_merge(
        $enable['dependencies'],
        $pageCount['dependencies'],
        $archive['dependencies'],
    ))),
];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
