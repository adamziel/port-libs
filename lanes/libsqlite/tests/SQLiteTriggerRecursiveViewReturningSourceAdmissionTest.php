<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$initialRows = [
    ['key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
    ['key_name' => 'landing_url', 'key_value' => 'https://old-landing_url.test', 'load_policy' => 'yes'],
    ['key_name' => 'routing_rules', 'key_value' => 'old-rules', 'load_policy' => 'no'],
];
$viewDefinition = [
    'name' => 'app_setting_import_view',
    'current_source' => 'main@trigger-source-admission-current',
    'next_source' => 'main@trigger-source-admission-next',
    'mapping' => ['name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
];
$recursiveTriggers = [
    ['name' => 'app_settings_ai_landing_url', 'when' => 'base_url', 'target' => 'landing_url', 'value' => '{value}/landing_url'],
    ['name' => 'app_settings_au_rewrite', 'when' => 'landing_url', 'target' => 'routing_rules', 'value' => 'flushed:{value}'],
];
$returningProjection = [
    ['expr' => 'new.key_name', 'as' => 'name'],
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'old_or_null.key_value', 'as' => 'old_value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'trigger_depth'],
    ['expr' => 'source', 'as' => 'view_source'],
    ['expr' => 'trigger', 'as' => 'trigger_name'],
    static fn (array $new, ?array $old, array $incoming, string $event, int $ordinal, int $depth, string $source, ?string $trigger): string => $source . ':' . $event . ':' . $ordinal . ':' . $depth . ':' . ($trigger ?? 'view') . ':' . $new['key_name'],
];
$currentViewRows = [
    ['name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes'],
    ['name' => 'app_summary', 'value' => 'Current Tagline', 'load_policy_flag' => 'yes'],
];
$nextViewRows = [
    ['name' => 'base_url', 'value' => 'https://next.test', 'load_policy_flag' => 'yes'],
    ['name' => 'fresh_module', 'value' => 'enabled', 'load_policy_flag' => 'no'],
];

$sourceAdmissionPlan = static fn (array $options = [], ?array $current = null, ?array $next = null, ?array $returning = null, ?array $view = null, ?array $triggers = null): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeRecursiveViewReturningSourceAdmission(
    $initialRows,
    $current ?? $currentViewRows,
    $next ?? $nextViewRows,
    $view ?? $viewDefinition,
    ['key_name'],
    $triggers ?? $recursiveTriggers,
    $returning ?? $returningProjection,
    $options + ['savepoint' => 'app_recursive_view_returning_source_admission'],
);

$retainedSource = static fn (): array => $sourceAdmissionPlan();
$releasedSource = static fn (): array => $sourceAdmissionPlan(['release_current' => true]);
$rolledSourceAdmission = static fn (): array => $sourceAdmissionPlan(['release_current' => true, 'rollback_next' => true]);
$nonRecursiveSource = static fn (): array => $sourceAdmissionPlan(['recursive_triggers' => false]);

$sourceAdmissionCases = [
    'retained status' => [static fn (): mixed => $retainedSource()['status'], 'trigger-recursive-view-returning-current-source-retained'],
    'savepoint retained' => [static fn (): mixed => $retainedSource()['savepoint'], 'app_recursive_view_returning_source_admission'],
    'view retained' => [static fn (): mixed => $retainedSource()['view'], 'app_setting_import_view'],
    'current source retained' => [static fn (): mixed => $retainedSource()['current_source'], 'main@trigger-source-admission-current'],
    'next source retained' => [static fn (): mixed => $retainedSource()['next_source'], 'main@trigger-source-admission-next'],
    'visible source is next when current rolls back' => [static fn (): mixed => $retainedSource()['visible_source'], 'main@trigger-source-admission-next'],
    'current source not admitted by default' => [static fn (): mixed => $retainedSource()['current_source_admitted'], false],
    'next source admitted by default' => [static fn (): mixed => $retainedSource()['next_source_admitted'], true],
    'next input is saved current source' => [static fn (): mixed => $retainedSource()['next_input'], 'saved-current-source'],
    'recursive triggers enabled' => [static fn (): mixed => $retainedSource()['recursive_triggers'], true],
    'changes include next source only' => [static fn (): mixed => $retainedSource()['changes'], 4],
    'current changes counted diagnostically' => [static fn (): mixed => $retainedSource()['current_changes'], 4],
    'next changes counted' => [static fn (): mixed => $retainedSource()['next_changes'], 4],
    'before rows retained' => [static fn (): mixed => array_column($retainedSource()['before_rows'], 'key_name'), ['base_url', 'landing_url', 'routing_rules']],
    'current recursive rows are attempted' => [static fn (): mixed => array_column($retainedSource()['current_rows'], 'key_name'), ['base_url', 'landing_url', 'routing_rules', 'app_summary']],
    'current recursive landing_url value attempted' => [static fn (): mixed => $retainedSource()['current_rows'][1]['key_value'], 'https://current.test/landing_url'],
    'current recursive rewrite value attempted' => [static fn (): mixed => $retainedSource()['current_rows'][2]['key_value'], 'flushed:https://current.test/landing_url'],
    'after savepoint uses next source rows' => [static fn (): mixed => array_column($retainedSource()['after_savepoint'], 'key_name'), ['base_url', 'landing_url', 'routing_rules', 'fresh_module']],
    'after savepoint base_url is next' => [static fn (): mixed => $retainedSource()['after_savepoint'][0]['key_value'], 'https://next.test'],
    'after savepoint landing_url follows next' => [static fn (): mixed => $retainedSource()['after_savepoint'][1]['key_value'], 'https://next.test/landing_url'],
    'after savepoint rewrite follows next recursive landing_url' => [static fn (): mixed => $retainedSource()['after_savepoint'][2]['key_value'], 'flushed:https://next.test/landing_url'],
    'returning names are admitted next rows only' => [static fn (): mixed => array_column(array_column($retainedSource()['returning_rows'], 'returning'), 'name'), ['base_url', 'landing_url', 'routing_rules', 'fresh_module']],
    'returning depths include recursive side effects' => [static fn (): mixed => array_column(array_column($retainedSource()['returning_rows'], 'returning'), 'trigger_depth'), [0, 1, 2, 0]],
    'returning sources are next' => [static fn (): mixed => array_unique(array_column(array_column($retainedSource()['returning_rows'], 'returning'), 'view_source')), ['main@trigger-source-admission-next']],
    'returning trigger names preserve view then recursive triggers' => [static fn (): mixed => array_column(array_column($retainedSource()['returning_rows'], 'returning'), 'trigger_name'), [null, 'app_settings_ai_landing_url', 'app_settings_au_rewrite', null]],
    'returning old value for next base_url uses saved baseline' => [static fn (): mixed => $retainedSource()['returning_rows'][0]['returning']['old_value'], 'https://old.test'],
    'returning old value for inserted next module is null' => [static fn (): mixed => $retainedSource()['returning_rows'][3]['returning']['old_value'], null],
    'callable returning trace records next recursion' => [static fn (): mixed => array_column(array_column($retainedSource()['returning_rows'], 'returning'), 'expr7'), ['main@trigger-source-admission-next:update:0:0:view:base_url', 'main@trigger-source-admission-next:update:0:1:app_settings_ai_landing_url:landing_url', 'main@trigger-source-admission-next:update:0:2:app_settings_au_rewrite:routing_rules', 'main@trigger-source-admission-next:insert:1:0:view:fresh_module']],
    'suppressed returning names are current rows' => [static fn (): mixed => array_column(array_column($retainedSource()['suppressed_returning_rows'], 'returning'), 'name'), ['base_url', 'landing_url', 'routing_rules', 'app_summary']],
    'discarded returning count is current rows' => [static fn (): mixed => $retainedSource()['discarded_returning_count'], 4],
    'current stream rolled back' => [static fn (): mixed => array_unique(array_column($retainedSource()['current_yield_stream'], 'rolled_back_after_yield')), [true]],
    'next stream admitted' => [static fn (): mixed => array_unique(array_column($retainedSource()['next_yield_stream'], 'admitted')), [true]],
    'attempted source phases' => [static fn (): mixed => array_column($retainedSource()['attempted_source_stream'], 'phase'), ['current', 'current', 'current', 'current', 'next', 'next', 'next', 'next']],
    'attempted source admission flags' => [static fn (): mixed => array_column($retainedSource()['attempted_source_stream'], 'admitted'), [false, false, false, false, true, true, true, true]],
    'current trigger effects target settings' => [static fn (): mixed => array_column($retainedSource()['current_trigger_effects'], 'target_setting'), ['landing_url', 'routing_rules']],
    'next trigger effects source settings' => [static fn (): mixed => array_column($retainedSource()['next_trigger_effects'], 'source_key'), ['base_url', 'landing_url']],
    'boundary retained before next source' => [static fn (): mixed => $retainedSource()['yield_boundary'], 'recursive-view-returning-current-source-yield-before-next-source'],
    'dependency closure marker' => [static fn (): mixed => $retainedSource()['dependency_closure'], 'reuses-native-recursive-trigger-returning-view-current-source-plans'],
    'source-admission dependency marker' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-admission', $retainedSource()['dependencies'], true), true],
    'returning dependency marker' => [static fn (): mixed => in_array('sqlite-returning-current-source-before-next-source', $retainedSource()['dependencies'], true), true],

    'released status' => [static fn (): mixed => $releasedSource()['status'], 'trigger-recursive-view-returning-current-next-source-admitted'],
    'released visible source is next' => [static fn (): mixed => $releasedSource()['visible_source'], 'main@trigger-source-admission-next'],
    'released current source admitted' => [static fn (): mixed => $releasedSource()['current_source_admitted'], true],
    'released next input current output' => [static fn (): mixed => $releasedSource()['next_input'], 'current-phase-output'],
    'released changes include both phases' => [static fn (): mixed => $releasedSource()['changes'], 8],
    'released final rows preserve current tagline' => [static fn (): mixed => array_column($releasedSource()['after_savepoint'], 'key_name'), ['base_url', 'landing_url', 'routing_rules', 'app_summary', 'fresh_module']],
    'released current returning names first' => [static fn (): mixed => array_slice(array_column(array_column($releasedSource()['returning_rows'], 'returning'), 'name'), 0, 4), ['base_url', 'landing_url', 'routing_rules', 'app_summary']],
    'released next returning names second' => [static fn (): mixed => array_slice(array_column(array_column($releasedSource()['returning_rows'], 'returning'), 'name'), 4), ['base_url', 'landing_url', 'routing_rules', 'fresh_module']],
    'released has no suppressed rows' => [static fn (): mixed => $releasedSource()['suppressed_returning_rows'], []],
    'released boundary' => [static fn (): mixed => $releasedSource()['yield_boundary'], 'recursive-view-returning-current-next-sources-admitted'],

    'rolled next status' => [static fn (): mixed => $rolledSourceAdmission()['status'], 'trigger-recursive-view-returning-current-source-admitted-next-rolled-back'],
    'rolled next visible source returns current' => [static fn (): mixed => $rolledSourceAdmission()['visible_source'], 'main@trigger-source-admission-current'],
    'rolled next admitted flags' => [static fn (): mixed => [$rolledSourceAdmission()['current_source_admitted'], $rolledSourceAdmission()['next_source_admitted']], [true, false]],
    'rolled next returning rows are current only' => [static fn (): mixed => array_column(array_column($rolledSourceAdmission()['returning_rows'], 'returning'), 'name'), ['base_url', 'landing_url', 'routing_rules', 'app_summary']],
    'rolled next suppressed rows are next only' => [static fn (): mixed => array_column(array_column($rolledSourceAdmission()['suppressed_returning_rows'], 'returning'), 'name'), ['base_url', 'landing_url', 'routing_rules', 'fresh_module']],
    'rolled next final rows are current output' => [static fn (): mixed => array_column($rolledSourceAdmission()['after_savepoint'], 'key_name'), ['base_url', 'landing_url', 'routing_rules', 'app_summary']],
    'rolled next discarded count' => [static fn (): mixed => $rolledSourceAdmission()['discarded_returning_count'], 4],

    'non recursive changes only direct view rows' => [static fn (): mixed => $nonRecursiveSource()['current_changes'], 2],
    'non recursive current names' => [static fn (): mixed => array_column($nonRecursiveSource()['current_rows'], 'key_name'), ['base_url', 'landing_url', 'routing_rules', 'app_summary']],
    'non recursive landing_url remains old' => [static fn (): mixed => $nonRecursiveSource()['current_rows'][1]['key_value'], 'https://old-landing_url.test'],
    'non recursive effects show suppression' => [static fn (): mixed => array_column($nonRecursiveSource()['current_trigger_effects'], 'result'), ['recursive-suppressed']],
    'non recursive admitted returning direct rows only' => [static fn (): mixed => array_column(array_column($nonRecursiveSource()['returning_rows'], 'returning'), 'name'), ['base_url', 'fresh_module']],

    'wildcard returning exposes row payload' => [static fn (): mixed => array_column(array_column($sourceAdmissionPlan([], null, null, ['*'])['returning_rows'], 'returning'), 'row')[0]['load_policy'], 'yes'],
    'custom savepoint accepted' => [static fn (): mixed => $sourceAdmissionPlan(['savepoint' => 'app_custom_recursive_source_admission'])['savepoint'], 'app_custom_recursive_source_admission'],
    'max depth accepted' => [static fn (): mixed => $sourceAdmissionPlan(['max_depth' => 3])['current_yield_stream'][2]['depth'], 2],
    'empty unique columns throws' => [static fn (): mixed => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeRecursiveViewReturningSourceAdmission($initialRows, [], [], $viewDefinition, [], $recursiveTriggers, $returningProjection), InvalidArgumentException::class],
    'empty returning throws' => [static fn (): mixed => $sourceAdmissionPlan([], null, null, []), InvalidArgumentException::class],
    'bad savepoint throws' => [static fn (): mixed => $sourceAdmissionPlan(['savepoint' => 'bad savepoint']), InvalidArgumentException::class],
    'bad max depth throws' => [static fn (): mixed => $sourceAdmissionPlan(['max_depth' => -1]), InvalidArgumentException::class],
    'bad view name throws' => [static fn (): mixed => $sourceAdmissionPlan([], null, null, null, ['name' => 'bad-name', 'current_source' => 'ok', 'next_source' => 'ok2', 'mapping' => ['name' => 'key_name']]), InvalidArgumentException::class],
    'bad current source throws' => [static fn (): mixed => $sourceAdmissionPlan([], null, null, null, ['name' => 'v', 'current_source' => 'bad source', 'next_source' => 'ok2', 'mapping' => ['name' => 'key_name']]), InvalidArgumentException::class],
    'bad mapping throws' => [static fn (): mixed => $sourceAdmissionPlan([], null, null, null, ['name' => 'v', 'current_source' => 'ok', 'next_source' => 'ok2', 'mapping' => []]), InvalidArgumentException::class],
    'missing view column throws' => [static fn (): mixed => $sourceAdmissionPlan([], [['name' => 'base_url']]), InvalidArgumentException::class],
    'bad trigger name throws' => [static fn (): mixed => $sourceAdmissionPlan([], null, null, null, null, [['name' => 'bad-name', 'when' => 'base_url', 'target' => 'landing_url', 'value' => 'x']]), InvalidArgumentException::class],
    'incomplete trigger throws' => [static fn (): mixed => $sourceAdmissionPlan([], null, null, null, null, [['name' => 'ok', 'when' => 'base_url', 'target' => 'landing_url']]), InvalidArgumentException::class],
    'old expression on insert throws' => [static fn (): mixed => $sourceAdmissionPlan([], [['name' => 'new_insert', 'value' => 'x', 'load_policy_flag' => 'yes']], [], [['expr' => 'old.key_value', 'as' => 'old_value']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($sourceAdmissionCases as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source admission ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
