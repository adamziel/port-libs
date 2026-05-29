<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerSavepointReturningViewCurrentSourceNextPlan;

$rows134 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'revision' => 1, 'source' => 'seed'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'revision' => 1, 'source' => 'seed'],
    ['option_id' => 3, 'option_name' => 'theme_mods', 'option_value' => 'a:0:{}', 'autoload' => 'no', 'revision' => 2, 'source' => 'seed'],
];
$currentMutations134 = [
    ['option_id' => 3, 'option_name' => 'theme_mods', 'option_value' => 'broken-view-trigger', 'autoload' => 'yes', 'revision' => 3, 'source' => 'current-import'],
    ['option_id' => 4, 'option_name' => 'plugin_seed', 'option_value' => '{"enabled":true}', 'autoload' => 'yes', 'revision' => 1, 'source' => 'current-import'],
];
$nextMutations134 = [
    ['option_id' => 5, 'option_name' => 'rewrite_rules', 'option_value' => 'cached', 'autoload' => 'yes', 'revision' => 1, 'source' => 'next-import'],
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://next.test', 'autoload' => 'yes', 'revision' => 2, 'source' => 'next-import'],
];
$currentView134 = [
    'name' => 'wp_autoload_options_view',
    'source' => 'main@view-cookie-134-current',
    'columns' => ['option_name', 'option_value', 'autoload', 'revision'],
    'where' => static fn (array $row): bool => ($row['autoload'] ?? null) === 'yes',
    'order_by' => 'option_name',
];
$nextView134 = [
    'name' => 'wp_autoload_options_view',
    'source' => 'main@view-cookie-134-next',
    'columns' => ['option_name', 'option_value', 'autoload', 'revision', 'source'],
    'where' => static fn (array $row): bool => ($row['autoload'] ?? null) === 'yes',
    'order_by' => 'option_name',
];
$triggers134 = [
    [
        'name' => 'wp_options_view_after_update_guard',
        'phase' => 'current',
        'event' => 'update',
        'when' => ['new.option_name', '=', 'theme_mods'],
        'raise' => 'rollback',
        'reason' => 'theme view trigger rollback',
        'values' => ['name' => 'new.option_name', 'value' => 'new.option_value', 'source_token' => 'source'],
    ],
    [
        'name' => 'wp_options_view_next_audit',
        'phase' => 'next',
        'event' => 'insert',
        'values' => ['name' => 'new.option_name', 'source_token' => 'source'],
    ],
];
$returning134 = [
    'option_name',
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'old.option_value', 'as' => 'old_value'],
    ['expr' => 'excluded.option_value', 'as' => 'incoming_value'],
    ['expr' => 'source', 'as' => 'source_token'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    static fn (array $new, ?array $old, array $mutation, string $event, int $ordinal, string $source): string => $source . ':' . $event . ':' . $ordinal . ':' . ($old['option_name'] ?? 'new') . '>' . $new['option_name'],
];

$plan134 = static fn (
    array $current = null,
    array $next = null,
    array $triggers = null,
    array $currentView = null,
    array $nextView = null,
    array $returning = null,
    array $options = [],
): array => SQLiteTriggerSavepointReturningViewCurrentSourceNextPlan::executeViewSavepointReturningRollback(
    $rows134,
    $current ?? $currentMutations134,
    $next ?? $nextMutations134,
    $currentView ?? $currentView134,
    $nextView ?? $nextView134,
    $triggers ?? $triggers134,
    $returning ?? $returning134,
    $options + ['key' => 'option_name', 'savepoint' => 'wp_import_view_batch', 'trigger' => 'wp_options_view_io_update_134'],
);

$rolled134 = static fn (): array => $plan134();
$released134 = static fn (): array => $plan134(null, null, array_slice($triggers134, 1));

$cases134 = [
    'rolled status' => [static fn (): mixed => $rolled134()['status'], 'trigger-savepoint-returning-view-current-source-rolled-back'],
    'savepoint retained' => [static fn (): mixed => $rolled134()['savepoint'], 'wp_import_view_batch'],
    'trigger retained' => [static fn (): mixed => $rolled134()['trigger'], 'wp_options_view_io_update_134'],
    'key retained' => [static fn (): mixed => $rolled134()['key'], 'option_name'],
    'current view source retained' => [static fn (): mixed => $rolled134()['current_view']['source'], 'main@view-cookie-134-current'],
    'next view source retained' => [static fn (): mixed => $rolled134()['next_view']['source'], 'main@view-cookie-134-next'],
    'visible view stays current after rollback' => [static fn (): mixed => $rolled134()['visible_view']['source'], 'main@view-cookie-134-current'],
    'current view columns retained' => [static fn (): mixed => $rolled134()['current_view']['columns'], ['option_name', 'option_value', 'autoload', 'revision']],
    'next view has generated source column' => [static fn (): mixed => $rolled134()['next_view']['columns'], ['option_name', 'option_value', 'autoload', 'revision', 'source']],
    'rollback flag true' => [static fn (): mixed => $rolled134()['rolled_back_to_savepoint'], true],
    'rollback reason retained' => [static fn (): mixed => $rolled134()['rollback_reason'], 'theme view trigger rollback'],
    'next source not admitted' => [static fn (): mixed => $rolled134()['next_source_admitted'], false],
    'suppressed next source retained' => [static fn (): mixed => $rolled134()['suppressed_next_source']['source'], 'main@view-cookie-134-next'],
    'changes zero after rollback' => [static fn (): mixed => $rolled134()['changes'], 0],
    'discarded returning count current rows only' => [static fn (): mixed => $rolled134()['discarded_returning_count'], 1],
    'current returning count stops at rollback' => [static fn (): mixed => count($rolled134()['current_returning_rows']), 1],
    'current returning phase' => [static fn (): mixed => $rolled134()['current_returning_rows'][0]['phase'], 'current'],
    'current returning source' => [static fn (): mixed => $rolled134()['current_returning_rows'][0]['source'], 'main@view-cookie-134-current'],
    'current returning view' => [static fn (): mixed => $rolled134()['current_returning_rows'][0]['view'], 'wp_autoload_options_view'],
    'current returning event update' => [static fn (): mixed => $rolled134()['current_returning_rows'][0]['event'], 'update'],
    'current returning name' => [static fn (): mixed => $rolled134()['current_returning_rows'][0]['returning']['option_name'], 'theme_mods'],
    'current returning new value' => [static fn (): mixed => $rolled134()['current_returning_rows'][0]['returning']['value'], 'broken-view-trigger'],
    'current returning old value' => [static fn (): mixed => $rolled134()['current_returning_rows'][0]['returning']['old_value'], 'a:0:{}'],
    'current returning incoming value' => [static fn (): mixed => $rolled134()['current_returning_rows'][0]['returning']['incoming_value'], 'broken-view-trigger'],
    'current returning source token alias' => [static fn (): mixed => $rolled134()['current_returning_rows'][0]['returning']['source_token'], 'main@view-cookie-134-current'],
    'current returning event alias' => [static fn (): mixed => $rolled134()['current_returning_rows'][0]['returning']['event_name'], 'update'],
    'current returning ordinal alias' => [static fn (): mixed => $rolled134()['current_returning_rows'][0]['returning']['ordinal_value'], 0],
    'current callable returning trace' => [static fn (): mixed => $rolled134()['current_returning_rows'][0]['returning']['expr7'], 'main@view-cookie-134-current:update:0:theme_mods>theme_mods'],
    'next returning suppressed' => [static fn (): mixed => $rolled134()['next_returning_rows'], []],
    'attempted next returning retained count' => [static fn (): mixed => count($rolled134()['attempted_next_returning_rows']), 2],
    'attempted next returning sources' => [static fn (): mixed => array_column($rolled134()['attempted_next_returning_rows'], 'source'), ['main@view-cookie-134-next', 'main@view-cookie-134-next']],
    'attempted next returning names' => [static fn (): mixed => array_column(array_column($rolled134()['attempted_next_returning_rows'], 'returning'), 'option_name'), ['rewrite_rules', 'siteurl']],
    'attempted next returning generated source token' => [static fn (): mixed => array_column(array_column($rolled134()['attempted_next_returning_rows'], 'returning'), 'source_token'), ['main@view-cookie-134-next', 'main@view-cookie-134-next']],
    'yield stream only current yielded row' => [static fn (): mixed => count($rolled134()['yield_stream']), 1],
    'yield stream marked rolled back' => [static fn (): mixed => $rolled134()['yield_stream'][0]['rolled_back_after_yield'], true],
    'yield stream savepoint retained' => [static fn (): mixed => $rolled134()['yield_stream'][0]['savepoint'], 'wp_import_view_batch'],
    'yield stream row key retained' => [static fn (): mixed => $rolled134()['yield_stream'][0]['row_key'], 'theme_mods'],
    'attempted next yield retained count' => [static fn (): mixed => count($rolled134()['attempted_next_yield_stream']), 2],
    'attempted next yield is not exposed' => [static fn (): mixed => $rolled134()['next_yield_stream'], []],
    'current rows include rolled back mutation evidence' => [static fn (): mixed => array_column($rolled134()['current_rows'], 'option_name'), ['siteurl', 'home', 'theme_mods']],
    'current rows mutation value evidence' => [static fn (): mixed => $rolled134()['current_rows'][2]['option_value'], 'broken-view-trigger'],
    'after savepoint restores names' => [static fn (): mixed => array_column($rolled134()['after_savepoint'], 'option_name'), ['siteurl', 'home', 'theme_mods']],
    'after savepoint restores value' => [static fn (): mixed => $rolled134()['after_savepoint'][2]['option_value'], 'a:0:{}'],
    'after savepoint excludes current insert after abort' => [static fn (): mixed => in_array('plugin_seed', array_column($rolled134()['after_savepoint'], 'option_name'), true), false],
    'current view rows see attempted current mutation' => [static fn (): mixed => array_column($rolled134()['current_view_rows'], 'option_name'), ['home', 'siteurl', 'theme_mods']],
    'current view rows include changed autoload theme' => [static fn (): mixed => $rolled134()['current_view_rows'][2]['option_value'], 'broken-view-trigger'],
    'next view rows suppressed after rollback' => [static fn (): mixed => $rolled134()['next_view_rows'], []],
    'attempted next view rows include generated column' => [static fn (): mixed => $rolled134()['attempted_next_view_rows'][2]['source'], 'next-import'],
    'trigger effect count includes rollback and attempted next audit' => [static fn (): mixed => count($rolled134()['trigger_effects_before_rollback']), 2],
    'rollback trigger effect first' => [static fn (): mixed => $rolled134()['trigger_effects_before_rollback'][0]['trigger'], 'wp_options_view_after_update_guard'],
    'rollback trigger effect raise' => [static fn (): mixed => $rolled134()['trigger_effects_before_rollback'][0]['raise'], 'rollback'],
    'rollback trigger effect source token' => [static fn (): mixed => $rolled134()['trigger_effects_before_rollback'][0]['row']['source_token'], 'main@view-cookie-134-current'],
    'attempted next audit effect retained' => [static fn (): mixed => $rolled134()['trigger_effects_before_rollback'][1]['trigger'], 'wp_options_view_next_audit'],
    'yield boundary records rollback source' => [static fn (): mixed => $rolled134()['yield_boundary'], 'view-returning-yield-then-savepoint-rollback-keeps-current-source'],
    'dependency includes next134' => [static fn (): mixed => in_array('sqlite-trigger-savepoint-returning-view-current-source', $rolled134()['dependencies'], true), true],
    'dependency includes returning before rollback' => [static fn (): mixed => in_array('sqlite-returning-yield-before-view-trigger-rollback', $rolled134()['dependencies'], true), true],
    'dependency includes next view blocked' => [static fn (): mixed => in_array('sqlite-next-view-source-blocked-by-savepoint-rollback', $rolled134()['dependencies'], true), true],

    'released status' => [static fn (): mixed => $released134()['status'], 'trigger-savepoint-returning-view-next-source-admitted'],
    'released visible view is next' => [static fn (): mixed => $released134()['visible_view']['source'], 'main@view-cookie-134-next'],
    'released next source admitted' => [static fn (): mixed => $released134()['next_source_admitted'], true],
    'released changes count current plus next' => [static fn (): mixed => $released134()['changes'], 4],
    'released current returning names' => [static fn (): mixed => array_column(array_column($released134()['current_returning_rows'], 'returning'), 'option_name'), ['theme_mods', 'plugin_seed']],
    'released next returning names' => [static fn (): mixed => array_column(array_column($released134()['next_returning_rows'], 'returning'), 'option_name'), ['rewrite_rules', 'siteurl']],
    'released yield phases' => [static fn (): mixed => array_column($released134()['yield_stream'], 'phase'), ['current', 'current', 'next', 'next']],
    'released yield rollback flags false' => [static fn (): mixed => array_column($released134()['yield_stream'], 'rolled_back_after_yield'), [false, false, false, false]],
    'released final includes plugin and rewrite' => [static fn (): mixed => array_column($released134()['after_savepoint'], 'option_name'), ['siteurl', 'home', 'theme_mods', 'plugin_seed', 'rewrite_rules']],
    'released final siteurl next value' => [static fn (): mixed => $released134()['after_savepoint'][0]['option_value'], 'https://next.test'],
    'released next view rows use generated source column' => [static fn (): mixed => array_column($released134()['next_view_rows'], 'source'), ['seed', 'current-import', 'next-import', 'next-import', 'current-import']],
    'released boundary records next source' => [static fn (): mixed => $released134()['yield_boundary'], 'view-returning-yield-then-release-admits-next-source'],

    'custom savepoint accepted' => [static fn (): mixed => $plan134(null, null, null, null, null, null, ['savepoint' => 'wp_retry_view'])['savepoint'], 'wp_retry_view'],
    'empty returning throws' => [static fn (): mixed => $plan134(null, null, null, null, null, []), InvalidArgumentException::class],
    'bad savepoint throws' => [static fn (): mixed => $plan134(null, null, null, null, null, null, ['savepoint' => 'bad savepoint']), InvalidArgumentException::class],
    'bad key throws' => [static fn (): mixed => $plan134(null, null, null, null, null, null, ['key' => 'bad-key']), InvalidArgumentException::class],
    'bad view source throws' => [static fn (): mixed => $plan134(null, null, null, ['name' => 'v', 'source' => 'bad source', 'columns' => ['option_name']]), InvalidArgumentException::class],
    'empty view columns throws' => [static fn (): mixed => $plan134(null, null, null, ['name' => 'v', 'source' => 'ok', 'columns' => []]), InvalidArgumentException::class],
    'bad view where throws' => [static fn (): mixed => $plan134(null, null, null, ['name' => 'v', 'source' => 'ok', 'columns' => ['option_name'], 'where' => 'no']), InvalidArgumentException::class],
    'missing mutation key throws' => [static fn (): mixed => $plan134([['option_value' => 'x']]), InvalidArgumentException::class],
    'duplicate row key throws' => [static fn (): mixed => SQLiteTriggerSavepointReturningViewCurrentSourceNextPlan::executeViewSavepointReturningRollback(array_merge($rows134, [['option_name' => 'siteurl']]), $currentMutations134, $nextMutations134, $currentView134, $nextView134, $triggers134, $returning134), InvalidArgumentException::class],
    'bad when operator throws' => [static fn (): mixed => $plan134(null, null, [['name' => 'bad', 'phase' => 'current', 'event' => 'update', 'when' => ['new.option_name', 'LIKE', 'theme_mods']]]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases134 as $name => [$callback, $expected]) {
    $tests['trigger savepoint returning view current source next134 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
