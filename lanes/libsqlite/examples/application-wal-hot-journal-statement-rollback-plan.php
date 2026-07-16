<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointReplayPlan;

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/wp-content/database/wp-statement-rollback.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$clean = [
    1 => $page('wp statement-rollback clean sqlite header'),
    2 => $page('wp statement-rollback clean wp_options root'),
    3 => $page('wp statement-rollback clean active_plugins'),
    4 => $page('wp statement-rollback clean transient'),
];
$dirtyDatabase = $page('wp statement-rollback dirty sqlite header')
    . $page('wp statement-rollback dirty wp_options root')
    . $page('wp statement-rollback dirty active_plugins')
    . $page('wp statement-rollback dirty transient');
$statementBefore = $page('wp statement-rollback statement before active_plugins insert');
$nextBefore = $page('wp statement-rollback retry before plugin option insert');

$nonce = 0x20260591;
$journalBytes = str_pad(
    SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($clean), $nonce, 4, $sectorSize, $pageSize),
    $sectorSize,
    "\0"
);
foreach ($clean as $pageNumber => $pageImage) {
    $journalBytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
}

$salt1 = 0x20260528;
$salt2 = 91;
$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 91, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [1, 0, 'wp statement-rollback retained schema frame'],
    [2, 4, 'wp statement-rollback retained wp_options frame'],
    [3, 0, 'wp statement-rollback failed active_plugins draft'],
    [4, 4, 'wp statement-rollback failed transient commit'],
] as $frame) {
    [$pageNumber, $commitPageCount, $label] = $frame;
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('application-import');
$savepoints->recordWalFrameWrite(1, 1);
$savepoints->recordWalFrameWrite(2, 2, true);
$savepoints->savepoint('plugin-batch');
$savepoints->beginStatementJournal('insert-active-plugin');
$savepoints->recordStatementPageImageWrite('insert-active-plugin', 3, $statementBefore);
$savepoints->recordStatementWalFrameWrite('insert-active-plugin', 3, 3);
$savepoints->recordStatementWalFrameWrite('insert-active-plugin', 4, 4, true);

$plan = SQLiteWalHotJournalSavepointReplayPlan::statementHotJournalRollbackPlan(
    SQLiteRollbackJournal::parse($journalBytes, true),
    $dirtyDatabase,
    $journalBytes,
    $savepoints,
    'plugin-batch',
    'insert-active-plugin',
    'retry-plugin-option',
    5,
    $nextBefore,
    SQLiteWal::parse($walBytes, $pageSize, true),
    $walBytes,
    $databasePath,
    [1, 2, 3, 4],
    [
        1 => $page('wp statement-rollback retained schema frame'),
        2 => $page('wp statement-rollback retained wp_options frame'),
        3 => $page('wp statement-rollback failed active_plugins draft'),
        4 => $page('wp statement-rollback failed transient commit'),
    ],
    true
);

$summary = [
    'status' => $plan['status'],
    'hotRecovered' => $plan['hot_recovered'],
    'currentStatement' => $plan['current_statement'],
    'nextStatement' => $plan['next_statement'],
    'rollbackToFrame' => $plan['rollback_to_frame'],
    'nextWalFrame' => $plan['next_wal_frame_index'],
    'restoredPages' => $plan['rollback_restored_page_numbers'],
    'failedActivePluginPresent' => str_contains($plan['statement_database_bytes'], 'failed active_plugins draft'),
    'statementBeforeImagePresent' => str_contains($plan['statement_database_bytes'], 'statement before active_plugins insert'),
    'walBytesLength' => $plan['statement_wal_bytes_length'],
    'dependencies' => $plan['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    if ($summary['status'] !== 'hot_journal_wal_statement_current_source_recovered_statement-rollback') {
        fwrite(STDERR, "unexpected statement current-source status\n");
        exit(1);
    }
    if ($summary['failedActivePluginPresent'] || !$summary['statementBeforeImagePresent'] || $summary['nextWalFrame'] !== 3) {
        fwrite(STDERR, "statement current-source recovery failed\n");
        exit(1);
    }
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
