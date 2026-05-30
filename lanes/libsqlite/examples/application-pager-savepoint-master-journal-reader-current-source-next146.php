<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerSavepointMasterJournalReaderCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLitePagerSavepointMasterJournalReaderCurrentSourceNextPlan.php';

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next146.sqlite';
$masterPath = '/srv/wp-content/database/wp-next146.sqlite-mj';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$before = [
    1 => $page('wp next146 stale schema before master recovery'),
    2 => $page('wp next146 stale option root before master recovery'),
    3 => $page('wp next146 stale plugin option before master recovery'),
];
$recovered = [
    1 => $page('wp next146 recovered schema from master journal'),
    2 => $page('wp next146 recovered option root from master journal'),
    3 => $page('wp next146 recovered plugin option from master journal'),
];
$savepoint = [
    3 => $page('wp next146 savepoint before plugin option current source'),
];
$recoveredSourceId = 'master-savepoint-reader:' . hash('sha256', $masterPath . '|' . $databasePath . '-journal');
$savepointSourceId = $recoveredSourceId . ':rollback-to:plugin-import';

$plan = SQLitePagerSavepointMasterJournalReaderCurrentSourceNextPlan::plan(
    $databasePath,
    $masterPath,
    $databasePath . "-journal\n",
    implode('', $before),
    $pageSize,
    $recovered,
    'plugin-import',
    $savepoint,
    [
        ['label' => 'wp-crashed-reader', 'page_number' => 2, 'source_id' => 'pre-master-source', 'epoch' => 2, 'pinned' => true],
        ['label' => 'wp-reopened-reader', 'page_number' => 3, 'source_id' => $savepointSourceId, 'epoch' => 4, 'pinned' => false],
    ],
    [1, 2, 3],
    [3 => $page('wp next146 plugin option rewrite after reader reopen')],
    'pre-master-source',
    2,
);

$summary = [
    'scenario' => 'application-pager-savepoint-master-journal-reader-current-source-next146',
    'status' => $plan['status'],
    'blockedReaders' => $plan['blocked_reader_labels'],
    'admittedReaders' => $plan['admitted_reader_labels'],
    'requiresReaderReopen' => $plan['requires_reader_reopen'],
    'nextWriteBeforePrefix' => $plan['next_writes'][0]['before_prefix'],
    'applicationUse' => 'A copied wp_options import can reject a pinned reader that predates master-journal recovery and admit the reopened reader after ROLLBACK TO restores the plugin option before-image.',
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal recovery and savepoint current-source primitives',
];

if ($summary['status'] !== 'pager-savepoint-master-journal-reader-current-source-next146'
    || $summary['blockedReaders'] !== ['wp-crashed-reader']
    || $summary['admittedReaders'] !== ['wp-reopened-reader']
    || $summary['requiresReaderReopen'] !== true
    || $summary['nextWriteBeforePrefix'] !== 'wp next146 savepoint before plugin option current source'
) {
    fwrite(STDERR, "application-pager-savepoint-master-journal-reader-current-source-next146 self-test failed\n");
    exit(1);
}

echo "application-pager-savepoint-master-journal-reader-current-source-next146 self-test passed\n";
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
