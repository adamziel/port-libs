<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/TestRunner.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'PortLibs\\LibSqlite\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    require_once dirname(__DIR__) . '/src/' . substr($class, strlen($prefix)) . '.php';
});

use PortLibs\LibSqlite\SQLiteTriggerUpsertRecursiveViewCurrentSourceNextPlan;

$plan = SQLiteTriggerUpsertRecursiveViewCurrentSourceNextPlan::execute(
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
        'source' => 'main@cookie148-current',
        'mapping' => ['name' => 'key_name', 'value' => 'key_value'],
    ],
    ['key_name'],
    [
        ['name' => 'app_settings_au_home', 'when' => 'base_url', 'target' => 'landing_page', 'value' => '{value}/landing_page'],
        ['name' => 'app_settings_au_rewrite', 'when' => 'landing_page', 'target' => 'route_rules', 'value' => 'flushed:{value}'],
    ],
);

assert($plan['status'] === 'trigger-upsert-recursive-view-current-source-retained-next148');
assert($plan['current_changes'] === 4);
assert($plan['changes'] === 0);
assert(array_column($plan['after_savepoint'], 'key_value') === ['https://old.test', 'https://old-landing_page.test', 'old-rules']);

echo json_encode([
    'status' => $plan['status'],
    'currentChanges' => $plan['current_changes'],
    'visibleSource' => $plan['visible_source'],
    'currentNames' => array_column($plan['current_rows'], 'key_name'),
    'afterSavepointValues' => array_column($plan['after_savepoint'], 'key_value'),
], JSON_PRETTY_PRINT) . PHP_EOL;
