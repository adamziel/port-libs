<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonbCheckCurrentNextPlan;

$jsonb64 = static fn (array $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

$schema64 = <<<'SQL'
CREATE TABLE app_settings(
  setting_id INTEGER PRIMARY KEY,
  key_name TEXT NOT NULL,
  key_value BLOB,
  load_policy TEXT,
  CHECK(json_valid(key_value, 8)),
  CHECK(json_type(key_value, '$.module') = 'object'),
  CHECK(json_type(key_value, '$.module.slug') = 'text'),
  CHECK(json_extract(key_value, '$.module.slug') <> ''),
  CHECK(json_extract(key_value, '$.module.rank') >= 1 AND json_extract(key_value, '$.module.rank') <= 99),
  CHECK(json_extract(key_value, '$.module.channel') IN ('stable','beta','nightly')),
  CHECK(json_array_length(key_value, '$.module.rules') >= 1),
  CHECK(json_type(key_value, '$.module.enabled') IN ('true','false')),
  CHECK(json_extract(key_value, '$.module.version') IS NOT NULL)
)
SQL;

$rows64 = [
    ['setting_id' => 201, 'key_name' => 'module_alpha_settings', 'key_value' => $jsonb64(['module' => ['slug' => 'alpha', 'rank' => 10, 'channel' => 'stable', 'rules' => ['cache'], 'enabled' => true, 'version' => '1.0']]), 'load_policy' => 'yes'],
    ['setting_id' => 202, 'key_name' => 'module_beta_settings', 'key_value' => $jsonb64(['module' => ['slug' => 'beta', 'rank' => 20, 'channel' => 'beta', 'rules' => ['seo', 'media'], 'enabled' => false, 'version' => '2.0']]), 'load_policy' => 'yes'],
    ['setting_id' => 203, 'key_name' => 'module_gamma_settings', 'key_value' => $jsonb64(['module' => ['slug' => 'gamma', 'rank' => 30, 'channel' => 'nightly', 'rules' => ['debug'], 'enabled' => true, 'version' => '3.0']]), 'load_policy' => 'no'],
];

$changes64 = [
    ['op' => 'UPDATE', 'rowid' => 201, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.module.rank', 'value' => 15],
        ['function' => 'jsonb_set', 'path' => '$.module.channel', 'value' => 'beta'],
    ]],
    ['op' => 'UPDATE', 'rowid' => 202, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.module.rank', 'value' => 120],
    ]],
    ['op' => 'UPDATE', 'rowid' => 203, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.module.rules', 'value' => $jsonb64([])],
    ]],
    ['op' => 'INSERT', 'row' => ['setting_id' => 204, 'key_name' => 'module_delta_settings', 'key_value' => $jsonb64(['module' => ['slug' => 'delta', 'rank' => 40, 'channel' => 'stable', 'rules' => ['import'], 'enabled' => false, 'version' => '4.0']]), 'load_policy' => 'yes']],
    ['op' => 'INSERT', 'row' => ['setting_id' => 205, 'key_name' => 'module_empty_slug_settings', 'key_value' => $jsonb64(['module' => ['slug' => '', 'rank' => 50, 'channel' => 'stable', 'rules' => ['import'], 'enabled' => true, 'version' => '5.0']]), 'load_policy' => 'yes']],
    ['op' => 'INSERT', 'row' => ['setting_id' => 206, 'key_name' => 'module_missing_version_settings', 'key_value' => $jsonb64(['module' => ['slug' => 'missing-version', 'rank' => 60, 'channel' => 'stable', 'rules' => ['import'], 'enabled' => true]]), 'load_policy' => 'no']],
];

$plan64 = static fn (): array => SQLiteJsonbCheckCurrentNextPlan::plan($schema64, $rows64, $changes64);
$term64 = static function (array $plan, int $change, int $check, int $term = 0): array {
    return $plan['next'][$change]['checks'][$check]['terms'][$term];
};
$decode64 = static fn (SQLiteBlobValue $blob): mixed => SQLiteJsonB::decode($blob->bytes);

$tests = [
    'jsonb check current next64 extracts table name and check count' => static function (TestRunner $t) use ($plan64): void {
        $plan = $plan64();
        $t->same('app_settings', $plan['table']);
        $t->same(9, count($plan['checks']));
    },
    'jsonb check current next64 preserves check sql order' => static function (TestRunner $t) use ($plan64): void {
        $checks = array_column($plan64()['checks'], 'sql');
        $t->same('CHECK(json_valid(key_value, 8))', $checks[0]);
        $t->same('CHECK(json_extract(key_value, \'$.module.version\') IS NOT NULL)', $checks[8]);
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
        $t->same([201, 202, 203, 204], array_column($after, 'setting_id'));
    },
    'jsonb check current next64 decodes accepted updated jsonb payload' => static function (TestRunner $t) use ($plan64, $decode64): void {
        $alpha = $decode64($plan64()['after'][0]['key_value']);
        $t->same(15, $alpha['module']['rank']);
        $t->same('beta', $alpha['module']['channel']);
    },
    'jsonb check current next64 preserves rejected rank update payload' => static function (TestRunner $t) use ($plan64, $decode64): void {
        $beta = $decode64($plan64()['after'][1]['key_value']);
        $t->same(20, $beta['module']['rank']);
    },
    'jsonb check current next64 preserves rejected empty rules payload' => static function (TestRunner $t) use ($plan64, $decode64): void {
        $gamma = $decode64($plan64()['after'][2]['key_value']);
        $t->same(['debug'], $gamma['module']['rules']);
    },
    'jsonb check current next64 appends accepted insert row' => static function (TestRunner $t) use ($plan64): void {
        $after = $plan64()['after'];
        $t->same(204, $after[3]['setting_id']);
        $t->same('module_delta_settings', $after[3]['key_name']);
    },
];

$termCases64 = [
    'valid jsonb flag term accepts strict blob' => [0, 0, 1, true],
    'module object type term accepts object root' => [0, 1, 'object', true],
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
    'after slugs reflect accepted only' => ['module.slug', ['alpha', 'beta', 'gamma', 'delta']],
    'after ranks reflect accepted only' => ['module.rank', [15, 20, 30, 40]],
    'after channels reflect accepted only' => ['module.channel', ['beta', 'beta', 'nightly', 'stable']],
    'after versions remain required' => ['module.version', ['1.0', '2.0', '3.0', '4.0']],
    'after rules preserve rejected empty array update' => ['module.rules.count', [1, 2, 1, 1]],
];
foreach ($afterCases64 as $name => [$path, $expected]) {
    $tests['jsonb check current next64 ' . $name] = static function (TestRunner $t) use ($plan64, $decode64, $path, $expected): void {
        $actual = [];
        foreach ($plan64()['after'] as $row) {
            $value = $decode64($row['key_value']);
            foreach (explode('.', $path) as $part) {
                $value = $part === 'count' ? count($value) : $value[$part];
            }
            $actual[] = $value;
        }
        $t->same($expected, $actual);
    };
}

$guardCases64 = [
    'rejects schema without checks' => static fn (): array => SQLiteJsonbCheckCurrentNextPlan::plan('CREATE TABLE app_settings(setting_id INTEGER PRIMARY KEY, key_value BLOB)', $rows64, $changes64),
    'rejects malformed check parentheses' => static fn (): array => SQLiteJsonbCheckCurrentNextPlan::plan('CREATE TABLE app_settings(setting_id INTEGER, key_value BLOB, CHECK(json_valid(key_value,8)', $rows64, $changes64),
    'rejects update without rowid' => static fn (): array => SQLiteJsonbCheckCurrentNextPlan::plan($schema64, $rows64, [['op' => 'UPDATE', 'set' => ['load_policy' => 'no']]]),
    'rejects update missing current row' => static fn (): array => SQLiteJsonbCheckCurrentNextPlan::plan($schema64, $rows64, [['op' => 'UPDATE', 'rowid' => 999, 'set' => ['load_policy' => 'no']]]),
    'rejects insert without row' => static fn (): array => SQLiteJsonbCheckCurrentNextPlan::plan($schema64, $rows64, [['op' => 'INSERT']]),
    'rejects delete op as out of scope' => static fn (): array => SQLiteJsonbCheckCurrentNextPlan::plan($schema64, $rows64, [['op' => 'DELETE', 'rowid' => 201]]),
    'rejects rows without rowid alias' => static fn (): array => SQLiteJsonbCheckCurrentNextPlan::plan($schema64, [['key_name' => 'bad', 'key_value' => $jsonb64(['module' => []])]], []),
];
foreach ($guardCases64 as $name => $callable) {
    $tests['jsonb check current next64 ' . $name] = static function (TestRunner $t) use ($callable): void {
        $t->throws(InvalidArgumentException::class, $callable);
    };
}

$tests['jsonb check current next64 supports neutral default json column option'] = static function (TestRunner $t) use ($jsonb64): void {
    $schema = <<<'SQL'
CREATE TABLE event_records(
  id INTEGER PRIMARY KEY,
  payload_jsonb BLOB,
  CHECK(json_valid(payload_jsonb, 8)),
  CHECK(json_extract(payload_jsonb, '$.rank') BETWEEN 1 AND 9)
)
SQL;
    $plan = SQLiteJsonbCheckCurrentNextPlan::plan(
        $schema,
        [['id' => 1, 'payload_jsonb' => $jsonb64(['rank' => 2])]],
        [
            ['op' => 'UPDATE', 'rowid' => 1, 'mutations' => [
                ['function' => 'jsonb_set', 'path' => '$.rank', 'value' => 7],
            ]],
            ['op' => 'INSERT', 'row' => ['id' => 2, 'payload_jsonb' => $jsonb64(['rank' => 12])]],
        ],
        ['jsonColumn' => 'payload_jsonb'],
    );

    $t->same('event_records', $plan['table']);
    $t->same([true, false], array_column($plan['next'], 'ok'));
    $t->same([1], array_column($plan['accepted'], 'rowid'));
    $t->same(7, SQLiteJsonB::decode($plan['after'][0]['payload_jsonb']->bytes)['rank']);
};

$tests['jsonb check current next64 supports neutral dynamic rowid column option'] = static function (TestRunner $t) use ($jsonb64): void {
    $schema = <<<'SQL'
CREATE TABLE tenant_settings(
  tenant_setting_key TEXT PRIMARY KEY,
  payload_jsonb BLOB,
  CHECK(json_valid(payload_jsonb, 8)),
  CHECK(json_extract(payload_jsonb, '$.rank') BETWEEN 1 AND 9)
)
SQL;
    $plan = SQLiteJsonbCheckCurrentNextPlan::plan(
        $schema,
        [
            ['tenant_setting_key' => 'tenant-a:feature-flags', 'payload_jsonb' => $jsonb64(['rank' => 2])],
            ['tenant_setting_key' => 'tenant-b:feature-flags', 'payload_jsonb' => $jsonb64(['rank' => 4])],
        ],
        [
            ['op' => 'UPDATE', 'rowid' => 'tenant-a:feature-flags', 'mutations' => [
                ['function' => 'jsonb_set', 'path' => '$.rank', 'value' => 7],
            ]],
            ['op' => 'INSERT', 'row' => ['tenant_setting_key' => 'tenant-c:feature-flags', 'payload_jsonb' => $jsonb64(['rank' => 12])]],
        ],
        ['jsonColumn' => 'payload_jsonb', 'rowidColumn' => 'tenant_setting_key'],
    );

    $t->same('tenant_settings', $plan['table']);
    $t->same(['tenant-a:feature-flags', 'tenant-b:feature-flags'], array_column($plan['current'], 'rowid'));
    $t->same(['tenant-a:feature-flags'], array_column($plan['accepted'], 'rowid'));
    $t->same(['tenant-c:feature-flags'], array_column($plan['rejected'], 'rowid'));
    $t->same(7, SQLiteJsonB::decode($plan['after'][0]['payload_jsonb']->bytes)['rank']);
};

$tests['jsonb check current next64 keeps production source-neutral defaults'] = static function (TestRunner $t) use ($jsonb64): void {
    $source = file_get_contents(dirname(__DIR__) . '/src/SQLiteJsonbCheckCurrentNextPlan.php');
    $t->true(is_string($source));
    $forbidden = [
        'w' . 'p_',
        'w' . 'p_options',
        'option' . '_id',
        'option' . '_name',
        'option' . '_value',
        'auto' . 'load',
        'blog' . '_id',
        'Word' . 'Press',
        'word' . 'press',
    ];
    $pattern = '/' . implode('|', array_map('preg_quote', $forbidden)) . '/';
    $t->same(0, preg_match($pattern, (string) $source));

    $schema = <<<'SQL'
CREATE TABLE app_settings(
  setting_id INTEGER PRIMARY KEY,
  key_value BLOB,
  CHECK(json_valid(key_value, 8)),
  CHECK(json_extract(key_value, '$.rank') BETWEEN 1 AND 9)
)
SQL;
    $plan = SQLiteJsonbCheckCurrentNextPlan::plan(
        $schema,
        [['setting_id' => 1, 'key_value' => $jsonb64(['rank' => 2])]],
        [
            ['op' => 'UPDATE', 'rowid' => 1, 'mutations' => [
                ['function' => 'jsonb_set', 'path' => '$.rank', 'value' => 7],
            ]],
            ['op' => 'INSERT', 'row' => ['setting_id' => 2, 'key_value' => $jsonb64(['rank' => 12])]],
        ],
    );

    $t->same([1], array_column($plan['accepted'], 'rowid'));
    $t->same([2], array_column($plan['rejected'], 'rowid'));
    $t->same(7, SQLiteJsonB::decode($plan['after'][0]['key_value']->bytes)['rank']);
};

$tests['jsonb check current next64 rejects invalid neutral rowid column option'] = static function (TestRunner $t) use ($schema64, $rows64, $changes64): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn (): array => SQLiteJsonbCheckCurrentNextPlan::plan($schema64, $rows64, $changes64, ['rowidColumn' => 'tenant-key']),
    );
};

$tests['jsonb check current next64 rejects invalid neutral json column option'] = static function (TestRunner $t) use ($schema64, $rows64, $changes64): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn (): array => SQLiteJsonbCheckCurrentNextPlan::plan($schema64, $rows64, $changes64, ['jsonColumn' => 'payload-jsonb']),
    );
};

return $tests;
