<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLiteTriggerUpsertRecursiveViewCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$summary = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentYieldTicket(
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
        'source' => 'main@cookie235-current',
        'mapping' => ['name' => 'key_name', 'value' => 'key_value'],
    ],
    ['key_name'],
    [
        ['name' => 'app_settings_au_home', 'when' => 'base_url', 'target' => 'landing_page', 'value' => '{value}/landing_page'],
        ['name' => 'app_settings_au_rewrite', 'when' => 'landing_page', 'target' => 'route_rules', 'value' => 'flushed:{value}'],
    ],
    [
        'savepoint' => 'app_view_recursive_235',
        'current_upsert_source_next232' => 'app.current.upsert.source.235',
        'current_view_source_next232' => 'main@cookie235-current',
        'current_trigger_program_next232' => 'app.current.recursive.trigger.program.235',
        'current_yield_ticket_source_next235' => 'app.current.yield.ticket.source.235',
        'current_yield_resume_cursor_next235' => 'app.current.yield.cursor.235',
        'auto_ack_current_yield_tickets_next235' => true,
    ],
);

if (
    $summary['status_next235'] !== 'trigger-recursive-view-upsert-current-source-next235-yield-released'
    || $summary['current_yield_ticket_plan_next235']['decision'] !== 'publish-next-source-after-current-recursive-view-upsert-yields'
    || array_column($summary['visible_returning_rows_next235'], 'key_name') !== ['base_url', 'landing_page', 'route_rules', 'site_title', 'base_url', 'landing_page', 'route_rules', 'fresh_module']
    || $summary['held_next_yield_stream_next235'] !== []
) {
    fwrite(STDERR, "application-trigger-recursive-view-upsert-current-source-next235 self-test failed\n");
    exit(1);
}

echo "application-trigger-recursive-view-upsert-current-source-next235 self-test passed\n";
