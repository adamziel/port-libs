<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeSorterDistinctSourceTransitionPlan;

$tests = [];

$currentRows = [
    ['rowid' => 1, 'site' => 'main', 'bucket' => 'core', 'option_name' => 'SiteUrl', 'bytes' => '24', 'enabled' => 1],
    ['rowid' => 2, 'site' => 'main', 'bucket' => 'core', 'option_name' => 'siteurl', 'bytes' => 24, 'enabled' => 1],
    ['rowid' => 3, 'site' => 'main', 'bucket' => 'plugin', 'option_name' => 'plugin_cache', 'bytes' => '12', 'enabled' => 1],
    ['rowid' => 4, 'site' => 'main', 'bucket' => 'plugin', 'option_name' => 'Plugin_Cache', 'bytes' => 12.0, 'enabled' => 1],
    ['rowid' => 5, 'site' => 'main', 'bucket' => 'plugin', 'option_name' => 'plugin_cache ', 'bytes' => '12.00', 'enabled' => 1],
    ['rowid' => 6, 'site' => 'network', 'bucket' => 'transient', 'option_name' => '_transient_feed', 'bytes' => 30, 'enabled' => 0],
    ['rowid' => 7, 'site' => 'network', 'bucket' => 'transient', 'option_name' => '_transient_feed_timeout', 'bytes' => '30', 'enabled' => 1],
    ['rowid' => 8, 'site' => 'main', 'bucket' => null, 'option_name' => null, 'bytes' => null, 'enabled' => 1],
];

$nextRows = [
    ['rowid' => 1, 'site' => 'main', 'bucket' => 'core', 'option_name' => 'SiteUrl', 'bytes' => '24', 'enabled' => 1],
    ['rowid' => 2, 'site' => 'main', 'bucket' => 'core', 'option_name' => 'siteurl', 'bytes' => 24, 'enabled' => 1],
    ['rowid' => 3, 'site' => 'main', 'bucket' => 'plugin', 'option_name' => 'plugin_cache', 'bytes' => '12', 'enabled' => 0],
    ['rowid' => 4, 'site' => 'main', 'bucket' => 'plugin', 'option_name' => 'Plugin_Cache', 'bytes' => 12.0, 'enabled' => 1],
    ['rowid' => 5, 'site' => 'main', 'bucket' => 'plugin', 'option_name' => 'plugin_cache ', 'bytes' => '12.00', 'enabled' => 1],
    ['rowid' => 7, 'site' => 'network', 'bucket' => 'transient', 'option_name' => '_transient_feed_timeout', 'bytes' => '30', 'enabled' => 1],
    ['rowid' => 8, 'site' => 'main', 'bucket' => null, 'option_name' => null, 'bytes' => null, 'enabled' => 1],
    ['rowid' => 9, 'site' => 'main', 'bucket' => 'plugin', 'option_name' => 'plugin_cache_new', 'bytes' => '13', 'enabled' => 1],
    ['rowid' => 10, 'site' => 'network', 'bucket' => 'late', 'option_name' => 'Zoo_Option', 'bytes' => '99', 'enabled' => 1],
];

$plan = static fn (): array => SQLiteVdbeSorterDistinctSourceTransitionPlan::plan(
    $currentRows,
    $nextRows,
    'option_name',
    'rowid',
    'rowid',
    'enabled',
    'G',
    ['NOCASE'],
);

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $segment) {
        $value = $value[$segment];
    }

    return $value;
};

$cases = [
    'current values' => ['currentValues', [8, 7, 3, 5, 1]],
    'next values' => ['nextValues', [8, 7, 4, 5, 9, 1, 10]],
    'retained values' => ['retained', [8, 7, 4, 5, 1]],
    'inserted values' => ['inserted', [9, 10]],
    'deleted values empty after collation match' => ['deleted', []],
    'changed flag' => ['changed', true],
    'first moved id' => ['moved.0.id', 1],
    'first moved from' => ['moved.0.from', 4],
    'first moved to' => ['moved.0.to', 5],
    'moved count' => ['moved', 1, 'count'],
    'changed representative current' => ['changedRepresentatives.0.current', 3],
    'changed representative next' => ['changedRepresentatives.0.next', 4],
    'changed representative current key' => ['changedRepresentatives.0.currentKey', ['plugin_cache']],
    'changed representative next key' => ['changedRepresentatives.0.nextKey', ['Plugin_Cache']],
    'current duplicate representative' => ['currentDuplicateSkips.0.representative', 3],
    'current duplicate skipped ids' => ['currentDuplicateSkips.0.skipped', [4]],
    'next duplicate representative' => ['nextDuplicateSkips.0.representative', 1],
    'next duplicate skipped ids' => ['nextDuplicateSkips.0.skipped', [2]],
    'current first key' => ['currentDistinct.0.key', [null]],
    'current first value' => ['currentDistinct.0.value', 8],
    'current first sequence' => ['currentDistinct.0.sequence', 7],
    'current transient key' => ['currentDistinct.1.key', ['_transient_feed_timeout']],
    'current transient row id' => ['currentDistinct.1.id', 7],
    'current plugin representative id' => ['currentDistinct.2.id', 3],
    'current plugin skipped duplicate' => ['currentDistinct.2.skipped', [4]],
    'current padded plugin id' => ['currentDistinct.3.id', 5],
    'current siteurl representative id' => ['currentDistinct.4.id', 1],
    'current siteurl skipped' => ['currentDistinct.4.skipped', [2]],
    'next null key' => ['nextDistinct.0.key', [null]],
    'next transient id' => ['nextDistinct.1.id', 7],
    'next plugin representative id' => ['nextDistinct.2.id', 4],
    'next plugin skipped none' => ['nextDistinct.2.skipped', []],
    'next padded plugin id' => ['nextDistinct.3.id', 5],
    'next inserted plugin id' => ['nextDistinct.4.id', 9],
    'next siteurl representative id' => ['nextDistinct.5.id', 1],
    'next siteurl skipped id' => ['nextDistinct.5.skipped', [2]],
    'next late id' => ['nextDistinct.6.id', 10],
    'dependency sorter distinct' => ['dependencies.0', 'sqlite-vdbe-sorter-distinct'],
    'dependency affinity' => ['dependencies.1', 'sqlite-affinity-comparison'],
    'dependency collation' => ['dependencies.2', 'sqlite-collation-sequence'],
    'current distinct count' => ['currentDistinct', 5, 'count'],
    'next distinct count' => ['nextDistinct', 7, 'count'],
    'changed representative count' => ['changedRepresentatives', 1, 'count'],
];

foreach ($cases as $name => $case) {
    $tests['vdbe sorter distinct collation current source next116 ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $case): void {
        [$path, $expected] = $case;
        $actual = $valueAt($plan(), $path);
        if (($case[2] ?? null) === 'count') {
            $actual = count($actual);
        }

        $t->same($expected, $actual);
    };
}

$tests['vdbe sorter distinct collation current source next116 binary keeps case variants as inserted and retained'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $plan = SQLiteVdbeSorterDistinctSourceTransitionPlan::plan($currentRows, $nextRows, 'option_name', 'rowid', 'rowid', 'enabled', 'G', ['BINARY']);

    $t->same([8, 4, 1, 7, 3, 5, 2], $plan['currentValues']);
    $t->same([8, 4, 1, 10, 7, 5, 9, 2], $plan['nextValues']);
};

$tests['vdbe sorter distinct collation current source next116 rtrim collapses trailing-space duplicate class'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $plan = SQLiteVdbeSorterDistinctSourceTransitionPlan::plan($currentRows, $nextRows, 'option_name', 'rowid', 'rowid', 'enabled', 'G', ['RTRIM']);

    $t->same([8, 4, 1, 7, 3, 2], $plan['currentValues']);
    $t->same([8, 4, 1, 10, 7, 5, 9, 2], $plan['nextValues']);
};

$tests['vdbe sorter distinct collation current source next116 numeric affinity changes representative after filter'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $plan = SQLiteVdbeSorterDistinctSourceTransitionPlan::plan($currentRows, $nextRows, 'bytes', 'rowid', 'rowid', 'enabled', 'C', ['BINARY']);

    $t->same([8, 3, 1, 7], $plan['currentValues']);
    $t->same([8, 4, 9, 1, 7, 10], $plan['nextValues']);
    $t->same(3, $plan['changedRepresentatives'][0]['current']);
    $t->same(4, $plan['changedRepresentatives'][0]['next']);
};

$tests['vdbe sorter distinct collation current source next116 none affinity preserves byte storage classes'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $plan = SQLiteVdbeSorterDistinctSourceTransitionPlan::plan($currentRows, $nextRows, 'bytes', 'rowid', 'rowid', 'enabled', 'A', ['BINARY']);

    $t->same([8, 4, 2, 3, 5, 1, 7], $plan['currentValues']);
    $t->same([8, 4, 2, 5, 9, 1, 7, 10], $plan['nextValues']);
};

$tests['vdbe sorter distinct collation current source next116 composite keys track per site classes'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $plan = SQLiteVdbeSorterDistinctSourceTransitionPlan::plan($currentRows, $nextRows, ['site', 'option_name'], 'rowid', 'rowid', 'enabled', 'GG', ['BINARY', 'NOCASE']);

    $t->same([8, 3, 5, 1, 7], $plan['currentValues']);
    $t->same([8, 4, 5, 9, 1, 7, 10], $plan['nextValues']);
};

$tests['vdbe sorter distinct collation current source next116 unchanged source reports unchanged'] = static function (TestRunner $t) use ($currentRows): void {
    $plan = SQLiteVdbeSorterDistinctSourceTransitionPlan::plan($currentRows, $currentRows, 'option_name', 'rowid', 'rowid', 'enabled', 'G', ['NOCASE']);

    $t->same(false, $plan['changed']);
    $t->same([], $plan['inserted']);
    $t->same([], $plan['deleted']);
};

$tests['vdbe sorter distinct collation current source next116 deleted class is reported when no collation peer remains'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $rows = array_values(array_filter($nextRows, static fn (array $row): bool => $row['rowid'] !== 7));
    $plan = SQLiteVdbeSorterDistinctSourceTransitionPlan::plan($currentRows, $rows, 'option_name', 'rowid', 'rowid', 'enabled', 'G', ['NOCASE']);

    $t->same([7], $plan['deleted']);
};

$tests['vdbe sorter distinct collation current source next116 all disabled next rows become deleted classes'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $rows = array_map(static function (array $row): array {
        $row['enabled'] = 0;
        return $row;
    }, $nextRows);
    $plan = SQLiteVdbeSorterDistinctSourceTransitionPlan::plan($currentRows, $rows, 'option_name', 'rowid', 'rowid', 'enabled', 'G', ['NOCASE']);

    $t->same([8, 7, 3, 5, 1], $plan['deleted']);
    $t->same([], $plan['nextValues']);
};

$tests['vdbe sorter distinct collation current source next116 rejects unsupported collation'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVdbeSorterDistinctSourceTransitionPlan::plan([['rowid' => 1, 'v' => 'a']], [], 'v', 'rowid', 'rowid', null, 'G', ['UNICODE']));
};

$tests['vdbe sorter distinct collation current source next116 rejects associative rows'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVdbeSorterDistinctSourceTransitionPlan::plan(['a' => ['rowid' => 1, 'v' => 'a']], [], 'v', 'rowid', 'rowid'));
};

$tests['vdbe sorter distinct collation current source next116 rejects missing id column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVdbeSorterDistinctSourceTransitionPlan::plan([['v' => 'a']], [], 'v', 'v', 'rowid'));
};

$tests['vdbe sorter distinct collation current source next116 rejects missing key column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVdbeSorterDistinctSourceTransitionPlan::plan([['rowid' => 1, 'v' => 'a']], [], 'missing', 'v', 'rowid'));
};

$tests['vdbe sorter distinct collation current source next116 rejects missing value column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVdbeSorterDistinctSourceTransitionPlan::plan([['rowid' => 1, 'v' => 'a']], [], 'v', 'missing', 'rowid'));
};

$tests['vdbe sorter distinct collation current source next116 rejects missing filter column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVdbeSorterDistinctSourceTransitionPlan::plan([['rowid' => 1, 'v' => 'a']], [], 'v', 'v', 'rowid', 'enabled'));
};

$tests['vdbe sorter distinct collation current source next116 rejects empty row id column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVdbeSorterDistinctSourceTransitionPlan::plan([], [], 'v', 'v', ''));
};

$tests['vdbe sorter distinct collation current source next116 rejects empty key list'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVdbeSorterDistinctSourceTransitionPlan::plan([], [], [], 'v', 'rowid'));
};

return $tests;
