<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonbCheckCurrentNextPlan;

$jsonb67 = static fn (array $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

$schema67 = <<<'SQL'
CREATE TABLE wp_options(
  option_id INTEGER PRIMARY KEY,
  option_name TEXT NOT NULL,
  option_value BLOB,
  autoload TEXT,
  CHECK(json_valid(option_value, 8)),
  CHECK(json_extract(option_value, '$.plugin.channel') = 'stable' OR json_extract(option_value, '$.plugin.channel') = 'beta'),
  CHECK(NOT json_extract(option_value, '$.plugin.deprecated')),
  CHECK(json_extract(option_value, '$.plugin.requires') IS NULL OR json_extract(option_value, '$.plugin.requires') <= 6.7),
  CHECK(NOT (json_extract(option_value, '$.plugin.channel') = 'beta' AND json_extract(option_value, '$.plugin.rank') > 50))
)
SQL;

$rows67 = [
    ['option_id' => 301, 'option_name' => 'plugin_alpha_settings', 'option_value' => $jsonb67(['plugin' => ['channel' => 'stable', 'rank' => 10, 'deprecated' => false, 'requires' => 6.5]]), 'autoload' => 'yes'],
    ['option_id' => 302, 'option_name' => 'plugin_beta_settings', 'option_value' => $jsonb67(['plugin' => ['channel' => 'beta', 'rank' => 40, 'deprecated' => false]]), 'autoload' => 'yes'],
    ['option_id' => 303, 'option_name' => 'plugin_legacy_settings', 'option_value' => $jsonb67(['plugin' => ['channel' => 'stable', 'rank' => 45, 'deprecated' => false, 'requires' => 6.4]]), 'autoload' => 'no'],
];

$changes67 = [
    ['op' => 'UPDATE', 'rowid' => 301, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.plugin.channel', 'value' => 'beta'],
        ['function' => 'jsonb_set', 'path' => '$.plugin.rank', 'value' => 35],
    ]],
    ['op' => 'UPDATE', 'rowid' => 302, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.plugin.rank', 'value' => 75],
    ]],
    ['op' => 'UPDATE', 'rowid' => 303, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.plugin.deprecated', 'value' => true],
    ]],
    ['op' => 'INSERT', 'row' => ['option_id' => 304, 'option_name' => 'plugin_future_settings', 'option_value' => $jsonb67(['plugin' => ['channel' => 'stable', 'rank' => 12, 'deprecated' => false, 'requires' => 6.8]]), 'autoload' => 'no']],
    ['op' => 'INSERT', 'row' => ['option_id' => 305, 'option_name' => 'plugin_release_settings', 'option_value' => $jsonb67(['plugin' => ['channel' => 'stable', 'rank' => 15, 'deprecated' => false]]), 'autoload' => 'yes']],
    ['op' => 'INSERT', 'row' => ['option_id' => 306, 'option_name' => 'plugin_nightly_settings', 'option_value' => $jsonb67(['plugin' => ['channel' => 'nightly', 'rank' => 15, 'deprecated' => false]]), 'autoload' => 'no']],
    ['op' => 'INSERT', 'row' => ['option_id' => 307, 'option_name' => 'plugin_beta_import_settings', 'option_value' => $jsonb67(['plugin' => ['channel' => 'beta', 'rank' => 50, 'deprecated' => false, 'requires' => 6.7]]), 'autoload' => 'yes']],
];

$plan67 = static fn (): array => SQLiteJsonbCheckCurrentNextPlan::plan($schema67, $rows67, $changes67);
$decode67 = static fn (SQLiteBlobValue $blob): mixed => SQLiteJsonB::decode($blob->bytes);
$term67 = static function (array $plan, int $change, int $check, int $term = 0): array {
    return $plan['next'][$change]['checks'][$check]['terms'][$term];
};

$tests = [
    'jsonb check current next67 extracts logical check constraints' => static function (TestRunner $t) use ($plan67): void {
        $plan = $plan67();
        $t->same('wp_options', $plan['table']);
        $t->same(5, count($plan['checks']));
    },
    'jsonb check current next67 preserves or and not sql text' => static function (TestRunner $t) use ($plan67): void {
        $checks = array_column($plan67()['checks'], 'sql');
        $t->same("CHECK(json_extract(option_value, '$.plugin.channel') = 'stable' OR json_extract(option_value, '$.plugin.channel') = 'beta')", $checks[1]);
        $t->same("CHECK(NOT json_extract(option_value, '$.plugin.deprecated'))", $checks[2]);
    },
    'jsonb check current next67 current rows satisfy logical checks' => static function (TestRunner $t) use ($plan67): void {
        $t->same([true, true, true], array_column($plan67()['current'], 'ok'));
    },
    'jsonb check current next67 accepts only logical pass candidates' => static function (TestRunner $t) use ($plan67): void {
        $plan = $plan67();
        $t->same(3, $plan['changes']);
        $t->same([301, 305, 307], array_column($plan['accepted'], 'rowid'));
    },
    'jsonb check current next67 rejects logical failures' => static function (TestRunner $t) use ($plan67): void {
        $plan = $plan67();
        $t->same(4, $plan['rejectedChanges']);
        $t->same([302, 303, 304, 306], array_column($plan['rejected'], 'rowid'));
    },
    'jsonb check current next67 after rows preserve rejected current images' => static function (TestRunner $t) use ($plan67, $decode67): void {
        $after = $plan67()['after'];
        $payloads = array_map(static fn (array $row): array => $decode67($row['option_value']), $after);
        $t->same([35, 40, 45, 15, 50], array_map(static fn (array $payload): int => $payload['plugin']['rank'], $payloads));
    },
];

$statusCases67 = [
    'next ok states reflect OR and NOT admission' => ['next', 'ok', [true, false, false, false, true, false, true]],
    'accepted ops include update then inserts' => ['accepted', 'op', ['UPDATE', 'INSERT', 'INSERT']],
    'rejected ops preserve candidate order' => ['rejected', 'op', ['UPDATE', 'UPDATE', 'INSERT', 'INSERT']],
    'current rowids are stable' => ['current', 'rowid', [301, 302, 303]],
    'next rowids include all candidates' => ['next', 'rowid', [301, 302, 303, 304, 305, 306, 307]],
    'accepted rowids are final changes' => ['accepted', 'rowid', [301, 305, 307]],
    'rejected rowids are skipped' => ['rejected', 'rowid', [302, 303, 304, 306]],
];
foreach ($statusCases67 as $name => [$section, $column, $expected]) {
    $tests['jsonb check current next67 ' . $name] = static function (TestRunner $t) use ($plan67, $section, $column, $expected): void {
        $t->same($expected, array_column($plan67()[$section], $column));
    };
}

$termCases67 = [
    'accepted beta update OR actual values' => [0, 1, ['beta', 'beta'], true],
    'rejected nightly insert OR actual values' => [5, 1, ['nightly', 'nightly'], false],
    'accepted release insert OR actual values' => [4, 1, ['stable', 'stable'], true],
    'deprecated false update passes NOT actual' => [0, 2, 0, true],
    'deprecated true update fails NOT actual' => [2, 2, 1, false],
    'missing requires passes OR null branch' => [4, 3, [null, null], true],
    'requires 6.8 fails OR numeric branch' => [3, 3, [6.8, 6.8], false],
    'beta rank 75 fails NOT grouped AND' => [1, 4, ['beta', 75], false],
    'beta rank 50 passes NOT grouped AND' => [6, 4, ['beta', 50], true],
    'stable rank 15 passes NOT grouped AND' => [4, 4, ['stable', 15], true],
];
foreach ($termCases67 as $name => [$change, $check, $actual, $ok]) {
    $tests['jsonb check current next67 ' . $name] = static function (TestRunner $t) use ($plan67, $term67, $change, $check, $actual, $ok): void {
        $term = $term67($plan67(), $change, $check);
        $t->same($actual, $term['actual']);
        $t->same($ok, $term['ok']);
    };
}

$childCases67 = [
    'OR child keeps stable comparison false for beta update' => [0, 1, 0, false, 'beta'],
    'OR child keeps beta comparison true for beta update' => [0, 1, 1, true, 'beta'],
    'OR child keeps stable comparison false for nightly insert' => [5, 1, 0, false, 'nightly'],
    'OR child keeps beta comparison false for nightly insert' => [5, 1, 1, false, 'nightly'],
    'requires null child passes missing requires insert' => [4, 3, 0, true, null],
    'requires upper bound child admits missing requires insert null' => [4, 3, 1, true, null],
    'requires null child fails future insert' => [3, 3, 0, false, 6.8],
    'requires upper bound child fails future insert' => [3, 3, 1, false, 6.8],
    'NOT child records beta high rank truth before inversion' => [1, 4, 0, true, ['beta', 75]],
    'NOT child records beta rank 50 false before inversion' => [6, 4, 0, false, ['beta', 50]],
];
foreach ($childCases67 as $name => [$change, $check, $child, $ok, $actual]) {
    $tests['jsonb check current next67 ' . $name] = static function (TestRunner $t) use ($plan67, $term67, $change, $check, $child, $ok, $actual): void {
        $term = $term67($plan67(), $change, $check);
        $t->same($actual, $term['terms'][$child]['actual']);
        $t->same($ok, $term['terms'][$child]['ok']);
    };
}

$afterCases67 = [
    'after names reflect accepted inserts only' => ['option_name', ['plugin_alpha_settings', 'plugin_beta_settings', 'plugin_legacy_settings', 'plugin_release_settings', 'plugin_beta_import_settings']],
    'after autoload values keep accepted order' => ['autoload', ['yes', 'yes', 'no', 'yes', 'yes']],
];
foreach ($afterCases67 as $name => [$column, $expected]) {
    $tests['jsonb check current next67 ' . $name] = static function (TestRunner $t) use ($plan67, $column, $expected): void {
        $t->same($expected, array_column($plan67()['after'], $column));
    };
}

$structureCases67 = [
    'OR term exposes two evaluated children' => [0, 1, 'OR', 2],
    'requires OR term exposes two evaluated children' => [4, 3, 'OR', 2],
    'NOT deprecated term exposes one evaluated child' => [2, 2, 'NOT', 1],
    'NOT grouped beta-rank term exposes one AND child' => [1, 4, 'NOT', 1],
    'grouped beta-rank child exposes two AND comparisons' => [1, 4, 'AND', 2, 0],
];
foreach ($structureCases67 as $name => $case) {
    [$change, $check, $operator, $childCount] = $case;
    $child = $case[4] ?? null;
    $tests['jsonb check current next67 ' . $name] = static function (TestRunner $t) use ($plan67, $term67, $change, $check, $operator, $childCount, $child): void {
        $term = $term67($plan67(), $change, $check);
        if ($child !== null) {
            $term = $term['terms'][$child];
        }
        $t->same($operator, $term['operator']);
        $t->same($childCount, count($term['terms']));
    };
}

$guardCases67 = [
    'rejects unsupported OR expression literal fallback' => static fn (): array => SQLiteJsonbCheckCurrentNextPlan::plan('CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_value BLOB, CHECK(option_value OR missing_function(option_value)))', $rows67, []),
    'rejects unsupported NOT function literal fallback' => static fn (): array => SQLiteJsonbCheckCurrentNextPlan::plan('CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_value BLOB, CHECK(NOT missing_function(option_value)))', $rows67, []),
];
foreach ($guardCases67 as $name => $callable) {
    $tests['jsonb check current next67 ' . $name] = static function (TestRunner $t) use ($callable): void {
        $t->throws(InvalidArgumentException::class, $callable);
    };
}

return $tests;
