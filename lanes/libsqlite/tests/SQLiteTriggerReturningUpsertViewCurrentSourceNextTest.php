<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerReturningUpsertViewCurrentSourceNextPlan;

$rows149 = [
    ['setting_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'revision' => 1, 'source' => 'seed'],
    ['setting_id' => 2, 'key_name' => 'home', 'key_value' => 'https://home.test', 'load_policy' => 'yes', 'revision' => 1, 'source' => 'seed'],
    ['setting_id' => 3, 'key_name' => 'display_name', 'key_value' => 'Old Blog', 'load_policy' => 'no', 'revision' => 1, 'source' => 'seed'],
];

$currentView149 = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-149-current',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-149-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'audit_label' => 'current-trigger-body',
];
$nextView149 = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-149-next',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-149-next',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'origin'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'origin' => 'source'],
    'audit_label' => 'next-trigger-body',
];
$assign149 = [
    'setting_id' => static fn (array $old, array $incoming, string $phase): mixed => $incoming['setting_id'],
    'key_value' => static fn (array $old, array $incoming, string $phase): mixed => $incoming['key_value'],
    'load_policy' => static fn (array $old, array $incoming, string $phase): mixed => $incoming['load_policy'],
    'source' => static fn (array $old, array $incoming, string $phase): string => (string) ($incoming['source'] ?? $phase . '-trigger'),
    'revision' => static fn (array $old, array $incoming, string $phase): int => (int) $old['revision'] + 1,
];
$returning149 = [
    ['expr' => 'new.key_name', 'as' => 'name'],
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'old.key_value', 'as' => 'old_value'],
    ['expr' => 'excluded.key_value', 'as' => 'incoming_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    static fn (array $new, ?array $old, array $incoming, string $event, int $ordinal, string $source): string => $source . ':' . $event . ':' . $ordinal . ':' . ($old['key_name'] ?? 'new') . '>' . $new['key_name'],
];
$currentInput149 = [
    ['import_id' => 11, 'name' => 'siteurl', 'value' => 'https://current.test', 'load_policy_flag' => 'yes'],
    ['import_id' => 12, 'name' => 'skip_current_setting', 'value' => 'blocked', 'load_policy_flag' => 'no', '_raise_ignore' => true],
    ['import_id' => 13, 'name' => 'fresh_feature', 'value' => 'enabled', 'load_policy_flag' => 'no'],
];
$nextInput149 = [
    ['import_id' => 21, 'name' => 'home', 'value' => 'https://next-home.test', 'load_policy_flag' => 'yes', 'origin' => 'next-import'],
    ['import_id' => 22, 'name' => 'display_name', 'value' => 'blocked next', 'load_policy_flag' => 'no', 'origin' => 'next-import', '_raise_ignore' => true],
    ['import_id' => 23, 'name' => 'cache_rules', 'value' => 'cached', 'load_policy_flag' => 'yes', 'origin' => 'next-import'],
];

$plan149 = static fn (array $options = [], ?array $currentInput = null, ?array $nextInput = null, ?array $currentView = null, ?array $nextView = null, ?array $returning = null): array => SQLiteTriggerReturningUpsertViewCurrentSourceNextPlan::execute(
    $rows149,
    $currentInput ?? $currentInput149,
    $nextInput ?? $nextInput149,
    $currentView ?? $currentView149,
    $nextView ?? $nextView149,
    ['key_name'],
    $assign149,
    $returning ?? $returning149,
    $options + ['key' => 'key_name', 'savepoint' => 'app_import_view_149'],
);

$pinned149 = static fn (): array => $plan149();
$admitted149 = static fn (): array => $plan149(['admit_next_source' => true]);

$cases149 = [
    'pinned status' => [static fn (): mixed => $pinned149()['status'], 'trigger-returning-upsert-view-current-source-pinned-next149'],
    'pinned savepoint' => [static fn (): mixed => $pinned149()['savepoint'], 'app_import_view_149'],
    'pinned key' => [static fn (): mixed => $pinned149()['key'], 'key_name'],
    'pinned current source' => [static fn (): mixed => $pinned149()['current_view']['source'], 'main@view-cookie-149-current'],
    'pinned current trigger source' => [static fn (): mixed => $pinned149()['current_view']['trigger_source'], 'main@trigger-cookie-149-current'],
    'pinned next source' => [static fn (): mixed => $pinned149()['next_view']['source'], 'main@view-cookie-149-next'],
    'pinned next trigger source' => [static fn (): mixed => $pinned149()['next_view']['trigger_source'], 'main@trigger-cookie-149-next'],
    'pinned visible source remains current' => [static fn (): mixed => $pinned149()['visible_view']['source'], 'main@view-cookie-149-current'],
    'pinned trigger source changed' => [static fn (): mixed => $pinned149()['trigger_source_changed'], true],
    'pinned next source not admitted' => [static fn (): mixed => $pinned149()['next_source_admitted'], false],
    'pinned current columns' => [static fn (): mixed => $pinned149()['current_view']['columns'], ['import_id', 'name', 'value', 'load_policy_flag']],
    'pinned next columns include origin' => [static fn (): mixed => $pinned149()['next_view']['columns'], ['import_id', 'name', 'value', 'load_policy_flag', 'origin']],
    'pinned current mapping name' => [static fn (): mixed => $pinned149()['current_view']['mapping']['name'], 'key_name'],
    'pinned next mapping origin' => [static fn (): mixed => $pinned149()['next_view']['mapping']['origin'], 'source'],
    'pinned changes zero after current source drains' => [static fn (): mixed => $pinned149()['changes'], 0],
    'pinned current changes counted' => [static fn (): mixed => $pinned149()['current_changes'], 2],
    'pinned next changes suppressed' => [static fn (): mixed => $pinned149()['next_changes'], 0],
    'pinned attempted next changes counted' => [static fn (): mixed => $pinned149()['attempted_next_changes'], 2],
    'pinned statement rows current only' => [static fn (): mixed => $pinned149()['statement_rows'], 3],
    'pinned attempted statement rows both sources' => [static fn (): mixed => $pinned149()['attempted_statement_rows'], 6],
    'pinned current returning count skips ignored' => [static fn (): mixed => count($pinned149()['current_returning_rows']), 2],
    'pinned current returning names' => [static fn (): mixed => array_column(array_column($pinned149()['current_returning_rows'], 'returning'), 'name'), ['siteurl', 'fresh_feature']],
    'pinned current returning trigger aliases' => [static fn (): mixed => array_column(array_column($pinned149()['current_returning_rows'], 'returning'), 'trigger_source_alias'), ['main@trigger-cookie-149-current', 'main@trigger-cookie-149-current']],
    'pinned old value for update' => [static fn (): mixed => $pinned149()['current_returning_rows'][0]['returning']['old_value'], 'https://old.test'],
    'pinned old value for insert null' => [static fn (): mixed => $pinned149()['current_returning_rows'][1]['returning']['old_value'], null],
    'pinned callable traces' => [static fn (): mixed => array_column(array_column($pinned149()['current_returning_rows'], 'returning'), 'expr7'), ['main@trigger-cookie-149-current:update:0:siteurl>siteurl', 'main@trigger-cookie-149-current:insert:2:new>fresh_feature']],
    'pinned current skipped count' => [static fn (): mixed => count($pinned149()['current_skipped_rows']), 1],
    'pinned current skipped status' => [static fn (): mixed => $pinned149()['current_skipped_rows'][0]['status'], 'skipped-raise-ignore'],
    'pinned current skipped incoming name' => [static fn (): mixed => $pinned149()['current_skipped_rows'][0]['incoming_row']['key_name'], 'skip_current_setting'],
    'pinned yield statuses' => [static fn (): mixed => array_column($pinned149()['current_yield_stream'], 'status'), ['changed', 'skipped-raise-ignore', 'changed']],
    'pinned yield changed flags' => [static fn (): mixed => array_column($pinned149()['current_yield_stream'], 'changed'), [true, false, true]],
    'pinned skipped returning null' => [static fn (): mixed => $pinned149()['current_yield_stream'][1]['returning'], null],
    'pinned current rows include fresh feature' => [static fn (): mixed => array_column($pinned149()['current_rows'], 'key_name'), ['siteurl', 'home', 'display_name', 'fresh_feature']],
    'pinned current siteurl updated' => [static fn (): mixed => $pinned149()['current_rows'][0]['key_value'], 'https://current.test'],
    'pinned after savepoint restores base' => [static fn (): mixed => array_column($pinned149()['after_savepoint'], 'key_name'), ['siteurl', 'home', 'display_name']],
    'pinned after savepoint restores value' => [static fn (): mixed => $pinned149()['after_savepoint'][0]['key_value'], 'https://old.test'],
    'pinned next returning suppressed' => [static fn (): mixed => $pinned149()['next_returning_rows'], []],
    'pinned attempted next returning names' => [static fn (): mixed => array_column(array_column($pinned149()['attempted_next_returning_rows'], 'returning'), 'name'), ['home', 'cache_rules']],
    'pinned attempted next trigger aliases' => [static fn (): mixed => array_column(array_column($pinned149()['attempted_next_returning_rows'], 'returning'), 'trigger_source_alias'), ['main@trigger-cookie-149-next', 'main@trigger-cookie-149-next']],
    'pinned attempted next skipped count' => [static fn (): mixed => count($pinned149()['attempted_next_skipped_rows']), 1],
    'pinned attempted next skipped name' => [static fn (): mixed => $pinned149()['attempted_next_skipped_rows'][0]['incoming_row']['key_name'], 'display_name'],
    'pinned trigger effects current labels' => [static fn (): mixed => array_column($pinned149()['current_trigger_effects'], 'audit_label'), ['current-trigger-body', 'current-trigger-body']],
    'pinned attempted next trigger labels' => [static fn (): mixed => array_column($pinned149()['attempted_next_trigger_effects'], 'audit_label'), ['next-trigger-body', 'next-trigger-body']],
    'pinned boundary' => [static fn (): mixed => $pinned149()['yield_boundary'], 'instead-of-view-trigger-current-source-drained-before-next-trigger-source'],
    'pinned dependency marker' => [static fn (): mixed => in_array('sqlite-trigger-returning-upsert-view-current-source-next149', $pinned149()['dependencies'], true), true],

    'admitted status' => [static fn (): mixed => $admitted149()['status'], 'trigger-returning-upsert-view-next-source-admitted-next149'],
    'admitted visible source is next' => [static fn (): mixed => $admitted149()['visible_view']['source'], 'main@view-cookie-149-next'],
    'admitted next source flag' => [static fn (): mixed => $admitted149()['next_source_admitted'], true],
    'admitted changes include current and next' => [static fn (): mixed => $admitted149()['changes'], 4],
    'admitted next changes counted' => [static fn (): mixed => $admitted149()['next_changes'], 2],
    'admitted statement rows both phases' => [static fn (): mixed => $admitted149()['statement_rows'], 6],
    'admitted next returning names' => [static fn (): mixed => array_column(array_column($admitted149()['next_returning_rows'], 'returning'), 'name'), ['home', 'cache_rules']],
    'admitted final names' => [static fn (): mixed => array_column($admitted149()['after_savepoint'], 'key_name'), ['siteurl', 'home', 'display_name', 'fresh_feature', 'cache_rules']],
    'admitted final sources' => [static fn (): mixed => array_map(static fn (array $row): mixed => $row['source'] ?? null, $admitted149()['after_savepoint']), ['current-trigger', 'next-import', 'seed', null, 'next-import']],
    'admitted home updated by next source' => [static fn (): mixed => $admitted149()['after_savepoint'][1]['key_value'], 'https://next-home.test'],
    'admitted skipped display_name preserved' => [static fn (): mixed => $admitted149()['after_savepoint'][2]['key_value'], 'Old Blog'],
    'admitted next trigger effects visible' => [static fn (): mixed => array_column($admitted149()['next_trigger_effects'], 'key_name'), ['home', 'cache_rules']],
    'admitted boundary' => [static fn (): mixed => $admitted149()['yield_boundary'], 'instead-of-view-trigger-next-source-admitted-after-current-drain'],

    'custom savepoint accepted' => [static fn (): mixed => $plan149(['savepoint' => 'app_custom_view_149'])['savepoint'], 'app_custom_view_149'],
    'empty returning throws' => [static fn (): mixed => $plan149([], null, null, null, null, []), InvalidArgumentException::class],
    'bad savepoint throws' => [static fn (): mixed => $plan149(['savepoint' => 'bad savepoint']), InvalidArgumentException::class],
    'bad key throws' => [static fn (): mixed => $plan149(['key' => 'bad-key']), InvalidArgumentException::class],
    'empty unique columns throws' => [static fn (): mixed => SQLiteTriggerReturningUpsertViewCurrentSourceNextPlan::execute($rows149, [], [], $currentView149, $nextView149, [], $assign149, $returning149), InvalidArgumentException::class],
    'bad assignment column throws' => [static fn (): mixed => SQLiteTriggerReturningUpsertViewCurrentSourceNextPlan::execute($rows149, [], [], $currentView149, $nextView149, ['key_name'], ['bad-column' => static fn (): int => 1], $returning149), InvalidArgumentException::class],
    'bad trigger source throws' => [static fn (): mixed => $plan149([], null, null, ['name' => 'v', 'source' => 'ok', 'trigger' => 'trg', 'trigger_source' => 'bad source', 'columns' => ['name'], 'mapping' => ['name' => 'key_name']]), InvalidArgumentException::class],
    'empty view columns throws' => [static fn (): mixed => $plan149([], null, null, ['name' => 'v', 'source' => 'ok', 'trigger' => 'trg', 'trigger_source' => 'ok', 'columns' => [], 'mapping' => ['name' => 'key_name']]), InvalidArgumentException::class],
    'bad view mapping throws' => [static fn (): mixed => $plan149([], null, null, ['name' => 'v', 'source' => 'ok', 'trigger' => 'trg', 'trigger_source' => 'ok', 'columns' => ['name'], 'mapping' => ['missing' => 'key_name']]), InvalidArgumentException::class],
    'missing view column throws' => [static fn (): mixed => $plan149([], [['import_id' => 1, 'value' => 'x', 'load_policy_flag' => 'yes']]), InvalidArgumentException::class],
    'duplicate base key throws' => [static fn (): mixed => SQLiteTriggerReturningUpsertViewCurrentSourceNextPlan::execute(array_merge($rows149, [['key_name' => 'siteurl']]), [], [], $currentView149, $nextView149, ['key_name'], $assign149, $returning149), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases149 as $name => [$callback, $expected]) {
    $tests['trigger returning upsert view current source next149 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
