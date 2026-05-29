<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePagerJournalSavepointHotRollbackCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/wp-content/database/wp-next118.sqlite';
$masterPath = '/wp-content/database/wp-next118.sqlite-mj';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$clean = [
    1 => $page('wp next118 clean schema before failed import'),
    2 => $page('wp next118 clean wp_options root before retry'),
    3 => $page('wp next118 clean active_plugins before retry'),
];
$dirtyDatabase = $page('wp next118 dirty schema after crash')
    . $page('wp next118 dirty wp_options root after crash')
    . $page('wp next118 dirty active_plugins after crash');
$retryWrites = [
    2 => $page('wp next118 retry wp_options root inside savepoint'),
    3 => $page('wp next118 retry active_plugins inside savepoint'),
];

$nonce = 0x2026118;
$journalBytes = str_pad(
    SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($clean), $nonce, 3, $sectorSize, $pageSize),
    $sectorSize,
    "\0"
);
foreach ($clean as $pageNumber => $image) {
    $journalBytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
}

$plan = SQLitePagerJournalSavepointHotRollbackCurrentSourceNextPlan::currentSourceNext(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    $pageSize,
    'wordpress-option-retry',
    [
        2 => $clean[2],
        3 => $clean[3],
    ],
    $retryWrites,
    $masterPath,
    $databasePath . "-journal\n"
);

$summary = [
    'status' => $plan['status'],
    'hotRecovered' => $plan['hot_recovered'],
    'currentSourceVerified' => $plan['current_source_verified'],
    'savepoint' => $plan['savepoint'],
    'retryPages' => $plan['retry_page_numbers'],
    'rollbackMatchesRecovered' => $plan['images_match_after_rollback'],
    'dirtyActivePluginPresent' => str_contains($plan['rollback_database_bytes'], 'dirty active_plugins after crash'),
    'retryActivePluginPresent' => str_contains($plan['rollback_database_bytes'], 'retry active_plugins inside savepoint'),
    'cleanActivePluginPresent' => str_contains($plan['rollback_database_bytes'], 'clean active_plugins before retry'),
    'dependencies' => $plan['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    if ($summary['status'] !== 'pager_journal_savepoint_hot_rollback_current_source_next118') {
        fwrite(STDERR, "unexpected pager journal savepoint hot rollback status\n");
        exit(1);
    }
    if (!$summary['hotRecovered'] || !$summary['currentSourceVerified'] || !$summary['rollbackMatchesRecovered']) {
        fwrite(STDERR, "pager journal savepoint hot rollback did not preserve recovered current source\n");
        exit(1);
    }
    if ($summary['dirtyActivePluginPresent'] || $summary['retryActivePluginPresent'] || !$summary['cleanActivePluginPresent']) {
        fwrite(STDERR, "savepoint rollback did not restore clean active_plugins page\n");
        exit(1);
    }
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
