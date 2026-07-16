<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonbGeneratedIndexOperatorCurrentSourceNextPlan;

$jsonb = static fn (array $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

$indexes = static fn (): array => [
    [
        'name' => 'idx_operator_channel',
        'rootPage' => 1071,
        'sql' => "CREATE INDEX idx_operator_channel ON app_settings((key_value ->> '$.feature.channel') COLLATE NOCASE, key_name) WHERE load_policy = 'yes'",
    ],
    [
        'name' => 'idx_operator_priority',
        'rootPage' => 1072,
        'sql' => "CREATE INDEX idx_operator_priority ON app_settings(key_value ->> '$.feature.priority', setting_id)",
    ],
    [
        'name' => 'idx_operator_limits_json',
        'rootPage' => 1073,
        'sql' => "CREATE INDEX idx_operator_limits_json ON app_settings((key_value -> '$.feature.limits') COLLATE BINARY DESC, key_name)",
    ],
    [
        'name' => 'idx_operator_enabled_partial',
        'rootPage' => 1074,
        'sql' => "CREATE INDEX idx_operator_enabled_partial ON app_settings(key_value ->> '$.feature.enabled') WHERE key_value IS NOT NULL",
    ],
];

$currentRows = static fn (): array => [
    [
        'setting_id' => 1,
        'key_name' => 'feature_cache_settings',
        'load_policy' => 'yes',
        'key_value' => $jsonb(['feature' => ['channel' => 'stable', 'priority' => 7, 'enabled' => true, 'limits' => ['daily' => 25]]]),
    ],
    [
        'setting_id' => 2,
        'key_name' => 'feature_forms_settings',
        'load_policy' => 'no',
        'key_value' => $jsonb(['feature' => ['channel' => 'beta', 'priority' => 3, 'enabled' => false, 'limits' => ['daily' => 10]]]),
    ],
    [
        'setting_id' => 3,
        'key_name' => 'feature_seo_settings',
        'load_policy' => 'yes',
        'key_value' => '{"feature":{"channel":"dev","priority":5,"enabled":true,"limits":{"daily":12}}}',
    ],
    [
        'setting_id' => 4,
        'key_name' => 'feature_empty_settings',
        'load_policy' => 'yes',
        'key_value' => null,
    ],
];

$nextRows = static fn (): array => [
    [
        'setting_id' => 1,
        'key_name' => 'feature_cache_settings',
        'load_policy' => 'yes',
        'key_value' => $jsonb(['feature' => ['channel' => 'rc', 'priority' => 8, 'enabled' => true, 'limits' => ['daily' => 30]]]),
    ],
    [
        'setting_id' => 2,
        'key_name' => 'feature_forms_settings',
        'load_policy' => 'yes',
        'key_value' => $jsonb(['feature' => ['channel' => 'beta', 'priority' => 3, 'enabled' => false, 'limits' => ['daily' => 10]]]),
    ],
    [
        'setting_id' => 3,
        'key_name' => 'feature_seo_settings',
        'load_policy' => 'no',
        'key_value' => '{"feature":{"channel":"dev","priority":9,"enabled":false,"limits":{"daily":12}}}',
    ],
    [
        'setting_id' => 5,
        'key_name' => 'feature_shop_settings',
        'load_policy' => 'yes',
        'key_value' => $jsonb(['feature' => ['channel' => 'stable', 'priority' => 6, 'enabled' => true, 'limits' => ['daily' => 40]]]),
    ],
];

$plan = static fn (): array => SQLiteJsonbGeneratedIndexOperatorCurrentSourceNextPlan::plan($indexes(), $currentRows(), $nextRows());
$entry = static function (array $entries, int $rowid, string $index, mixed $value): ?array {
    foreach ($entries as $entry) {
        if ($entry['rowid'] === $rowid && $entry['index'] === $index && $entry['value'] === $value) {
            return $entry;
        }
    }

    return null;
};

$tests = [
    'jsonb generated index operator current source next107 action label' => static fn (TestRunner $t) => $t->same('jsonb-generated-index-operator-current-source-next107', $plan()['action']),
    'jsonb generated index operator current source next107 parses operator indexes' => static fn (TestRunner $t) => $t->same(['idx_operator_channel', 'idx_operator_priority', 'idx_operator_limits_json', 'idx_operator_enabled_partial'], array_column($plan()['indexes'], 'name')),
    'jsonb generated index operator current source next107 preserves root pages' => static fn (TestRunner $t) => $t->same([1071, 1072, 1073, 1074], array_column($plan()['indexes'], 'rootPage')),
    'jsonb generated index operator current source next107 preserves operator families' => static fn (TestRunner $t) => $t->same(['->>', '->>', '->', '->>'], array_column($plan()['indexes'], 'operator')),
    'jsonb generated index operator current source next107 preserves normalized paths' => static fn (TestRunner $t) => $t->same(['$.feature.channel', '$.feature.priority', '$.feature.limits', '$.feature.enabled'], array_column($plan()['indexes'], 'path')),
    'jsonb generated index operator current source next107 preserves collations' => static fn (TestRunner $t) => $t->same(['NOCASE', 'BINARY', 'BINARY', 'BINARY'], array_column($plan()['indexes'], 'collation')),
    'jsonb generated index operator current source next107 preserves descending flag' => static fn (TestRunner $t) => $t->same([false, false, true, false], array_column($plan()['indexes'], 'descending')),
    'jsonb generated index operator current source next107 marks partial indexes' => static fn (TestRunner $t) => $t->same([true, false, false, true], array_column($plan()['indexes'], 'partialPredicate') !== [] ? array_map(static fn ($p): bool => $p !== null, array_column($plan()['indexes'], 'partialPredicate')) : []),
    'jsonb generated index operator current source next107 row transition rowids' => static fn (TestRunner $t) => $t->same([1, 2, 3, 4, 5], array_column($plan()['row_transitions'], 'rowid')),
    'jsonb generated index operator current source next107 row transition states' => static fn (TestRunner $t) => $t->same(['updated', 'updated', 'updated', 'deleted', 'inserted'], array_column($plan()['row_transitions'], 'state')),
    'jsonb generated index operator current source next107 changed entry count' => static fn (TestRunner $t) => $t->same(19, $plan()['changed_entry_count']),
    'jsonb generated index operator current source next107 unchanged rows track stable priority' => static fn (TestRunner $t) => $t->same(true, $entry($plan()['unchanged_entries'], 2, 'idx_operator_priority', 3) !== null),
    'jsonb generated index operator current source next107 unchanged rows track stable limits json' => static fn (TestRunner $t) => $t->same(true, $entry($plan()['unchanged_entries'], 2, 'idx_operator_limits_json', '{"daily":10}') !== null),
    'jsonb generated index operator current source next107 unchanged row current and next active' => static fn (TestRunner $t) => $t->same([true, true], [$entry($plan()['unchanged_entries'], 2, 'idx_operator_priority', 3)['currentActive'] ?? null, $entry($plan()['unchanged_entries'], 2, 'idx_operator_priority', 3)['nextActive'] ?? null]),
    'jsonb generated index operator current source next107 channel delete for changed jsonb row' => static fn (TestRunner $t) => $t->same(true, $entry($plan()['delete_entries'], 1, 'idx_operator_channel', 'stable') !== null),
    'jsonb generated index operator current source next107 channel insert for changed jsonb row' => static fn (TestRunner $t) => $t->same(true, $entry($plan()['insert_entries'], 1, 'idx_operator_channel', 'rc') !== null),
    'jsonb generated index operator current source next107 priority delete for changed jsonb row' => static fn (TestRunner $t) => $t->same(true, $entry($plan()['delete_entries'], 1, 'idx_operator_priority', 7) !== null),
    'jsonb generated index operator current source next107 priority insert for changed jsonb row' => static fn (TestRunner $t) => $t->same(true, $entry($plan()['insert_entries'], 1, 'idx_operator_priority', 8) !== null),
    'jsonb generated index operator current source next107 json value delete canonical object' => static fn (TestRunner $t) => $t->same(true, $entry($plan()['delete_entries'], 1, 'idx_operator_limits_json', '{"daily":25}') !== null),
    'jsonb generated index operator current source next107 json value insert canonical object' => static fn (TestRunner $t) => $t->same(true, $entry($plan()['insert_entries'], 1, 'idx_operator_limits_json', '{"daily":30}') !== null),
    'jsonb generated index operator current source next107 partial activation inserts channel' => static fn (TestRunner $t) => $t->same(true, $entry($plan()['insert_entries'], 2, 'idx_operator_channel', 'beta') !== null),
    'jsonb generated index operator current source next107 partial activation keeps enabled false value' => static fn (TestRunner $t) => $t->same(true, $entry($plan()['unchanged_entries'], 2, 'idx_operator_enabled_partial', 0) !== null),
    'jsonb generated index operator current source next107 partial deactivation deletes channel' => static fn (TestRunner $t) => $t->same(true, $entry($plan()['delete_entries'], 3, 'idx_operator_channel', 'dev') !== null),
    'jsonb generated index operator current source next107 text json priority update deletes old' => static fn (TestRunner $t) => $t->same(true, $entry($plan()['delete_entries'], 3, 'idx_operator_priority', 5) !== null),
    'jsonb generated index operator current source next107 text json priority update inserts next' => static fn (TestRunner $t) => $t->same(true, $entry($plan()['insert_entries'], 3, 'idx_operator_priority', 9) !== null),
    'jsonb generated index operator current source next107 text json enabled update deletes true' => static fn (TestRunner $t) => $t->same(true, $entry($plan()['delete_entries'], 3, 'idx_operator_enabled_partial', 1) !== null),
    'jsonb generated index operator current source next107 text json enabled update inserts false' => static fn (TestRunner $t) => $t->same(true, $entry($plan()['insert_entries'], 3, 'idx_operator_enabled_partial', 0) !== null),
    'jsonb generated index operator current source next107 deleted null row emits null source entries' => static fn (TestRunner $t) => $t->same(['idx_operator_channel', 'idx_operator_priority', 'idx_operator_limits_json'], array_column(array_values(array_filter($plan()['delete_entries'], static fn (array $entry): bool => $entry['rowid'] === 4)), 'index')),
    'jsonb generated index operator current source next107 inserted row channel insert' => static fn (TestRunner $t) => $t->same(true, $entry($plan()['insert_entries'], 5, 'idx_operator_channel', 'stable') !== null),
    'jsonb generated index operator current source next107 inserted row priority insert' => static fn (TestRunner $t) => $t->same(true, $entry($plan()['insert_entries'], 5, 'idx_operator_priority', 6) !== null),
    'jsonb generated index operator current source next107 inserted row limits insert' => static fn (TestRunner $t) => $t->same(true, $entry($plan()['insert_entries'], 5, 'idx_operator_limits_json', '{"daily":40}') !== null),
    'jsonb generated index operator current source next107 inserted row enabled insert' => static fn (TestRunner $t) => $t->same(true, $entry($plan()['insert_entries'], 5, 'idx_operator_enabled_partial', 1) !== null),
    'jsonb generated index operator current source next107 delete entries keep operation labels' => static fn (TestRunner $t) => $t->same(['delete-current'], array_values(array_unique(array_column($plan()['delete_entries'], 'operation')))),
    'jsonb generated index operator current source next107 insert entries keep operation labels' => static fn (TestRunner $t) => $t->same(['insert-next'], array_values(array_unique(array_column($plan()['insert_entries'], 'operation')))),
    'jsonb generated index operator current source next107 delete entries expose source column' => static fn (TestRunner $t) => $t->same(['key_value'], array_values(array_unique(array_column($plan()['delete_entries'], 'sourceColumn')))),
    'jsonb generated index operator current source next107 insert entries expose source column' => static fn (TestRunner $t) => $t->same(['key_value'], array_values(array_unique(array_column($plan()['insert_entries'], 'sourceColumn')))),
    'jsonb generated index operator current source next107 row change embeds current value' => static fn (TestRunner $t) => $t->same('stable', $plan()['row_transitions'][0]['index_changes'][0]['currentValue']),
    'jsonb generated index operator current source next107 row change embeds next value' => static fn (TestRunner $t) => $t->same('rc', $plan()['row_transitions'][0]['index_changes'][0]['nextValue']),
    'jsonb generated index operator current source next107 row change embeds canonical json next value' => static fn (TestRunner $t) => $t->same('{"daily":30}', $plan()['row_transitions'][0]['index_changes'][2]['nextValue']),
    'jsonb generated index operator current source next107 preserves deleted current row' => static fn (TestRunner $t) => $t->same('feature_empty_settings', $plan()['row_transitions'][3]['current']['key_name']),
    'jsonb generated index operator current source next107 preserves inserted next row' => static fn (TestRunner $t) => $t->same('feature_shop_settings', $plan()['row_transitions'][4]['next']['key_name']),
    'jsonb generated index operator current source next107 ignores non operator indexes' => static function (TestRunner $t) use ($currentRows, $nextRows): void {
        $plan = SQLiteJsonbGeneratedIndexOperatorCurrentSourceNextPlan::plan([
            ['name' => 'idx_plain', 'sql' => 'CREATE INDEX idx_plain ON app_settings(key_name)'],
        ], $currentRows(), $nextRows());
        $t->same([], $plan['indexes']);
        $t->same([], $plan['delete_entries']);
        $t->same([], $plan['insert_entries']);
    },
    'jsonb generated index operator current source next107 rejects missing rowid' => static function (TestRunner $t) use ($indexes, $nextRows): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonbGeneratedIndexOperatorCurrentSourceNextPlan::plan($indexes(), [['key_value' => '{}']], $nextRows()));
    },
    'jsonb generated index operator current source next107 rejects duplicate rowid' => static function (TestRunner $t) use ($indexes, $currentRows, $nextRows): void {
        $rows = $currentRows();
        $rows[] = $rows[0];
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonbGeneratedIndexOperatorCurrentSourceNextPlan::plan($indexes(), $rows, $nextRows()));
    },
    'jsonb generated index operator current source next107 rejects missing source column' => static function (TestRunner $t) use ($indexes, $nextRows): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonbGeneratedIndexOperatorCurrentSourceNextPlan::plan($indexes(), [['setting_id' => 1]], $nextRows()));
    },
    'jsonb generated index operator current source next107 rejects non json source column' => static function (TestRunner $t) use ($indexes, $nextRows): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonbGeneratedIndexOperatorCurrentSourceNextPlan::plan($indexes(), [['setting_id' => 1, 'load_policy' => 'yes', 'key_value' => ['bad']]], $nextRows()));
    },
];

foreach ([
    [1, 'idx_operator_channel', 'stable'],
    [1, 'idx_operator_priority', 7],
    [1, 'idx_operator_limits_json', '{"daily":25}'],
    [3, 'idx_operator_channel', 'dev'],
    [3, 'idx_operator_priority', 5],
    [3, 'idx_operator_enabled_partial', 1],
] as [$rowid, $index, $value]) {
    $tests["jsonb generated index operator current source next107 delete lookup {$rowid} {$index}"] = static fn (TestRunner $t) => $t->same(true, $entry($plan()['delete_entries'], $rowid, $index, $value) !== null);
}

foreach ([
    [1, 'idx_operator_channel', 'rc'],
    [1, 'idx_operator_priority', 8],
    [1, 'idx_operator_limits_json', '{"daily":30}'],
    [2, 'idx_operator_channel', 'beta'],
    [3, 'idx_operator_priority', 9],
    [3, 'idx_operator_enabled_partial', 0],
    [5, 'idx_operator_channel', 'stable'],
    [5, 'idx_operator_priority', 6],
    [5, 'idx_operator_limits_json', '{"daily":40}'],
    [5, 'idx_operator_enabled_partial', 1],
] as [$rowid, $index, $value]) {
    $tests["jsonb generated index operator current source next107 insert lookup {$rowid} {$index}"] = static fn (TestRunner $t) => $t->same(true, $entry($plan()['insert_entries'], $rowid, $index, $value) !== null);
}

return $tests;
