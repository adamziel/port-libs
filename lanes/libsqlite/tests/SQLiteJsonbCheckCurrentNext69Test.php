<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonbCheckCurrentNextPlan;

$jsonb69 = static fn (array $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

$schema69 = <<<'SQL'
CREATE TABLE app_settings(
  setting_id INTEGER PRIMARY KEY,
  key_name TEXT NOT NULL,
  key_value BLOB,
  load_policy TEXT,
  CHECK(json_valid(key_value, 8)),
  CHECK(json_extract(key_value, '$.plugin.channel') NOT IN ('nightly','dev','blocked')),
  CHECK(json_extract(key_value, '$.plugin.rank') NOT BETWEEN 51 AND 99),
  CHECK(json_extract(key_value, '$.plugin.min_app') NOT BETWEEN 6.8 AND 7.9),
  CHECK(json_extract(key_value, '$.plugin.family') NOT IN ('legacy','insecure')),
  CHECK(json_extract(key_value, '$.plugin.channel') NOT IN ('nightly','dev') AND json_extract(key_value, '$.plugin.rank') NOT BETWEEN 80 AND 90)
)
SQL;

$rows69 = [
    ['setting_id' => 401, 'key_name' => 'plugin_alpha_settings', 'key_value' => $jsonb69(['plugin' => ['channel' => 'stable', 'rank' => 25, 'min_app' => 6.5, 'family' => 'modern']]), 'load_policy' => 'yes'],
    ['setting_id' => 402, 'key_name' => 'plugin_beta_settings', 'key_value' => $jsonb69(['plugin' => ['channel' => 'beta', 'rank' => 50, 'min_app' => 6.7, 'family' => 'modern']]), 'load_policy' => 'yes'],
    ['setting_id' => 403, 'key_name' => 'plugin_archive_settings', 'key_value' => $jsonb69(['plugin' => ['channel' => 'stable', 'rank' => 100, 'min_app' => 8.0, 'family' => 'archive']]), 'load_policy' => 'no'],
];

$changes69 = [
    ['op' => 'UPDATE', 'rowid' => 401, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.plugin.rank', 'value' => 45],
        ['function' => 'jsonb_set', 'path' => '$.plugin.channel', 'value' => 'beta'],
    ]],
    ['op' => 'UPDATE', 'rowid' => 402, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.plugin.channel', 'value' => 'nightly'],
    ]],
    ['op' => 'UPDATE', 'rowid' => 403, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.plugin.rank', 'value' => 75],
    ]],
    ['op' => 'INSERT', 'row' => ['setting_id' => 404, 'key_name' => 'plugin_future_settings', 'key_value' => $jsonb69(['plugin' => ['channel' => 'stable', 'rank' => 10, 'min_app' => 7.0, 'family' => 'modern']]), 'load_policy' => 'no']],
    ['op' => 'INSERT', 'row' => ['setting_id' => 405, 'key_name' => 'plugin_legacy_settings', 'key_value' => $jsonb69(['plugin' => ['channel' => 'stable', 'rank' => 10, 'min_app' => 6.6, 'family' => 'legacy']]), 'load_policy' => 'no']],
    ['op' => 'INSERT', 'row' => ['setting_id' => 406, 'key_name' => 'plugin_safe_settings', 'key_value' => $jsonb69(['plugin' => ['channel' => 'stable', 'rank' => 100, 'min_app' => 8.0, 'family' => 'modern']]), 'load_policy' => 'yes']],
    ['op' => 'INSERT', 'row' => ['setting_id' => 407, 'key_name' => 'plugin_high_rank_settings', 'key_value' => $jsonb69(['plugin' => ['channel' => 'stable', 'rank' => 85, 'min_app' => 8.0, 'family' => 'modern']]), 'load_policy' => 'yes']],
    ['op' => 'INSERT', 'row' => ['setting_id' => 408, 'key_name' => 'plugin_blocked_settings', 'key_value' => $jsonb69(['plugin' => ['channel' => 'blocked', 'rank' => 20, 'min_app' => 6.6, 'family' => 'modern']]), 'load_policy' => 'no']],
    ['op' => 'INSERT', 'row' => ['setting_id' => 409, 'key_name' => 'plugin_low_rank_settings', 'key_value' => $jsonb69(['plugin' => ['channel' => 'beta', 'rank' => 1, 'min_app' => 6.4, 'family' => 'modern']]), 'load_policy' => 'yes']],
];

$plan69 = static fn (): array => SQLiteJsonbCheckCurrentNextPlan::plan($schema69, $rows69, $changes69);
$decode69 = static fn (SQLiteBlobValue $blob): mixed => SQLiteJsonB::decode($blob->bytes);
$term69 = static function (array $plan, int $change, int $check, int $term = 0): array {
    return $plan['next'][$change]['checks'][$check]['terms'][$term];
};

$tests = [
    'jsonb check current next69 extracts not-in not-between check constraints' => static function (TestRunner $t) use ($plan69): void {
        $plan = $plan69();
        $t->same('app_settings', $plan['table']);
        $t->same(6, count($plan['checks']));
    },
    'jsonb check current next69 preserves not-in and not-between sql text' => static function (TestRunner $t) use ($plan69): void {
        $checks = array_column($plan69()['checks'], 'sql');
        $t->same("CHECK(json_extract(key_value, '$.plugin.channel') NOT IN ('nightly','dev','blocked'))", $checks[1]);
        $t->same("CHECK(json_extract(key_value, '$.plugin.rank') NOT BETWEEN 51 AND 99)", $checks[2]);
    },
    'jsonb check current next69 validates all current rows' => static function (TestRunner $t) use ($plan69): void {
        $t->same([true, true, true], array_column($plan69()['current'], 'ok'));
    },
    'jsonb check current next69 accepts only rows outside denied ranges' => static function (TestRunner $t) use ($plan69): void {
        $plan = $plan69();
        $t->same(3, $plan['changes']);
        $t->same([401, 406, 409], array_column($plan['accepted'], 'rowid'));
    },
    'jsonb check current next69 rejects denied channels and ranges' => static function (TestRunner $t) use ($plan69): void {
        $plan = $plan69();
        $t->same(6, $plan['rejectedChanges']);
        $t->same([402, 403, 404, 405, 407, 408], array_column($plan['rejected'], 'rowid'));
    },
    'jsonb check current next69 after rows include accepted update and inserts only' => static function (TestRunner $t) use ($plan69): void {
        $t->same([401, 402, 403, 406, 409], array_column($plan69()['after'], 'setting_id'));
    },
    'jsonb check current next69 rejected updates preserve current jsonb images' => static function (TestRunner $t) use ($plan69, $decode69): void {
        $after = $plan69()['after'];
        $beta = $decode69($after[1]['key_value']);
        $archive = $decode69($after[2]['key_value']);
        $t->same('beta', $beta['plugin']['channel']);
        $t->same(100, $archive['plugin']['rank']);
    },
];

$statusCases69 = [
    'next ok states match not-in not-between admission' => ['next', 'ok', [true, false, false, false, false, true, false, false, true]],
    'accepted ops preserve candidate order' => ['accepted', 'op', ['UPDATE', 'INSERT', 'INSERT']],
    'rejected ops preserve candidate order' => ['rejected', 'op', ['UPDATE', 'UPDATE', 'INSERT', 'INSERT', 'INSERT', 'INSERT']],
    'current rowids are stable' => ['current', 'rowid', [401, 402, 403]],
    'next rowids include all candidates' => ['next', 'rowid', [401, 402, 403, 404, 405, 406, 407, 408, 409]],
    'accepted rowids are final admitted changes' => ['accepted', 'rowid', [401, 406, 409]],
    'rejected rowids are skipped changes' => ['rejected', 'rowid', [402, 403, 404, 405, 407, 408]],
];
foreach ($statusCases69 as $name => [$section, $column, $expected]) {
    $tests['jsonb check current next69 ' . $name] = static function (TestRunner $t) use ($plan69, $section, $column, $expected): void {
        $t->same($expected, array_column($plan69()[$section], $column));
    };
}

$termCases69 = [
    'accepted beta update passes channel NOT IN' => [0, 1, 'beta', true],
    'nightly update fails channel NOT IN' => [1, 1, 'nightly', false],
    'blocked insert fails channel NOT IN' => [7, 1, 'blocked', false],
    'accepted rank 45 passes denied rank band' => [0, 2, 45, true],
    'archive rank 75 update fails denied rank band' => [2, 2, 75, false],
    'safe rank 100 passes denied rank band' => [5, 2, 100, true],
    'high rank 85 insert fails denied rank band' => [6, 2, 85, false],
    'future min app 7 fails denied future band' => [3, 3, 7.0, false],
    'safe min app 8 passes denied future band' => [5, 3, 8.0, true],
    'low min app 6.4 passes denied future band' => [8, 3, 6.4, true],
    'legacy family fails family NOT IN' => [4, 4, 'legacy', false],
    'modern family passes family NOT IN' => [5, 4, 'modern', true],
    'blocked modern still passes family NOT IN' => [7, 4, 'modern', true],
    'accepted beta low rank passes grouped channel NOT IN term' => [8, 5, 'beta', true],
    'high stable rank passes grouped channel NOT IN term' => [6, 5, 'stable', true],
    'nightly update fails grouped channel NOT IN term' => [1, 5, 'nightly', false],
];
foreach ($termCases69 as $name => [$change, $check, $actual, $ok]) {
    $tests['jsonb check current next69 ' . $name] = static function (TestRunner $t) use ($plan69, $term69, $change, $check, $actual, $ok): void {
        $term = $term69($plan69(), $change, $check);
        $t->same($actual, $term['actual']);
        $t->same($ok, $term['ok']);
    };
}

$andTermCases69 = [
    'grouped rank term passes for beta low rank' => [8, 1, 'NOT BETWEEN', 1, true],
    'grouped rank term fails for stable high rank' => [6, 1, 'NOT BETWEEN', 85, false],
    'grouped rank term passes for nightly update' => [1, 1, 'NOT BETWEEN', 50, true],
];
foreach ($andTermCases69 as $name => [$change, $termIndex, $operator, $actual, $ok]) {
    $tests['jsonb check current next69 ' . $name] = static function (TestRunner $t) use ($plan69, $term69, $change, $termIndex, $operator, $actual, $ok): void {
        $term = $term69($plan69(), $change, 5, $termIndex);
        $t->same($operator, $term['operator']);
        $t->same($actual, $term['actual']);
        $t->same($ok, $term['ok']);
    };
}

$afterCases69 = [
    'after names reflect accepted only' => ['key_name', ['plugin_alpha_settings', 'plugin_beta_settings', 'plugin_archive_settings', 'plugin_safe_settings', 'plugin_low_rank_settings']],
    'after load_policy reflects accepted inserts' => ['load_policy', ['yes', 'yes', 'no', 'yes', 'yes']],
];
foreach ($afterCases69 as $name => [$column, $expected]) {
    $tests['jsonb check current next69 ' . $name] = static function (TestRunner $t) use ($plan69, $column, $expected): void {
        $t->same($expected, array_column($plan69()['after'], $column));
    };
}

$decodedAfterCases69 = [
    'after channels reflect accepted only' => ['plugin.channel', ['beta', 'beta', 'stable', 'stable', 'beta']],
    'after ranks reflect accepted only' => ['plugin.rank', [45, 50, 100, 100, 1]],
    'after min app values reflect accepted only' => ['plugin.min_app', [6.5, 6.7, 8.0, 8.0, 6.4]],
    'after families remain outside denied list' => ['plugin.family', ['modern', 'modern', 'archive', 'modern', 'modern']],
];
foreach ($decodedAfterCases69 as $name => [$path, $expected]) {
    $tests['jsonb check current next69 ' . $name] = static function (TestRunner $t) use ($plan69, $decode69, $path, $expected): void {
        $actual = [];
        foreach ($plan69()['after'] as $row) {
            $value = $decode69($row['key_value']);
            foreach (explode('.', $path) as $part) {
                $value = $value[$part];
            }
            $actual[] = $value;
        }
        $t->same($expected, $actual);
    };
}

$structureCases69 = [
    'channel check parses NOT IN operator' => [0, 1, 'NOT IN'],
    'rank check parses NOT BETWEEN operator' => [2, 2, 'NOT BETWEEN'],
    'min app check parses NOT BETWEEN operator' => [3, 3, 'NOT BETWEEN'],
    'family check parses NOT IN operator' => [4, 4, 'NOT IN'],
    'grouped check first term parses NOT IN operator' => [8, 5, 'NOT IN'],
];
foreach ($structureCases69 as $name => [$change, $check, $operator]) {
    $tests['jsonb check current next69 ' . $name] = static function (TestRunner $t) use ($plan69, $term69, $change, $check, $operator): void {
        $t->same($operator, $term69($plan69(), $change, $check)['operator']);
    };
}

$tests['jsonb check current next69 grouped check keeps two top-level AND terms'] = static function (TestRunner $t) use ($plan69): void {
    $terms = $plan69()['next'][8]['checks'][5]['terms'];
    $t->same(['NOT IN', 'NOT BETWEEN'], array_column($terms, 'operator'));
};

$guardCases69 = [
    'rejects non literal NOT IN list entry' => static fn (): array => SQLiteJsonbCheckCurrentNextPlan::plan("CREATE TABLE app_settings(setting_id INTEGER PRIMARY KEY, key_value BLOB, CHECK(json_extract(key_value, '$.plugin.channel') NOT IN (lower('dev'))))", $rows69, []),
    'rejects non literal NOT BETWEEN lower bound' => static fn (): array => SQLiteJsonbCheckCurrentNextPlan::plan("CREATE TABLE app_settings(setting_id INTEGER PRIMARY KEY, key_value BLOB, CHECK(json_extract(key_value, '$.plugin.rank') NOT BETWEEN json_extract(key_value, '$.a') AND 9))", $rows69, []),
];
foreach ($guardCases69 as $name => $callable) {
    $tests['jsonb check current next69 ' . $name] = static function (TestRunner $t) use ($callable): void {
        $t->throws(InvalidArgumentException::class, $callable);
    };
}

return $tests;
