<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows158 = [
    ['option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_name' => 'home', 'option_value' => 'https://old-home.test', 'autoload' => 'yes'],
    ['option_name' => 'rewrite_rules', 'option_value' => 'old-rules', 'autoload' => 'no'],
];
$view158 = [
    'name' => 'wp_option_import_view',
    'current_source' => 'main@trigger158-current',
    'next_source' => 'main@trigger158-next',
    'mapping' => ['name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
];
$triggers158 = [
    ['name' => 'wp_options_ai_home', 'when' => 'siteurl', 'target' => 'home', 'value' => '{value}/home'],
    ['name' => 'wp_options_au_rewrite', 'when' => 'home', 'target' => 'rewrite_rules', 'value' => 'flushed:{value}'],
];
$returning158 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'old_or_null.option_value', 'as' => 'old_value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'trigger_depth'],
    ['expr' => 'source', 'as' => 'view_source'],
    ['expr' => 'trigger', 'as' => 'trigger_name'],
    static fn (array $new, ?array $old, array $incoming, string $event, int $ordinal, int $depth, string $source, ?string $trigger): string => $source . ':' . $event . ':' . $ordinal . ':' . $depth . ':' . ($trigger ?? 'view') . ':' . $new['option_name'],
];
$current158 = [
    ['name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes'],
    ['name' => 'blogdescription', 'value' => 'Current Tagline', 'autoload_flag' => 'yes'],
];
$next158 = [
    ['name' => 'siteurl', 'value' => 'https://next.test', 'autoload_flag' => 'yes'],
    ['name' => 'fresh_plugin', 'value' => 'enabled', 'autoload_flag' => 'no'],
];

$plan158 = static fn (array $options = [], ?array $current = null, ?array $next = null, ?array $returning = null, ?array $view = null, ?array $triggers = null): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext158(
    $rows158,
    $current ?? $current158,
    $next ?? $next158,
    $view ?? $view158,
    ['option_name'],
    $triggers ?? $triggers158,
    $returning ?? $returning158,
    $options + ['savepoint' => 'wp_recursive_view_returning_158'],
);

$retained158 = static fn (): array => $plan158();
$released158 = static fn (): array => $plan158(['release_current' => true]);
$rolledNext158 = static fn (): array => $plan158(['release_current' => true, 'rollback_next' => true]);
$nonRecursive158 = static fn (): array => $plan158(['recursive_triggers' => false]);

$cases158 = [
    'retained status' => [static fn (): mixed => $retained158()['status'], 'trigger-recursive-view-returning-current-source-retained-next158'],
    'savepoint retained' => [static fn (): mixed => $retained158()['savepoint'], 'wp_recursive_view_returning_158'],
    'view retained' => [static fn (): mixed => $retained158()['view'], 'wp_option_import_view'],
    'current source retained' => [static fn (): mixed => $retained158()['current_source'], 'main@trigger158-current'],
    'next source retained' => [static fn (): mixed => $retained158()['next_source'], 'main@trigger158-next'],
    'visible source is next when current rolls back' => [static fn (): mixed => $retained158()['visible_source'], 'main@trigger158-next'],
    'current source not admitted by default' => [static fn (): mixed => $retained158()['current_source_admitted'], false],
    'next source admitted by default' => [static fn (): mixed => $retained158()['next_source_admitted'], true],
    'next input is saved current source' => [static fn (): mixed => $retained158()['next_input'], 'saved-current-source'],
    'recursive triggers enabled' => [static fn (): mixed => $retained158()['recursive_triggers'], true],
    'changes include next source only' => [static fn (): mixed => $retained158()['changes'], 4],
    'current changes counted diagnostically' => [static fn (): mixed => $retained158()['current_changes'], 4],
    'next changes counted' => [static fn (): mixed => $retained158()['next_changes'], 4],
    'before rows retained' => [static fn (): mixed => array_column($retained158()['before_rows'], 'option_name'), ['siteurl', 'home', 'rewrite_rules']],
    'current recursive rows are attempted' => [static fn (): mixed => array_column($retained158()['current_rows'], 'option_name'), ['siteurl', 'home', 'rewrite_rules', 'blogdescription']],
    'current recursive home value attempted' => [static fn (): mixed => $retained158()['current_rows'][1]['option_value'], 'https://current.test/home'],
    'current recursive rewrite value attempted' => [static fn (): mixed => $retained158()['current_rows'][2]['option_value'], 'flushed:https://current.test/home'],
    'after savepoint uses next source rows' => [static fn (): mixed => array_column($retained158()['after_savepoint'], 'option_name'), ['siteurl', 'home', 'rewrite_rules', 'fresh_plugin']],
    'after savepoint siteurl is next' => [static fn (): mixed => $retained158()['after_savepoint'][0]['option_value'], 'https://next.test'],
    'after savepoint home follows next' => [static fn (): mixed => $retained158()['after_savepoint'][1]['option_value'], 'https://next.test/home'],
    'after savepoint rewrite follows next recursive home' => [static fn (): mixed => $retained158()['after_savepoint'][2]['option_value'], 'flushed:https://next.test/home'],
    'returning names are admitted next rows only' => [static fn (): mixed => array_column(array_column($retained158()['returning_rows'], 'returning'), 'name'), ['siteurl', 'home', 'rewrite_rules', 'fresh_plugin']],
    'returning depths include recursive side effects' => [static fn (): mixed => array_column(array_column($retained158()['returning_rows'], 'returning'), 'trigger_depth'), [0, 1, 2, 0]],
    'returning sources are next' => [static fn (): mixed => array_unique(array_column(array_column($retained158()['returning_rows'], 'returning'), 'view_source')), ['main@trigger158-next']],
    'returning trigger names preserve view then recursive triggers' => [static fn (): mixed => array_column(array_column($retained158()['returning_rows'], 'returning'), 'trigger_name'), [null, 'wp_options_ai_home', 'wp_options_au_rewrite', null]],
    'returning old value for next siteurl uses saved baseline' => [static fn (): mixed => $retained158()['returning_rows'][0]['returning']['old_value'], 'https://old.test'],
    'returning old value for inserted next plugin is null' => [static fn (): mixed => $retained158()['returning_rows'][3]['returning']['old_value'], null],
    'callable returning trace records next recursion' => [static fn (): mixed => array_column(array_column($retained158()['returning_rows'], 'returning'), 'expr7'), ['main@trigger158-next:update:0:0:view:siteurl', 'main@trigger158-next:update:0:1:wp_options_ai_home:home', 'main@trigger158-next:update:0:2:wp_options_au_rewrite:rewrite_rules', 'main@trigger158-next:insert:1:0:view:fresh_plugin']],
    'suppressed returning names are current rows' => [static fn (): mixed => array_column(array_column($retained158()['suppressed_returning_rows'], 'returning'), 'name'), ['siteurl', 'home', 'rewrite_rules', 'blogdescription']],
    'discarded returning count is current rows' => [static fn (): mixed => $retained158()['discarded_returning_count'], 4],
    'current stream rolled back' => [static fn (): mixed => array_unique(array_column($retained158()['current_yield_stream'], 'rolled_back_after_yield')), [true]],
    'next stream admitted' => [static fn (): mixed => array_unique(array_column($retained158()['next_yield_stream'], 'admitted')), [true]],
    'attempted source phases' => [static fn (): mixed => array_column($retained158()['attempted_source_stream'], 'phase'), ['current', 'current', 'current', 'current', 'next', 'next', 'next', 'next']],
    'attempted source admission flags' => [static fn (): mixed => array_column($retained158()['attempted_source_stream'], 'admitted'), [false, false, false, false, true, true, true, true]],
    'current trigger effects target options' => [static fn (): mixed => array_column($retained158()['current_trigger_effects'], 'target_option'), ['home', 'rewrite_rules']],
    'next trigger effects source options' => [static fn (): mixed => array_column($retained158()['next_trigger_effects'], 'source_option'), ['siteurl', 'home']],
    'boundary retained before next source' => [static fn (): mixed => $retained158()['yield_boundary'], 'recursive-view-returning-current-source-yield-before-next-source'],
    'dependency closure marker' => [static fn (): mixed => $retained158()['dependency_closure'], 'reuses-native-recursive-trigger-returning-view-current-source-plans'],
    'next158 dependency marker' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next158', $retained158()['dependencies'], true), true],
    'returning dependency marker' => [static fn (): mixed => in_array('sqlite-returning-current-source-before-next-source', $retained158()['dependencies'], true), true],

    'released status' => [static fn (): mixed => $released158()['status'], 'trigger-recursive-view-returning-current-next-source-admitted-next158'],
    'released visible source is next' => [static fn (): mixed => $released158()['visible_source'], 'main@trigger158-next'],
    'released current source admitted' => [static fn (): mixed => $released158()['current_source_admitted'], true],
    'released next input current output' => [static fn (): mixed => $released158()['next_input'], 'current-phase-output'],
    'released changes include both phases' => [static fn (): mixed => $released158()['changes'], 8],
    'released final rows preserve current tagline' => [static fn (): mixed => array_column($released158()['after_savepoint'], 'option_name'), ['siteurl', 'home', 'rewrite_rules', 'blogdescription', 'fresh_plugin']],
    'released current returning names first' => [static fn (): mixed => array_slice(array_column(array_column($released158()['returning_rows'], 'returning'), 'name'), 0, 4), ['siteurl', 'home', 'rewrite_rules', 'blogdescription']],
    'released next returning names second' => [static fn (): mixed => array_slice(array_column(array_column($released158()['returning_rows'], 'returning'), 'name'), 4), ['siteurl', 'home', 'rewrite_rules', 'fresh_plugin']],
    'released has no suppressed rows' => [static fn (): mixed => $released158()['suppressed_returning_rows'], []],
    'released boundary' => [static fn (): mixed => $released158()['yield_boundary'], 'recursive-view-returning-current-next-sources-admitted'],

    'rolled next status' => [static fn (): mixed => $rolledNext158()['status'], 'trigger-recursive-view-returning-current-source-admitted-next-rolled-back-next158'],
    'rolled next visible source returns current' => [static fn (): mixed => $rolledNext158()['visible_source'], 'main@trigger158-current'],
    'rolled next admitted flags' => [static fn (): mixed => [$rolledNext158()['current_source_admitted'], $rolledNext158()['next_source_admitted']], [true, false]],
    'rolled next returning rows are current only' => [static fn (): mixed => array_column(array_column($rolledNext158()['returning_rows'], 'returning'), 'name'), ['siteurl', 'home', 'rewrite_rules', 'blogdescription']],
    'rolled next suppressed rows are next only' => [static fn (): mixed => array_column(array_column($rolledNext158()['suppressed_returning_rows'], 'returning'), 'name'), ['siteurl', 'home', 'rewrite_rules', 'fresh_plugin']],
    'rolled next final rows are current output' => [static fn (): mixed => array_column($rolledNext158()['after_savepoint'], 'option_name'), ['siteurl', 'home', 'rewrite_rules', 'blogdescription']],
    'rolled next discarded count' => [static fn (): mixed => $rolledNext158()['discarded_returning_count'], 4],

    'non recursive changes only direct view rows' => [static fn (): mixed => $nonRecursive158()['current_changes'], 2],
    'non recursive current names' => [static fn (): mixed => array_column($nonRecursive158()['current_rows'], 'option_name'), ['siteurl', 'home', 'rewrite_rules', 'blogdescription']],
    'non recursive home remains old' => [static fn (): mixed => $nonRecursive158()['current_rows'][1]['option_value'], 'https://old-home.test'],
    'non recursive effects show suppression' => [static fn (): mixed => array_column($nonRecursive158()['current_trigger_effects'], 'result'), ['recursive-suppressed']],
    'non recursive admitted returning direct rows only' => [static fn (): mixed => array_column(array_column($nonRecursive158()['returning_rows'], 'returning'), 'name'), ['siteurl', 'fresh_plugin']],

    'wildcard returning exposes row payload' => [static fn (): mixed => array_column(array_column($plan158([], null, null, ['*'])['returning_rows'], 'returning'), 'row')[0]['autoload'], 'yes'],
    'custom savepoint accepted' => [static fn (): mixed => $plan158(['savepoint' => 'wp_custom_recursive_158'])['savepoint'], 'wp_custom_recursive_158'],
    'max depth accepted' => [static fn (): mixed => $plan158(['max_depth' => 3])['current_yield_stream'][2]['depth'], 2],
    'empty unique columns throws' => [static fn (): mixed => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext158($rows158, [], [], $view158, [], $triggers158, $returning158), InvalidArgumentException::class],
    'empty returning throws' => [static fn (): mixed => $plan158([], null, null, []), InvalidArgumentException::class],
    'bad savepoint throws' => [static fn (): mixed => $plan158(['savepoint' => 'bad savepoint']), InvalidArgumentException::class],
    'bad max depth throws' => [static fn (): mixed => $plan158(['max_depth' => -1]), InvalidArgumentException::class],
    'bad view name throws' => [static fn (): mixed => $plan158([], null, null, null, ['name' => 'bad-name', 'current_source' => 'ok', 'next_source' => 'ok2', 'mapping' => ['name' => 'option_name']]), InvalidArgumentException::class],
    'bad current source throws' => [static fn (): mixed => $plan158([], null, null, null, ['name' => 'v', 'current_source' => 'bad source', 'next_source' => 'ok2', 'mapping' => ['name' => 'option_name']]), InvalidArgumentException::class],
    'bad mapping throws' => [static fn (): mixed => $plan158([], null, null, null, ['name' => 'v', 'current_source' => 'ok', 'next_source' => 'ok2', 'mapping' => []]), InvalidArgumentException::class],
    'missing view column throws' => [static fn (): mixed => $plan158([], [['name' => 'siteurl']]), InvalidArgumentException::class],
    'bad trigger name throws' => [static fn (): mixed => $plan158([], null, null, null, null, [['name' => 'bad-name', 'when' => 'siteurl', 'target' => 'home', 'value' => 'x']]), InvalidArgumentException::class],
    'incomplete trigger throws' => [static fn (): mixed => $plan158([], null, null, null, null, [['name' => 'ok', 'when' => 'siteurl', 'target' => 'home']]), InvalidArgumentException::class],
    'old expression on insert throws' => [static fn (): mixed => $plan158([], [['name' => 'new_insert', 'value' => 'x', 'autoload_flag' => 'yes']], [], [['expr' => 'old.option_value', 'as' => 'old_value']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases158 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next158 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
