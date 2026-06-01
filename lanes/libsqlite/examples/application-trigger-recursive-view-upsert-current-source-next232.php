<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLiteTriggerUpsertRecursiveViewCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$summary = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentUpsertConflictSeal(
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
        'source' => 'main@cookie232-current',
        'mapping' => ['name' => 'key_name', 'value' => 'key_value'],
    ],
    ['key_name'],
    [
        ['name' => 'app_settings_au_home', 'when' => 'base_url', 'target' => 'landing_page', 'value' => '{value}/landing_page'],
        ['name' => 'app_settings_au_rewrite', 'when' => 'landing_page', 'target' => 'route_rules', 'value' => 'flushed:{value}'],
    ],
    [
        'savepoint' => 'app_view_recursive_232',
        'current_upsert_source_next232' => 'wp.current.upsert.source.232',
        'current_view_source_next232' => 'main@cookie232-current',
        'current_trigger_program_next232' => 'wp.current.recursive.trigger.program.232',
        'auto_ack_current_upsert_conflict_seals_next232' => true,
    ],
);

if (
    $summary['status_next232'] !== 'trigger-recursive-view-upsert-current-source-next232-conflict-released'
    || $summary['current_upsert_conflict_plan_next232']['decision'] !== 'publish-next-source-after-current-recursive-upsert-conflicts'
    || array_column($summary['visible_returning_rows_next232'], 'key_name') !== ['base_url', 'landing_page', 'route_rules', 'site_title', 'base_url', 'landing_page', 'route_rules', 'fresh_module']
    || $summary['held_next_yield_stream_next232'] !== []
) {
    fwrite(STDERR, "application-trigger-recursive-view-upsert-current-source-next232 self-test failed\n");
    exit(1);
}

echo "application-trigger-recursive-view-upsert-current-source-next232 self-test passed\n";
