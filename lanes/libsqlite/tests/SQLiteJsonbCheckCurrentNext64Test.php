<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonbCheckCurrentNextPlan;

$jsonb64 = static fn (array $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

$schema64 = <<<'SQL'
CREATE TABLE wp_options(
  option_id INTEGER PRIMARY KEY,
  option_name TEXT NOT NULL,
  option_value BLOB,
  autoload TEXT,
  CHECK(json_valid(option_value, 8)),
  CHECK(json_type(option_value, '$.plugin') = 'object'),
  CHECK(json_type(option_value, '$.plugin.slug') = 'text'),
  CHECK(json_extract(option_value, '$.plugin.slug') <> ''),
  CHECK(json_extract(option_value, '$.plugin.rank') >= 1 AND json_extract(option_value, '$.plugin.rank') <= 99),
  CHECK(json_extract(option_value, '$.plugin.channel') IN ('stable','beta','nightly')),
  CHECK(json_array_length(option_value, '$.plugin.rules') >= 1),
  CHECK(json_type(option_value, '$.plugin.enabled') IN ('true','false')),
  CHECK(json_extract(option_value, '$.plugin.version') IS NOT NULL)
)
SQL;

$rows64 = [
    ['option_id' => 201, 'option_name' => 'plugin_alpha_settings', 'option_value' => $jsonb64(['plugin' => ['slug' => 'alpha', 'rank' => 10, 'channel' => 'stable', 'rules' => ['cache'], 'enabled' => true, 'version' => '1.0']]), 'autoload' => 'yes'],
    ['option_id' => 202, 'option_name' => 'plugin_beta_settings', 'option_value' => $jsonb64(['plugin' => ['slug' => 'beta', 'rank' => 20, 'channel' => 'beta', 'rules' => ['seo', 'media'], 'enabled' => false, 'version' => '2.0']]), 'autoload' => 'yes'],
    ['option_id' => 203, 'option_name' => 'plugin_gamma_settings', 'option_value' => $jsonb64(['plugin' => ['slug' => 'gamma', 'rank' => 30, 'channel' => 'nightly', 'rules' => ['debug'], 'enabled' => true, 'version' => '3.0']]), 'autoload' => 'no'],
];

$changes64 = [
    ['op' => 'UPDATE', 'rowid' => 201, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.plugin.rank', 'value' => 15],
        ['function' => 'jsonb_set', 'path' => '$.plugin.channel', 'value' => 'beta'],
    ]],
    ['op' => 'UPDATE', 'rowid' => 202, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.plugin.rank', 'value' => 120],
    ]],
    ['op' => 'UPDATE', 'rowid' => 203, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.plugin.rules', 'value' => $jsonb64([])],
    ]],
    ['op' => 'INSERT', 'row' => ['option_id' => 204, 'option_name' => 'plugin_delta_settings', 'option_value' => $jsonb64(['plugin' => ['slug' => 'delta', 'rank' => 40, 'channel' => 'stable', 'rules' => ['import'], 'enabled' => false, 'version' => '4.0']]), 'autoload' => 'yes']],
    ['op' => 'INSERT', 'row' => ['option_id' => 205, 'option_name' => 'plugin_empty_slug_settings', 'option_value' => $jsonb64(['plugin' => ['slug' => '', 'rank' => 50, 'channel' => 'stable', 'rules' => ['import'], 'enabled' => true, 'version' => '5.0']]), 'autoload' => 'yes']],
    ['op' => 'INSERT', 'row' => ['option_id' => 206, 'option_name' => 'plugin_missing_version_settings', 'option_value' => $jsonb64(['plugin' => ['slug' => 'missing-version', 'rank' => 60, 'channel' => 'stable', 'rules' => ['import'], 'enabled' => true]]), 'autoload' => 'no']],
];

$plan64 = static fn (): array => SQLiteJsonbCheckCurrentNextPlan::plan($schema64, $rows64, $changes64);
$term64 = static function (array $plan, int $change, int $check, int $term = 0): array {
    return $plan['next'][$change]['checks'][$check]['terms'][$term];
};
$decode64 = static fn (SQLiteBlobValue $blob): mixed => SQLiteJsonB::decode($blob->bytes);

$tests = [
    'jsonb check current next64 extracts table name and check count' => static function (TestRunner $t) use ($plan64): void {
        $plan = $plan64();
        $t->same('wp_options', $plan['table']);
        $t->same(9, count($plan['checks']));
    },
    'jsonb check current next64 preserves check sql order' => static function (TestRunner $t) use ($plan64): void {
        $checks = array_column($plan64()['checks'], 'sql');
        $t->same('CHECK(json_valid(option_value, 8))', $checks[0]);
        $t->same('CHECK(json_extract(option_value, \'$.plugin.version\') IS NOT NULL)', $checks[8]);
    },
    'jsonb check current next64 validates all current rows' => static function (TestRunner $t) use ($plan64): void {
        $t->same([true, true, true], array_column($plan64()['current'], 'ok'));
    },
    'jsonb check current next64 reports no current failures' => static function (TestRunner $t) use ($plan64): void {
        $t->same(0, $plan64()['currentFailures']);
    },
    'jsonb check current next64 accepts update and insert candidates only' => static function (TestRunner $t) use ($plan64): void {
        $plan = $plan64();
        $t->same(2, $plan['changes']);
        $t->same([201, 204], array_column($plan['accepted'], 'rowid'));
    },
    'jsonb check current next64 rejects four bad next candidates' => static function (TestRunner $t) use ($plan64): void {
        $plan = $plan64();
        $t->same(4, $plan['rejectedChanges']);
        $t->same([202, 203, 205, 206], array_column($plan['rejected'], 'rowid'));
    },
    'jsonb check current next64 keeps rejected updates out of after rows' => static function (TestRunner $t) use ($plan64): void {
        $after = $plan64()['after'];
        $t->same([201, 202, 203, 204], array_column($after, 'option_id'));
    },
    'jsonb check current next64 decodes accepted updated jsonb payload' => static function (TestRunner $t) use ($plan64, $decode64): void {
        $alpha = $decode64($plan64()['after'][0]['option_value']);
        $t->same(15, $alpha['plugin']['rank']);
        $t->same('beta', $alpha['plugin']['channel']);
    },
    'jsonb check current next64 preserves rejected rank update payload' => static function (TestRunner $t) use ($plan64, $decode64): void {
        $beta = $decode64($plan64()['after'][1]['option_value']);
        $t->same(20, $beta['plugin']['rank']);
    },
    'jsonb check current next64 preserves rejected empty rules payload' => static function (TestRunner $t) use ($plan64, $decode64): void {
        $gamma = $decode64($plan64()['after'][2]['option_value']);
        $t->same(['debug'], $gamma['plugin']['rules']);
    },
    'jsonb check current next64 appends accepted insert row' => static function (TestRunner $t) use ($plan64): void {
        $after = $plan64()['after'];
        $t->same(204, $after[3]['option_id']);
        $t->same('plugin_delta_settings', $after[3]['option_name']);
    },
];

$termCases64 = [
    'valid jsonb flag term accepts strict blob' => [0, 0, 1, true],
    'plugin object type term accepts object root' => [0, 1, 'object', true],
    'slug type term accepts text slug' => [0, 2, 'text', true],
    'slug non empty term accepts alpha' => [0, 3, 'alpha', true],
    'rank lower-bound term accepts updated rank' => [0, 4, 15, true],
    'channel in term accepts updated beta channel' => [0, 5, 'beta', true],
    'array length term accepts non empty rules' => [0, 6, 1, true],
    'enabled type term accepts true boolean' => [0, 7, 'true', true],
    'version not null term accepts version string' => [0, 8, '1.0', true],
    'rank lower-bound term accepts high update before upper-bound failure' => [1, 4, 120, true],
    'rank upper-bound term rejects high update' => [1, 4, 120, false, 1],
    'empty rules term rejects zero array length' => [2, 6, 0, false],
    'empty slug term rejects empty text' => [4, 3, '', false],
    'missing version term rejects null' => [5, 8, null, false],
];
foreach ($termCases64 as $name => $case) {
    [$change, $check, $actual, $ok] = $case;
    $termIndex = $case[4] ?? 0;
    $tests['jsonb check current next64 ' . $name] = static function (TestRunner $t) use ($plan64, $term64, $change, $check, $termIndex, $actual, $ok): void {
        $term = $term64($plan64(), $change, $check, $termIndex);
        $t->same($actual, $term['actual']);
        $t->same($ok, $term['ok']);
    };
}

$statusCases64 = [
    'next row ok states match sqlite check admission' => ['next', 'ok', [true, false, false, true, false, false]],
    'accepted ops preserve update before insert order' => ['accepted', 'op', ['UPDATE', 'INSERT']],
    'rejected ops preserve failed candidate order' => ['rejected', 'op', ['UPDATE', 'UPDATE', 'INSERT', 'INSERT']],
    'current rowids are stable' => ['current', 'rowid', [201, 202, 203]],
    'next rowids include insert candidates' => ['next', 'rowid', [201, 202, 203, 204, 205, 206]],
    'accepted rowids are final mutations' => ['accepted', 'rowid', [201, 204]],
    'rejected rowids are skipped mutations' => ['rejected', 'rowid', [202, 203, 205, 206]],
];
foreach ($statusCases64 as $name => [$section, $column, $expected]) {
    $tests['jsonb check current next64 ' . $name] = static function (TestRunner $t) use ($plan64, $section, $column, $expected): void {
        $t->same($expected, array_column($plan64()[$section], $column));
    };
}

$afterCases64 = [
    'after slugs reflect accepted only' => ['plugin.slug', ['alpha', 'beta', 'gamma', 'delta']],
    'after ranks reflect accepted only' => ['plugin.rank', [15, 20, 30, 40]],
    'after channels reflect accepted only' => ['plugin.channel', ['beta', 'beta', 'nightly', 'stable']],
    'after versions remain required' => ['plugin.version', ['1.0', '2.0', '3.0', '4.0']],
    'after rules preserve rejected empty array update' => ['plugin.rules.count', [1, 2, 1, 1]],
];
foreach ($afterCases64 as $name => [$path, $expected]) {
    $tests['jsonb check current next64 ' . $name] = static function (TestRunner $t) use ($plan64, $decode64, $path, $expected): void {
        $actual = [];
        foreach ($plan64()['after'] as $row) {
            $value = $decode64($row['option_value']);
            foreach (explode('.', $path) as $part) {
                $value = $part === 'count' ? count($value) : $value[$part];
            }
            $actual[] = $value;
        }
        $t->same($expected, $actual);
    };
}

$guardCases64 = [
    'rejects schema without checks' => static fn (): array => SQLiteJsonbCheckCurrentNextPlan::plan('CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_value BLOB)', $rows64, $changes64),
    'rejects malformed check parentheses' => static fn (): array => SQLiteJsonbCheckCurrentNextPlan::plan('CREATE TABLE wp_options(option_id INTEGER, option_value BLOB, CHECK(json_valid(option_value,8)', $rows64, $changes64),
    'rejects update without rowid' => static fn (): array => SQLiteJsonbCheckCurrentNextPlan::plan($schema64, $rows64, [['op' => 'UPDATE', 'set' => ['autoload' => 'no']]]),
    'rejects update missing current row' => static fn (): array => SQLiteJsonbCheckCurrentNextPlan::plan($schema64, $rows64, [['op' => 'UPDATE', 'rowid' => 999, 'set' => ['autoload' => 'no']]]),
    'rejects insert without row' => static fn (): array => SQLiteJsonbCheckCurrentNextPlan::plan($schema64, $rows64, [['op' => 'INSERT']]),
    'rejects delete op as out of scope' => static fn (): array => SQLiteJsonbCheckCurrentNextPlan::plan($schema64, $rows64, [['op' => 'DELETE', 'rowid' => 201]]),
    'rejects rows without rowid alias' => static fn (): array => SQLiteJsonbCheckCurrentNextPlan::plan($schema64, [['option_name' => 'bad', 'option_value' => $jsonb64(['plugin' => []])]], []),
];
foreach ($guardCases64 as $name => $callable) {
    $tests['jsonb check current next64 ' . $name] = static function (TestRunner $t) use ($callable): void {
        $t->throws(InvalidArgumentException::class, $callable);
    };
}

return $tests;
