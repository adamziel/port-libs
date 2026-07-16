<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerSavepointCurrentNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$events = [
    ['op' => 'begin', 'name' => 'wp_import'],
    ['op' => 'page_write', 'page' => 2],
    ['op' => 'savepoint', 'name' => 'plugin_batch'],
    ['op' => 'page_image_write', 'page' => 4, 'image' => str_repeat('P', 64)],
    ['op' => 'page_write', 'page' => 5],
    ['op' => 'savepoint', 'name' => 'single_option'],
    ['op' => 'page_image_write', 'page' => 6, 'image' => str_repeat('R', 64)],
    ['op' => 'page_write', 'page' => 8],
];

$plan = SQLitePagerSavepointCurrentNextPlan::rollbackJournalLifecycle($events, [
    'op' => 'rollback_to',
    'name' => 'plugin_batch',
    'journal_mode' => 'delete',
    'page_size' => 1024,
    'database_page_count' => 16,
]);

echo json_encode([
    'scenario' => 'application-pager-savepoint-rollback-journal-lifecycle',
    'applicationUse' => 'Plan rollback-journal current/next pager lifecycle for copied wp_options imports where a failed plugin batch rolls back to an outer SAVEPOINT while preserving the transaction for later rows.',
    'status' => $plan['status'],
    'currentSavepoints' => $plan['current']['names'],
    'nextSavepoints' => $plan['next']['names'],
    'restorePages' => $plan['journal_lifecycle']['restore_page_numbers'],
    'statementJournalPages' => $plan['journal_lifecycle']['statement_journal_pages'],
    'journalBytesBefore' => $plan['journal_lifecycle']['journal_bytes_before'],
    'journalBytesAfter' => $plan['journal_lifecycle']['journal_bytes_after'],
    'finalDisposition' => $plan['journal_lifecycle']['final_disposition'],
    'syncSequence' => $plan['journal_lifecycle']['sync_sequence'],
    'dependencies' => $plan['journal_lifecycle']['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
