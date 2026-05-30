<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteRollbackJournal.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalHeader.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalPage.php';
require_once __DIR__ . '/../src/SQLiteSavepointStack.php';
require_once __DIR__ . '/../src/SQLiteVfsFileWriter.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointReplayPlan.php';

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/wp-content/database/wp-next117.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$local = static fn (string $root, string $path): string => $root . '/' . ltrim($path, '/');

$clean = [
    1 => $page('next117 smoke clean sqlite header'),
    2 => $page('next117 smoke clean wp_options root'),
    3 => $page('next117 smoke clean active_plugins'),
    4 => $page('next117 smoke clean transient'),
];
$dirtyDatabase = $page('next117 smoke dirty sqlite header')
    . $page('next117 smoke dirty wp_options root')
    . $page('next117 smoke dirty active_plugins failed')
    . $page('next117 smoke dirty transient failed');
$statementBefore = $page('next117 smoke statement before active_plugins');
$nextBefore = $page('next117 smoke retry before plugin option');

$journalHeader = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($clean), 0x20261117, 4, $sectorSize, $pageSize);
$journalBytes = str_pad($journalHeader, $sectorSize, "\0");
foreach ($clean as $pageNumber => $pageImage) {
    $journalBytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, 0x20261117));
}

$salt1 = 0x20260528;
$salt2 = 117;
$walHeaderPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 117, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($walHeaderPrefix, false);
$walBytes = $walHeaderPrefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [1, 0, 'next117 smoke retained schema frame'],
    [2, 4, 'next117 smoke retained wp_options root frame'],
    [3, 0, 'next117 smoke failed active_plugins frame'],
    [4, 4, 'next117 smoke failed transient commit'],
] as [$pageNumber, $commitPageCount, $label]) {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$root = sys_get_temp_dir() . '/port-libsqlite-application-next117-' . bin2hex(random_bytes(4));
$directory = dirname($local($root, $databasePath));
if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
    throw new RuntimeException('Unable to create Application next117 smoke directory');
}
file_put_contents($local($root, $databasePath), $dirtyDatabase);
file_put_contents($local($root, $databasePath . '-journal'), $journalBytes);
file_put_contents($local($root, $databasePath . '-wal'), $walBytes);

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('application-import-next117');
$savepoints->recordWalFrameWrite(1, 1);
$savepoints->recordWalFrameWrite(2, 2, true);
$savepoints->savepoint('plugin-batch-next117');
$savepoints->beginStatementJournal('insert-active-plugin-next117');
$savepoints->recordStatementPageImageWrite('insert-active-plugin-next117', 3, $statementBefore);
$savepoints->recordStatementWalFrameWrite('insert-active-plugin-next117', 3, 3);
$savepoints->recordStatementWalFrameWrite('insert-active-plugin-next117', 4, 4, true);

$writer = new SQLiteVfsFileWriter($root);
$result = $writer->applyWalHotJournalStatementRollback(
    $savepoints,
    'plugin-batch-next117',
    'insert-active-plugin-next117',
    'retry-plugin-option-next117',
    5,
    $nextBefore,
    $databasePath,
    [1, 2, 3, 4],
    [
        1 => $page('next117 smoke retained schema frame'),
        2 => $page('next117 smoke retained wp_options root frame'),
        3 => $page('next117 smoke failed active_plugins frame'),
        4 => $page('next117 smoke failed transient commit'),
    ],
    true
);

$databaseBytes = (string) file_get_contents($local($root, $databasePath));
$walPrefixBytes = (string) file_get_contents($local($root, $databasePath . '-wal'));
$summary = [
    'scenario' => 'application-wal-statement-hot-rollback-current-source-next117',
    'applicationUse' => 'Apply hot rollback-journal recovery plus current WAL statement-journal rollback through bounded native PHP VFS file handles for a copied wp_options import, preserving retained WAL frames, deleting the hot journal, and truncating failed statement WAL frames before retry.',
    'status' => $result['status'],
    'applied' => $result['applied'],
    'journalDeleted' => !is_file($local($root, $databasePath . '-journal')),
    'rollbackToFrame' => $result['recovery']['rollback_to_frame'],
    'walBytes' => strlen($walPrefixBytes),
    'databaseHasStatementBefore' => str_contains($databaseBytes, 'next117 smoke statement before active_plugins'),
    'databaseHasFailedStatement' => str_contains($databaseBytes, 'next117 smoke dirty active_plugins failed'),
    'dependencies' => $result['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    if ($summary['status'] !== 'applied' || !$summary['journalDeleted'] || !$summary['databaseHasStatementBefore'] || $summary['databaseHasFailedStatement']) {
        fwrite(STDERR, "application WAL statement hot rollback current-source next117 smoke failed\n");
        exit(1);
    }
    echo "application WAL statement hot rollback current-source next117 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
