<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerUpsertRecursiveViewCurrentSourceNextPlan;

$rows148 = [
    ['key_name' => 'base_url', 'key_value' => 'https://old.test'],
    ['key_name' => 'landing_page', 'key_value' => 'https://old-landing_page.test'],
    ['key_name' => 'route_rules', 'key_value' => 'old-rules'],
];
$view148 = [
    'name' => 'app_setting_import_view',
    'source' => 'main@cookie148-current',
    'mapping' => ['name' => 'key_name', 'value' => 'key_value'],
];
$triggers148 = [
    ['name' => 'app_settings_au_home', 'when' => 'base_url', 'target' => 'landing_page', 'value' => '{value}/landing_page'],
    ['name' => 'app_settings_au_rewrite', 'when' => 'landing_page', 'target' => 'route_rules', 'value' => 'flushed:{value}'],
];
$current148 = [
    ['name' => 'base_url', 'value' => 'https://current.test'],
    ['name' => 'site_title', 'value' => 'Current Blog'],
];
$next148 = [
    ['name' => 'base_url', 'value' => 'https://next.test'],
    ['name' => 'fresh_module', 'value' => 'enabled'],
];

$plan148 = static fn (array $options = [], ?array $current = null, ?array $next = null, ?array $view = null, ?array $triggers = null): array => SQLiteTriggerUpsertRecursiveViewCurrentSourceNextPlan::execute(
    $rows148,
    $current ?? $current148,
    $next ?? $next148,
    $view ?? $view148,
    ['key_name'],
    $triggers ?? $triggers148,
    $options + ['savepoint' => 'app_view_recursive_148'],
);

$retained148 = static fn (): array => $plan148();
$released148 = static fn (): array => $plan148(['release_next' => true]);
$suppressed148 = static fn (): array => $plan148(['recursive_triggers' => false]);

$cases148 = [
    'retained status' => [static fn (): mixed => $retained148()['status'], 'trigger-upsert-recursive-view-current-source-retained-next148'],
    'savepoint retained' => [static fn (): mixed => $retained148()['savepoint'], 'app_view_recursive_148'],
    'view retained' => [static fn (): mixed => $retained148()['view'], 'app_setting_import_view'],
    'current source retained' => [static fn (): mixed => $retained148()['current_source'], 'main@cookie148-current'],
    'visible source is current' => [static fn (): mixed => $retained148()['visible_source'], 'main@cookie148-current'],
    'next source not admitted' => [static fn (): mixed => $retained148()['next_source_admitted'], false],
    'recursive triggers enabled' => [static fn (): mixed => $retained148()['recursive_triggers'], true],
    'changes zero while savepoint retains current source' => [static fn (): mixed => $retained148()['changes'], 0],
    'current changes include recursive effects' => [static fn (): mixed => $retained148()['current_changes'], 4],
    'next changes suppressed' => [static fn (): mixed => $retained148()['next_changes'], 0],
    'current rows include inserted site_title' => [static fn (): mixed => array_column($retained148()['current_rows'], 'key_name'), ['base_url', 'landing_page', 'route_rules', 'site_title']],
    'current base_url updated' => [static fn (): mixed => $retained148()['current_rows'][0]['key_value'], 'https://current.test'],
    'current recursive landing_page updated' => [static fn (): mixed => $retained148()['current_rows'][1]['key_value'], 'https://current.test/landing_page'],
    'current nested rewrite updated' => [static fn (): mixed => $retained148()['current_rows'][2]['key_value'], 'flushed:https://current.test/landing_page'],
    'after savepoint restores base names' => [static fn (): mixed => array_column($retained148()['after_savepoint'], 'key_name'), ['base_url', 'landing_page', 'route_rules']],
    'after savepoint restores base values' => [static fn (): mixed => array_column($retained148()['after_savepoint'], 'key_value'), ['https://old.test', 'https://old-landing_page.test', 'old-rules']],
    'current yield event sequence' => [static fn (): mixed => array_column($retained148()['current_yield_stream'], 'event'), ['update', 'update', 'update', 'insert']],
    'current yield depth sequence' => [static fn (): mixed => array_column($retained148()['current_yield_stream'], 'depth'), [0, 1, 2, 0]],
    'current yield trigger sequence' => [static fn (): mixed => array_column($retained148()['current_yield_stream'], 'trigger'), [null, 'app_settings_au_home', 'app_settings_au_rewrite', null]],
    'current yield source sequence' => [static fn (): mixed => array_unique(array_column($retained148()['current_yield_stream'], 'source')), ['main@cookie148-current']],
    'current returning names' => [static fn (): mixed => array_column($retained148()['current_returning_rows'], 'key_name'), ['base_url', 'landing_page', 'route_rules', 'site_title']],
    'current returning old values' => [static fn (): mixed => array_column($retained148()['current_returning_rows'], 'old_value'), ['https://old.test', 'https://old-landing_page.test', 'old-rules', null]],
    'current returning phases' => [static fn (): mixed => array_unique(array_column($retained148()['current_returning_rows'], 'phase')), ['current']],
    'trigger effects are recursive upserts' => [static fn (): mixed => array_column($retained148()['current_trigger_effects'], 'result'), ['recursive-upsert', 'recursive-upsert']],
    'trigger effects names' => [static fn (): mixed => array_column($retained148()['current_trigger_effects'], 'trigger'), ['app_settings_au_home', 'app_settings_au_rewrite']],
    'trigger effects source keys' => [static fn (): mixed => array_column($retained148()['current_trigger_effects'], 'source_key'), ['base_url', 'landing_page']],
    'trigger effects target keys' => [static fn (): mixed => array_column($retained148()['current_trigger_effects'], 'target_key'), ['landing_page', 'route_rules']],
    'attempted next yield exists but suppressed' => [static fn (): mixed => array_column($retained148()['attempted_next_yield_stream'], 'event'), ['update', 'update', 'update', 'insert']],
    'attempted next source is tagged' => [static fn (): mixed => array_unique(array_column($retained148()['attempted_next_yield_stream'], 'source')), ['main@cookie148-current@next']],
    'attempted next returning names' => [static fn (): mixed => array_column($retained148()['attempted_next_returning_rows'], 'key_name'), ['base_url', 'landing_page', 'route_rules', 'fresh_module']],
    'attempted next effects are visible diagnostics' => [static fn (): mixed => array_column($retained148()['attempted_next_trigger_effects'], 'target_key'), ['landing_page', 'route_rules']],
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
    'released final names include fresh module' => [static fn (): mixed => array_column($released148()['after_savepoint'], 'key_name'), ['base_url', 'landing_page', 'route_rules', 'site_title', 'fresh_module']],
    'released final base_url is next' => [static fn (): mixed => $released148()['after_savepoint'][0]['key_value'], 'https://next.test'],
    'released final landing_page follows next base_url' => [static fn (): mixed => $released148()['after_savepoint'][1]['key_value'], 'https://next.test/landing_page'],
    'released final rewrite follows recursive landing_page' => [static fn (): mixed => $released148()['after_savepoint'][2]['key_value'], 'flushed:https://next.test/landing_page'],
    'released final site_title preserved from current' => [static fn (): mixed => $released148()['after_savepoint'][3]['key_value'], 'Current Blog'],
    'released next returning names' => [static fn (): mixed => array_column($released148()['next_returning_rows'], 'key_name'), ['base_url', 'landing_page', 'route_rules', 'fresh_module']],
    'released next yield depths' => [static fn (): mixed => array_column($released148()['next_yield_stream'], 'depth'), [0, 1, 2, 0]],
    'released boundary' => [static fn (): mixed => $released148()['yield_boundary'], 'recursive-view-upsert-release-admits-next-source'],

    'suppressed recursive changes only statement rows' => [static fn (): mixed => $suppressed148()['current_changes'], 2],
    'suppressed landing_page remains old' => [static fn (): mixed => $suppressed148()['current_rows'][1]['key_value'], 'https://old-landing_page.test'],
    'suppressed effects record suppression' => [static fn (): mixed => array_column($suppressed148()['current_trigger_effects'], 'result'), ['recursive-suppressed']],
    'suppressed yields only view rows' => [static fn (): mixed => array_column($suppressed148()['current_yield_stream'], 'depth'), [0, 0]],

    'custom savepoint accepted' => [static fn (): mixed => $plan148(['savepoint' => 'app_custom_148'])['savepoint'], 'app_custom_148'],
    'empty unique columns throws' => [static fn (): mixed => SQLiteTriggerUpsertRecursiveViewCurrentSourceNextPlan::execute($rows148, [], [], $view148, [], $triggers148), InvalidArgumentException::class],
    'bad savepoint throws' => [static fn (): mixed => $plan148(['savepoint' => 'bad name']), InvalidArgumentException::class],
    'bad view name throws' => [static fn (): mixed => $plan148([], null, null, ['name' => 'bad-name', 'source' => 'ok', 'mapping' => ['name' => 'key_name']]), InvalidArgumentException::class],
    'bad source throws' => [static fn (): mixed => $plan148([], null, null, ['name' => 'v', 'source' => 'bad source', 'mapping' => ['name' => 'key_name']]), InvalidArgumentException::class],
    'bad mapping throws' => [static fn (): mixed => $plan148([], null, null, ['name' => 'v', 'source' => 'ok', 'mapping' => []]), InvalidArgumentException::class],
    'missing view column throws' => [static fn (): mixed => $plan148([], [['name' => 'base_url']]), InvalidArgumentException::class],
    'bad trigger name throws' => [static fn (): mixed => $plan148([], null, null, null, [['name' => 'bad-name', 'when' => 'base_url', 'target' => 'landing_page', 'value' => 'x']]), InvalidArgumentException::class],
    'bad trigger target throws' => [static fn (): mixed => $plan148([], null, null, null, [['name' => 'ok', 'when' => 'base_url', 'target' => 'bad-target', 'value' => 'x']]), InvalidArgumentException::class],
    'incomplete trigger throws' => [static fn (): mixed => $plan148([], null, null, null, [['name' => 'ok', 'when' => 'base_url', 'target' => 'landing_page']]), InvalidArgumentException::class],
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
