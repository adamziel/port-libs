<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerUpsertRecursiveViewCurrentSourceNextPlan;

$rows148 = [
    ['option_name' => 'siteurl', 'option_value' => 'https://old.test'],
    ['option_name' => 'home', 'option_value' => 'https://old-home.test'],
    ['option_name' => 'rewrite_rules', 'option_value' => 'old-rules'],
];
$view148 = [
    'name' => 'wp_option_import_view',
    'source' => 'main@cookie148-current',
    'mapping' => ['name' => 'option_name', 'value' => 'option_value'],
];
$triggers148 = [
    ['name' => 'wp_options_au_home', 'when' => 'siteurl', 'target' => 'home', 'value' => '{value}/home'],
    ['name' => 'wp_options_au_rewrite', 'when' => 'home', 'target' => 'rewrite_rules', 'value' => 'flushed:{value}'],
];
$current148 = [
    ['name' => 'siteurl', 'value' => 'https://current.test'],
    ['name' => 'blogname', 'value' => 'Current Blog'],
];
$next148 = [
    ['name' => 'siteurl', 'value' => 'https://next.test'],
    ['name' => 'fresh_plugin', 'value' => 'enabled'],
];

$plan148 = static fn (array $options = [], ?array $current = null, ?array $next = null, ?array $view = null, ?array $triggers = null): array => SQLiteTriggerUpsertRecursiveViewCurrentSourceNextPlan::execute(
    $rows148,
    $current ?? $current148,
    $next ?? $next148,
    $view ?? $view148,
    ['option_name'],
    $triggers ?? $triggers148,
    $options + ['savepoint' => 'wp_view_recursive_148'],
);

$retained148 = static fn (): array => $plan148();
$released148 = static fn (): array => $plan148(['release_next' => true]);
$suppressed148 = static fn (): array => $plan148(['recursive_triggers' => false]);

$cases148 = [
    'retained status' => [static fn (): mixed => $retained148()['status'], 'trigger-upsert-recursive-view-current-source-retained-next148'],
    'savepoint retained' => [static fn (): mixed => $retained148()['savepoint'], 'wp_view_recursive_148'],
    'view retained' => [static fn (): mixed => $retained148()['view'], 'wp_option_import_view'],
    'current source retained' => [static fn (): mixed => $retained148()['current_source'], 'main@cookie148-current'],
    'visible source is current' => [static fn (): mixed => $retained148()['visible_source'], 'main@cookie148-current'],
    'next source not admitted' => [static fn (): mixed => $retained148()['next_source_admitted'], false],
    'recursive triggers enabled' => [static fn (): mixed => $retained148()['recursive_triggers'], true],
    'changes zero while savepoint retains current source' => [static fn (): mixed => $retained148()['changes'], 0],
    'current changes include recursive effects' => [static fn (): mixed => $retained148()['current_changes'], 4],
    'next changes suppressed' => [static fn (): mixed => $retained148()['next_changes'], 0],
    'current rows include inserted blogname' => [static fn (): mixed => array_column($retained148()['current_rows'], 'option_name'), ['siteurl', 'home', 'rewrite_rules', 'blogname']],
    'current siteurl updated' => [static fn (): mixed => $retained148()['current_rows'][0]['option_value'], 'https://current.test'],
    'current recursive home updated' => [static fn (): mixed => $retained148()['current_rows'][1]['option_value'], 'https://current.test/home'],
    'current nested rewrite updated' => [static fn (): mixed => $retained148()['current_rows'][2]['option_value'], 'flushed:https://current.test/home'],
    'after savepoint restores base names' => [static fn (): mixed => array_column($retained148()['after_savepoint'], 'option_name'), ['siteurl', 'home', 'rewrite_rules']],
    'after savepoint restores base values' => [static fn (): mixed => array_column($retained148()['after_savepoint'], 'option_value'), ['https://old.test', 'https://old-home.test', 'old-rules']],
    'current yield event sequence' => [static fn (): mixed => array_column($retained148()['current_yield_stream'], 'event'), ['update', 'update', 'update', 'insert']],
    'current yield depth sequence' => [static fn (): mixed => array_column($retained148()['current_yield_stream'], 'depth'), [0, 1, 2, 0]],
    'current yield trigger sequence' => [static fn (): mixed => array_column($retained148()['current_yield_stream'], 'trigger'), [null, 'wp_options_au_home', 'wp_options_au_rewrite', null]],
    'current yield source sequence' => [static fn (): mixed => array_unique(array_column($retained148()['current_yield_stream'], 'source')), ['main@cookie148-current']],
    'current returning names' => [static fn (): mixed => array_column($retained148()['current_returning_rows'], 'option_name'), ['siteurl', 'home', 'rewrite_rules', 'blogname']],
    'current returning old values' => [static fn (): mixed => array_column($retained148()['current_returning_rows'], 'old_value'), ['https://old.test', 'https://old-home.test', 'old-rules', null]],
    'current returning phases' => [static fn (): mixed => array_unique(array_column($retained148()['current_returning_rows'], 'phase')), ['current']],
    'trigger effects are recursive upserts' => [static fn (): mixed => array_column($retained148()['current_trigger_effects'], 'result'), ['recursive-upsert', 'recursive-upsert']],
    'trigger effects names' => [static fn (): mixed => array_column($retained148()['current_trigger_effects'], 'trigger'), ['wp_options_au_home', 'wp_options_au_rewrite']],
    'trigger effects source options' => [static fn (): mixed => array_column($retained148()['current_trigger_effects'], 'source_option'), ['siteurl', 'home']],
    'trigger effects target options' => [static fn (): mixed => array_column($retained148()['current_trigger_effects'], 'target_option'), ['home', 'rewrite_rules']],
    'attempted next yield exists but suppressed' => [static fn (): mixed => array_column($retained148()['attempted_next_yield_stream'], 'event'), ['update', 'update', 'update', 'insert']],
    'attempted next source is tagged' => [static fn (): mixed => array_unique(array_column($retained148()['attempted_next_yield_stream'], 'source')), ['main@cookie148-current@next']],
    'attempted next returning names' => [static fn (): mixed => array_column($retained148()['attempted_next_returning_rows'], 'option_name'), ['siteurl', 'home', 'rewrite_rules', 'fresh_plugin']],
    'attempted next effects are visible diagnostics' => [static fn (): mixed => array_column($retained148()['attempted_next_trigger_effects'], 'target_option'), ['home', 'rewrite_rules']],
    'next returning rows suppressed' => [static fn (): mixed => $retained148()['next_returning_rows'], []],
    'next trigger effects suppressed' => [static fn (): mixed => $retained148()['next_trigger_effects'], []],
    'boundary is current source' => [static fn (): mixed => $retained148()['yield_boundary'], 'recursive-view-upsert-current-source-yield-before-next-source'],
    'dependency marker present' => [static fn (): mixed => in_array('sqlite-trigger-upsert-recursive-view-current-source-next148', $retained148()['dependencies'], true), true],
    'recursive dependency marker present' => [static fn (): mixed => in_array('sqlite-recursive-trigger-side-effect-current-source-yield', $retained148()['dependencies'], true), true],

    'released status' => [static fn (): mixed => $released148()['status'], 'trigger-upsert-recursive-view-next-source-admitted-next148'],
    'released visible source is next' => [static fn (): mixed => $released148()['visible_source'], 'main@cookie148-current@next'],
    'released next admitted' => [static fn (): mixed => $released148()['next_source_admitted'], true],
    'released changes include both sources' => [static fn (): mixed => $released148()['changes'], 8],
    'released next changes include recursive effects' => [static fn (): mixed => $released148()['next_changes'], 4],
    'released final names include fresh plugin' => [static fn (): mixed => array_column($released148()['after_savepoint'], 'option_name'), ['siteurl', 'home', 'rewrite_rules', 'blogname', 'fresh_plugin']],
    'released final siteurl is next' => [static fn (): mixed => $released148()['after_savepoint'][0]['option_value'], 'https://next.test'],
    'released final home follows next siteurl' => [static fn (): mixed => $released148()['after_savepoint'][1]['option_value'], 'https://next.test/home'],
    'released final rewrite follows recursive home' => [static fn (): mixed => $released148()['after_savepoint'][2]['option_value'], 'flushed:https://next.test/home'],
    'released final blogname preserved from current' => [static fn (): mixed => $released148()['after_savepoint'][3]['option_value'], 'Current Blog'],
    'released next returning names' => [static fn (): mixed => array_column($released148()['next_returning_rows'], 'option_name'), ['siteurl', 'home', 'rewrite_rules', 'fresh_plugin']],
    'released next yield depths' => [static fn (): mixed => array_column($released148()['next_yield_stream'], 'depth'), [0, 1, 2, 0]],
    'released boundary' => [static fn (): mixed => $released148()['yield_boundary'], 'recursive-view-upsert-release-admits-next-source'],

    'suppressed recursive changes only statement rows' => [static fn (): mixed => $suppressed148()['current_changes'], 2],
    'suppressed home remains old' => [static fn (): mixed => $suppressed148()['current_rows'][1]['option_value'], 'https://old-home.test'],
    'suppressed effects record suppression' => [static fn (): mixed => array_column($suppressed148()['current_trigger_effects'], 'result'), ['recursive-suppressed']],
    'suppressed yields only view rows' => [static fn (): mixed => array_column($suppressed148()['current_yield_stream'], 'depth'), [0, 0]],

    'custom savepoint accepted' => [static fn (): mixed => $plan148(['savepoint' => 'wp_custom_148'])['savepoint'], 'wp_custom_148'],
    'empty unique columns throws' => [static fn (): mixed => SQLiteTriggerUpsertRecursiveViewCurrentSourceNextPlan::execute($rows148, [], [], $view148, [], $triggers148), InvalidArgumentException::class],
    'bad savepoint throws' => [static fn (): mixed => $plan148(['savepoint' => 'bad name']), InvalidArgumentException::class],
    'bad view name throws' => [static fn (): mixed => $plan148([], null, null, ['name' => 'bad-name', 'source' => 'ok', 'mapping' => ['name' => 'option_name']]), InvalidArgumentException::class],
    'bad source throws' => [static fn (): mixed => $plan148([], null, null, ['name' => 'v', 'source' => 'bad source', 'mapping' => ['name' => 'option_name']]), InvalidArgumentException::class],
    'bad mapping throws' => [static fn (): mixed => $plan148([], null, null, ['name' => 'v', 'source' => 'ok', 'mapping' => []]), InvalidArgumentException::class],
    'missing view column throws' => [static fn (): mixed => $plan148([], [['name' => 'siteurl']]), InvalidArgumentException::class],
    'bad trigger name throws' => [static fn (): mixed => $plan148([], null, null, null, [['name' => 'bad-name', 'when' => 'siteurl', 'target' => 'home', 'value' => 'x']]), InvalidArgumentException::class],
    'bad trigger target throws' => [static fn (): mixed => $plan148([], null, null, null, [['name' => 'ok', 'when' => 'siteurl', 'target' => 'bad-target', 'value' => 'x']]), InvalidArgumentException::class],
    'incomplete trigger throws' => [static fn (): mixed => $plan148([], null, null, null, [['name' => 'ok', 'when' => 'siteurl', 'target' => 'home']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases148 as $name => [$callback, $expected]) {
    $tests['trigger upsert recursive view current source next148 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
