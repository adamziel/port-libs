<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerRollbackJournalCurrentPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$sectorSize = 512;
$nonce = 0x20260529;
$databasePath = '/srv/www/wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$currentPages = [
    1 => $page('wp_options schema current before plugin copy'),
    2 => $page('wp_options active_plugins current before plugin copy'),
    3 => $page('wp_options autoload index current before plugin copy'),
];
$dirtyPages = [
    2 => $page('wp_options active_plugins after plugin copy'),
    3 => $page('wp_options autoload index after plugin copy'),
];
$databaseBytes = implode('', $currentPages);

$journalBytes = static function (array $pages) use ($sectorSize, $pageSize, $nonce): string {
    $bytes = str_pad(
        SQLiteRollbackJournalHeader::MAGIC . pack('NNNNN', count($pages), $nonce, 3, $sectorSize, $pageSize),
        $sectorSize,
        "\0"
    );
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};

$admitted = SQLitePagerRollbackJournalCurrentPlan::admitCurrentJournal(
    $databasePath,
    $databaseBytes,
    $journalBytes([2 => $currentPages[2], 3 => $currentPages[3]]),
    $dirtyPages,
    $pageSize
);

$blocked = SQLitePagerRollbackJournalCurrentPlan::admitCurrentJournal(
    $databasePath,
    $databaseBytes,
    $journalBytes([2 => $page('stale active_plugins copy'), 3 => $currentPages[3]]),
    $dirtyPages,
    $pageSize
);

$summary = [
    'scenario' => 'application-pager-rollback-journal-current',
    'applicationUse' => 'A copied Application database admits dirty wp_options pages only when the rollback journal contains page images from the current database file, preventing a stale journal from publishing plugin import changes.',
    'status' => $admitted['status'],
    'blockedStatus' => $blocked['status'],
    'admittedPages' => $admitted['admitted_pages'],
    'blockedReasons' => $blocked['blocked_reasons'],
    'stalePageReasons' => $blocked['rejected_pages'][2],
    'nextContainsImportedPluginState' => str_contains($admitted['next_database_bytes'], 'active_plugins after plugin copy'),
    'blockedPreservesCurrentDatabase' => $blocked['current_database_bytes'] === $blocked['next_database_bytes'],
    'dependencies' => $admitted['dependencies'],
    'dependencyClosure' => 'no new support component needed; this reuses lane-local rollback journal parsing, checksum validation, and pager current-page image fencing',
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if (in_array('--self-test', $argv, true)) {
    if (
        $summary['status'] !== 'pager_rollback_journal_current_admitted'
        || $summary['blockedStatus'] !== 'pager_rollback_journal_current_blocked'
        || $summary['admittedPages'] !== [2, 3]
        || $summary['blockedReasons'] !== ['some_dirty_pages_lack_current_rollback_images']
        || $summary['stalePageReasons'] !== ['rollback_image_not_from_current_database_page']
        || $summary['nextContainsImportedPluginState'] !== true
        || $summary['blockedPreservesCurrentDatabase'] !== true
    ) {
        fwrite(STDERR, "application-pager-rollback-journal-current self-test failed\n");
        exit(1);
    }

    echo "application-pager-rollback-journal-current self-test passed\n";
}
