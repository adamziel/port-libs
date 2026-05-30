<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonbCheckCurrentNextPlan;

$jsonb68 = static fn (array $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

$schema68 = <<<'SQL'
CREATE TABLE app_settings(
  setting_id INTEGER PRIMARY KEY,
  key_name TEXT NOT NULL,
  key_value BLOB,
  load_policy TEXT,
  CHECK(json_valid(key_value, 8)),
  CHECK(json_type(key_value, '$.plugin.slug') = 'text'),
  CHECK(json_type(key_value, '$.plugin.description') = 'text'),
  CHECK(json_extract(key_value, '$.plugin.channel') IN ('stable','beta') OR json_extract(key_value, '$.plugin.channel') IS NULL),
  CHECK(json_extract(key_value, '$.plugin.priority') IS NULL OR json_extract(key_value, '$.plugin.priority') >= 0),
  CHECK(json_extract(key_value, '$.plugin.priority') IS NULL OR json_extract(key_value, '$.plugin.priority') <= 10),
  CHECK(json_type(key_value, '$.plugin.rules') = 'array' OR json_extract(key_value, '$.plugin.rules') IS NULL)
)
SQL;

$rows68 = [
    ['setting_id' => 301, 'key_name' => 'plugin_alpha_settings', 'key_value' => $jsonb68(['plugin' => ['slug' => 'alpha', 'channel' => 'stable', 'priority' => 5, 'rules' => ['cache']]]), 'load_policy' => 'yes'],
    ['setting_id' => 302, 'key_name' => 'plugin_beta_settings', 'key_value' => $jsonb68(['plugin' => ['slug' => 'beta', 'description' => 'Beta import', 'priority' => 0]]), 'load_policy' => 'no'],
    ['setting_id' => 303, 'key_name' => 'plugin_gamma_settings', 'key_value' => $jsonb68(['plugin' => ['slug' => 'gamma']]), 'load_policy' => 'yes'],
];

$changes68 = [
    ['op' => 'UPDATE', 'rowid' => 301, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.plugin.description', 'value' => 'Alpha updated'],
        ['function' => 'jsonb_set', 'path' => '$.plugin.priority', 'value' => 10],
    ]],
    ['op' => 'UPDATE', 'rowid' => 302, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.plugin.channel', 'value' => 'nightly'],
    ]],
    ['op' => 'UPDATE', 'rowid' => 303, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.plugin.description', 'value' => 42],
    ]],
    ['op' => 'INSERT', 'row' => ['setting_id' => 304, 'key_name' => 'plugin_delta_settings', 'key_value' => $jsonb68(['plugin' => ['slug' => 'delta', 'channel' => 'beta', 'priority' => 7]]), 'load_policy' => 'yes']],
    ['op' => 'INSERT', 'row' => ['setting_id' => 305, 'key_name' => 'plugin_bad_priority_high', 'key_value' => $jsonb68(['plugin' => ['slug' => 'high', 'priority' => 11]]), 'load_policy' => 'no']],
    ['op' => 'INSERT', 'row' => ['setting_id' => 306, 'key_name' => 'plugin_bad_priority_low', 'key_value' => $jsonb68(['plugin' => ['slug' => 'low', 'priority' => -1]]), 'load_policy' => 'no']],
    ['op' => 'INSERT', 'row' => ['setting_id' => 307, 'key_name' => 'plugin_bad_rules_type', 'key_value' => $jsonb68(['plugin' => ['slug' => 'rules', 'rules' => 'cache']]), 'load_policy' => 'no']],
    ['op' => 'INSERT', 'row' => ['setting_id' => 308, 'key_name' => 'plugin_malformed_jsonb', 'key_value' => new SQLiteBlobValue("\x8c\xff"), 'load_policy' => 'no']],
];

$plan68 = static fn (): array => SQLiteJsonbCheckCurrentNextPlan::plan($schema68, $rows68, $changes68);
$decode68 = static fn (SQLiteBlobValue $blob): mixed => SQLiteJsonB::decode($blob->bytes);
$term68 = static function (array $plan, string $section, int $row, int $check, int $term = 0): array {
    return $plan[$section][$row]['checks'][$check]['terms'][$term];
};

$tests = [
    'jsonb check current next68 extracts optional check table metadata' => static function (TestRunner $t) use ($plan68): void {
        $plan = $plan68();
        $t->same('app_settings', $plan['table']);
        $t->same(7, count($plan['checks']));
    },
    'jsonb check current next68 preserves optional check sql order' => static function (TestRunner $t) use ($plan68): void {
        $checks = array_column($plan68()['checks'], 'sql');
        $t->same('CHECK(json_type(key_value, \'$.plugin.description\') = \'text\')', $checks[2]);
        $t->same('CHECK(json_type(key_value, \'$.plugin.rules\') = \'array\' OR json_extract(key_value, \'$.plugin.rules\') IS NULL)', $checks[6]);
    },
    'jsonb check current next68 admits current rows with missing optional json paths' => static function (TestRunner $t) use ($plan68): void {
        $t->same([true, true, true], array_column($plan68()['current'], 'ok'));
    },
    'jsonb check current next68 reports zero current failures for nullable check results' => static function (TestRunner $t) use ($plan68): void {
        $t->same(0, $plan68()['currentFailures']);
    },
    'jsonb check current next68 accepts only sqlite check clean mutations' => static function (TestRunner $t) use ($plan68): void {
        $plan = $plan68();
        $t->same(2, $plan['changes']);
        $t->same([301, 304], array_column($plan['accepted'], 'rowid'));
    },
    'jsonb check current next68 rejects failed optional check mutations' => static function (TestRunner $t) use ($plan68): void {
        $plan = $plan68();
        $t->same(6, $plan['rejectedChanges']);
        $t->same([302, 303, 305, 306, 307, 308], array_column($plan['rejected'], 'rowid'));
    },
    'jsonb check current next68 keeps rejected rows out of after image' => static function (TestRunner $t) use ($plan68): void {
        $t->same([301, 302, 303, 304], array_column($plan68()['after'], 'setting_id'));
    },
    'jsonb check current next68 applies accepted jsonb updates to after image' => static function (TestRunner $t) use ($plan68, $decode68): void {
        $alpha = $decode68($plan68()['after'][0]['key_value']);
        $t->same('Alpha updated', $alpha['plugin']['description']);
        $t->same(10, $alpha['plugin']['priority']);
    },
    'jsonb check current next68 appends accepted optional insert' => static function (TestRunner $t) use ($plan68, $decode68): void {
        $delta = $decode68($plan68()['after'][3]['key_value']);
        $t->same('delta', $delta['plugin']['slug']);
        $t->same('beta', $delta['plugin']['channel']);
    },
    'jsonb check current next68 preserves rejected channel update' => static function (TestRunner $t) use ($plan68, $decode68): void {
        $beta = $decode68($plan68()['after'][1]['key_value']);
        $t->same(false, isset($beta['plugin']['channel']));
    },
    'jsonb check current next68 preserves rejected description update' => static function (TestRunner $t) use ($plan68, $decode68): void {
        $gamma = $decode68($plan68()['after'][2]['key_value']);
        $t->same(false, isset($gamma['plugin']['description']));
    },
];

$statusCases68 = [
    'next ok states include nullable passes and concrete failures' => ['next', 'ok', [true, false, false, true, false, false, false, false]],
    'accepted ops preserve update insert order' => ['accepted', 'op', ['UPDATE', 'INSERT']],
    'rejected ops preserve current next order' => ['rejected', 'op', ['UPDATE', 'UPDATE', 'INSERT', 'INSERT', 'INSERT', 'INSERT']],
    'current rowids stay stable' => ['current', 'rowid', [301, 302, 303]],
    'next rowids include all candidates' => ['next', 'rowid', [301, 302, 303, 304, 305, 306, 307, 308]],
    'accepted rowids are final mutations' => ['accepted', 'rowid', [301, 304]],
    'rejected rowids identify failed mutations' => ['rejected', 'rowid', [302, 303, 305, 306, 307, 308]],
];
foreach ($statusCases68 as $name => [$section, $column, $expected]) {
    $tests['jsonb check current next68 ' . $name] = static function (TestRunner $t) use ($plan68, $section, $column, $expected): void {
        $t->same($expected, array_column($plan68()[$section], $column));
    };
}

$termCases68 = [
    'missing current description comparison is null and check-admitted' => ['current', 0, 2, 0, null, null, true],
    'present current description comparison is true' => ['current', 1, 2, 0, 'text', true, true],
    'missing current channel disjunction is check-admitted' => ['current', 1, 3, 0, [null, null], true, true],
    'missing current priority lower disjunction is check-admitted' => ['current', 2, 4, 0, [null, null], true, true],
    'missing current priority upper disjunction is check-admitted' => ['current', 2, 5, 0, [null, null], true, true],
    'missing current rules disjunction is check-admitted' => ['current', 1, 6, 0, [null, null], true, true],
    'accepted update description comparison is true' => ['next', 0, 2, 0, 'text', true, true],
    'accepted update priority upper disjunction is true' => ['next', 0, 5, 0, [10, 10], true, true],
    'rejected channel disjunction is false' => ['next', 1, 3, 0, ['nightly', 'nightly'], false, false],
    'rejected integer description comparison is false' => ['next', 2, 2, 0, 'integer', false, false],
    'accepted insert channel disjunction is true' => ['next', 3, 3, 0, ['beta', 'beta'], true, true],
    'accepted insert missing description remains admitted' => ['next', 3, 2, 0, null, null, true],
    'high priority lower disjunction is true' => ['next', 4, 4, 0, [11, 11], true, true],
    'high priority upper disjunction is false' => ['next', 4, 5, 0, [11, 11], false, false],
    'low priority lower disjunction is false' => ['next', 5, 4, 0, [-1, -1], false, false],
    'low priority upper disjunction is true' => ['next', 5, 5, 0, [-1, -1], true, true],
    'bad rules disjunction is false' => ['next', 6, 6, 0, ['text', 'cache'], false, false],
    'malformed jsonb valid check is false' => ['next', 7, 0, 0, 0, false, false],
];
foreach ($termCases68 as $name => [$section, $row, $check, $term, $actual, $result, $ok]) {
    $tests['jsonb check current next68 ' . $name] = static function (TestRunner $t) use ($plan68, $term68, $section, $row, $check, $term, $actual, $result, $ok): void {
        $evaluated = $term68($plan68(), $section, $row, $check, $term);
        $t->same($actual, $evaluated['actual']);
        $t->same($result, $evaluated['result']);
        $t->same($ok, $evaluated['ok']);
    };
}

$childCases68 = [
    'channel bad first child reports failed IN result' => [1, 3, 0, 'nightly', false, false],
    'channel bad second child reports failed IS NULL result' => [1, 3, 1, 'nightly', false, false],
    'missing channel first child reports null IN result' => [2, 3, 0, null, null, true],
    'missing channel second child reports true IS NULL result' => [2, 3, 1, null, true, true],
    'high priority first child reports false IS NULL result' => [4, 5, 0, 11, false, false],
    'high priority second child reports failed upper bound' => [4, 5, 1, 11, false, false],
    'missing rules first child reports null equality result' => [3, 6, 0, null, null, true],
    'missing rules second child reports true IS NULL result' => [3, 6, 1, null, true, true],
];
foreach ($childCases68 as $name => [$row, $check, $child, $actual, $result, $ok]) {
    $tests['jsonb check current next68 ' . $name] = static function (TestRunner $t) use ($plan68, $term68, $row, $check, $child, $actual, $result, $ok): void {
        $or = $term68($plan68(), 'next', $row, $check);
        $evaluated = $or['terms'][$child];
        $t->same($actual, $evaluated['actual']);
        $t->same($result, $evaluated['result']);
        $t->same($ok, $evaluated['ok']);
    };
}

$afterCases68 = [
    'after slugs reflect accepted changes only' => ['plugin.slug', ['alpha', 'beta', 'gamma', 'delta']],
    'after priorities reflect accepted changes only' => ['plugin.priority', [10, 0, null, 7]],
    'after channels reflect accepted changes only' => ['plugin.channel', ['stable', null, null, 'beta']],
    'after descriptions reflect accepted changes only' => ['plugin.description', ['Alpha updated', 'Beta import', null, null]],
];
foreach ($afterCases68 as $name => [$path, $expected]) {
    $tests['jsonb check current next68 ' . $name] = static function (TestRunner $t) use ($plan68, $decode68, $path, $expected): void {
        $actual = [];
        foreach ($plan68()['after'] as $row) {
            $value = $decode68($row['key_value']);
            foreach (explode('.', $path) as $part) {
                $value = is_array($value) && array_key_exists($part, $value) ? $value[$part] : null;
            }
            $actual[] = $value;
        }
        $t->same($expected, $actual);
    };
}

return $tests;
