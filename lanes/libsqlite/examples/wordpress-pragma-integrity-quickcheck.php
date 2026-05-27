<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePragmaIntegrityCheck;

$pageSize = 512;
$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage = substr_replace($firstPage, pack('N', 3), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 2), 32, 4);
$firstPage = substr_replace($firstPage, pack('N', 2), 36, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$wordpressDatabase = $firstPage
    . SQLiteFreelistTrunkPage::assemble(null, [3], $pageSize)
    . str_repeat("\0", $pageSize);

$ok = SQLitePragmaIntegrityCheck::execute('PRAGMA quick_check', $wordpressDatabase);
$corrupt = SQLitePragmaIntegrityCheck::execute(
    'PRAGMA integrity_check(1)',
    substr_replace($wordpressDatabase, pack('N', 9), 56, 4),
);

echo json_encode([
    'scenario' => 'copied-wp-options-integrity-preflight',
    'quick_check' => $ok['rows'],
    'integrity_check_limited' => $corrupt['rows'],
], JSON_PRETTY_PRINT) . "\n";
