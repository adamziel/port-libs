<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePagerStatementJournalSavepointCurrentSourceNextPlan;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$current = [
    1 => $page('next102 current sqlite header'),
    2 => $page('next102 savepoint wp_options root before failure'),
    3 => $page('next102 failed statement active_plugins option'),
    4 => $page('next102 savepoint autoload index before failure'),
    5 => $page('next102 failed statement plugin index'),
    6 => $page('next102 current untouched comments page'),
];

$plan = SQLitePagerStatementJournalSavepointCurrentSourceNextPlan::plan(
    '/wp-content/database/wp-next102.sqlite',
    implode('', $current),
    $pageSize,
    'plugin-batch-next102',
    'insert-active-plugin-next102',
    'retry-active-plugin-next102',
    $current,
    [
        2 => $page('next102 before savepoint wp_options root'),
        4 => $page('next102 before savepoint autoload index'),
    ],
    [
        3 => $page('next102 before failed active_plugins option'),
        5 => $page('next102 before failed plugin index'),
    ],
    [
        3 => $current[3],
        5 => $current[5],
    ],
    [
        2 => $current[2],
        3 => $page('next102 before failed active_plugins option'),
        5 => $page('next102 before failed plugin index'),
        7 => str_repeat("\0", $pageSize),
    ],
    [
        2 => $page('next102 retry keeps savepoint root'),
        3 => $page('next102 retry active_plugins option'),
        5 => $page('next102 retry plugin index'),
        7 => $page('next102 retry overflow leaf append'),
    ],
    true
);

$summary = [
    'scenario' => 'application-pager-statement-journal-savepoint-current-source-next102',
    'status' => $plan['status'],
    'currentSourceVerified' => $plan['current_source_verified'],
    'statementRestoredPages' => $plan['statement_restored_page_numbers'],
    'nextStatementPages' => $plan['next_statement_page_numbers'],
    'releaseMergedPages' => $plan['release_merged_page_numbers'],
    'finalPageThree' => $plan['final_prefixes'][3],
    'finalPageSeven' => $plan['final_prefixes'][7],
    'applicationUse' => 'During a copied wp_options plugin import, a failed statement can be rolled back from its statement journal while the surrounding savepoint remains current, then the retry statement starts from the restored source before RELEASE merges the successful pages.',
];

if (
    $summary['status'] !== 'pager_statement_journal_savepoint_current_source_next102'
    || !$summary['currentSourceVerified']
    || $summary['statementRestoredPages'] !== [3, 5]
    || $summary['nextStatementPages'] !== [2, 3, 5, 7]
    || $summary['releaseMergedPages'] !== [2, 3, 4, 5, 7]
    || $summary['finalPageThree'] !== 'next102 retry active_plugins option'
    || $summary['finalPageSeven'] !== 'next102 retry overflow leaf append'
) {
    fwrite(STDERR, "application-pager-statement-journal-savepoint-current-source-next102 self-test failed\n");
    exit(1);
}

fwrite(STDOUT, json_encode($summary, JSON_PRETTY_PRINT) . "\n");
