<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityRecoveryCurrentNextPlan;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$headerPage = static function (int $pageCount, int $largestRootPage = 3) use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $largestRootPage), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};
$database = static function (array $rowIds) use ($headerPage, $pageSize): string {
    $pointerMap = substr_replace(str_repeat("\0", $pageSize), chr(SQLitePointerMapEntry::ROOT_PAGE) . pack('N', 0), 0, 5);
    $leaf = SQLiteTableLeafPage::assemble(array_map(
        static fn (int $rowId): string => SQLiteTableLeafCell::encode($rowId, "option-{$rowId}"),
        $rowIds,
    ), $pageSize);

    return $headerPage(3) . $pointerMap . $leaf;
};

$dirtyDatabase = $database([1, 90, 12]);
$recoveredDatabase = $database([1, 12, 90]);
$plan = SQLitePragmaIntegrityRecoveryCurrentNextPlan::compare(
    $dirtyDatabase,
    $recoveredDatabase,
    [],
    [],
    'PRAGMA integrity_check',
    [
        ['op' => 'write', 'path' => '/srv/www/wp-content/database/.ht.sqlite', 'reason' => 'apply_recovered_wp_options_leaf_page'],
        ['op' => 'sync', 'path' => '/srv/www/wp-content/database/.ht.sqlite', 'reason' => 'sync_database_before_import_resume'],
    ],
);

echo json_encode([
    'scenario' => 'application-pragma-integrity-recovery-current-next76',
    'applicationUse' => 'Gate a copied wp_options recovery handoff by comparing PRAGMA integrity_check diagnostics before and after the recovered database image. Recovery may resume only when no findings persist or appear in the next image.',
    'status' => $plan['status'],
    'mustBlockCommit' => $plan['must_block_commit'],
    'currentTotal' => $plan['current']['total'],
    'nextTotal' => $plan['next']['total'],
    'resolvedMessages' => array_column($plan['resolved'], 'message'),
    'operationReasons' => array_column($plan['recovery_operations'], 'reason'),
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
