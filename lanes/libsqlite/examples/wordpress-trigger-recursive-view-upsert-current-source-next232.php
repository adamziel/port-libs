<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLiteTriggerUpsertRecursiveViewCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$summary = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentUpsertConflictSeal(
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
        'source' => 'main@cookie232-current',
        'mapping' => ['name' => 'option_name', 'value' => 'option_value'],
    ],
    ['option_name'],
    [
        ['name' => 'wp_options_au_home', 'when' => 'siteurl', 'target' => 'home', 'value' => '{value}/home'],
        ['name' => 'wp_options_au_rewrite', 'when' => 'home', 'target' => 'rewrite_rules', 'value' => 'flushed:{value}'],
    ],
    [
        'savepoint' => 'wp_view_recursive_232',
        'current_upsert_source_next232' => 'wp.current.upsert.source.232',
        'current_view_source_next232' => 'main@cookie232-current',
        'current_trigger_program_next232' => 'wp.current.recursive.trigger.program.232',
        'auto_ack_current_upsert_conflict_seals_next232' => true,
    ],
);

if (
    $summary['status_next232'] !== 'trigger-recursive-view-upsert-current-source-next232-conflict-released'
    || $summary['current_upsert_conflict_plan_next232']['decision'] !== 'publish-next-source-after-current-recursive-upsert-conflicts'
    || array_column($summary['visible_returning_rows_next232'], 'option_name') !== ['siteurl', 'home', 'rewrite_rules', 'blogname', 'siteurl', 'home', 'rewrite_rules', 'fresh_plugin']
    || $summary['held_next_yield_stream_next232'] !== []
) {
    fwrite(STDERR, "wordpress-trigger-recursive-view-upsert-current-source-next232 self-test failed\n");
    exit(1);
}

echo "wordpress-trigger-recursive-view-upsert-current-source-next232 self-test passed\n";
