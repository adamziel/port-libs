<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePagerMasterJournalCacheCurrentNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;

$pageSize = 512;
$sectorSize = 512;
$root = '/srv/www/wp-content/database';
$mainDb = $root . '/wp.sqlite';
$metaDb = $root . '/wp_meta.sqlite';
$cacheDb = $root . '/wp_cache.sqlite';
$masterPath = $root . '/.ht.sqlite-mj77';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$journalBytes = static function (string $label, int $nonce) use ($page, $sectorSize, $pageSize): string {
    $image = $page($label);
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', 1, $nonce, 1, $sectorSize, $pageSize);

    return str_pad($header, $sectorSize, "\0")
        . pack('N', 1)
        . $image
        . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
};

$mainJournal = $mainDb . '-journal';
$metaJournal = $metaDb . '-journal';
$cacheJournal = $cacheDb . '-journal';

$plan = SQLitePagerMasterJournalCacheCurrentNextPlan::currentNext(
    $masterPath,
    $mainJournal . "\n" . $metaJournal . "\n",
    $metaJournal . "\n" . $cacheJournal . "\n",
    [
        [
            'database_path' => $mainDb,
            'current_journal_bytes' => $journalBytes('current wp_options journal before recovery', 0x77020001),
            'next_journal_bytes' => null,
        ],
        [
            'database_path' => $metaDb,
            'current_journal_bytes' => $journalBytes('metadata journal before recovery', 0x77020002),
            'next_journal_bytes' => $journalBytes('metadata journal before recovery', 0x77020002),
            'next_reserved_lock' => true,
        ],
        [
            'database_path' => $cacheDb,
            'current_journal_bytes' => null,
            'next_journal_bytes' => $journalBytes('new object-cache journal after crash', 0x77020003),
        ],
    ]
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'master_journal_cache_refreshed_current_next');
    assert($plan['cache_invalidated'] === true);
    assert($plan['journal_rechecks'][$mainJournal]['cache_action'] === 'clear_cached_hot_journal');
    assert($plan['journal_rechecks'][$cacheJournal]['cache_action'] === 'candidate_new_hot_journal');
    echo "application-pager-master-journal-cache-current-next77 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'removedMembers' => $plan['member_delta']['removed'],
    'addedMembers' => $plan['member_delta']['added'],
    'mainAction' => $plan['journal_rechecks'][$mainJournal]['cache_action'],
    'cacheAction' => $plan['journal_rechecks'][$cacheJournal]['cache_action'],
], JSON_PRETTY_PRINT) . "\n";
