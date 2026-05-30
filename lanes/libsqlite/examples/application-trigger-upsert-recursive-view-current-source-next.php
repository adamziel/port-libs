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
        'source' => 'main@cookie148-current',
        'mapping' => ['name' => 'option_name', 'value' => 'option_value'],
    ],
    ['option_name'],
    [
        ['name' => 'wp_options_au_home', 'when' => 'siteurl', 'target' => 'home', 'value' => '{value}/home'],
        ['name' => 'wp_options_au_rewrite', 'when' => 'home', 'target' => 'rewrite_rules', 'value' => 'flushed:{value}'],
    ],
);

assert($plan['status'] === 'trigger-upsert-recursive-view-current-source-retained-next148');
assert($plan['current_changes'] === 4);
assert($plan['changes'] === 0);
assert(array_column($plan['after_savepoint'], 'option_value') === ['https://old.test', 'https://old-home.test', 'old-rules']);

echo json_encode([
    'status' => $plan['status'],
    'currentChanges' => $plan['current_changes'],
    'visibleSource' => $plan['visible_source'],
    'currentNames' => array_column($plan['current_rows'], 'option_name'),
    'afterSavepointValues' => array_column($plan['after_savepoint'], 'option_value'),
], JSON_PRETTY_PRINT) . PHP_EOL;
