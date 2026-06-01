<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLiteTriggerUpsertRecursiveViewCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$summary = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentUpsertReceipt(
    [
        ['key_name' => 'base_url', 'key_value' => 'https://old.test'],
        ['key_name' => 'landing_page', 'key_value' => 'https://old-landing_page.test'],
        ['key_name' => 'route_rules', 'key_value' => 'old-rules'],
    ],
    [
        ['name' => 'base_url', 'value' => 'https://current.test'],
        ['name' => 'site_title', 'value' => 'Current Blog'],
    ],
    [
        ['name' => 'base_url', 'value' => 'https://next.test'],
        ['name' => 'fresh_module', 'value' => 'enabled'],
    ],
    [
        'name' => 'app_setting_import_view',
        'source' => 'main@cookie238-current',
        'mapping' => ['name' => 'key_name', 'value' => 'key_value'],
    ],
    ['key_name'],
    [
        ['name' => 'app_settings_au_home', 'when' => 'base_url', 'target' => 'landing_page', 'value' => '{value}/landing_page'],
        ['name' => 'app_settings_au_rewrite', 'when' => 'landing_page', 'target' => 'route_rules', 'value' => 'flushed:{value}'],
    ],
    [
        'savepoint' => 'app_view_recursive_238',
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
        || array_column($summary['visible_returning_rows_next238'], 'key_name') !== ['base_url', 'landing_page', 'route_rules', 'site_title', 'base_url', 'landing_page', 'route_rules', 'fresh_module']
        || $summary['held_next_resume_stream_next238'] !== []
    ) {
        fwrite(STDERR, "application-trigger-recursive-view-upsert-current-source-next238 self-test failed\n");
        exit(1);
    }

    echo "application-trigger-recursive-view-upsert-current-source-next238 self-test passed\n";
    exit(0);
}

echo json_encode([
    'scenario' => 'application-trigger-recursive-view-upsert-current-source-next238',
    'status' => $summary['status_next238'],
    'decision' => $summary['current_resume_receipt_plan_next238']['decision'],
    'visibleReturning' => array_column($summary['visible_returning_rows_next238'], 'key_name'),
    'requiredReceipts' => count($summary['required_current_resume_receipts_next238']),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
