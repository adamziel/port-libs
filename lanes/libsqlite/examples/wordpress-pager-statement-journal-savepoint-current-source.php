<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;

$pageSize = 128;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$cleanTransient = $page('clean transient before failed insert');
$cleanIndex = $page('clean option index before failed insert');
$dirtyTransient = $page('current failed transient insert page');
$dirtyIndex = $page('current failed index insert page');
$databaseBytes = $page('current sqlite header')
    . $page('current wp_options root')
    . $page('current active_plugins')
    . $dirtyTransient
    . $dirtyIndex;

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-import');
$stack->recordPageImageWrite(1, $page('clean sqlite header'));
$stack->recordWalFrameWrite(1, 1);
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('plugin-batch');
$stack->recordPageImageWrite(3, $page('clean active_plugins'));
$stack->recordWalFrameWrite(3, 3);
$stack->beginStatementJournal('insert-transient');
$stack->recordStatementPageImageWrite('insert-transient', 4, $cleanTransient);
$stack->recordStatementPageImageWrite('insert-transient', 5, $cleanIndex);
$stack->recordStatementWalFrameWrite('insert-transient', 4, 4);
$stack->recordStatementWalFrameWrite('insert-transient', 5, 5, true);

$plan = $stack->rollbackStatementCurrentSourceAndBeginStatementJournal(
    'insert-transient',
    'retry-transient',
    $databaseBytes,
    [4 => $dirtyTransient, 5 => $dirtyIndex],
    6,
    $page('current retry transient before image'),
    $pageSize,
    true
);

$summary = [
    'scenario' => 'wordpress-pager-statement-journal-savepoint-current-source',
    'currentSourceVerified' => $plan['current_source_verified'],
    'sourcePages' => $plan['current_source_page_numbers'],
    'restoredPages' => $plan['rollback_restored_page_numbers'],
    'nextStatement' => $plan['next_statement'],
    'nextWalFrame' => $plan['next_wal_frame_index'],
    'nextJournalPages' => $plan['statement_journals_after_next'][0]['page_numbers'],
    'wordpressUse' => 'Roll back a failed wp_options statement only when the copied database image still matches the current statement-journal source pages, then open the retry statement journal without ext/sqlite.',
];

if (!$summary['currentSourceVerified'] || $summary['restoredPages'] !== [4, 5] || $summary['nextWalFrame'] !== 4) {
    fwrite(STDERR, "wordpress-pager-statement-journal-savepoint-current-source self-test failed\n");
    exit(1);
}

fwrite(STDOUT, json_encode($summary, JSON_PRETTY_PRINT) . "\n");
