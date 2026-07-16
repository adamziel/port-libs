<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePagerStatementJournalWalSavepointCurrentSourceNextPlan;

$pageSize = 512;
$databasePath = '/wp-content/database/wp-next112.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$current = [
    1 => $page('next112 current sqlite header'),
    2 => $page('next112 savepoint wp_options root current'),
    3 => $page('next112 failed active_plugins option wal frame'),
    4 => $page('next112 savepoint autoload index current'),
    5 => $page('next112 failed plugin index wal frame'),
    6 => $page('next112 current untouched comments page'),
];
$statementBefore = [
    3 => $page('next112 before failed active_plugins option'),
    5 => $page('next112 before failed plugin index'),
];

$plan = SQLitePagerStatementJournalWalSavepointCurrentSourceNextPlan::plan(
    $databasePath,
    implode('', $current),
    $pageSize,
    $databasePath . '-wal',
    10,
    'plugin-batch-next112',
    'insert-active-plugin-next112',
    'retry-active-plugin-next112',
    $current,
    [
        ['frame' => 11, 'page_number' => 2, 'image' => $current[2]],
        ['frame' => 12, 'page_number' => 4, 'image' => $current[4]],
    ],
    $statementBefore,
    [
        ['frame' => 13, 'page_number' => 3, 'image' => $current[3]],
        ['frame' => 14, 'page_number' => 5, 'image' => $current[5], 'commit_frame' => true],
    ],
    [
        2 => $current[2],
        3 => $statementBefore[3],
        5 => $statementBefore[5],
        7 => str_repeat("\0", $pageSize),
    ],
    [
        ['frame' => 13, 'page_number' => 2, 'image' => $page('next112 retry keeps savepoint root')],
        ['frame' => 14, 'page_number' => 3, 'image' => $page('next112 retry active_plugins option')],
        ['frame' => 15, 'page_number' => 5, 'image' => $page('next112 retry plugin index')],
        ['frame' => 16, 'page_number' => 7, 'image' => $page('next112 retry overflow leaf append'), 'commit_frame' => true],
    ],
    true
);

$summary = [
    'scenario' => 'application-pager-statement-journal-wal-savepoint-current-source-next112',
    'status' => $plan['status'],
    'walTruncateToFrame' => $plan['wal_truncate_to_frame'],
    'discardedStatementFrames' => $plan['discarded_statement_frame_numbers'],
    'retryFrames' => $plan['next_statement_wal_frame_numbers'],
    'statementRestoredPages' => $plan['statement_restored_page_numbers'],
    'finalPageThree' => $plan['final_prefixes'][3],
    'finalPageSeven' => $plan['final_prefixes'][7],
    'applicationUse' => 'During a copied wp_options WAL import, a failed statement can be rolled back from its statement journal while the outer savepoint WAL frames remain retained. The retry appends frames after the retained savepoint prefix instead of building on failed statement frames.',
];

if (
    $summary['status'] !== 'pager_statement_journal_wal_savepoint_current_source_next112'
    || $summary['walTruncateToFrame'] !== 12
    || $summary['discardedStatementFrames'] !== [13, 14]
    || $summary['retryFrames'] !== [13, 14, 15, 16]
    || $summary['statementRestoredPages'] !== [3, 5]
    || $summary['finalPageThree'] !== 'next112 retry active_plugins option'
    || $summary['finalPageSeven'] !== 'next112 retry overflow leaf append'
) {
    fwrite(STDERR, "application-pager-statement-journal-wal-savepoint-current-source-next112 self-test failed\n");
    exit(1);
}

fwrite(STDOUT, json_encode($summary, JSON_PRETTY_PRINT) . "\n");
