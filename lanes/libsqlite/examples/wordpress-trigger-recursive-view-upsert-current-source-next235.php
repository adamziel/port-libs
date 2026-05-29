<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLiteTriggerUpsertRecursiveViewCurrentSourceNext148Plan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$summary = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeNext235(
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
        'source' => 'main@cookie235-current',
        'mapping' => ['name' => 'option_name', 'value' => 'option_value'],
    ],
    ['option_name'],
    [
        ['name' => 'wp_options_au_home', 'when' => 'siteurl', 'target' => 'home', 'value' => '{value}/home'],
        ['name' => 'wp_options_au_rewrite', 'when' => 'home', 'target' => 'rewrite_rules', 'value' => 'flushed:{value}'],
    ],
    [
        'savepoint' => 'wp_view_recursive_235',
        'current_upsert_source_next232' => 'wp.current.upsert.source.235',
        'current_view_source_next232' => 'main@cookie235-current',
        'current_trigger_program_next232' => 'wp.current.recursive.trigger.program.235',
        'current_yield_ticket_source_next235' => 'wp.current.yield.ticket.source.235',
        'current_yield_resume_cursor_next235' => 'wp.current.yield.cursor.235',
        'auto_ack_current_yield_tickets_next235' => true,
    ],
);

if (
    $summary['status_next235'] !== 'trigger-recursive-view-upsert-current-source-next235-yield-released'
    || $summary['current_yield_ticket_plan_next235']['decision'] !== 'publish-next-source-after-current-recursive-view-upsert-yields'
    || array_column($summary['visible_returning_rows_next235'], 'option_name') !== ['siteurl', 'home', 'rewrite_rules', 'blogname', 'siteurl', 'home', 'rewrite_rules', 'fresh_plugin']
    || $summary['held_next_yield_stream_next235'] !== []
) {
    fwrite(STDERR, "wordpress-trigger-recursive-view-upsert-current-source-next235 self-test failed\n");
    exit(1);
}

echo "wordpress-trigger-recursive-view-upsert-current-source-next235 self-test passed\n";
