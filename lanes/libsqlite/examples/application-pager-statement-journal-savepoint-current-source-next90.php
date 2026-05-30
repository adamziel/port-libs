<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;

$pageSize = 96;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('current header')
    . $page('current wp_options root')
    . $page('current plugin option')
    . $page('current plugin index')
    . $page('current retry slot');

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-import');
$stack->recordPageImageWrite(1, $page('before header'));
$stack->recordWalFrameWrite(1, 1);
$stack->recordPageImageWrite(2, $page('before root'));
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('plugin-batch');
$stack->recordPageImageWrite(3, $page('before plugin option'));
$stack->recordWalFrameWrite(3, 3);
$stack->beginStatementJournal('plugin-option-update');
$stack->recordStatementPageImageWrite('plugin-option-update', 3, $page('stmt before plugin option'));
$stack->recordStatementWalFrameWrite('plugin-option-update', 4, 3, true);
$stack->savepoint('single-option');
$stack->recordPageImageWrite(4, $page('before plugin index'));
$stack->recordWalFrameWrite(5, 4);
$stack->beginStatementJournal('single-option-update');
$stack->recordStatementPageImageWrite('single-option-update', 4, $page('stmt before plugin index'));
$stack->recordStatementWalFrameWrite('single-option-update', 6, 4, true);

$plan = $stack->releaseCurrentSourceAndBeginStatementJournal(
    'single-option',
    'retry-plugin-option',
    $databaseBytes,
    [4 => $page('current plugin index')],
    5,
    $page('before retry slot'),
    $pageSize,
    true
);

$summary = [
    'scenario' => 'application-pager-statement-journal-savepoint-current-source-next90',
    'currentSourceVerified' => $plan['current_source_verified'],
    'releasedSavepoint' => $plan['released_savepoint'],
    'discardedStatementJournals' => $plan['discarded_statement_journals'],
    'namesAfterRelease' => $plan['names_after_release'],
    'nextStatement' => $plan['next_statement'],
    'nextStatementSavepoint' => $plan['statement_journals_after_next'][1]['savepoint'] ?? null,
    'nextWalFrame' => $plan['next_wal_frame_index'],
    'applicationUse' => 'Release a successful inner wp_options savepoint only after the current database page image matches the statement-journal source, then open the next retry statement under the merged parent savepoint without ext/sqlite.',
];

if (
    !$summary['currentSourceVerified']
    || $summary['namesAfterRelease'] !== ['wp-import', 'plugin-batch']
    || $summary['discardedStatementJournals'] !== ['single-option-update']
    || $summary['nextStatementSavepoint'] !== 'plugin-batch'
    || $summary['nextWalFrame'] !== 7
) {
    fwrite(STDERR, "application-pager-statement-journal-savepoint-current-source-next90 self-test failed\n");
    exit(1);
}

fwrite(STDOUT, json_encode($summary, JSON_PRETTY_PRINT) . "\n");
