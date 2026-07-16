<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerUpsertSavepointReturningCurrentSourceNextPlan;

$rows132 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'revision' => 1, 'source' => 'seed'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'revision' => 1, 'source' => 'seed'],
    ['option_id' => 3, 'option_name' => 'theme_mods', 'option_value' => 'a:0:{}', 'autoload' => 'no', 'revision' => 2, 'source' => 'seed'],
];
$incoming132 = [
    ['option_id' => 4, 'option_name' => 'plugin_seed', 'option_value' => '{"enabled":true}', 'autoload' => 'no', 'revision' => 1, 'source' => 'import'],
    ['option_id' => 5, 'option_name' => 'siteurl', 'option_value' => 'https://new.test', 'autoload' => 'yes', 'revision' => 2, 'source' => 'import'],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'option_value' => 'broken-trigger', 'autoload' => 'no', 'revision' => 3, 'source' => 'import'],
];
$assignments132 = [
    'option_id' => static fn (array $old, array $incoming): int => $old['option_id'],
    'option_value' => static fn (array $old, array $incoming): string => $incoming['option_value'],
    'autoload' => static fn (array $old, array $incoming): string => $incoming['autoload'],
    'revision' => static fn (array $old, array $incoming): int => $old['revision'] + 1,
    'source' => static fn (array $old, array $incoming): string => $incoming['source'],
];
$triggers132 = [
    [
        'name' => 'wp_options_bu_audit',
        'timing' => 'before',
        'event' => 'update',
        'values' => ['old_name' => 'old.option_name', 'new_value' => 'new.option_value'],
    ],
    [
        'name' => 'wp_options_au_revision_stamp',
        'timing' => 'after',
        'event' => 'update',
        'mutate_target' => true,
        'set' => ['source' => 'after-trigger'],
        'values' => ['name' => 'new.option_name', 'revision' => 'new.revision'],
    ],
    [
        'name' => 'wp_options_ai_autoload_stamp',
        'timing' => 'after',
        'event' => 'insert',
        'mutate_target' => true,
        'set' => ['source' => 'after-insert'],
        'values' => ['name' => 'new.option_name', 'autoload' => 'new.autoload'],
    ],
    [
        'name' => 'wp_options_au_theme_guard',
        'timing' => 'after',
        'event' => 'update',
        'when' => ['new.option_name', '=', 'theme_mods'],
        'raise' => 'rollback',
        'reason' => 'theme mods trigger abort',
        'values' => ['name' => 'new.option_name', 'value' => 'new.option_value'],
    ],
];
$returning132 = [
    'option_id',
    'option_name',
    'option_value',
    'source',
    ['expr' => 'new.revision', 'as' => 'next_revision'],
    static fn (array $new, ?array $old, string $action, int $statement): string => $statement . ':' . $action . ':' . ($old['revision'] ?? 0) . '>' . $new['revision'],
];

$plan132 = static fn (array $incoming = null, array $triggers = null, ?callable $where = null, string $savepoint = 'wp_import_batch'): array => SQLiteTriggerUpsertSavepointReturningCurrentSourceNextPlan::executeWithinSavepoint(
    $savepoint,
    $rows132,
    $incoming ?? $incoming132,
    ['option_name'],
    $assignments132,
    $triggers ?? $triggers132,
    $returning132,
    $where,
);
$rolled132 = static fn (): array => $plan132();
$released132 = static fn (): array => $plan132($incoming132, array_slice($triggers132, 0, 3));
$where132 = static fn (): array => $plan132($incoming132, array_slice($triggers132, 0, 3), static fn (array $old, array $incoming): bool => $incoming['revision'] > $old['revision']);

$cases132 = [
    'savepoint name is preserved' => [static fn (): mixed => $rolled132()['savepoint'], 'wp_import_batch'],
    'status records current source behavior' => [static fn (): mixed => $rolled132()['status'], 'trigger-upsert-savepoint-returning-current-source-next132-ready'],
    'rollback flag is set after trigger raise rollback' => [static fn (): mixed => $rolled132()['rolled_back_to_savepoint'], true],
    'rollback reason comes from after trigger' => [static fn (): mixed => $rolled132()['rollback_reason'], 'theme mods trigger abort'],
    'three rows yield RETURNING before rollback' => [static fn (): mixed => count($rolled132()['current_returning']), 3],
    'next returning is empty after rollback' => [static fn (): mixed => $rolled132()['next_returning'], []],
    'discarded returning count matches yielded rows' => [static fn (): mixed => $rolled132()['discarded_returning_count'], 3],
    'changes are zero after savepoint rollback' => [static fn (): mixed => $rolled132()['changes'], 0],
    'returning option ids preserve insert and conflict targets' => [static fn (): mixed => array_column($rolled132()['current_returning'], 'option_id'), [4, 1, 3]],
    'returning option names preserve statement order' => [static fn (): mixed => array_column($rolled132()['current_returning'], 'option_name'), ['plugin_seed', 'siteurl', 'theme_mods']],
    'returning option values see assigned row images' => [static fn (): mixed => array_column($rolled132()['current_returning'], 'option_value'), ['{"enabled":true}', 'https://new.test', 'broken-trigger']],
    'returning source is pre after-trigger mutation' => [static fn (): mixed => array_column($rolled132()['current_returning'], 'source'), ['import', 'import', 'import']],
    'returning revisions use inserted and updated values' => [static fn (): mixed => array_column($rolled132()['current_returning'], 'next_revision'), [1, 2, 3]],
    'returning callable traces action and old revision' => [static fn (): mixed => array_column($rolled132()['current_returning'], 'expr5'), ['0:insert:0>1', '1:update:1>2', '2:update:2>3']],
    'yield stream statements are ordered' => [static fn (): mixed => array_column($rolled132()['yield_stream'], 'statement'), [0, 1, 2]],
    'yield stream actions distinguish insert update update' => [static fn (): mixed => array_column($rolled132()['yield_stream'], 'action'), ['insert', 'update', 'update']],
    'yield stream marks rows rolled back after yield' => [static fn (): mixed => array_column($rolled132()['yield_stream'], 'rolled_back_after_yield'), [true, true, true]],
    'yield stream carries savepoint on each row' => [static fn (): mixed => array_column($rolled132()['yield_stream'], 'savepoint'), ['wp_import_batch', 'wp_import_batch', 'wp_import_batch']],
    'yield stream embeds returning names' => [static fn (): mixed => array_column(array_column($rolled132()['yield_stream'], 'returning'), 'option_name'), ['plugin_seed', 'siteurl', 'theme_mods']],
    'after statement has inserted plugin row before rollback' => [static fn (): mixed => array_column($rolled132()['after_statement'], 'option_name'), ['siteurl', 'home', 'theme_mods', 'plugin_seed']],
    'after statement applies after trigger source stamps' => [static fn (): mixed => array_column($rolled132()['after_statement'], 'source'), ['after-trigger', 'seed', 'after-trigger', 'after-insert']],
    'after statement keeps updated siteurl value' => [static fn (): mixed => array_column($rolled132()['after_statement'], 'option_value', 'option_name')['siteurl'], 'https://new.test'],
    'after statement keeps updated theme value before rollback' => [static fn (): mixed => array_column($rolled132()['after_statement'], 'option_value', 'option_name')['theme_mods'], 'broken-trigger'],
    'after savepoint restores original option names' => [static fn (): mixed => array_column($rolled132()['after_savepoint'], 'option_name'), ['siteurl', 'home', 'theme_mods']],
    'after savepoint restores original option values' => [static fn (): mixed => array_column($rolled132()['after_savepoint'], 'option_value'), ['https://old.test', 'https://old.test', 'a:0:{}']],
    'after savepoint removes inserted plugin row' => [static fn (): mixed => in_array('plugin_seed', array_column($rolled132()['after_savepoint'], 'option_name'), true), false],
    'after savepoint restores original sources' => [static fn (): mixed => array_column($rolled132()['after_savepoint'], 'source'), ['seed', 'seed', 'seed']],
    'inserted rows are discarded by rollback' => [static fn (): mixed => $rolled132()['inserted_rows'], []],
    'updated rows are discarded by rollback' => [static fn (): mixed => $rolled132()['updated_rows'], []],
    'skipped rows remain empty in rollback path' => [static fn (): mixed => $rolled132()['skipped_rows'], []],
    'trigger effects include insert stamp' => [static fn (): mixed => $rolled132()['trigger_effects_before_rollback'][0]['trigger'], 'wp_options_ai_autoload_stamp'],
    'trigger effects include before update audit' => [static fn (): mixed => $rolled132()['trigger_effects_before_rollback'][1]['trigger'], 'wp_options_bu_audit'],
    'trigger effects include siteurl after update stamp' => [static fn (): mixed => $rolled132()['trigger_effects_before_rollback'][2]['trigger'], 'wp_options_au_revision_stamp'],
    'trigger effects include theme rollback trigger' => [static fn (): mixed => $rolled132()['trigger_effects_before_rollback'][5]['trigger'], 'wp_options_au_theme_guard'],
    'rollback trigger carries raise mode' => [static fn (): mixed => $rolled132()['trigger_effects_before_rollback'][5]['raise'], 'rollback'],
    'before update audit sees old siteurl' => [static fn (): mixed => $rolled132()['trigger_effects_before_rollback'][1]['row']['old_name'], 'siteurl'],
    'before update audit sees new theme value' => [static fn (): mixed => $rolled132()['trigger_effects_before_rollback'][3]['row']['new_value'], 'broken-trigger'],
    'dependencies include returning before rollback marker' => [static fn (): mixed => in_array('sqlite-returning-yield-before-savepoint-rollback', $rolled132()['dependencies'], true), true],
    'dependencies include after trigger mutation marker' => [static fn (): mixed => in_array('sqlite-after-trigger-mutation-hidden-from-returning', $rolled132()['dependencies'], true), true],
    'dependencies include savepoint restore marker' => [static fn (): mixed => in_array('sqlite-savepoint-restores-current-source-after-trigger-rollback', $rolled132()['dependencies'], true), true],

    'release path is not rolled back' => [static fn (): mixed => $released132()['rolled_back_to_savepoint'], false],
    'release path keeps next returning rows' => [static fn (): mixed => array_column($released132()['next_returning'], 'option_name'), ['plugin_seed', 'siteurl', 'theme_mods']],
    'release path changes equal yielded rows' => [static fn (): mixed => $released132()['changes'], 3],
    'release path inserted row survives' => [static fn (): mixed => array_column($released132()['inserted_rows'], 'option_name'), ['plugin_seed']],
    'release path updated rows survive' => [static fn (): mixed => array_column($released132()['updated_rows'], 'option_name'), ['siteurl', 'theme_mods']],
    'release path final sources include after triggers' => [static fn (): mixed => array_column($released132()['after_savepoint'], 'source'), ['after-trigger', 'seed', 'after-trigger', 'after-insert']],
    'release path yield stream is not rollback marked' => [static fn (): mixed => array_column($released132()['yield_stream'], 'rolled_back_after_yield'), [false, false, false]],
    'where path skips stale theme update' => [static fn (): mixed => array_column($where132()['skipped_rows'], 'option_name'), []],
    'where path can skip equal revision update' => [static fn (): mixed => array_column($plan132([['option_id' => 5, 'option_name' => 'siteurl', 'option_value' => 'same', 'autoload' => 'yes', 'revision' => 1, 'source' => 'import']], array_slice($triggers132, 0, 3), static fn (array $old, array $incoming): bool => $incoming['revision'] > $old['revision'])['skipped_rows'], 'option_name'), ['siteurl']],
    'where skipped update yields no returning row' => [static fn (): mixed => $plan132([['option_id' => 5, 'option_name' => 'siteurl', 'option_value' => 'same', 'autoload' => 'yes', 'revision' => 1, 'source' => 'import']], array_slice($triggers132, 0, 3), static fn (array $old, array $incoming): bool => $incoming['revision'] > $old['revision'])['current_returning'], []],
    'custom savepoint is accepted' => [static fn (): mixed => $plan132($incoming132, $triggers132, null, 'wp_retry')['savepoint'], 'wp_retry'],
    'empty savepoint throws' => [static fn (): mixed => $plan132($incoming132, $triggers132, null, '   '), InvalidArgumentException::class],
    'missing unique incoming column throws' => [static fn (): mixed => $plan132([['option_id' => 9]], $triggers132), InvalidArgumentException::class],
    'bad unique column definition throws' => [static fn (): mixed => SQLiteTriggerUpsertSavepointReturningCurrentSourceNextPlan::executeWithinSavepoint('x', $rows132, $incoming132, [''], $assignments132, $triggers132, $returning132), InvalidArgumentException::class],
    'bad returning OLD on insert throws' => [static fn (): mixed => SQLiteTriggerUpsertSavepointReturningCurrentSourceNextPlan::executeWithinSavepoint('x', $rows132, [['option_id' => 9, 'option_name' => 'new_option', 'option_value' => 'x', 'autoload' => 'no', 'revision' => 1, 'source' => 'import']], ['option_name'], $assignments132, [], ['old.option_name']), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases132 as $name => [$callback, $expected]) {
    $tests['trigger upsert savepoint returning current source next132 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
