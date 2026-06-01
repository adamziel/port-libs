<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerUpsertReturningViewCurrentSourceNextPlan;

$rows144 = [
    ['setting_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'revision' => 1, 'source' => 'seed'],
    ['setting_id' => 2, 'key_name' => 'home', 'key_value' => 'https://home.test', 'load_policy' => 'yes', 'revision' => 1, 'source' => 'seed'],
    ['setting_id' => 3, 'key_name' => 'blogname', 'key_value' => 'Old Blog', 'load_policy' => 'no', 'revision' => 1, 'source' => 'seed'],
];

$currentView144 = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-144-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'where' => static fn (array $old, array $incoming): bool => ($incoming['load_policy'] ?? null) !== 'skip',
];
$nextView144 = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-144-next',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'origin'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'origin' => 'source'],
    'where' => static fn (array $old, array $incoming): bool => ($incoming['source'] ?? null) !== 'preview-only',
];
$assignments144 = [
    'setting_id' => static fn (array $old, array $incoming): mixed => $incoming['setting_id'],
    'key_value' => static fn (array $old, array $incoming): mixed => $incoming['key_value'],
    'load_policy' => static fn (array $old, array $incoming): mixed => $incoming['load_policy'],
    'source' => static fn (array $old, array $incoming): mixed => $incoming['source'] ?? 'current-import',
    'revision' => static fn (array $old, array $incoming): int => (int) $old['revision'] + 1,
];
$returning144 = [
    ['expr' => 'new.key_name', 'as' => 'name'],
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'old_or_null.key_value', 'as' => 'old_value'],
    ['expr' => 'excluded.key_value', 'as' => 'incoming_value'],
    ['expr' => 'source', 'as' => 'view_source'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    static fn (array $new, ?array $old, array $incoming, string $event, int $ordinal, string $source): string => $source . ':' . $event . ':' . $ordinal . ':' . ($old['key_name'] ?? 'new') . '>' . $new['key_name'],
];

$currentRows144 = [
    ['import_id' => 11, 'name' => 'siteurl', 'value' => 'https://current.test', 'load_policy_flag' => 'yes'],
    ['import_id' => 12, 'name' => 'home', 'value' => 'https://skip.test', 'load_policy_flag' => 'skip'],
    ['import_id' => 13, 'name' => 'fresh_plugin', 'value' => 'enabled', 'load_policy_flag' => 'no'],
];
$nextRows144 = [
    ['import_id' => 21, 'name' => 'home', 'value' => 'https://next-home.test', 'load_policy_flag' => 'yes', 'origin' => 'next-import'],
    ['import_id' => 22, 'name' => 'blogname', 'value' => 'Preview Only', 'load_policy_flag' => 'no', 'origin' => 'preview-only'],
    ['import_id' => 23, 'name' => 'rewrite_rules', 'value' => 'cached', 'load_policy_flag' => 'yes', 'origin' => 'next-import'],
];

$plan144 = static fn (array $options = [], ?array $currentRows = null, ?array $nextRows = null, ?array $currentView = null, ?array $nextView = null, ?array $returning = null): array => SQLiteTriggerUpsertReturningViewCurrentSourceNextPlan::execute(
    $rows144,
    $currentRows ?? $currentRows144,
    $nextRows ?? $nextRows144,
    $currentView ?? $currentView144,
    $nextView ?? $nextView144,
    ['key_name'],
    $assignments144,
    $returning ?? $returning144,
    $options + ['key' => 'key_name', 'savepoint' => 'app_import_view_144', 'trigger' => 'app_settings_view_io_upsert_144'],
);

$retained144 = static fn (): array => $plan144();
$released144 = static fn (): array => $plan144(['release_current' => true]);

$cases144 = [
    'retained status' => [static fn (): mixed => $retained144()['status'], 'trigger-upsert-returning-view-current-source-retained-next144'],
    'retained savepoint' => [static fn (): mixed => $retained144()['savepoint'], 'app_import_view_144'],
    'retained trigger' => [static fn (): mixed => $retained144()['trigger'], 'app_settings_view_io_upsert_144'],
    'retained key' => [static fn (): mixed => $retained144()['key'], 'key_name'],
    'retained current source' => [static fn (): mixed => $retained144()['current_view']['source'], 'main@view-cookie-144-current'],
    'retained next source' => [static fn (): mixed => $retained144()['next_view']['source'], 'main@view-cookie-144-next'],
    'retained visible source stays current' => [static fn (): mixed => $retained144()['visible_view']['source'], 'main@view-cookie-144-current'],
    'retained current columns' => [static fn (): mixed => $retained144()['current_view']['columns'], ['import_id', 'name', 'value', 'load_policy_flag']],
    'retained next columns include origin' => [static fn (): mixed => $retained144()['next_view']['columns'], ['import_id', 'name', 'value', 'load_policy_flag', 'origin']],
    'retained current mapping option name' => [static fn (): mixed => $retained144()['current_view']['mapping']['name'], 'key_name'],
    'retained next mapping generated source' => [static fn (): mixed => $retained144()['next_view']['mapping']['origin'], 'source'],
    'retained next source not admitted' => [static fn (): mixed => $retained144()['next_source_admitted'], false],
    'retained changes zero after savepoint hold' => [static fn (): mixed => $retained144()['changes'], 0],
    'retained current changes counted diagnostically' => [static fn (): mixed => $retained144()['current_changes'], 2],
    'retained next changes zero' => [static fn (): mixed => $retained144()['next_changes'], 0],
    'retained statement rows current only' => [static fn (): mixed => $retained144()['statement_rows'], 3],
    'retained attempted statement rows includes next' => [static fn (): mixed => $retained144()['attempted_statement_rows'], 6],
    'retained current returning count excludes skipped' => [static fn (): mixed => count($retained144()['current_returning_rows']), 2],
    'retained current returning names' => [static fn (): mixed => array_column(array_column($retained144()['current_returning_rows'], 'returning'), 'name'), ['siteurl', 'fresh_plugin']],
    'retained current returning values' => [static fn (): mixed => array_column(array_column($retained144()['current_returning_rows'], 'returning'), 'value'), ['https://current.test', 'enabled']],
    'retained current old value for update' => [static fn (): mixed => $retained144()['current_returning_rows'][0]['returning']['old_value'], 'https://old.test'],
    'retained current old value insert null' => [static fn (): mixed => $retained144()['current_returning_rows'][1]['returning']['old_value'], null],
    'retained incoming value preserved' => [static fn (): mixed => $retained144()['current_returning_rows'][0]['returning']['incoming_value'], 'https://current.test'],
    'retained view source alias' => [static fn (): mixed => array_column(array_column($retained144()['current_returning_rows'], 'returning'), 'view_source'), ['main@view-cookie-144-current', 'main@view-cookie-144-current']],
    'retained event aliases' => [static fn (): mixed => array_column(array_column($retained144()['current_returning_rows'], 'returning'), 'event_name'), ['update', 'insert']],
    'retained ordinal aliases skip gap' => [static fn (): mixed => array_column(array_column($retained144()['current_returning_rows'], 'returning'), 'ordinal_value'), [0, 2]],
    'retained callable traces' => [static fn (): mixed => array_column(array_column($retained144()['current_returning_rows'], 'returning'), 'expr7'), ['main@view-cookie-144-current:update:0:siteurl>siteurl', 'main@view-cookie-144-current:insert:2:new>fresh_plugin']],
    'retained current skipped count' => [static fn (): mixed => count($retained144()['current_skipped_rows']), 1],
    'retained current skipped status' => [static fn (): mixed => $retained144()['current_skipped_rows'][0]['status'], 'skipped-do-update-where'],
    'retained current skipped row name' => [static fn (): mixed => $retained144()['current_skipped_rows'][0]['incoming_row']['key_name'], 'home'],
    'retained current skipped current row preserved' => [static fn (): mixed => $retained144()['current_skipped_rows'][0]['current_row']['key_value'], 'https://home.test'],
    'retained suppressed skipped count current only' => [static fn (): mixed => $retained144()['returning_suppressed_for_skipped_count'], 1],
    'retained yield stream statuses' => [static fn (): mixed => array_column($retained144()['current_yield_stream'], 'status'), ['changed', 'skipped-do-update-where', 'changed']],
    'retained yield changed flags' => [static fn (): mixed => array_column($retained144()['current_yield_stream'], 'changed'), [true, false, true]],
    'retained skipped returning null' => [static fn (): mixed => $retained144()['current_yield_stream'][1]['returning'], null],
    'retained current rows include attempted fresh plugin' => [static fn (): mixed => array_column($retained144()['current_rows'], 'key_name'), ['siteurl', 'home', 'blogname', 'fresh_plugin']],
    'retained current siteurl attempted value' => [static fn (): mixed => $retained144()['current_rows'][0]['key_value'], 'https://current.test'],
    'retained current home skipped value' => [static fn (): mixed => $retained144()['current_rows'][1]['key_value'], 'https://home.test'],
    'retained after savepoint restores base names' => [static fn (): mixed => array_column($retained144()['after_savepoint'], 'key_name'), ['siteurl', 'home', 'blogname']],
    'retained after savepoint restores siteurl value' => [static fn (): mixed => $retained144()['after_savepoint'][0]['key_value'], 'https://old.test'],
    'retained next returning suppressed' => [static fn (): mixed => $retained144()['next_returning_rows'], []],
    'retained attempted next returning count' => [static fn (): mixed => count($retained144()['attempted_next_returning_rows']), 2],
    'retained attempted next returning names' => [static fn (): mixed => array_column(array_column($retained144()['attempted_next_returning_rows'], 'returning'), 'name'), ['home', 'rewrite_rules']],
    'retained attempted next skipped name' => [static fn (): mixed => $retained144()['attempted_next_skipped_rows'][0]['incoming_row']['key_name'], 'blogname'],
    'retained attempted next source tokens' => [static fn (): mixed => array_column(array_column($retained144()['attempted_next_returning_rows'], 'returning'), 'view_source'), ['main@view-cookie-144-next', 'main@view-cookie-144-next']],
    'retained attempted next yield statuses' => [static fn (): mixed => array_column($retained144()['attempted_next_yield_stream'], 'status'), ['changed', 'skipped-do-update-where', 'changed']],
    'retained boundary' => [static fn (): mixed => $retained144()['yield_boundary'], 'view-upsert-returning-current-source-retained-before-next-source'],
    'retained dependency marker' => [static fn (): mixed => in_array('sqlite-trigger-upsert-returning-view-current-source-next144', $retained144()['dependencies'], true), true],
    'retained skip dependency marker' => [static fn (): mixed => in_array('sqlite-upsert-do-update-where-skips-returning', $retained144()['dependencies'], true), true],

    'released status' => [static fn (): mixed => $released144()['status'], 'trigger-upsert-returning-view-next-source-admitted-next144'],
    'released visible source is next' => [static fn (): mixed => $released144()['visible_view']['source'], 'main@view-cookie-144-next'],
    'released next source admitted' => [static fn (): mixed => $released144()['next_source_admitted'], true],
    'released changes include current and next changed rows' => [static fn (): mixed => $released144()['changes'], 4],
    'released current changes' => [static fn (): mixed => $released144()['current_changes'], 2],
    'released next changes' => [static fn (): mixed => $released144()['next_changes'], 2],
    'released statement rows all phases' => [static fn (): mixed => $released144()['statement_rows'], 6],
    'released next returning names' => [static fn (): mixed => array_column(array_column($released144()['next_returning_rows'], 'returning'), 'name'), ['home', 'rewrite_rules']],
    'released next skipped count' => [static fn (): mixed => count($released144()['next_skipped_rows']), 1],
    'released suppressed skipped count includes both phases' => [static fn (): mixed => $released144()['returning_suppressed_for_skipped_count'], 2],
    'released final names' => [static fn (): mixed => array_column($released144()['after_savepoint'], 'key_name'), ['siteurl', 'home', 'blogname', 'fresh_plugin', 'rewrite_rules']],
    'released final sources' => [static fn (): mixed => array_map(static fn (array $row): mixed => $row['source'] ?? null, $released144()['after_savepoint']), ['current-import', 'next-import', 'seed', null, 'next-import']],
    'released final home updated by next' => [static fn (): mixed => $released144()['after_savepoint'][1]['key_value'], 'https://next-home.test'],
    'released blogname skipped by next source where' => [static fn (): mixed => $released144()['after_savepoint'][2]['key_value'], 'Old Blog'],
    'released next yield phases' => [static fn (): mixed => array_column($released144()['next_yield_stream'], 'phase'), ['next', 'next', 'next']],
    'released boundary' => [static fn (): mixed => $released144()['yield_boundary'], 'view-upsert-returning-release-admits-next-source'],

    'custom savepoint accepted' => [static fn (): mixed => $plan144(['savepoint' => 'app_custom_view_144'])['savepoint'], 'app_custom_view_144'],
    'empty returning throws' => [static fn (): mixed => $plan144([], null, null, null, null, []), InvalidArgumentException::class],
    'bad key throws' => [static fn (): mixed => $plan144(['key' => 'bad-key']), InvalidArgumentException::class],
    'bad savepoint throws' => [static fn (): mixed => $plan144(['savepoint' => 'bad savepoint']), InvalidArgumentException::class],
    'empty unique columns throws' => [static fn (): mixed => SQLiteTriggerUpsertReturningViewCurrentSourceNextPlan::execute($rows144, [], [], $currentView144, $nextView144, [], $assignments144, $returning144), InvalidArgumentException::class],
    'bad assignment column throws' => [static fn (): mixed => SQLiteTriggerUpsertReturningViewCurrentSourceNextPlan::execute($rows144, [], [], $currentView144, $nextView144, ['key_name'], ['bad-column' => static fn (): int => 1], $returning144), InvalidArgumentException::class],
    'bad view source throws' => [static fn (): mixed => $plan144([], null, null, ['name' => 'v', 'source' => 'bad source', 'columns' => ['name'], 'mapping' => ['name' => 'key_name']]), InvalidArgumentException::class],
    'empty view columns throws' => [static fn (): mixed => $plan144([], null, null, ['name' => 'v', 'source' => 'ok', 'columns' => [], 'mapping' => ['name' => 'key_name']]), InvalidArgumentException::class],
    'bad view mapping throws' => [static fn (): mixed => $plan144([], null, null, ['name' => 'v', 'source' => 'ok', 'columns' => ['name'], 'mapping' => ['missing' => 'key_name']]), InvalidArgumentException::class],
    'bad where throws' => [static fn (): mixed => $plan144([], null, null, ['name' => 'v', 'source' => 'ok', 'columns' => ['name'], 'mapping' => ['name' => 'key_name'], 'where' => 'no']), InvalidArgumentException::class],
    'missing view column throws' => [static fn (): mixed => $plan144([], [['import_id' => 1, 'value' => 'x', 'load_policy_flag' => 'yes']]), InvalidArgumentException::class],
    'duplicate base key throws' => [static fn (): mixed => SQLiteTriggerUpsertReturningViewCurrentSourceNextPlan::execute(array_merge($rows144, [['key_name' => 'siteurl']]), [], [], $currentView144, $nextView144, ['key_name'], $assignments144, $returning144), InvalidArgumentException::class],
    'old expression on insert throws' => [static fn (): mixed => $plan144([], [['import_id' => 30, 'name' => 'fresh_old', 'value' => 'x', 'load_policy_flag' => 'yes']], [], null, null, [['expr' => 'old.key_value', 'as' => 'old_value']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases144 as $name => [$callback, $expected]) {
    $tests['trigger upsert returning view current source next144 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
