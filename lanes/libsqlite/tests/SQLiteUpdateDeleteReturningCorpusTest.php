<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpdateDeleteLimitPlan;

$tests = [];

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 24, 'value' => 'https://example.test'],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 24, 'value' => 'https://example.test'],
    ['option_id' => 3, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 12, 'value' => 'feed'],
    ['option_id' => 4, 'option_name' => '_transient_big', 'autoload' => 'no', 'bytes' => 110, 'value' => str_repeat('x', 8)],
    ['option_id' => 5, 'option_name' => '_transient_small', 'autoload' => 'no', 'bytes' => 7, 'value' => 'tiny'],
    ['option_id' => 6, 'option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 9, 'value' => 'Example'],
];

$deletePlan = static fn (): SQLiteUpdateDeleteLimitPlan => SQLiteUpdateDeleteLimitPlan::delete(
    $rows,
    static fn (array $row): bool => $row['autoload'] === 'no',
    [
        ['column' => 'bytes', 'direction' => 'DESC'],
        ['column' => 'option_name'],
    ],
    limit: 2,
    offset: 1,
    rowIdColumn: 'option_id',
);

$updatePlan = static fn (): SQLiteUpdateDeleteLimitPlan => SQLiteUpdateDeleteLimitPlan::update(
    $rows,
    static fn (array $row): bool => $row['autoload'] === 'yes',
    [
        'autoload' => 'no',
        'bytes' => static fn (array $row): int => (int) $row['bytes'] + 1,
        'returned_label' => static fn (array $row): string => $row['option_name'] . ':updated',
    ],
    [['column' => 'option_name']],
    limit: 2,
    rowIdColumn: 'option_id',
);

$returningCases = [
    'delete default returns old row ids in mutation order' => [static fn (): mixed => array_column($deletePlan()->returningRows(), 'option_id'), [3, 5]],
    'delete default returns old option names' => [static fn (): mixed => array_column($deletePlan()->returningRows(), 'option_name'), ['_transient_feed', '_transient_small']],
    'delete selected rows keep order by order' => [static fn (): mixed => array_column($deletePlan()->selectedRows, 'option_id'), [3, 5]],
    'delete result rows omit returned rows' => [static fn (): mixed => array_column($deletePlan()->resultRows, 'option_id'), [1, 2, 4, 6]],
    'delete projected option id' => [static fn (): mixed => $deletePlan()->returningRows(['option_id']), [['option_id' => 3], ['option_id' => 5]]],
    'delete projected mixed columns' => [static fn (): mixed => $deletePlan()->returningRows(['option_name', 'bytes']), [['option_name' => '_transient_feed', 'bytes' => 12], ['option_name' => '_transient_small', 'bytes' => 7]]],
    'delete aliased column projection' => [static fn (): mixed => $deletePlan()->returningRows(['deleted_name' => 'option_name']), [['deleted_name' => '_transient_feed'], ['deleted_name' => '_transient_small']]],
    'delete callable projection' => [static fn (): mixed => $deletePlan()->returningRows(['summary' => static fn (array $row): string => $row['option_name'] . ':' . $row['bytes']]), [['summary' => '_transient_feed:12'], ['summary' => '_transient_small:7']]],
    'delete wildcard projection preserves full old row' => [static fn (): mixed => array_keys($deletePlan()->returningRows(['*'])[0]), ['option_id', 'option_name', 'autoload', 'bytes', 'value']],
    'delete wildcard can be followed by alias' => [static fn (): mixed => $deletePlan()->returningRows(['*', 'deleted' => static fn (): int => 1])[0]['deleted'], 1],
    'delete returning empty when no rows match' => [static fn (): mixed => SQLiteUpdateDeleteLimitPlan::delete($rows, static fn (): bool => false, [], 5, 0, 'option_id')->returningRows(['option_id']), []],
    'delete returning zero limit is empty' => [static fn (): mixed => SQLiteUpdateDeleteLimitPlan::delete($rows, static fn (): bool => true, [], 0, 0, 'option_id')->returningRows(['option_id']), []],
    'delete returning negative limit after offset' => [static fn (): mixed => array_column(SQLiteUpdateDeleteLimitPlan::delete($rows, static fn (array $row): bool => $row['autoload'] === 'no', [['column' => 'bytes', 'direction' => 'DESC']], -1, 1, 'option_id')->returningRows(), 'option_id'), [3, 5]],
    'delete returning keeps custom rowid column in projection' => [static fn (): mixed => $deletePlan()->returningRows(['option_id'])[0]['option_id'], 3],
    'delete returning callable sees old values' => [static fn (): mixed => $deletePlan()->returningRows(['autoload_state' => static fn (array $row): string => $row['autoload']]), [['autoload_state' => 'no'], ['autoload_state' => 'no']]],
    'update default returns new row ids in mutation order' => [static fn (): mixed => array_column($updatePlan()->returningRows(), 'option_id'), [2, 6]],
    'update default returns changed autoload values' => [static fn (): mixed => array_column($updatePlan()->returningRows(), 'autoload'), ['no', 'no']],
    'update default returns computed assignment column' => [static fn (): mixed => array_column($updatePlan()->returningRows(), 'returned_label'), ['home:updated', 'blogname:updated']],
    'update result rows preserve source row order' => [static fn (): mixed => array_column($updatePlan()->resultRows, 'option_id'), [1, 2, 3, 4, 5, 6]],
    'update projected new bytes' => [static fn (): mixed => $updatePlan()->returningRows(['option_id', 'bytes']), [['option_id' => 2, 'bytes' => 25], ['option_id' => 6, 'bytes' => 10]]],
    'update aliased projection returns new values' => [static fn (): mixed => $updatePlan()->returningRows(['name' => 'option_name', 'state' => 'autoload']), [['name' => 'home', 'state' => 'no'], ['name' => 'blogname', 'state' => 'no']]],
    'update callable projection sees new values' => [static fn (): mixed => $updatePlan()->returningRows(['label' => static fn (array $row): string => $row['option_name'] . ':' . $row['autoload']]), [['label' => 'home:no'], ['label' => 'blogname:no']]],
    'update wildcard projection contains assigned column' => [static fn (): mixed => array_key_exists('returned_label', $updatePlan()->returningRows(['*'])[0]), true],
    'update wildcard then callable projection' => [static fn (): mixed => $updatePlan()->returningRows(['*', 'changed' => static fn (): string => 'yes'])[1]['changed'], 'yes'],
    'update returning offset selects later source row' => [static fn (): mixed => array_column(SQLiteUpdateDeleteLimitPlan::update($rows, static fn (array $row): bool => $row['autoload'] === 'yes', ['autoload' => 'no'], [['column' => 'option_id']], 1, 1, 'option_id')->returningRows(['option_id']), 'option_id'), [2]],
    'update returning no match is empty' => [static fn (): mixed => SQLiteUpdateDeleteLimitPlan::update($rows, static fn (): bool => false, ['autoload' => 'no'], [], null, 0, 'option_id')->returningRows(['option_id']), []],
    'update returning callable may return null' => [static fn (): mixed => $updatePlan()->returningRows(['nullable' => static fn (array $row): mixed => $row['option_name'] === 'home' ? null : $row['option_name']]), [['nullable' => null], ['nullable' => 'blogname']]],
    'update returning does not mutate input rows' => [static fn (): mixed => array_column($rows, 'autoload'), ['yes', 'yes', 'no', 'no', 'no', 'yes']],
    'update returning assignments summary remains separate' => [static fn (): mixed => $updatePlan()->toArray()['assignments'], ['autoload' => 'no', 'bytes' => 'callable', 'returned_label' => 'callable']],
    'returning projection validates missing column' => [static fn (): mixed => $deletePlan()->returningRows(['missing']), InvalidArgumentException::class],
    'returning projection validates empty column' => [static fn (): mixed => $deletePlan()->returningRows(['']), InvalidArgumentException::class],
    'returning projection validates missing alias source' => [static fn (): mixed => $updatePlan()->returningRows(['bad' => 'missing']), InvalidArgumentException::class],
    'returning projection validates expression type' => [static fn (): mixed => $updatePlan()->returningRows(['bad' => ['option_id']]), InvalidArgumentException::class],
];

foreach ($returningCases as $name => [$callback, $expected]) {
    $tests['upstream update delete returning corpus ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
