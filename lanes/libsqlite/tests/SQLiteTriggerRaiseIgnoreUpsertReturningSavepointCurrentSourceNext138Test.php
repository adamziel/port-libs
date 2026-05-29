<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerUpsertSavepointReturningCurrentSourceNextPlan;

$rows138 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'revision' => 1, 'source' => 'seed'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'revision' => 1, 'source' => 'seed'],
    ['option_id' => 3, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'yes', 'revision' => 2, 'source' => 'seed'],
];
$incoming138 = [
    ['option_id' => 4, 'option_name' => 'siteurl', 'option_value' => 'https://blocked.test', 'autoload' => 'yes', 'revision' => 2, 'source' => 'import'],
    ['option_id' => 5, 'option_name' => '_transient_import_lock', 'option_value' => 'lock', 'autoload' => 'no', 'revision' => 1, 'source' => 'import'],
    ['option_id' => 6, 'option_name' => 'home', 'option_value' => 'https://new-home.test', 'autoload' => 'yes', 'revision' => 2, 'source' => 'import'],
    ['option_id' => 7, 'option_name' => 'blogname', 'option_value' => 'Imported Blog', 'autoload' => 'yes', 'revision' => 1, 'source' => 'import'],
    ['option_id' => 8, 'option_name' => 'active_plugins', 'option_value' => 'a:1:{i:0;s:13:"plugin/a.php";}', 'autoload' => 'yes', 'revision' => 3, 'source' => 'import'],
];
$assignments138 = [
    'option_id' => static fn (array $old, array $incoming): int => $old['option_id'],
    'option_value' => static fn (array $old, array $incoming): string => $incoming['option_value'],
    'autoload' => static fn (array $old, array $incoming): string => $incoming['autoload'],
    'revision' => static fn (array $old, array $incoming): int => max((int) $old['revision'], (int) $incoming['revision']),
    'source' => static fn (array $old, array $incoming): string => $incoming['source'],
];
$triggers138 = [
    [
        'name' => 'wp_options_bu_ignore_siteurl_import',
        'timing' => 'before',
        'event' => 'update',
        'when' => ['new.option_name', '=', 'siteurl'],
        'raise' => 'ignore',
        'reason' => 'siteurl is protected during import',
        'values' => ['name' => 'new.option_name', 'incoming' => 'new.option_value', 'old' => 'old.option_value'],
    ],
    [
        'name' => 'wp_options_bi_ignore_transient_lock',
        'timing' => 'before',
        'event' => 'insert',
        'when' => ['new.option_name', '=', '_transient_import_lock'],
        'raise' => 'ignore',
        'reason' => 'transient import lock is statement-local',
        'values' => ['name' => 'new.option_name', 'incoming' => 'new.option_value'],
    ],
    [
        'name' => 'wp_options_bu_audit',
        'timing' => 'before',
        'event' => 'update',
        'values' => ['name' => 'new.option_name', 'old_value' => 'old.option_value', 'new_value' => 'new.option_value'],
    ],
    [
        'name' => 'wp_options_au_stamp',
        'timing' => 'after',
        'event' => 'update',
        'mutate_target' => true,
        'set' => ['source' => 'after-update-trigger'],
        'values' => ['name' => 'new.option_name', 'revision' => 'new.revision'],
    ],
    [
        'name' => 'wp_options_ai_stamp',
        'timing' => 'after',
        'event' => 'insert',
        'mutate_target' => true,
        'set' => ['source' => 'after-insert-trigger'],
        'values' => ['name' => 'new.option_name', 'autoload' => 'new.autoload'],
    ],
];
$returning138 = [
    'option_id',
    'option_name',
    'option_value',
    'source',
    ['expr' => 'new.revision', 'as' => 'next_revision'],
    static fn (array $new, ?array $old, string $action, int $statement): string => $statement . ':' . $action . ':' . ($old['revision'] ?? 0) . '>' . $new['revision'],
];

$plan138 = static fn (array $incoming = null, array $triggers = null, ?callable $where = null): array => SQLiteTriggerUpsertSavepointReturningCurrentSourceNextPlan::executeWithinSavepoint(
    'wp_import_ignore',
    $rows138,
    $incoming ?? $incoming138,
    ['option_name'],
    $assignments138,
    $triggers ?? $triggers138,
    $returning138,
    $where,
);
$ignored138 = static fn (): array => $plan138();
$noIgnore138 = static fn (): array => $plan138($incoming138, array_slice($triggers138, 2));
$where138 = static fn (): array => $plan138($incoming138, $triggers138, static fn (array $old, array $incoming): bool => $incoming['revision'] > $old['revision']);

$cases138 = [
    'status stays current source upsert returning planner' => [static fn (): mixed => $ignored138()['status'], 'trigger-upsert-savepoint-returning-current-source-next132-ready'],
    'savepoint name is preserved' => [static fn (): mixed => $ignored138()['savepoint'], 'wp_import_ignore'],
    'raise ignore does not roll back savepoint' => [static fn (): mixed => $ignored138()['rolled_back_to_savepoint'], false],
    'raise ignore has no rollback reason' => [static fn (): mixed => $ignored138()['rollback_reason'], null],
    'raise ignore rows are listed separately' => [static fn (): mixed => count($ignored138()['ignored_rows']), 2],
    'ignored statements preserve input positions' => [static fn (): mixed => array_column($ignored138()['ignored_rows'], 'statement'), [0, 1]],
    'ignored actions preserve update insert order' => [static fn (): mixed => array_column($ignored138()['ignored_rows'], 'action'), ['update', 'insert']],
    'ignored reasons come from triggers' => [static fn (): mixed => array_column($ignored138()['ignored_rows'], 'reason'), ['siteurl is protected during import', 'transient import lock is statement-local']],
    'ignored update row is the incoming conflict row' => [static fn (): mixed => $ignored138()['ignored_rows'][0]['row']['option_value'], 'https://blocked.test'],
    'ignored insert row is the transient lock row' => [static fn (): mixed => $ignored138()['ignored_rows'][1]['row']['option_name'], '_transient_import_lock'],
    'current returning excludes ignored rows' => [static fn (): mixed => array_column($ignored138()['current_returning'], 'option_name'), ['home', 'blogname', 'active_plugins']],
    'next returning equals current returning after release' => [static fn (): mixed => $ignored138()['next_returning'], $ignored138()['current_returning']],
    'returning option ids preserve target rows and inserts' => [static fn (): mixed => array_column($ignored138()['current_returning'], 'option_id'), [2, 7, 3]],
    'returning option values use candidate rows' => [static fn (): mixed => array_column($ignored138()['current_returning'], 'option_value'), ['https://new-home.test', 'Imported Blog', 'a:1:{i:0;s:13:"plugin/a.php";}']],
    'returning source is captured before after triggers' => [static fn (): mixed => array_column($ignored138()['current_returning'], 'source'), ['import', 'import', 'import']],
    'returning revisions preserve update insert update' => [static fn (): mixed => array_column($ignored138()['current_returning'], 'next_revision'), [2, 1, 3]],
    'returning callable statement indexes skip ignored output but not input positions' => [static fn (): mixed => array_column($ignored138()['current_returning'], 'expr5'), ['2:update:1>2', '3:insert:0>1', '4:update:2>3']],
    'yield stream omits ignored rows' => [static fn (): mixed => array_column($ignored138()['yield_stream'], 'statement'), [2, 3, 4]],
    'yield stream actions preserve changed rows' => [static fn (): mixed => array_column($ignored138()['yield_stream'], 'action'), ['update', 'insert', 'update']],
    'yield stream is not rollback marked' => [static fn (): mixed => array_column($ignored138()['yield_stream'], 'rolled_back_after_yield'), [false, false, false]],
    'yield stream carries savepoint' => [static fn (): mixed => array_column($ignored138()['yield_stream'], 'savepoint'), ['wp_import_ignore', 'wp_import_ignore', 'wp_import_ignore']],
    'changes count excludes ignored rows' => [static fn (): mixed => $ignored138()['changes'], 3],
    'discarded returning count is zero without rollback' => [static fn (): mixed => $ignored138()['discarded_returning_count'], 0],
    'inserted rows exclude ignored transient lock' => [static fn (): mixed => array_column($ignored138()['inserted_rows'], 'option_name'), ['blogname']],
    'updated rows exclude ignored siteurl conflict' => [static fn (): mixed => array_column($ignored138()['updated_rows'], 'option_name'), ['home', 'active_plugins']],
    'skipped rows remain separate from ignored rows' => [static fn (): mixed => $ignored138()['skipped_rows'], []],
    'after statement preserves protected siteurl' => [static fn (): mixed => array_column($ignored138()['after_statement'], 'option_value', 'option_name')['siteurl'], 'https://old.test'],
    'after statement omits ignored transient lock insert' => [static fn (): mixed => in_array('_transient_import_lock', array_column($ignored138()['after_statement'], 'option_name'), true), false],
    'after statement includes later inserted blogname' => [static fn (): mixed => array_column($ignored138()['after_statement'], 'option_value', 'option_name')['blogname'], 'Imported Blog'],
    'after statement updates home after ignored rows' => [static fn (): mixed => array_column($ignored138()['after_statement'], 'option_value', 'option_name')['home'], 'https://new-home.test'],
    'after statement updates active plugins after ignored rows' => [static fn (): mixed => array_column($ignored138()['after_statement'], 'option_value', 'option_name')['active_plugins'], 'a:1:{i:0;s:13:"plugin/a.php";}'],
    'after statement applies after-trigger stamps only to changed rows' => [static fn (): mixed => array_column($ignored138()['after_statement'], 'source'), ['seed', 'after-update-trigger', 'after-update-trigger', 'after-insert-trigger']],
    'after savepoint equals statement image when released' => [static fn (): mixed => $ignored138()['after_savepoint'], $ignored138()['after_statement']],
    'before trigger effects include ignored update guard' => [static fn (): mixed => $ignored138()['trigger_effects_before_rollback'][0]['trigger'], 'wp_options_bu_ignore_siteurl_import'],
    'ignored update trigger records raise ignore' => [static fn (): mixed => $ignored138()['trigger_effects_before_rollback'][0]['raise'], 'ignore'],
    'ignored update trigger sees old value' => [static fn (): mixed => $ignored138()['trigger_effects_before_rollback'][0]['row']['old'], 'https://old.test'],
    'ignored update still records later before audit' => [static fn (): mixed => $ignored138()['trigger_effects_before_rollback'][1]['trigger'], 'wp_options_bu_audit'],
    'ignored insert trigger records raise ignore' => [static fn (): mixed => $ignored138()['trigger_effects_before_rollback'][2]['raise'], 'ignore'],
    'nonignored update audit follows ignored rows' => [static fn (): mixed => $ignored138()['trigger_effects_before_rollback'][3]['trigger'], 'wp_options_bu_audit'],
    'after update stamp follows audit for home' => [static fn (): mixed => $ignored138()['trigger_effects_before_rollback'][4]['trigger'], 'wp_options_au_stamp'],
    'after insert stamp fires for blogname only' => [static fn (): mixed => $ignored138()['trigger_effects_before_rollback'][5]['trigger'], 'wp_options_ai_stamp'],
    'active plugins audit records new value' => [static fn (): mixed => $ignored138()['trigger_effects_before_rollback'][6]['row']['new_value'], 'a:1:{i:0;s:13:"plugin/a.php";}'],
    'dependencies include ignored returning suppression marker' => [static fn (): mixed => in_array('sqlite-trigger-raise-ignore-suppresses-returning-row', $ignored138()['dependencies'], true), true],
    'dependencies retain returning yield marker' => [static fn (): mixed => in_array('sqlite-upsert-returning-trigger-current-source', $ignored138()['dependencies'], true), true],
    'no ignore path returns all five input rows' => [static fn (): mixed => array_column($noIgnore138()['current_returning'], 'option_name'), ['siteurl', '_transient_import_lock', 'home', 'blogname', 'active_plugins']],
    'no ignore path changes all five rows' => [static fn (): mixed => $noIgnore138()['changes'], 5],
    'no ignore path inserts transient lock' => [static fn (): mixed => in_array('_transient_import_lock', array_column($noIgnore138()['after_statement'], 'option_name'), true), true],
    'no ignore path updates siteurl' => [static fn (): mixed => array_column($noIgnore138()['after_statement'], 'option_value', 'option_name')['siteurl'], 'https://blocked.test'],
    'where predicate skip is not classified as ignore' => [static fn (): mixed => array_column($where138()['skipped_rows'], 'option_name'), []],
    'where predicate false still records skipped conflict' => [static fn (): mixed => array_column($plan138([['option_id' => 9, 'option_name' => 'home', 'option_value' => 'same', 'autoload' => 'yes', 'revision' => 1, 'source' => 'import']], $triggers138, static fn (array $old, array $incoming): bool => $incoming['revision'] > $old['revision'])['skipped_rows'], 'option_name'), ['home']],
    'where predicate skipped conflict has no ignored row' => [static fn (): mixed => $plan138([['option_id' => 9, 'option_name' => 'home', 'option_value' => 'same', 'autoload' => 'yes', 'revision' => 1, 'source' => 'import']], $triggers138, static fn (array $old, array $incoming): bool => $incoming['revision'] > $old['revision'])['ignored_rows'], []],
    'ignore trigger can suppress only insert' => [static fn (): mixed => array_column($plan138([['_bad' => 'unused', 'option_id' => 5, 'option_name' => '_transient_import_lock', 'option_value' => 'lock', 'autoload' => 'no', 'revision' => 1, 'source' => 'import']], $triggers138)['ignored_rows'], 'action'), ['insert']],
    'ignore trigger can suppress only update' => [static fn (): mixed => array_column($plan138([['option_id' => 4, 'option_name' => 'siteurl', 'option_value' => 'https://blocked.test', 'autoload' => 'yes', 'revision' => 2, 'source' => 'import']], $triggers138)['ignored_rows'], 'action'), ['update']],
    'ignore-only insert yields no returning rows' => [static fn (): mixed => $plan138([['option_id' => 5, 'option_name' => '_transient_import_lock', 'option_value' => 'lock', 'autoload' => 'no', 'revision' => 1, 'source' => 'import']], $triggers138)['current_returning'], []],
    'ignore-only update yields no returning rows' => [static fn (): mixed => $plan138([['option_id' => 4, 'option_name' => 'siteurl', 'option_value' => 'https://blocked.test', 'autoload' => 'yes', 'revision' => 2, 'source' => 'import']], $triggers138)['current_returning'], []],
    'ignore-only rows do not change count' => [static fn (): mixed => $plan138([['option_id' => 4, 'option_name' => 'siteurl', 'option_value' => 'https://blocked.test', 'autoload' => 'yes', 'revision' => 2, 'source' => 'import']], $triggers138)['changes'], 0],
    'ignore-only update keeps after savepoint original' => [static fn (): mixed => array_column($plan138([['option_id' => 4, 'option_name' => 'siteurl', 'option_value' => 'https://blocked.test', 'autoload' => 'yes', 'revision' => 2, 'source' => 'import']], $triggers138)['after_savepoint'], 'option_value', 'option_name')['siteurl'], 'https://old.test'],
    'unsupported raise mode is only recorded when not rollback or ignore' => [static fn (): mixed => $plan138([['option_id' => 9, 'option_name' => 'blogdescription', 'option_value' => 'tagline', 'autoload' => 'yes', 'revision' => 1, 'source' => 'import']], [[
        'name' => 'wp_options_bi_note',
        'timing' => 'before',
        'event' => 'insert',
        'raise' => 'fail',
        'values' => ['name' => 'new.option_name'],
    ]])['changes'], 1],
    'malformed ignore WHEN throws' => [static fn (): mixed => $plan138($incoming138, [[
        'name' => 'bad_ignore',
        'timing' => 'before',
        'event' => 'update',
        'when' => ['new.option_name', '='],
        'raise' => 'ignore',
    ]]), InvalidArgumentException::class],
    'ignore trigger with missing new column throws before classification' => [static fn (): mixed => $plan138([['option_id' => 9, 'option_name' => 'blogdescription', 'option_value' => 'tagline', 'autoload' => 'yes', 'revision' => 1, 'source' => 'import']], [[
        'name' => 'bad_value',
        'timing' => 'before',
        'event' => 'insert',
        'raise' => 'ignore',
        'values' => ['missing' => 'new.missing_column'],
    ]]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases138 as $name => [$callback, $expected]) {
    $tests['trigger raise ignore upsert returning savepoint current source next138 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
