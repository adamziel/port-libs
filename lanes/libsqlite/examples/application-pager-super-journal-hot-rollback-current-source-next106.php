<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteRollbackJournalHeader.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalPage.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournal.php';
require_once __DIR__ . '/../src/SQLitePagerSuperJournalHotRollbackCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePagerSuperJournalHotRollbackCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;

$pageSize = 512;
$sectorSize = 512;
$mainPath = '/wp-content/database/main.sqlite';
$sitePath = '/wp-content/database/site.sqlite';
$superPath = '/wp-content/database/wp-super-next106';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$journal = static function (array $pages, int $nonce) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, count($pages), $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};

$mainJournal = $journal([
    1 => $page('clean main schema before plugin activation'),
    2 => $page('clean main active_plugins before plugin activation'),
], 0x10610001);
$siteJournal = $journal([
    1 => $page('clean site schema before network option import'),
    2 => $page('clean site upload_path before network option import'),
], 0x10610002);

$plan = SQLitePagerSuperJournalHotRollbackCurrentSourceNextPlan::currentSourceNext(
    $superPath,
    $mainPath . "-journal\n" . $sitePath . "-journal\n",
    [
        [
            'database_path' => $mainPath,
            'current_database_bytes' => $page('dirty main schema after crashed plugin activation') . $page('dirty main active_plugins after crashed plugin activation') . $page('dirty main tail page after crash'),
            'current_journal_bytes' => $mainJournal,
            'stale_database_bytes' => $page('stale main snapshot ignored') . $page('stale active_plugins ignored'),
        ],
        [
            'database_path' => $sitePath,
            'current_database_bytes' => $page('dirty site schema after network option import') . $page('dirty site upload_path after network option import'),
            'current_journal_bytes' => $siteJournal,
        ],
    ],
    $pageSize
);

$summary = [
    'scenario' => 'application_pager_super_journal_hot_rollback_current_source_next106',
    'status' => $plan['status'],
    'recoveredDatabases' => $plan['recovered_database_count'],
    'blockedDatabases' => $plan['blocked_database_count'],
    'staleCandidatesIgnored' => $plan['stale_candidate_count'],
    'superJournalMembers' => $plan['super_journal_members'],
    'journalActions' => $plan['journal_actions'],
    'operations' => array_column($plan['operations'], 'reason'),
    'mainNextPrefix' => $plan['hot_journals'][$mainPath . '-journal']['next_database_prefix'],
    'applicationUse' => 'Recover attached copied Application SQLite databases after a crashed multi-database plugin import by replaying only current-source hot rollback journals named by the super-journal, ignoring stale snapshots, and deleting the super-journal only after every named participant clears.',
    'dependencyClosure' => 'No new support component needed; this reuses existing rollback-journal parsing and native VFS write-operation planning.',
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    assert($summary['status'] === 'super_journal_current_source_hot_rollback_complete');
    assert($summary['recoveredDatabases'] === 2);
    assert($summary['staleCandidatesIgnored'] === 1);
    assert(in_array('delete_super_journal_after_current_source_hot_rollback', $summary['operations'], true));
    echo "application-pager-super-journal-hot-rollback-current-source-next106 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
