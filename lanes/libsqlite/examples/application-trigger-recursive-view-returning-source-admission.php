<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeRecursiveViewReturningSourceAdmission(
    [
        ['key_name' => 'base_url', 'key_value' => 'https://old.example', 'load_policy' => 'yes'],
        ['key_name' => 'landing_url', 'key_value' => 'https://old-landing_url.example', 'load_policy' => 'yes'],
        ['key_name' => 'routing_rules', 'key_value' => 'old-rules', 'load_policy' => 'no'],
    ],
    [
        ['name' => 'base_url', 'value' => 'https://current.example', 'load_policy_flag' => 'yes'],
        ['name' => 'app_summary', 'value' => 'Current Tagline', 'load_policy_flag' => 'yes'],
    ],
    [
        ['name' => 'base_url', 'value' => 'https://next.example', 'load_policy_flag' => 'yes'],
        ['name' => 'fresh_module', 'value' => 'enabled', 'load_policy_flag' => 'no'],
    ],
    [
        'name' => 'app_setting_import_view',
        'current_source' => 'main@trigger-source-admission-current',
        'next_source' => 'main@trigger-source-admission-next',
        'mapping' => ['name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    ],
    ['key_name'],
    [
        ['name' => 'app_settings_ai_landing_url', 'when' => 'base_url', 'target' => 'landing_url', 'value' => '{value}/landing_url'],
        ['name' => 'app_settings_au_rewrite', 'when' => 'landing_url', 'target' => 'routing_rules', 'value' => 'flushed:{value}'],
    ],
    [
        ['expr' => 'new.key_name', 'as' => 'name'],
        ['expr' => 'new.key_value', 'as' => 'value'],
        ['expr' => 'depth', 'as' => 'trigger_depth'],
        ['expr' => 'source', 'as' => 'view_source'],
    ],
    ['savepoint' => 'app_recursive_view_returning_source_admission']
);

$result = [
    'scenario' => 'application-trigger-recursive-view-returning-source-admission',
    'applicationUse' => 'Preview copied app_settings imports through an INSTEAD OF view trigger where recursive trigger side effects yield RETURNING rows from the current source, roll back before the next source is admitted, and keep the final option rows aligned with the visible source without requiring ext/sqlite.',
    'status' => $summary['status'],
    'visibleSource' => $summary['visible_source'],
    'admittedReturningNames' => array_column(array_column($summary['returning_rows'], 'returning'), 'name'),
    'suppressedReturningNames' => array_column(array_column($summary['suppressed_returning_rows'], 'returning'), 'name'),
    'finalSettingKeys' => array_column($summary['after_savepoint'], 'key_name'),
    'dependencyClosure' => $summary['dependency_closure'],
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    assert($result['status'] === 'trigger-recursive-view-returning-current-source-retained');
    assert($result['visibleSource'] === 'main@trigger-source-admission-next');
    assert($result['admittedReturningNames'] === ['base_url', 'landing_url', 'routing_rules', 'fresh_module']);
    assert($result['suppressedReturningNames'] === ['base_url', 'landing_url', 'routing_rules', 'app_summary']);
    assert($result['finalSettingKeys'] === ['base_url', 'landing_url', 'routing_rules', 'fresh_module']);
    assert($result['dependencyClosure'] === 'reuses-native-recursive-trigger-returning-view-current-source-plans');
    echo "application-trigger-recursive-view-returning-source-admission self-test passed\n";
}

return $result;
