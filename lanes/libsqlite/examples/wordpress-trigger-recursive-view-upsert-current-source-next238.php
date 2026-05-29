<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLiteTriggerUpsertRecursiveViewCurrentSourceNext148Plan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$summary = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeNext238(
    [
        ['option_name' => 'siteurl', 'option_value' => 'https://old.test'],
        ['option_name' => 'home', 'option_value' => 'https://old-home.test'],
        ['option_name' => 'rewrite_rules', 'option_value' => 'old-rules'],
    ],
    [
        ['name' => 'siteurl', 'value' => 'https://current.test'],
        ['name' => 'blogname', 'value' => 'Current Blog'],
    ],
    [
        ['name' => 'siteurl', 'value' => 'https://next.test'],
        ['name' => 'fresh_plugin', 'value' => 'enabled'],
    ],
    [
        'name' => 'wp_option_import_view',
        'source' => 'main@cookie238-current',
        'mapping' => ['name' => 'option_name', 'value' => 'option_value'],
    ],
    ['option_name'],
    [
        ['name' => 'wp_options_au_home', 'when' => 'siteurl', 'target' => 'home', 'value' => '{value}/home'],
        ['name' => 'wp_options_au_rewrite', 'when' => 'home', 'target' => 'rewrite_rules', 'value' => 'flushed:{value}'],
    ],
    [
        'savepoint' => 'wp_view_recursive_238',
        'current_upsert_source_next232' => 'wp.current.upsert.source.238',
        'current_view_source_next232' => 'main@cookie238-current',
        'current_trigger_program_next232' => 'wp.current.recursive.trigger.program.238',
        'current_yield_ticket_source_next235' => 'wp.current.yield.ticket.source.238',
        'current_yield_resume_cursor_next235' => 'wp.current.yield.cursor.238',
        'current_resume_source_next238' => 'wp.current.resume.source.238',
        'current_resume_cursor_next238' => 'wp.current.resume.cursor.238',
        'current_resume_epoch_next238' => 'wp.current.resume.epoch.238',
        'auto_ack_current_resume_receipts_next238' => true,
    ],
);

if (($argv[1] ?? '') === '--self-test') {
    if (
        $summary['status_next238'] !== 'trigger-recursive-view-upsert-current-source-next238-resume-released'
        || $summary['current_resume_receipt_plan_next238']['decision'] !== 'publish-next-source-after-current-recursive-view-upsert-resume'
        || array_column($summary['visible_returning_rows_next238'], 'option_name') !== ['siteurl', 'home', 'rewrite_rules', 'blogname', 'siteurl', 'home', 'rewrite_rules', 'fresh_plugin']
        || $summary['held_next_resume_stream_next238'] !== []
    ) {
        fwrite(STDERR, "wordpress-trigger-recursive-view-upsert-current-source-next238 self-test failed\n");
        exit(1);
    }

    echo "wordpress-trigger-recursive-view-upsert-current-source-next238 self-test passed\n";
    exit(0);
}

echo json_encode([
    'scenario' => 'wordpress-trigger-recursive-view-upsert-current-source-next238',
    'status' => $summary['status_next238'],
    'decision' => $summary['current_resume_receipt_plan_next238']['decision'],
    'visibleReturning' => array_column($summary['visible_returning_rows_next238'], 'option_name'),
    'requiredReceipts' => count($summary['required_current_resume_receipts_next238']),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
