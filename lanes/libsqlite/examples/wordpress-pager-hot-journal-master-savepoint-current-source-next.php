<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerHotJournalMasterSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/wp/wp-content/database/wp-options.sqlite';
$journalPath = $databasePath . '-journal';
$masterPath = '/srv/wp/wp-content/database/wp-import-master';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cleanPages = [
    1 => $page('wp next134 clean header from master journal'),
    2 => $page('wp next134 clean active_plugins from master journal'),
    3 => $page('wp next134 clean autoload from master journal'),
];
$databaseBytes = $page('wp next134 dirty header') . $page('wp next134 dirty active_plugins') . $page('wp next134 dirty autoload');

$nonce = 0x134134;
$journalBytes = str_pad(
    SQLiteRollbackJournalHeader::MAGIC . pack('N*', 3, $nonce, 3, $sectorSize, $pageSize),
    $sectorSize,
    "\0"
);
foreach ($cleanPages as $pageNumber => $image) {
    $journalBytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
}
$journal = SQLiteRollbackJournal::parse($journalBytes, true);

$plan = SQLitePagerHotJournalMasterSavepointCurrentSourceNextPlan::plan(
    $databasePath,
    $masterPath,
    "/srv/wp/wp-content/database/stale.sqlite-journal\n",
    $journalPath . "\n",
    $journalPath . "\n",
    $journal,
    $databaseBytes,
    $journalBytes,
    'plugin_batch',
    [
        1 => $page('wp next134 dirty header'),
        2 => $page('wp next134 dirty active_plugins'),
        3 => $page('wp next134 dirty autoload'),
    ],
    [
        2 => $page('wp next134 savepoint active_plugins retry'),
        4 => $page('wp next134 savepoint transient retry'),
    ],
    [1, 2, 3, 4]
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'pager_hot_journal_master_savepoint_current_source_next134');
    assert($plan['cached_stale_rejected'] === true);
    assert($plan['hot_recovered'] === true);
    assert($plan['read_labels'][1] === 'wp next134 clean active_plugins from master journal');
    assert($plan['after_write_labels'][2] === 'wp next134 savepoint active_plugins retry');
    echo "wordpress-pager-hot-journal-master-savepoint-current-source-next134 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'cached_stale_rejected' => $plan['cached_stale_rejected'],
    'hot_recovered' => $plan['hot_recovered'],
    'read_sources' => $plan['read_sources'],
], JSON_PRETTY_PRINT) . "\n";
