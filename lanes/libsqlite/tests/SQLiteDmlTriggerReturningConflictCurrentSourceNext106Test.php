<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDmlTriggerReturningConflictCurrentSourceNextPlan;

$baseRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'revision' => 5],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'revision' => 3],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Old Blog', 'autoload' => 'no', 'revision' => 2],
];

$triggers = [
    [
        'name' => 'wp_options_bi_alias_siteurl',
        'timing' => 'before',
        'event' => 'insert',
        'action' => 'set-new',
        'when' => ['new.option_name', '=', 'siteurl_alias'],
        'set' => ['option_name' => 'siteurl', 'option_id' => 101, 'revision' => 6],
        'values' => ['name' => 'new.option_name', 'id' => 'new.option_id'],
    ],
    [
        'name' => 'wp_options_bd_audit',
        'timing' => 'before',
        'event' => 'delete',
        'action' => 'delete-side',
        'values' => ['deleted_name' => 'old.option_name', 'deleted_id' => 'old.option_id'],
    ],
    [
        'name' => 'wp_options_ad_audit',
        'timing' => 'after',
        'event' => 'delete',
        'action' => 'delete-side',
        'values' => ['deleted_name' => 'old.option_name', 'deleted_id' => 'old.option_id'],
    ],
    [
        'name' => 'wp_options_ai_touch',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'set-new',
        'set' => ['option_value' => 'after-trigger-touch', 'revision' => 999],
        'values' => ['name' => 'new.option_name', 'value' => 'new.option_value', 'revision' => 'new.revision'],
    ],
];

$returning = [
    'option_id',
    'option_name',
    'option_value',
    ['expr' => 'new.revision', 'as' => 'statement_revision'],
    static fn (array $new, ?array $old, int $ordinal, string $event): string => $event . ':' . $ordinal . ':' . $new['option_name'],
];

$run = static function (array $incoming, string $conflictAction = 'replace', array $options = [], ?array $projection = null) use ($baseRows, $triggers, $returning): array {
    return SQLiteDmlTriggerReturningConflictCurrentSourceNextPlan::insertRows(
        $baseRows,
        $incoming,
        ['option_name'],
        $triggers,
        $conflictAction,
        $projection ?? $returning,
        $options,
    );
};

$replacePlan = static fn (): array => $run([
    ['option_id' => 10, 'option_name' => 'siteurl_alias', 'option_value' => 'https://new.test', 'autoload' => 'yes', 'revision' => 1],
    ['option_id' => 20, 'option_name' => 'fresh_plugin', 'option_value' => 'enabled', 'autoload' => 'no', 'revision' => 1],
    ['option_id' => 21, 'option_name' => 'fresh_plugin', 'option_value' => 'enabled-2', 'autoload' => 'yes', 'revision' => 2],
]);

$ignorePlan = static fn (): array => $run([
    ['option_id' => 30, 'option_name' => 'home', 'option_value' => 'ignored', 'autoload' => 'no', 'revision' => 1],
    ['option_id' => 31, 'option_name' => 'fresh_ignored_batch', 'option_value' => 'inserted', 'autoload' => 'yes', 'revision' => 1],
], 'ignore');

$replaceNoRecursiveDeleteTriggers = static fn (): array => $run([
    ['option_id' => 40, 'option_name' => 'home', 'option_value' => 'replace-no-delete-trigger', 'autoload' => 'no', 'revision' => 1],
], 'replace', ['recursive_triggers' => false]);

$nullPlan = static fn (): array => $run([
    ['option_id' => 50, 'option_name' => null, 'option_value' => 'first-null', 'autoload' => 'no', 'revision' => 1],
    ['option_id' => 51, 'option_name' => null, 'option_value' => 'second-null', 'autoload' => 'no', 'revision' => 2],
]);

$cases = [
    'replace changes count includes three inserts and two delete replacements' => [static fn (): mixed => $replacePlan()['changes'], 5],
    'replace final names preserve current source after delete before insert' => [static fn (): mixed => array_column($replacePlan()['rows'], 'option_name'), ['home', 'blogname', 'siteurl', 'fresh_plugin']],
    'replace final siteurl id comes from before trigger mutation' => [static fn (): mixed => array_values(array_filter($replacePlan()['rows'], static fn (array $row): bool => $row['option_name'] === 'siteurl'))[0]['option_id'], 101],
    'replace final fresh plugin is last same statement source' => [static fn (): mixed => array_values(array_filter($replacePlan()['rows'], static fn (array $row): bool => $row['option_name'] === 'fresh_plugin'))[0]['option_value'], 'after-trigger-touch'],
    'replace inserted rows include after trigger target mutations' => [static fn (): mixed => array_column($replacePlan()['inserted'], 'revision'), [999, 999, 999]],
    'replace deleted rows include old siteurl and first fresh plugin' => [static fn (): mixed => array_column($replacePlan()['deleted'], 'option_name'), ['siteurl', 'fresh_plugin']],
    'replace ignored rows empty' => [static fn (): mixed => $replacePlan()['ignored'], []],
    'replace returning rows keep before after-trigger values' => [static fn (): mixed => array_column($replacePlan()['returning_rows'], 'option_value'), ['https://new.test', 'enabled', 'enabled-2']],
    'replace returning revision is before after-trigger revision' => [static fn (): mixed => array_column($replacePlan()['returning_rows'], 'statement_revision'), [6, 1, 2]],
    'replace returning callable sees statement ordinals' => [static fn (): mixed => array_column($replacePlan()['returning_rows'], 'expr4'), ['insert:0:siteurl', 'insert:1:fresh_plugin', 'insert:2:fresh_plugin']],
    'replace yielded statuses all inserted' => [static fn (): mixed => array_column($replacePlan()['yielded'], 'status'), ['inserted', 'inserted', 'inserted']],
    'replace yielded first conflict index is original siteurl' => [static fn (): mixed => $replacePlan()['yielded'][0]['conflict_indexes'], [0]],
    'replace yielded third conflict index points at same-statement current row' => [static fn (): mixed => $replacePlan()['yielded'][2]['conflict_indexes'], [3]],
    'replace yielded returning omits deleted row image' => [static fn (): mixed => $replacePlan()['yielded'][0]['returning']['option_name'], 'siteurl'],
    'replace delete triggers fire when recursive triggers enabled' => [static fn (): mixed => array_values(array_filter(array_column($replacePlan()['trigger_effects'], 'trigger'), static fn (?string $name): bool => $name === 'wp_options_bd_audit' || $name === 'wp_options_ad_audit')), ['wp_options_bd_audit', 'wp_options_ad_audit', 'wp_options_bd_audit', 'wp_options_ad_audit']],
    'replace before insert alias trigger fires once' => [static fn (): mixed => count(array_filter(array_column($replacePlan()['trigger_effects'], 'trigger'), static fn (?string $name): bool => $name === 'wp_options_bi_alias_siteurl')), 1],
    'replace after insert touch fires for all inserted attempts' => [static fn (): mixed => count(array_filter(array_column($replacePlan()['trigger_effects'], 'trigger'), static fn (?string $name): bool => $name === 'wp_options_ai_touch')), 3],
    'replace first delete trigger sees old id' => [static fn (): mixed => $replacePlan()['trigger_effects'][1]['row']['deleted_id'], 1],
    'replace same statement delete trigger sees first fresh plugin id' => [static fn (): mixed => $replacePlan()['trigger_effects'][6]['row']['deleted_id'], 20],
    'replace dependency records current source behavior' => [static fn (): mixed => $replacePlan()['dependencies'][0], 'sqlite-insert-or-replace-returning-current-source-next106'],
    'replace conflict action recorded' => [static fn (): mixed => $replacePlan()['conflict_action'], 'replace'],
    'replace recursive trigger flag recorded' => [static fn (): mixed => $replacePlan()['recursive_triggers'], true],

    'ignore changes only include non-conflicting insert' => [static fn (): mixed => $ignorePlan()['changes'], 1],
    'ignore current conflicting home remains unchanged' => [static fn (): mixed => $ignorePlan()['rows'][1]['option_value'], 'https://home.test'],
    'ignore inserted fresh row appended' => [static fn (): mixed => array_column($ignorePlan()['inserted'], 'option_name'), ['fresh_ignored_batch']],
    'ignore records ignored candidate' => [static fn (): mixed => array_column($ignorePlan()['ignored'], 'option_name'), ['home']],
    'ignore omits returning row for ignored conflict' => [static fn (): mixed => count($ignorePlan()['returning_rows']), 1],
    'ignore yielded statuses show ignored then inserted' => [static fn (): mixed => array_column($ignorePlan()['yielded'], 'status'), ['ignored-conflict', 'inserted']],
    'ignore yielded ignored returning is null' => [static fn (): mixed => $ignorePlan()['yielded'][0]['returning'], null],
    'ignore records conflict effect' => [static fn (): mixed => $ignorePlan()['trigger_effects'][0]['action'], 'ignore'],
    'ignore conflict action recorded' => [static fn (): mixed => $ignorePlan()['conflict_action'], 'ignore'],

    'replace with recursive triggers off still replaces row' => [static fn (): mixed => array_column($replaceNoRecursiveDeleteTriggers()['rows'], 'option_name'), ['siteurl', 'blogname', 'home']],
    'replace with recursive triggers off suppresses delete triggers' => [static fn (): mixed => array_values(array_filter(array_column($replaceNoRecursiveDeleteTriggers()['trigger_effects'], 'event'), static fn (string $event): bool => $event === 'delete')), []],
    'replace with recursive triggers off records false flag' => [static fn (): mixed => $replaceNoRecursiveDeleteTriggers()['recursive_triggers'], false],
    'replace with recursive triggers off changes count includes delete and insert' => [static fn (): mixed => $replaceNoRecursiveDeleteTriggers()['changes'], 2],

    'null unique values do not conflict' => [static fn (): mixed => $nullPlan()['changes'], 2],
    'null unique rows both remain' => [static fn (): mixed => array_column(array_slice($nullPlan()['rows'], -2), 'option_value'), ['after-trigger-touch', 'after-trigger-touch']],
    'null unique returning preserves both statement values' => [static fn (): mixed => array_column($nullPlan()['returning_rows'], 'option_value'), ['first-null', 'second-null']],
    'null unique deleted rows empty' => [static fn (): mixed => $nullPlan()['deleted'], []],

    'star returning contains before after-trigger image' => [static fn (): mixed => $run([['option_id' => 60, 'option_name' => 'star_row', 'option_value' => 'star', 'autoload' => 'no', 'revision' => 1]], 'replace', [], ['*'])['returning_rows'][0]['*']['option_value'], 'star'],
    'new column returning resolves explicit new prefix' => [static fn (): mixed => $run([['option_id' => 61, 'option_name' => 'prefixed_row', 'option_value' => 'prefixed', 'autoload' => 'no', 'revision' => 1]], 'replace', [], [['expr' => 'new.option_name', 'as' => 'name']])['returning_rows'][0]['name'], 'prefixed_row'],
    'abort conflict throws' => [static fn (): mixed => $run([['option_id' => 70, 'option_name' => 'home', 'option_value' => 'abort', 'autoload' => 'no', 'revision' => 1]], 'abort'), InvalidArgumentException::class],
    'fail conflict throws' => [static fn (): mixed => $run([['option_id' => 71, 'option_name' => 'home', 'option_value' => 'fail', 'autoload' => 'no', 'revision' => 1]], 'fail'), InvalidArgumentException::class],
    'rollback conflict throws' => [static fn (): mixed => $run([['option_id' => 72, 'option_name' => 'home', 'option_value' => 'rollback', 'autoload' => 'no', 'revision' => 1]], 'rollback'), InvalidArgumentException::class],
    'unsupported conflict action throws' => [static fn (): mixed => $run([], 'bad'), InvalidArgumentException::class],
    'empty unique columns throw' => [static fn (): mixed => SQLiteDmlTriggerReturningConflictCurrentSourceNextPlan::insertRows($baseRows, [], []), InvalidArgumentException::class],
    'malformed unique column throws' => [static fn (): mixed => SQLiteDmlTriggerReturningConflictCurrentSourceNextPlan::insertRows($baseRows, [], ['bad-column']), InvalidArgumentException::class],
    'missing current unique column throws' => [static fn (): mixed => SQLiteDmlTriggerReturningConflictCurrentSourceNextPlan::insertRows([['option_id' => 1]], [['option_id' => 2, 'option_name' => 'x']], ['option_name']), InvalidArgumentException::class],
    'missing incoming unique column throws' => [static fn (): mixed => SQLiteDmlTriggerReturningConflictCurrentSourceNextPlan::insertRows($baseRows, [['option_id' => 2]], ['option_name']), InvalidArgumentException::class],
    'empty returning projection throws' => [static fn (): mixed => $run([['option_id' => 80, 'option_name' => 'empty_projection', 'option_value' => 'x', 'autoload' => 'no', 'revision' => 1]], 'replace', [], []), InvalidArgumentException::class],
    'malformed returning alias throws' => [static fn (): mixed => $run([['option_id' => 81, 'option_name' => 'bad_alias', 'option_value' => 'x', 'autoload' => 'no', 'revision' => 1]], 'replace', [], [['expr' => 'new.option_name', 'as' => 'bad-alias']]), InvalidArgumentException::class],
    'missing returning column throws' => [static fn (): mixed => $run([['option_id' => 82, 'option_name' => 'missing_returning', 'option_value' => 'x', 'autoload' => 'no', 'revision' => 1]], 'replace', [], ['missing_column']), InvalidArgumentException::class],
    'malformed when clause throws' => [static fn (): mixed => SQLiteDmlTriggerReturningConflictCurrentSourceNextPlan::insertRows($baseRows, [['option_id' => 83, 'option_name' => 'bad_when', 'option_value' => 'x', 'autoload' => 'no', 'revision' => 1]], ['option_name'], [['timing' => 'before', 'event' => 'insert', 'when' => ['new.option_name']]]), InvalidArgumentException::class],
    'unsupported trigger action throws' => [static fn (): mixed => SQLiteDmlTriggerReturningConflictCurrentSourceNextPlan::insertRows($baseRows, [['option_id' => 84, 'option_name' => 'bad_action', 'option_value' => 'x', 'autoload' => 'no', 'revision' => 1]], ['option_name'], [['timing' => 'before', 'event' => 'insert', 'action' => 'upsert-row']]), InvalidArgumentException::class],
    'old reference in insert trigger throws' => [static fn (): mixed => SQLiteDmlTriggerReturningConflictCurrentSourceNextPlan::insertRows($baseRows, [['option_id' => 85, 'option_name' => 'bad_old', 'option_value' => 'x', 'autoload' => 'no', 'revision' => 1]], ['option_name'], [['timing' => 'before', 'event' => 'insert', 'action' => 'audit', 'values' => ['bad' => 'old.option_name']]]), InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['dml trigger returning conflict current source next106 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
