<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePagerHotJournalWalRecoveryPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/wp-content/database/wp.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$dirty = [
    1 => $page('next93 dirty schema'),
    2 => $page('next93 dirty options root'),
    3 => $page('next93 dirty active plugins'),
    4 => $page('next93 dirty transient statement'),
    5 => $page('next93 dirty autoload index statement'),
];
$clean = [
    1 => $page('next93 clean schema'),
    2 => $page('next93 clean options root'),
    3 => $page('next93 clean active plugins'),
    4 => $page('next93 clean transient before statement'),
    5 => $page('next93 clean autoload index before statement'),
];

$nonce = 0x93000093;
$journalBytes = str_pad(SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($clean), $nonce, count($clean), $sectorSize, $pageSize), $sectorSize, "\0");
foreach ($clean as $pageNumber => $image) {
    $journalBytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
}
$journal = SQLiteRollbackJournal::parse($journalBytes, true);

$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 93, 0x93939393, 0x39393939);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [1, 0, 'next93 wal schema retained'],
    [2, 3, 'next93 wal options retained commit'],
    [3, 0, 'next93 wal active plugins retained draft'],
    [4, 0, 'next93 wal dirty transient statement'],
    [5, 5, 'next93 wal dirty autoload index statement'],
] as [$pageNumber, $commit, $label]) {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commit, 0x93939393, 0x39393939);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-import-next93');
$stack->recordPageImageWrite(1, $clean[1]);
$stack->recordWalFrameWrite(1, 1);
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('plugin-batch-next93');
$stack->recordPageImageWrite(3, $clean[3]);
$stack->recordWalFrameWrite(3, 3);
$stack->beginStatementJournal('insert-transient-next93');
$stack->recordStatementPageImageWrite('insert-transient-next93', 4, $clean[4]);
$stack->recordStatementPageImageWrite('insert-transient-next93', 5, $clean[5]);
$stack->recordStatementWalFrameWrite('insert-transient-next93', 4, 4);
$stack->recordStatementWalFrameWrite('insert-transient-next93', 5, 5, true);

$plan = SQLitePagerHotJournalWalRecoveryPlan::statementWalRecoveryCurrentSourceNext(
    $journal,
    implode('', $dirty),
    $journalBytes,
    $walBytes,
    $databasePath,
    $stack,
    'insert-transient-next93',
    'retry-transient-next93',
    [
        4 => $page('next93 wal dirty transient statement'),
        5 => $page('next93 wal dirty autoload index statement'),
    ],
    6,
    $page('next93 retry transient before next statement'),
    $pageSize,
    true,
    false,
    true,
    true
);

$summary = [
    'status' => $plan['status'],
    'reason' => $plan['reason'],
    'hotRecovered' => $plan['hot_recovered'],
    'currentSources' => $plan['current_reader_sources'],
    'currentFrames' => $plan['current_reader_frame_indexes'],
    'rolledBackPages' => $plan['rollback_restored_page_numbers'],
    'nextStatement' => $plan['statement_journals_after_next'][0],
    'dependencies' => $plan['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['status'] === 'hot_journal_statement_current_source_next');
    assert($summary['hotRecovered'] === true);
    assert($summary['currentSources'] === ['wal', 'wal']);
    assert($summary['rolledBackPages'] === [4, 5]);
    assert($summary['nextStatement']['name'] === 'retry-transient-next93');
    echo "application-hot-journal-savepoint-statement-current-source-next93 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
