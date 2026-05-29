<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$initialRows = [
    ['option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_name' => 'home', 'option_value' => 'https://old-home.test', 'autoload' => 'yes'],
    ['option_name' => 'rewrite_rules', 'option_value' => 'old-rules', 'autoload' => 'no'],
];
$viewDefinition = [
    'name' => 'wp_option_import_view',
    'current_source' => 'main@trigger-source-admission-current',
    'next_source' => 'main@trigger-source-admission-next',
    'mapping' => ['name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
];
$recursiveTriggers = [
    ['name' => 'wp_options_ai_home', 'when' => 'siteurl', 'target' => 'home', 'value' => '{value}/home'],
    ['name' => 'wp_options_au_rewrite', 'when' => 'home', 'target' => 'rewrite_rules', 'value' => 'flushed:{value}'],
];
$returningProjection = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'old_or_null.option_value', 'as' => 'old_value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'trigger_depth'],
    ['expr' => 'source', 'as' => 'view_source'],
    ['expr' => 'trigger', 'as' => 'trigger_name'],
    static fn (array $new, ?array $old, array $incoming, string $event, int $ordinal, int $depth, string $source, ?string $trigger): string => $source . ':' . $event . ':' . $ordinal . ':' . $depth . ':' . ($trigger ?? 'view') . ':' . $new['option_name'],
];
$currentViewRows = [
    ['name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes'],
    ['name' => 'blogdescription', 'value' => 'Current Tagline', 'autoload_flag' => 'yes'],
];
$nextViewRows = [
    ['name' => 'siteurl', 'value' => 'https://next.test', 'autoload_flag' => 'yes'],
    ['name' => 'fresh_plugin', 'value' => 'enabled', 'autoload_flag' => 'no'],
];

$sourceAdmissionPlan = static fn (array $options = [], ?array $current = null, ?array $next = null, ?array $returning = null, ?array $view = null, ?array $triggers = null): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeRecursiveViewReturningSourceAdmission(
    $initialRows,
    $current ?? $currentViewRows,
    $next ?? $nextViewRows,
    $view ?? $viewDefinition,
    ['option_name'],
    $triggers ?? $recursiveTriggers,
    $returning ?? $returningProjection,
    $options + ['savepoint' => 'wp_recursive_view_returning_source_admission'],
);

$retainedSource = static fn (): array => $sourceAdmissionPlan();
$releasedSource = static fn (): array => $sourceAdmissionPlan(['release_current' => true]);
$rolledSourceAdmission = static fn (): array => $sourceAdmissionPlan(['release_current' => true, 'rollback_next' => true]);
$nonRecursiveSource = static fn (): array => $sourceAdmissionPlan(['recursive_triggers' => false]);

$sourceAdmissionCases = [
    'retained status' => [static fn (): mixed => $retainedSource()['status'], 'trigger-recursive-view-returning-current-source-retained'],
    'savepoint retained' => [static fn (): mixed => $retainedSource()['savepoint'], 'wp_recursive_view_returning_source_admission'],
    'view retained' => [static fn (): mixed => $retainedSource()['view'], 'wp_option_import_view'],
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
    'before rows retained' => [static fn (): mixed => array_column($retainedSource()['before_rows'], 'option_name'), ['siteurl', 'home', 'rewrite_rules']],
    'current recursive rows are attempted' => [static fn (): mixed => array_column($retainedSource()['current_rows'], 'option_name'), ['siteurl', 'home', 'rewrite_rules', 'blogdescription']],
    'current recursive home value attempted' => [static fn (): mixed => $retainedSource()['current_rows'][1]['option_value'], 'https://current.test/home'],
    'current recursive rewrite value attempted' => [static fn (): mixed => $retainedSource()['current_rows'][2]['option_value'], 'flushed:https://current.test/home'],
    'after savepoint uses next source rows' => [static fn (): mixed => array_column($retainedSource()['after_savepoint'], 'option_name'), ['siteurl', 'home', 'rewrite_rules', 'fresh_plugin']],
    'after savepoint siteurl is next' => [static fn (): mixed => $retainedSource()['after_savepoint'][0]['option_value'], 'https://next.test'],
    'after savepoint home follows next' => [static fn (): mixed => $retainedSource()['after_savepoint'][1]['option_value'], 'https://next.test/home'],
    'after savepoint rewrite follows next recursive home' => [static fn (): mixed => $retainedSource()['after_savepoint'][2]['option_value'], 'flushed:https://next.test/home'],
    'returning names are admitted next rows only' => [static fn (): mixed => array_column(array_column($retainedSource()['returning_rows'], 'returning'), 'name'), ['siteurl', 'home', 'rewrite_rules', 'fresh_plugin']],
    'returning depths include recursive side effects' => [static fn (): mixed => array_column(array_column($retainedSource()['returning_rows'], 'returning'), 'trigger_depth'), [0, 1, 2, 0]],
    'returning sources are next' => [static fn (): mixed => array_unique(array_column(array_column($retainedSource()['returning_rows'], 'returning'), 'view_source')), ['main@trigger-source-admission-next']],
    'returning trigger names preserve view then recursive triggers' => [static fn (): mixed => array_column(array_column($retainedSource()['returning_rows'], 'returning'), 'trigger_name'), [null, 'wp_options_ai_home', 'wp_options_au_rewrite', null]],
    'returning old value for next siteurl uses saved baseline' => [static fn (): mixed => $retainedSource()['returning_rows'][0]['returning']['old_value'], 'https://old.test'],
    'returning old value for inserted next plugin is null' => [static fn (): mixed => $retainedSource()['returning_rows'][3]['returning']['old_value'], null],
    'callable returning trace records next recursion' => [static fn (): mixed => array_column(array_column($retainedSource()['returning_rows'], 'returning'), 'expr7'), ['main@trigger-source-admission-next:update:0:0:view:siteurl', 'main@trigger-source-admission-next:update:0:1:wp_options_ai_home:home', 'main@trigger-source-admission-next:update:0:2:wp_options_au_rewrite:rewrite_rules', 'main@trigger-source-admission-next:insert:1:0:view:fresh_plugin']],
    'suppressed returning names are current rows' => [static fn (): mixed => array_column(array_column($retainedSource()['suppressed_returning_rows'], 'returning'), 'name'), ['siteurl', 'home', 'rewrite_rules', 'blogdescription']],
    'discarded returning count is current rows' => [static fn (): mixed => $retainedSource()['discarded_returning_count'], 4],
    'current stream rolled back' => [static fn (): mixed => array_unique(array_column($retainedSource()['current_yield_stream'], 'rolled_back_after_yield')), [true]],
    'next stream admitted' => [static fn (): mixed => array_unique(array_column($retainedSource()['next_yield_stream'], 'admitted')), [true]],
    'attempted source phases' => [static fn (): mixed => array_column($retainedSource()['attempted_source_stream'], 'phase'), ['current', 'current', 'current', 'current', 'next', 'next', 'next', 'next']],
    'attempted source admission flags' => [static fn (): mixed => array_column($retainedSource()['attempted_source_stream'], 'admitted'), [false, false, false, false, true, true, true, true]],
    'current trigger effects target options' => [static fn (): mixed => array_column($retainedSource()['current_trigger_effects'], 'target_option'), ['home', 'rewrite_rules']],
    'next trigger effects source options' => [static fn (): mixed => array_column($retainedSource()['next_trigger_effects'], 'source_option'), ['siteurl', 'home']],
    'boundary retained before next source' => [static fn (): mixed => $retainedSource()['yield_boundary'], 'recursive-view-returning-current-source-yield-before-next-source'],
    'dependency closure marker' => [static fn (): mixed => $retainedSource()['dependency_closure'], 'reuses-native-recursive-trigger-returning-view-current-source-plans'],
    'source-admission dependency marker' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-admission', $retainedSource()['dependencies'], true), true],
    'returning dependency marker' => [static fn (): mixed => in_array('sqlite-returning-current-source-before-next-source', $retainedSource()['dependencies'], true), true],

    'released status' => [static fn (): mixed => $releasedSource()['status'], 'trigger-recursive-view-returning-current-next-source-admitted'],
    'released visible source is next' => [static fn (): mixed => $releasedSource()['visible_source'], 'main@trigger-source-admission-next'],
    'released current source admitted' => [static fn (): mixed => $releasedSource()['current_source_admitted'], true],
    'released next input current output' => [static fn (): mixed => $releasedSource()['next_input'], 'current-phase-output'],
    'released changes include both phases' => [static fn (): mixed => $releasedSource()['changes'], 8],
    'released final rows preserve current tagline' => [static fn (): mixed => array_column($releasedSource()['after_savepoint'], 'option_name'), ['siteurl', 'home', 'rewrite_rules', 'blogdescription', 'fresh_plugin']],
    'released current returning names first' => [static fn (): mixed => array_slice(array_column(array_column($releasedSource()['returning_rows'], 'returning'), 'name'), 0, 4), ['siteurl', 'home', 'rewrite_rules', 'blogdescription']],
    'released next returning names second' => [static fn (): mixed => array_slice(array_column(array_column($releasedSource()['returning_rows'], 'returning'), 'name'), 4), ['siteurl', 'home', 'rewrite_rules', 'fresh_plugin']],
    'released has no suppressed rows' => [static fn (): mixed => $releasedSource()['suppressed_returning_rows'], []],
    'released boundary' => [static fn (): mixed => $releasedSource()['yield_boundary'], 'recursive-view-returning-current-next-sources-admitted'],

    'rolled next status' => [static fn (): mixed => $rolledSourceAdmission()['status'], 'trigger-recursive-view-returning-current-source-admitted-next-rolled-back'],
    'rolled next visible source returns current' => [static fn (): mixed => $rolledSourceAdmission()['visible_source'], 'main@trigger-source-admission-current'],
    'rolled next admitted flags' => [static fn (): mixed => [$rolledSourceAdmission()['current_source_admitted'], $rolledSourceAdmission()['next_source_admitted']], [true, false]],
    'rolled next returning rows are current only' => [static fn (): mixed => array_column(array_column($rolledSourceAdmission()['returning_rows'], 'returning'), 'name'), ['siteurl', 'home', 'rewrite_rules', 'blogdescription']],
    'rolled next suppressed rows are next only' => [static fn (): mixed => array_column(array_column($rolledSourceAdmission()['suppressed_returning_rows'], 'returning'), 'name'), ['siteurl', 'home', 'rewrite_rules', 'fresh_plugin']],
    'rolled next final rows are current output' => [static fn (): mixed => array_column($rolledSourceAdmission()['after_savepoint'], 'option_name'), ['siteurl', 'home', 'rewrite_rules', 'blogdescription']],
    'rolled next discarded count' => [static fn (): mixed => $rolledSourceAdmission()['discarded_returning_count'], 4],

    'non recursive changes only direct view rows' => [static fn (): mixed => $nonRecursiveSource()['current_changes'], 2],
    'non recursive current names' => [static fn (): mixed => array_column($nonRecursiveSource()['current_rows'], 'option_name'), ['siteurl', 'home', 'rewrite_rules', 'blogdescription']],
    'non recursive home remains old' => [static fn (): mixed => $nonRecursiveSource()['current_rows'][1]['option_value'], 'https://old-home.test'],
    'non recursive effects show suppression' => [static fn (): mixed => array_column($nonRecursiveSource()['current_trigger_effects'], 'result'), ['recursive-suppressed']],
    'non recursive admitted returning direct rows only' => [static fn (): mixed => array_column(array_column($nonRecursiveSource()['returning_rows'], 'returning'), 'name'), ['siteurl', 'fresh_plugin']],

    'wildcard returning exposes row payload' => [static fn (): mixed => array_column(array_column($sourceAdmissionPlan([], null, null, ['*'])['returning_rows'], 'returning'), 'row')[0]['autoload'], 'yes'],
    'custom savepoint accepted' => [static fn (): mixed => $sourceAdmissionPlan(['savepoint' => 'wp_custom_recursive_source_admission'])['savepoint'], 'wp_custom_recursive_source_admission'],
    'max depth accepted' => [static fn (): mixed => $sourceAdmissionPlan(['max_depth' => 3])['current_yield_stream'][2]['depth'], 2],
    'empty unique columns throws' => [static fn (): mixed => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeRecursiveViewReturningSourceAdmission($initialRows, [], [], $viewDefinition, [], $recursiveTriggers, $returningProjection), InvalidArgumentException::class],
    'empty returning throws' => [static fn (): mixed => $sourceAdmissionPlan([], null, null, []), InvalidArgumentException::class],
    'bad savepoint throws' => [static fn (): mixed => $sourceAdmissionPlan(['savepoint' => 'bad savepoint']), InvalidArgumentException::class],
    'bad max depth throws' => [static fn (): mixed => $sourceAdmissionPlan(['max_depth' => -1]), InvalidArgumentException::class],
    'bad view name throws' => [static fn (): mixed => $sourceAdmissionPlan([], null, null, null, ['name' => 'bad-name', 'current_source' => 'ok', 'next_source' => 'ok2', 'mapping' => ['name' => 'option_name']]), InvalidArgumentException::class],
    'bad current source throws' => [static fn (): mixed => $sourceAdmissionPlan([], null, null, null, ['name' => 'v', 'current_source' => 'bad source', 'next_source' => 'ok2', 'mapping' => ['name' => 'option_name']]), InvalidArgumentException::class],
    'bad mapping throws' => [static fn (): mixed => $sourceAdmissionPlan([], null, null, null, ['name' => 'v', 'current_source' => 'ok', 'next_source' => 'ok2', 'mapping' => []]), InvalidArgumentException::class],
    'missing view column throws' => [static fn (): mixed => $sourceAdmissionPlan([], [['name' => 'siteurl']]), InvalidArgumentException::class],
    'bad trigger name throws' => [static fn (): mixed => $sourceAdmissionPlan([], null, null, null, null, [['name' => 'bad-name', 'when' => 'siteurl', 'target' => 'home', 'value' => 'x']]), InvalidArgumentException::class],
    'incomplete trigger throws' => [static fn (): mixed => $sourceAdmissionPlan([], null, null, null, null, [['name' => 'ok', 'when' => 'siteurl', 'target' => 'home']]), InvalidArgumentException::class],
    'old expression on insert throws' => [static fn (): mixed => $sourceAdmissionPlan([], [['name' => 'new_insert', 'value' => 'x', 'autoload_flag' => 'yes']], [], [['expr' => 'old.option_value', 'as' => 'old_value']]), InvalidArgumentException::class],
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
