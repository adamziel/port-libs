<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePagerSavepointHotJournalMasterCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePagerSavepointHotJournalMasterCurrentSourceNextPlan;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databasePath = '/srv/wp/database/wp.sqlite';
$journalPath = $databasePath . '-journal';
$masterPath = $databasePath . '-mj142';

$plan = SQLitePagerSavepointHotJournalMasterCurrentSourceNextPlan::plan(
    $pageSize,
    $databasePath,
    $journalPath,
    $masterPath,
    $journalPath . "\n/srv/wp/database/site.sqlite-journal\n",
    $page('wp dirty schema page after crashed plugin activation') .
        $page('wp dirty active_plugins option after crashed plugin activation') .
        $page('wp dirty plugin setting row after crashed plugin activation'),
    'plugin-activation-retry',
    [
        1 => $page('wp clean schema page from hot journal'),
        2 => $page('wp clean active_plugins option from hot journal'),
    ],
    [
        2 => $page('wp retry active_plugins option inside savepoint'),
        4 => $page('wp retry autoload index page inside savepoint'),
    ],
    [1, 2, 3, 4]
);

if ($plan['status'] !== 'pager_savepoint_hot_journal_master_current_source_next142') {
    fwrite(STDERR, "unexpected status\n");
    exit(1);
}
if (($plan['captured_sources'][2] ?? null) !== 'hot-journal-master-current-source') {
    fwrite(STDERR, "savepoint did not capture the hot-journal recovered page\n");
    exit(1);
}
if (str_contains($plan['payloads'][$databasePath . '#pager-savepoint-hot-journal-master-current-source-rollback-next142'], 'retry active_plugins')) {
    fwrite(STDERR, "rollback payload retained retry active_plugins bytes\n");
    exit(1);
}
if (!str_contains($plan['payloads'][$databasePath . '#pager-savepoint-hot-journal-master-current-source-rollback-next142'], 'clean active_plugins')) {
    fwrite(STDERR, "rollback payload lost hot-journal active_plugins bytes\n");
    exit(1);
}

echo json_encode([
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint']['name'],
    'captured' => $plan['captured_sources'],
    'release_reads' => array_column($plan['release_reads'], 'prefix', 'page_number'),
], JSON_PRETTY_PRINT) . "\n";
