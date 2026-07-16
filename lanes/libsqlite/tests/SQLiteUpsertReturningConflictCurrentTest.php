<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'option_value' => 'https://old.test', 'slot' => 'primary', 'revision' => 1],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'no', 'option_value' => 'https://home.test', 'slot' => 'secondary', 'revision' => 3],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'maybe', 'option_value' => 'Old Blog', 'slot' => 'display', 'revision' => 7],
    ['option_id' => 4, 'option_name' => 'theme_mods', 'autoload' => null, 'option_value' => '{}', 'slot' => 'json', 'revision' => 2],
];

$assignments = [
    'option_id' => static fn (array $current, array $excluded): mixed => $excluded['option_id'],
    'autoload' => static fn (array $current, array $excluded): mixed => $excluded['autoload'],
    'option_value' => static fn (array $current, array $excluded): mixed => $excluded['option_value'],
    'slot' => static fn (array $current, array $excluded): mixed => $excluded['slot'],
    'revision' => static fn (array $current, array $excluded): int => (int) $current['revision'] + (int) $excluded['revision'],
];

$run = static function (array $incomingRows, ?callable $where = null) use ($rows, $assignments): array {
    return SQLiteUpsertDoUpdateWherePlan::execute(
        $rows,
        $incomingRows,
        ['option_name'],
        $assignments,
        $where,
        [['option_name'], ['autoload'], ['slot']],
    );
};

$cleanPlan = static fn (): array => $run([
    ['option_id' => 10, 'option_name' => 'siteurl', 'autoload' => 'manual', 'option_value' => 'https://new.test', 'slot' => 'canonical', 'revision' => 4],
    ['option_id' => 11, 'option_name' => 'fresh_plugin', 'autoload' => 'plugin', 'option_value' => 'enabled', 'slot' => 'plugin', 'revision' => 1],
    ['option_id' => 12, 'option_name' => 'theme_mods', 'autoload' => null, 'option_value' => '{"color":"blue"}', 'slot' => 'json-next', 'revision' => 5],
]);

$repeatPlan = static fn (): array => $run([
    ['option_id' => 20, 'option_name' => 'fresh_slot', 'autoload' => 'fresh-auto', 'option_value' => 'one', 'slot' => 'fresh-slot', 'revision' => 1],
    ['option_id' => 21, 'option_name' => 'fresh_slot', 'autoload' => 'fresh-auto-2', 'option_value' => 'two', 'slot' => 'fresh-slot-2', 'revision' => 2],
]);

$skipPlan = static fn (): array => $run([
    ['option_id' => 30, 'option_name' => 'siteurl', 'autoload' => 'yes', 'option_value' => 'would-conflict', 'slot' => 'primary', 'revision' => 9],
    ['option_id' => 31, 'option_name' => 'new_safe', 'autoload' => 'safe-auto', 'option_value' => 'safe', 'slot' => 'safe-slot', 'revision' => 1],
], static fn (array $current, array $excluded): bool => $excluded['autoload'] !== 'yes');

$cases = [
    'clean returning names include updates and insert' => [static fn (): mixed => array_column($cleanPlan()['returning_rows'], 'option_name'), ['siteurl', 'fresh_plugin', 'theme_mods']],
    'clean returning autoload values use excluded values' => [static fn (): mixed => array_column($cleanPlan()['returning_rows'], 'autoload'), ['manual', 'plugin', null]],
    'clean returning slot values use excluded values' => [static fn (): mixed => array_column($cleanPlan()['returning_rows'], 'slot'), ['canonical', 'plugin', 'json-next']],
    'clean updated rows include current conflicts only' => [static fn (): mixed => array_column($cleanPlan()['updated_rows'], 'option_name'), ['siteurl', 'theme_mods']],
    'clean inserted rows include non-conflict row' => [static fn (): mixed => array_column($cleanPlan()['inserted_rows'], 'option_name'), ['fresh_plugin']],
    'clean changes match returning row count' => [static fn (): mixed => $cleanPlan()['changes'], 3],
    'clean before preserves current autoload values' => [static fn (): mixed => array_column($cleanPlan()['before'], 'autoload'), ['yes', 'no', 'maybe', null]],
    'clean after keeps table rows plus append' => [static fn (): mixed => array_column($cleanPlan()['after'], 'option_name'), ['siteurl', 'home', 'blogname', 'theme_mods', 'fresh_plugin']],
    'clean update can keep null unique value' => [static fn (): mixed => $cleanPlan()['returning_rows'][2]['autoload'], null],
    'clean update with null unique does not conflict' => [static fn (): mixed => $cleanPlan()['returning_rows'][2]['option_value'], '{"color":"blue"}'],
    'clean projection reports current changed rows' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::returningRows($cleanPlan()['returning_rows'], ['name' => 'option_name', 'slot' => 'slot']), [['name' => 'siteurl', 'slot' => 'canonical'], ['name' => 'fresh_plugin', 'slot' => 'plugin'], ['name' => 'theme_mods', 'slot' => 'json-next']]],
    'clean projection callable sees update revisions' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::returningRows($cleanPlan()['returning_rows'], ['rev' => static fn (array $row): string => $row['option_name'] . ':' . $row['revision']]), [['rev' => 'siteurl:5'], ['rev' => 'fresh_plugin:1'], ['rev' => 'theme_mods:7']]],
    'repeat first row inserts' => [static fn (): mixed => $repeatPlan()['inserted_rows'][0]['option_value'], 'one'],
    'repeat second row updates inserted current row' => [static fn (): mixed => $repeatPlan()['updated_rows'][0]['option_value'], 'two'],
    'repeat update may change secondary unique autoload' => [static fn (): mixed => $repeatPlan()['returning_rows'][1]['autoload'], 'fresh-auto-2'],
    'repeat update may change secondary unique slot' => [static fn (): mixed => $repeatPlan()['returning_rows'][1]['slot'], 'fresh-slot-2'],
    'repeat after has one row for repeated option name' => [static fn (): mixed => count(array_filter($repeatPlan()['after'], static fn (array $row): bool => $row['option_name'] === 'fresh_slot')), 1],
    'repeat changes include insert and update' => [static fn (): mixed => $repeatPlan()['changes'], 2],
    'repeat returning order preserves statement order' => [static fn (): mixed => array_column($repeatPlan()['returning_rows'], 'option_value'), ['one', 'two']],
    'repeat revision uses current inserted revision' => [static fn (): mixed => $repeatPlan()['returning_rows'][1]['revision'], 3],
    'skip omits skipped conflicting update from returning' => [static fn (): mixed => array_column($skipPlan()['returning_rows'], 'option_name'), ['new_safe']],
    'skip records skipped incoming conflict' => [static fn (): mixed => array_column($skipPlan()['skipped_rows'], 'option_name'), ['siteurl']],
    'skip avoids secondary unique conflict because update does not run' => [static fn (): mixed => $skipPlan()['after'][0]['autoload'], 'yes'],
    'skip still inserts later safe row' => [static fn (): mixed => $skipPlan()['inserted_rows'][0]['option_name'], 'new_safe'],
    'skip changes count excludes skipped row' => [static fn (): mixed => $skipPlan()['changes'], 1],
    'insert conflicting autoload aborts against current row' => [static fn (): mixed => $run([['option_id' => 40, 'option_name' => 'fresh_conflict', 'autoload' => 'yes', 'option_value' => 'bad', 'slot' => 'fresh-conflict', 'revision' => 1]]), InvalidArgumentException::class],
    'insert conflicting slot aborts against current row' => [static fn (): mixed => $run([['option_id' => 41, 'option_name' => 'fresh_conflict', 'autoload' => 'fresh-conflict', 'option_value' => 'bad', 'slot' => 'secondary', 'revision' => 1]]), InvalidArgumentException::class],
    'insert null secondary unique autoload is allowed' => [static fn (): mixed => $run([['option_id' => 42, 'option_name' => 'fresh_null', 'autoload' => null, 'option_value' => 'ok', 'slot' => 'fresh-null', 'revision' => 1]])['returning_rows'][0]['option_name'], 'fresh_null'],
    'insert second null secondary unique autoload is allowed' => [static fn (): mixed => $run([['option_id' => 43, 'option_name' => 'fresh_null_a', 'autoload' => null, 'option_value' => 'ok-a', 'slot' => 'fresh-null-a', 'revision' => 1], ['option_id' => 44, 'option_name' => 'fresh_null_b', 'autoload' => null, 'option_value' => 'ok-b', 'slot' => 'fresh-null-b', 'revision' => 1]])['changes'], 2],
    'update conflicting autoload aborts against current row' => [static fn (): mixed => $run([['option_id' => 50, 'option_name' => 'siteurl', 'autoload' => 'no', 'option_value' => 'bad', 'slot' => 'canonical', 'revision' => 1]]), InvalidArgumentException::class],
    'update conflicting slot aborts against current row' => [static fn (): mixed => $run([['option_id' => 51, 'option_name' => 'siteurl', 'autoload' => 'manual', 'option_value' => 'bad', 'slot' => 'display', 'revision' => 1]]), InvalidArgumentException::class],
    'update may keep its own secondary autoload' => [static fn (): mixed => $run([['option_id' => 52, 'option_name' => 'siteurl', 'autoload' => 'yes', 'option_value' => 'same-auto', 'slot' => 'primary-next', 'revision' => 1]])['returning_rows'][0]['autoload'], 'yes'],
    'update may keep its own secondary slot' => [static fn (): mixed => $run([['option_id' => 53, 'option_name' => 'siteurl', 'autoload' => 'manual-2', 'option_value' => 'same-slot', 'slot' => 'primary', 'revision' => 1]])['returning_rows'][0]['slot'], 'primary'],
    'update may set secondary unique to null' => [static fn (): mixed => $run([['option_id' => 54, 'option_name' => 'siteurl', 'autoload' => null, 'option_value' => 'null-auto', 'slot' => 'primary-null', 'revision' => 1]])['returning_rows'][0]['autoload'], null],
    'later update conflicts with earlier inserted autoload' => [static fn (): mixed => $run([['option_id' => 55, 'option_name' => 'first_new', 'autoload' => 'first-auto', 'option_value' => 'first', 'slot' => 'first-slot', 'revision' => 1], ['option_id' => 56, 'option_name' => 'siteurl', 'autoload' => 'first-auto', 'option_value' => 'bad', 'slot' => 'primary-later', 'revision' => 1]]), InvalidArgumentException::class],
    'later insert conflicts with earlier updated slot' => [static fn (): mixed => $run([['option_id' => 57, 'option_name' => 'siteurl', 'autoload' => 'site-next', 'option_value' => 'ok', 'slot' => 'site-next-slot', 'revision' => 1], ['option_id' => 58, 'option_name' => 'later_new', 'autoload' => 'later-auto', 'option_value' => 'bad', 'slot' => 'site-next-slot', 'revision' => 1]]), InvalidArgumentException::class],
    'missing current secondary unique column aborts when checked' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::execute([['option_name' => 'siteurl', 'autoload' => 'yes']], [['option_name' => 'new', 'autoload' => 'no']], ['option_name'], ['autoload' => static fn (): string => 'no'], null, [['option_name'], ['slot']]), InvalidArgumentException::class],
    'missing incoming secondary unique column aborts when checked' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::execute([['option_name' => 'siteurl', 'autoload' => 'yes', 'slot' => 'one']], [['option_name' => 'new', 'autoload' => 'no']], ['option_name'], ['autoload' => static fn (): string => 'no'], null, [['option_name'], ['slot']]), InvalidArgumentException::class],
    'unique constraints validate list shape' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::execute($rows, [], ['option_name'], $assignments, null, ['bad' => ['autoload']]), InvalidArgumentException::class],
    'unique constraints validate non-empty list' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::execute($rows, [], ['option_name'], $assignments, null, []), InvalidArgumentException::class],
    'unique constraints validate nested list' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::execute($rows, [], ['option_name'], $assignments, null, ['autoload']), InvalidArgumentException::class],
    'unique constraints validate nested columns' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::execute($rows, [], ['option_name'], $assignments, null, [['bad-column!']]), InvalidArgumentException::class],
    'unique constraints default preserves prior single constraint behavior' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::execute($rows, [['option_id' => 60, 'option_name' => 'new_default', 'autoload' => 'yes', 'option_value' => 'allowed', 'slot' => 'primary', 'revision' => 1]], ['option_name'], $assignments)['returning_rows'][0]['option_name'], 'new_default'],
    'composite secondary unique detects current conflict' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::execute($rows, [['option_id' => 61, 'option_name' => 'new_composite', 'autoload' => 'yes', 'option_value' => 'bad', 'slot' => 'primary', 'revision' => 1]], ['option_name'], $assignments, null, [['option_name'], ['autoload', 'slot']]), InvalidArgumentException::class],
    'composite secondary unique allows partial null' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::execute($rows, [['option_id' => 62, 'option_name' => 'new_composite_null', 'autoload' => null, 'option_value' => 'ok', 'slot' => 'json', 'revision' => 1]], ['option_name'], $assignments, null, [['option_name'], ['autoload', 'slot']])['changes'], 1],
    'composite secondary unique catches update result conflict' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::execute($rows, [['option_id' => 63, 'option_name' => 'siteurl', 'autoload' => 'no', 'option_value' => 'bad', 'slot' => 'secondary', 'revision' => 1]], ['option_name'], $assignments, null, [['option_name'], ['autoload', 'slot']]), InvalidArgumentException::class],
    'projection still validates returning rows after current conflict checks' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::returningRows($cleanPlan()['returning_rows'], ['missing']), InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['upsert returning conflict current ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
