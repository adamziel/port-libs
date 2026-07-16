<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaEncodingPageTempStoreState;

$pragma = new SQLitePragmaEncodingPageTempStoreState([
    'main' => [
        'encoding' => 'UTF-8',
        'page_size' => 4096,
        'page_count' => 18,
        'database_empty' => true,
    ],
]);

$encoding = $pragma->execute("PRAGMA encoding='UTF-16le'");
$pageSize = $pragma->execute('PRAGMA page_size=8192');
$tempStore = $pragma->execute('PRAGMA temp_store=MEMORY');
$pageCount = $pragma->execute('PRAGMA page_count');

$report = [
    'scenario' => 'copied wp_options pragma encoding page temp_store preflight',
    'encoding' => $encoding['effective'],
    'page_size' => $pageSize['effective'],
    'page_count' => $pageCount['effective'],
    'temp_store' => $tempStore['effective'],
    'temp_encoding' => $pragma->execute('PRAGMA temp.encoding')['effective'],
    'dependencies' => array_values(array_unique(array_merge(
        $encoding['dependencies'],
        $pageSize['dependencies'],
        $pageCount['dependencies'],
        $tempStore['dependencies'],
    ))),
];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
