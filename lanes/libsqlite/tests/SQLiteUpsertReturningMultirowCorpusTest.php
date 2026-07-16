<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$baseRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'hits' => 5, 'note' => 'seed'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'hits' => 2, 'note' => 'seed'],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Old Blog', 'autoload' => 'no', 'hits' => 7, 'note' => 'seed'],
    ['option_id' => 4, 'option_name' => null, 'option_value' => 'anonymous', 'autoload' => 'no', 'hits' => 1, 'note' => 'seed'],
];

$execute = static function (array $incomingRows, ?callable $where = null) use ($baseRows): array {
    return SQLiteUpsertDoUpdateWherePlan::execute(
        $baseRows,
        $incomingRows,
        ['option_name'],
        [
            'option_id' => static fn (array $current, array $excluded): mixed => $excluded['option_id'],
            'option_value' => static fn (array $current, array $excluded): mixed => $excluded['option_value'],
            'autoload' => static fn (array $current, array $excluded): mixed => $excluded['autoload'],
            'hits' => static fn (array $current, array $excluded): int => (int) $current['hits'] + (int) $excluded['hits'],
            'note' => static fn (array $current, array $excluded): string => $current['note'] . '->' . $excluded['note'],
        ],
        $where,
    );
};

$mixedIncoming = [
    ['option_id' => 10, 'option_name' => 'siteurl', 'option_value' => 'https://new.test', 'autoload' => 'no', 'hits' => 3, 'note' => 'update-site'],
    ['option_id' => 11, 'option_name' => 'new_plugin', 'option_value' => 'enabled', 'autoload' => 'no', 'hits' => 1, 'note' => 'insert-plugin'],
    ['option_id' => 12, 'option_name' => 'home', 'option_value' => 'https://new-home.test', 'autoload' => 'yes', 'hits' => 4, 'note' => 'update-home'],
];

$skipIncoming = [
    ['option_id' => 20, 'option_name' => 'siteurl', 'option_value' => 'skip-site', 'autoload' => 'yes', 'hits' => 1, 'note' => 'skip-site'],
    ['option_id' => 21, 'option_name' => 'fresh_option', 'option_value' => 'fresh', 'autoload' => 'no', 'hits' => 1, 'note' => 'insert-fresh'],
    ['option_id' => 22, 'option_name' => 'blogname', 'option_value' => 'Blog New', 'autoload' => 'yes', 'hits' => 2, 'note' => 'update-blog'],
];

$repeatIncoming = [
    ['option_id' => 30, 'option_name' => 'transient_one', 'option_value' => 'one', 'autoload' => 'no', 'hits' => 1, 'note' => 'insert-one'],
    ['option_id' => 31, 'option_name' => 'transient_one', 'option_value' => 'two', 'autoload' => 'yes', 'hits' => 2, 'note' => 'update-two'],
    ['option_id' => 32, 'option_name' => 'transient_one', 'option_value' => 'three', 'autoload' => 'yes', 'hits' => 3, 'note' => 'update-three'],
];

$nullIncoming = [
    ['option_id' => 40, 'option_name' => null, 'option_value' => 'anonymous-two', 'autoload' => 'yes', 'hits' => 5, 'note' => 'insert-null-a'],
    ['option_id' => 41, 'option_name' => null, 'option_value' => 'anonymous-three', 'autoload' => 'no', 'hits' => 6, 'note' => 'insert-null-b'],
];

$mixedPlan = static fn (): array => $execute($mixedIncoming, static fn (): bool => true);
$skipPlan = static fn (): array => $execute($skipIncoming, static fn (array $current): bool => $current['autoload'] === 'no');
$repeatPlan = static fn (): array => $execute($repeatIncoming, static fn (): bool => true);
$nullPlan = static fn (): array => $execute($nullIncoming, static fn (): bool => true);

$returningCases = [
    'mixed returning count matches changed rows' => [static fn (): mixed => count($mixedPlan()['returning_rows']), 3],
    'mixed returning preserves statement order' => [static fn (): mixed => array_column($mixedPlan()['returning_rows'], 'option_name'), ['siteurl', 'new_plugin', 'home']],
    'mixed returning includes updated first row value' => [static fn (): mixed => $mixedPlan()['returning_rows'][0]['option_value'], 'https://new.test'],
    'mixed returning includes inserted middle row value' => [static fn (): mixed => $mixedPlan()['returning_rows'][1]['option_value'], 'enabled'],
    'mixed returning includes updated last row value' => [static fn (): mixed => $mixedPlan()['returning_rows'][2]['option_value'], 'https://new-home.test'],
    'mixed returning updates current plus excluded hits' => [static fn (): mixed => array_column($mixedPlan()['returning_rows'], 'hits'), [8, 1, 6]],
    'mixed returning exposes assigned option ids' => [static fn (): mixed => array_column($mixedPlan()['returning_rows'], 'option_id'), [10, 11, 12]],
    'mixed returning exposes composed notes' => [static fn (): mixed => array_column($mixedPlan()['returning_rows'], 'note'), ['seed->update-site', 'insert-plugin', 'seed->update-home']],
    'mixed inserted bucket remains independent' => [static fn (): mixed => array_column($mixedPlan()['inserted_rows'], 'option_name'), ['new_plugin']],
    'mixed updated bucket remains independent' => [static fn (): mixed => array_column($mixedPlan()['updated_rows'], 'option_name'), ['siteurl', 'home']],
    'mixed changes equals returning rows' => [static fn (): mixed => $mixedPlan()['changes'], 3],
    'mixed after keeps table order plus appended insert' => [static fn (): mixed => array_column($mixedPlan()['after'], 'option_name'), ['siteurl', 'home', 'blogname', null, 'new_plugin']],
    'mixed before rows remain unchanged' => [static fn (): mixed => array_column($mixedPlan()['before'], 'option_value'), ['https://old.test', 'https://home.test', 'Old Blog', 'anonymous']],
    'project mixed option names' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::returningRows($mixedPlan()['returning_rows'], ['option_name']), [['option_name' => 'siteurl'], ['option_name' => 'new_plugin'], ['option_name' => 'home']]],
    'project mixed option values' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::returningRows($mixedPlan()['returning_rows'], ['option_value']), [['option_value' => 'https://new.test'], ['option_value' => 'enabled'], ['option_value' => 'https://new-home.test']]],
    'project mixed multiple columns' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::returningRows($mixedPlan()['returning_rows'], ['option_name', 'hits']), [['option_name' => 'siteurl', 'hits' => 8], ['option_name' => 'new_plugin', 'hits' => 1], ['option_name' => 'home', 'hits' => 6]]],
    'project mixed aliases' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::returningRows($mixedPlan()['returning_rows'], ['name' => 'option_name', 'value' => 'option_value']), [['name' => 'siteurl', 'value' => 'https://new.test'], ['name' => 'new_plugin', 'value' => 'enabled'], ['name' => 'home', 'value' => 'https://new-home.test']]],
    'project mixed callable summaries' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::returningRows($mixedPlan()['returning_rows'], ['summary' => static fn (array $row): string => $row['option_name'] . ':' . $row['hits']]), [['summary' => 'siteurl:8'], ['summary' => 'new_plugin:1'], ['summary' => 'home:6']]],
    'project mixed wildcard preserves keys' => [static fn (): mixed => array_keys(SQLiteUpsertDoUpdateWherePlan::returningRows($mixedPlan()['returning_rows'], ['*'])[0]), ['option_id', 'option_name', 'option_value', 'autoload', 'hits', 'note']],
    'project mixed wildcard plus alias' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::returningRows($mixedPlan()['returning_rows'], ['*', 'label' => static fn (array $row): string => (string) $row['option_name']])[1]['label'], 'new_plugin'],
    'skip returning omits skipped conflict' => [static fn (): mixed => array_column($skipPlan()['returning_rows'], 'option_name'), ['fresh_option', 'blogname']],
    'skip records skipped conflict separately' => [static fn (): mixed => array_column($skipPlan()['skipped_rows'], 'option_name'), ['siteurl']],
    'skip changes ignores skipped conflict' => [static fn (): mixed => $skipPlan()['changes'], 2],
    'skip returning inserted row first' => [static fn (): mixed => $skipPlan()['returning_rows'][0]['option_name'], 'fresh_option'],
    'skip returning updated row second' => [static fn (): mixed => $skipPlan()['returning_rows'][1]['option_name'], 'blogname'],
    'skip returning update uses current plus excluded hits' => [static fn (): mixed => $skipPlan()['returning_rows'][1]['hits'], 9],
    'skip after leaves rejected row unchanged' => [static fn (): mixed => $skipPlan()['after'][0]['option_value'], 'https://old.test'],
    'skip projection aliases changed rows only' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::returningRows($skipPlan()['returning_rows'], ['name' => 'option_name']), [['name' => 'fresh_option'], ['name' => 'blogname']]],
    'repeat returning includes insert then two updates' => [static fn (): mixed => array_column($repeatPlan()['returning_rows'], 'option_value'), ['one', 'two', 'three']],
    'repeat returning later update sees inserted current' => [static fn (): mixed => $repeatPlan()['returning_rows'][1]['hits'], 3],
    'repeat returning third update sees second update' => [static fn (): mixed => $repeatPlan()['returning_rows'][2]['hits'], 6],
    'repeat returning notes chain through prior row' => [static fn (): mixed => array_column($repeatPlan()['returning_rows'], 'note'), ['insert-one', 'insert-one->update-two', 'insert-one->update-two->update-three']],
    'repeat inserted bucket contains only first row' => [static fn (): mixed => array_column($repeatPlan()['inserted_rows'], 'option_value'), ['one']],
    'repeat updated bucket contains later rows' => [static fn (): mixed => array_column($repeatPlan()['updated_rows'], 'option_value'), ['two', 'three']],
    'repeat after contains final value once' => [static fn (): mixed => array_values(array_filter($repeatPlan()['after'], static fn (array $row): bool => $row['option_name'] === 'transient_one'))[0]['option_value'], 'three'],
    'repeat projection callable sees final chain rows' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::returningRows($repeatPlan()['returning_rows'], ['note'])[2]['note'], 'insert-one->update-two->update-three'],
    'null unique returning inserts both null rows' => [static fn (): mixed => array_column($nullPlan()['returning_rows'], 'option_value'), ['anonymous-two', 'anonymous-three']],
    'null unique returning changes for both rows' => [static fn (): mixed => $nullPlan()['changes'], 2],
    'null unique inserted bucket includes both null rows' => [static fn (): mixed => count($nullPlan()['inserted_rows']), 2],
    'null unique updated bucket is empty' => [static fn (): mixed => $nullPlan()['updated_rows'], []],
    'null unique after has three null-key rows' => [static fn (): mixed => count(array_filter($nullPlan()['after'], static fn (array $row): bool => $row['option_name'] === null)), 3],
    'null unique projection preserves null names' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::returningRows($nullPlan()['returning_rows'], ['option_name']), [['option_name' => null], ['option_name' => null]]],
    'empty incoming returning rows empty' => [static fn (): mixed => $execute([], static fn (): bool => true)['returning_rows'], []],
    'empty incoming changes zero' => [static fn (): mixed => $execute([], static fn (): bool => true)['changes'], 0],
    'empty projection on empty rows stays empty' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::returningRows([], ['option_name']), []],
    'project default returns full rows' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::returningRows($mixedPlan()['returning_rows'])[0]['option_name'], 'siteurl'],
    'project callable may return null' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::returningRows($mixedPlan()['returning_rows'], ['maybe' => static fn (array $row): mixed => $row['option_name'] === 'home' ? null : $row['option_name']])[2]['maybe'], null],
    'project callable may return boolean' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::returningRows($mixedPlan()['returning_rows'], ['autoloaded' => static fn (array $row): bool => $row['autoload'] === 'yes'])[2]['autoloaded'], true],
    'project wildcard can be overridden by later alias' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::returningRows($mixedPlan()['returning_rows'], ['*', 'option_name' => static fn (): string => 'masked'])[0]['option_name'], 'masked'],
    'returning projection validates missing column' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::returningRows($mixedPlan()['returning_rows'], ['missing']), InvalidArgumentException::class],
    'returning projection validates empty column' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::returningRows($mixedPlan()['returning_rows'], ['']), InvalidArgumentException::class],
    'returning projection validates empty alias' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::returningRows($mixedPlan()['returning_rows'], ['' => 'option_name']), InvalidArgumentException::class],
    'returning projection validates missing alias source' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::returningRows($mixedPlan()['returning_rows'], ['bad' => 'missing']), InvalidArgumentException::class],
    'returning projection validates expression type' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::returningRows($mixedPlan()['returning_rows'], ['bad' => ['option_name']]), InvalidArgumentException::class],
    'returning rows validate list shape' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::returningRows(['bad' => ['option_name' => 'siteurl']], ['option_name']), InvalidArgumentException::class],
    'returning rows validate row arrays' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::returningRows(['siteurl'], ['option_name']), InvalidArgumentException::class],
];

foreach ($returningCases as $name => [$callback, $expected]) {
    $tests['upsert returning multirow corpus ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
