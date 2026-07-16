<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePagerStatementJournalSavepointMasterCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePagerStatementJournalSavepointMasterCurrentSourceNextPlan;

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-options-next123.sqlite';
$masterPath = '/srv/wp-content/database/wp-options-next123.sqlite-mj';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');

$stalePages = [
    1 => $page('wp next123 stale header'),
    2 => $page('wp next123 stale options root after crash'),
    3 => $page('wp next123 stale active_plugins statement page'),
    4 => $page('wp next123 stale autoload index'),
];

$recoveredPages = [
    1 => $page('wp next123 recovered header from master journal'),
    2 => $page('wp next123 recovered options root current source'),
    4 => $page('wp next123 recovered autoload index current source'),
];

$plan = SQLitePagerStatementJournalSavepointMasterCurrentSourceNextPlan::plan(
    $databasePath,
    $masterPath,
    $databasePath . "-journal\n",
    implode('', $stalePages),
    $pageSize,
    'plugin-import-next123',
    'insert-active-plugin-next123',
    'retry-active-plugin-next123',
    $recoveredPages,
    [
        2 => $recoveredPages[2],
        4 => $recoveredPages[4],
    ],
    [
        3 => $page('wp next123 before failed active_plugins insert'),
    ],
    [
        3 => $page('wp next123 failed active_plugins insert'),
    ],
    [
        2 => $recoveredPages[2],
        3 => $page('wp next123 before failed active_plugins insert'),
        5 => str_repeat("\0", $pageSize),
    ],
    [
        2 => $page('wp next123 retry options root'),
        3 => $page('wp next123 retry active_plugins insert'),
        5 => $page('wp next123 retry plugin metadata overflow'),
    ],
    true
);

if (($argv[1] ?? '') === '--self-test') {
    $checks = [
        $plan['status'] === 'pager_statement_journal_savepoint_master_current_source_next123',
        $plan['current_source_verified'] === true,
        $plan['release_merged_page_numbers'] === [1, 2, 3, 4, 5],
        str_contains($plan['statement_rollback_database_bytes'], 'wp next123 before failed active_plugins insert'),
        !str_contains($plan['statement_rollback_database_bytes'], 'wp next123 failed active_plugins insert'),
        str_contains($plan['final_database_bytes'], 'wp next123 retry active_plugins insert'),
        str_contains($plan['master_recovered_database_bytes'], 'wp next123 recovered options root current source'),
    ];

    foreach ($checks as $passed) {
        if (!$passed) {
            fwrite(STDERR, "application-pager-statement-savepoint-master-current-source-next123 self-test failed\n");
            exit(1);
        }
    }

    echo "application-pager-statement-savepoint-master-current-source-next123 self-test passed\n";
    exit(0);
}

echo json_encode([
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'master_recovered_pages' => $plan['master_recovered_page_numbers'],
    'statement_restored_pages' => $plan['statement_restored_page_numbers'],
    'next_statement_pages' => $plan['next_statement_page_numbers'],
    'release_merged_pages' => $plan['release_merged_page_numbers'],
    'operation_count' => count($plan['operations']),
], JSON_PRETTY_PRINT) . PHP_EOL;
