<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalSavepointCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerSavepointMasterJournalCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalHotRollbackCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournal.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalHeader.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalPage.php';
require_once __DIR__ . '/../src/SQLiteSavepointStack.php';

$pageSize = 512;
$sectorSize = 512;
$mainPath = '/srv/wp/database/main.sqlite';
$sitePath = '/srv/wp/database/site.sqlite';
$masterPath = '/srv/wp/database/main.sqlite-mj108';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$makeJournal = static function (array $pages, int $initialPageCount, int $nonce) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, $initialPageCount, $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wp-options-import');
$savepoints->savepoint('plugin-settings');
$savepoints->recordPageImageWrite(3, $page('wp plugin setting before retry after master recovery'));

$plan = SQLitePagerMasterJournalSavepointCurrentSourceNextPlan::currentSourceNext(
    $masterPath,
    $mainPath . "-journal\n" . $sitePath . "-journal\n",
    [
        [
            'database_path' => $mainPath,
            'current_database_bytes' => $page('dirty main schema after crashed plugin import') . $page('dirty active_plugins retry row') . $page('dirty plugin settings retry row'),
            'current_journal_bytes' => $makeJournal([
                1 => $page('clean main schema before crashed plugin import'),
                2 => $page('clean active_plugins before retry savepoint'),
                3 => $page('clean plugin settings before retry savepoint'),
            ], 3, 0x10800011),
        ],
        [
            'database_path' => $sitePath,
            'current_database_bytes' => $page('dirty attached site option after crashed import'),
            'current_journal_bytes' => $makeJournal([
                1 => $page('clean attached site option before crashed import'),
            ], 1, 0x10800012),
        ],
    ],
    $pageSize,
    $mainPath,
    'plugin-settings',
    $savepoints,
    [
        2 => $page('retry active_plugins after master journal recovery'),
        4 => $page('retry appended autoload option after recovery'),
    ]
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-savepoint-current-source-next108',
    'status' => $plan['status'],
    'masterRecovery' => $plan['retry_recovery']['master_recovery']['status'],
    'capturedPages' => $plan['captured_page_numbers'],
    'rollbackRestores' => $plan['rollback_preview']['restored_page_numbers'],
    'rollbackContainsRetryWrites' => $plan['rollback_preview']['contains_retry_writes'],
    'restoredPrefixes' => $plan['rollback_preview']['restored_prefixes'],
    'wordpressUse' => 'Copied WordPress option imports recover attached rollback journals through the master journal first, then seed active SAVEPOINT before-images from that recovered current source so a retry write can still ROLLBACK TO the savepoint without restoring stale crashed bytes.',
];

if (($argv[1] ?? '') === '--self-test') {
    if ($summary['status'] !== 'master_journal_savepoint_current_source_next') {
        throw new RuntimeException('Unexpected pager master-journal savepoint status');
    }
    if ($summary['rollbackContainsRetryWrites'] !== false) {
        throw new RuntimeException('Savepoint rollback preview retained retry writes');
    }
    fwrite(STDOUT, "wordpress-pager-master-journal-savepoint-current-source-next108 self-test passed\n");
    return;
}

fwrite(STDOUT, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
