<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonbPatchGeneratedIndexPlan;

$tests = [];

$jsonb48 = static fn (array $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

$generated48 = [
    "plugin_enabled INTEGER GENERATED ALWAYS AS (json_extract(jsonb_patch(option_value, '{plugin:{source:\"wp-import\",enabled:false}}'), '$.plugin.enabled')) VIRTUAL",
    "plugin_channel TEXT GENERATED ALWAYS AS (json_extract(jsonb_patch(option_value, '{plugin:{source:\"wp-import\"}}'), '$.plugin.channel')) STORED",
    "plugin_priority INTEGER GENERATED ALWAYS AS (json_extract(jsonb_patch(option_value, '{plugin:{source:\"wp-import\"}}'), '$.plugin.priority')) VIRTUAL",
];

$indexes48 = [
    [
        'name' => 'idx_wp_options_jsonb_channel_covering',
        'rootPage' => 91,
        'estimatedRows' => 120,
        'coveringColumns' => ['option_id', 'option_name', 'autoload', 'plugin_channel', 'plugin_priority'],
        'sql' => "CREATE INDEX idx_wp_options_jsonb_channel_covering ON wp_options(plugin_channel, option_name) WHERE autoload='yes'",
    ],
    [
        'name' => 'idx_wp_options_jsonb_priority_covering',
        'rootPage' => 92,
        'estimatedRows' => 80,
        'coveringColumns' => ['option_id', 'option_name', 'autoload', 'plugin_priority'],
        'sql' => "CREATE INDEX idx_wp_options_jsonb_priority_covering ON wp_options(json_extract(jsonb_patch(option_value, '{plugin:{source:\"wp-import\"}}'), '$.plugin.priority')) WHERE autoload='yes'",
    ],
    [
        'name' => 'idx_wp_options_jsonb_enabled_no_cover',
        'rootPage' => 93,
        'estimatedRows' => 60,
        'coveringColumns' => ['option_id', 'plugin_enabled'],
        'sql' => "CREATE INDEX idx_wp_options_jsonb_enabled_no_cover ON wp_options(plugin_enabled) WHERE autoload='yes'",
    ],
];

$rows48 = [
    [
        'option_id' => 101,
        'option_name' => 'plugin_alpha_settings',
        'autoload' => 'yes',
        'option_value' => $jsonb48(['plugin' => ['enabled' => false, 'channel' => 'beta', 'priority' => 3]]),
    ],
    [
        'option_id' => 102,
        'option_name' => 'plugin_beta_settings',
        'autoload' => 'yes',
        'option_value' => $jsonb48(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => 8]]),
    ],
    [
        'option_id' => 103,
        'option_name' => 'plugin_gamma_settings',
        'autoload' => 'no',
        'option_value' => $jsonb48(['plugin' => ['enabled' => true, 'channel' => 'nightly', 'priority' => 11]]),
    ],
];

$assignments48 = [
    'autoload' => static fn (array $current, array $incoming): mixed => $incoming['autoload'] ?? $current['autoload'],
    'option_value' => static fn (array $current, array $incoming): mixed => $incoming['option_value'] ?? $current['option_value'],
];

$predicate48 = [
    'operator' => 'AND',
    'terms' => [
        ['operator' => '=', 'left' => ['generatedColumn' => 'plugin_channel'], 'right' => 'stable'],
        ['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => 'yes'],
    ],
];

$plan48 = static function (array $incoming, array $needed = ['option_id', 'option_name', 'autoload', 'plugin_channel', 'plugin_priority'], ?callable $where = null) use ($indexes48, $generated48, $rows48, $assignments48, $predicate48): array {
    return SQLiteJsonbPatchGeneratedIndexPlan::planUpsertCoveringTable(
        $indexes48,
        $generated48,
        $rows48,
        $incoming,
        ['option_name'],
        $assignments48,
        $where,
        [['option_name'], ['option_id']],
        'option_id',
        $predicate48,
        [['column' => 'plugin_channel']],
        $needed,
    );
};

$tests['jsonb table upsert covering current next48 chooses covering channel index'] = static function (TestRunner $t) use ($plan48, $jsonb48): void {
    $plan = $plan48([['option_id' => 101, 'option_name' => 'plugin_alpha_settings', 'autoload' => 'yes', 'option_value' => $jsonb48(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => 5]])]]);
    $t->same('idx_wp_options_jsonb_channel_covering', $plan['covering_plan']['name']);
};

$tests['jsonb table upsert covering current next48 selected plan is covering'] = static function (TestRunner $t) use ($plan48, $jsonb48): void {
    $plan = $plan48([['option_id' => 101, 'option_name' => 'plugin_alpha_settings', 'autoload' => 'yes', 'option_value' => $jsonb48(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => 5]])]]);
    $t->same(true, $plan['covering_plan']['covering']);
};

$tests['jsonb table upsert covering current next48 selected plan preserves partial flag'] = static function (TestRunner $t) use ($plan48, $jsonb48): void {
    $plan = $plan48([['option_id' => 101, 'option_name' => 'plugin_alpha_settings', 'autoload' => 'yes', 'option_value' => $jsonb48(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => 5]])]]);
    $t->same(true, $plan['covering_plan']['partial']);
};

$tests['jsonb table upsert covering current next48 updates conflicting current row'] = static function (TestRunner $t) use ($plan48, $jsonb48): void {
    $plan = $plan48([['option_id' => 101, 'option_name' => 'plugin_alpha_settings', 'autoload' => 'yes', 'option_value' => $jsonb48(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => 5]])]]);
    $t->same([101], array_column($plan['upsert']['updated_rows'], 'option_id'));
};

$tests['jsonb table upsert covering current next48 returns covering generated channel'] = static function (TestRunner $t) use ($plan48, $jsonb48): void {
    $plan = $plan48([['option_id' => 101, 'option_name' => 'plugin_alpha_settings', 'autoload' => 'yes', 'option_value' => $jsonb48(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => 5]])]]);
    $t->same('stable', $plan['returning_covering_rows'][0]['plugin_channel']);
};

$tests['jsonb table upsert covering current next48 returns covering generated priority'] = static function (TestRunner $t) use ($plan48, $jsonb48): void {
    $plan = $plan48([['option_id' => 101, 'option_name' => 'plugin_alpha_settings', 'autoload' => 'yes', 'option_value' => $jsonb48(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => 5]])]]);
    $t->same(5, $plan['returning_covering_rows'][0]['plugin_priority']);
};

$tests['jsonb table upsert covering current next48 deletes current channel key'] = static function (TestRunner $t) use ($plan48, $jsonb48): void {
    $plan = $plan48([['option_id' => 101, 'option_name' => 'plugin_alpha_settings', 'autoload' => 'yes', 'option_value' => $jsonb48(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => 5]])]]);
    $t->same(true, jsonb_upsert48_entry($plan['index_changes']['delete_entries'], 101, 'idx_wp_options_jsonb_channel_covering', 'beta'));
};

$tests['jsonb table upsert covering current next48 inserts next channel key'] = static function (TestRunner $t) use ($plan48, $jsonb48): void {
    $plan = $plan48([['option_id' => 101, 'option_name' => 'plugin_alpha_settings', 'autoload' => 'yes', 'option_value' => $jsonb48(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => 5]])]]);
    $t->same(true, jsonb_upsert48_entry($plan['index_changes']['insert_entries'], 101, 'idx_wp_options_jsonb_channel_covering', 'stable'));
};

$tests['jsonb table upsert covering current next48 deletes current priority key'] = static function (TestRunner $t) use ($plan48, $jsonb48): void {
    $plan = $plan48([['option_id' => 101, 'option_name' => 'plugin_alpha_settings', 'autoload' => 'yes', 'option_value' => $jsonb48(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => 5]])]]);
    $t->same(true, jsonb_upsert48_entry($plan['index_changes']['delete_entries'], 101, 'idx_wp_options_jsonb_priority_covering', 3));
};

$tests['jsonb table upsert covering current next48 inserts next priority key'] = static function (TestRunner $t) use ($plan48, $jsonb48): void {
    $plan = $plan48([['option_id' => 101, 'option_name' => 'plugin_alpha_settings', 'autoload' => 'yes', 'option_value' => $jsonb48(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => 5]])]]);
    $t->same(true, jsonb_upsert48_entry($plan['index_changes']['insert_entries'], 101, 'idx_wp_options_jsonb_priority_covering', 5));
};

$channels48 = ['alpha', 'beta', 'canary', 'dev', 'edge', 'nightly', 'rc', 'stable', 'trunk', 'wp'];
foreach ($channels48 as $offset => $channel) {
    $tests["jsonb table upsert covering current next48 update channel {$channel}"] = static function (TestRunner $t) use ($plan48, $jsonb48, $channel, $offset): void {
        $plan = $plan48([['option_id' => 101, 'option_name' => 'plugin_alpha_settings', 'autoload' => 'yes', 'option_value' => $jsonb48(['plugin' => ['enabled' => true, 'channel' => $channel, 'priority' => 20 + $offset]])]]);
        $t->same($channel, $plan['returning_covering_rows'][0]['plugin_channel']);
    };
}

foreach ([0, 1, 2, 3, 5, 8, 13, 21, 34, 55, 89, 144] as $priority) {
    $tests["jsonb table upsert covering current next48 update priority {$priority}"] = static function (TestRunner $t) use ($plan48, $jsonb48, $priority): void {
        $plan = $plan48([['option_id' => 102, 'option_name' => 'plugin_beta_settings', 'autoload' => 'yes', 'option_value' => $jsonb48(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => $priority]])]]);
        $t->same($priority, $plan['returning_covering_rows'][0]['plugin_priority']);
    };
}

$tests['jsonb table upsert covering current next48 insert new row emits insert-new entries'] = static function (TestRunner $t) use ($plan48, $jsonb48): void {
    $plan = $plan48([['option_id' => 104, 'option_name' => 'plugin_delta_settings', 'autoload' => 'yes', 'option_value' => $jsonb48(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => 13]])]]);
    $t->same(true, jsonb_upsert48_entry($plan['insert_entries'], 104, 'idx_wp_options_jsonb_channel_covering', 'stable'));
};

$tests['jsonb table upsert covering current next48 insert new row returns covering projection'] = static function (TestRunner $t) use ($plan48, $jsonb48): void {
    $plan = $plan48([['option_id' => 104, 'option_name' => 'plugin_delta_settings', 'autoload' => 'yes', 'option_value' => $jsonb48(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => 13]])]]);
    $t->same('plugin_delta_settings', $plan['returning_covering_rows'][0]['option_name']);
};

$tests['jsonb table upsert covering current next48 insert autoload no skips partial insert entries'] = static function (TestRunner $t) use ($plan48, $jsonb48): void {
    $plan = $plan48([['option_id' => 105, 'option_name' => 'plugin_epsilon_settings', 'autoload' => 'no', 'option_value' => $jsonb48(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => 13]])]]);
    $t->same([], $plan['insert_entries']);
};

$tests['jsonb table upsert covering current next48 autoload activation inserts partial entries'] = static function (TestRunner $t) use ($plan48, $jsonb48): void {
    $plan = $plan48([['option_id' => 103, 'option_name' => 'plugin_gamma_settings', 'autoload' => 'yes', 'option_value' => $jsonb48(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => 11]])]]);
    $t->same(true, jsonb_upsert48_entry($plan['index_changes']['insert_entries'], 103, 'idx_wp_options_jsonb_channel_covering', 'stable'));
};

$tests['jsonb table upsert covering current next48 autoload activation has no partial delete'] = static function (TestRunner $t) use ($plan48, $jsonb48): void {
    $plan = $plan48([['option_id' => 103, 'option_name' => 'plugin_gamma_settings', 'autoload' => 'yes', 'option_value' => $jsonb48(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => 11]])]]);
    $t->same(false, jsonb_upsert48_entry($plan['index_changes']['delete_entries'], 103, 'idx_wp_options_jsonb_channel_covering', 'nightly'));
};

$tests['jsonb table upsert covering current next48 autoload deactivation deletes partial entries'] = static function (TestRunner $t) use ($plan48, $jsonb48): void {
    $plan = $plan48([['option_id' => 102, 'option_name' => 'plugin_beta_settings', 'autoload' => 'no', 'option_value' => $jsonb48(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => 8]])]]);
    $t->same(true, jsonb_upsert48_entry($plan['index_changes']['delete_entries'], 102, 'idx_wp_options_jsonb_channel_covering', 'stable'));
};

$tests['jsonb table upsert covering current next48 autoload deactivation has no next partial insert'] = static function (TestRunner $t) use ($plan48, $jsonb48): void {
    $plan = $plan48([['option_id' => 102, 'option_name' => 'plugin_beta_settings', 'autoload' => 'no', 'option_value' => $jsonb48(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => 8]])]]);
    $t->same(false, jsonb_upsert48_entry($plan['index_changes']['insert_entries'], 102, 'idx_wp_options_jsonb_channel_covering', 'stable'));
};

$tests['jsonb table upsert covering current next48 where false skips update'] = static function (TestRunner $t) use ($plan48, $jsonb48): void {
    $plan = $plan48([['option_id' => 101, 'option_name' => 'plugin_alpha_settings', 'autoload' => 'yes', 'option_value' => $jsonb48(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => 5]])]], ['option_id', 'plugin_channel'], static fn (): bool => false);
    $t->same([101], array_column($plan['upsert']['skipped_rows'], 'option_id'));
};

$tests['jsonb table upsert covering current next48 where false has no returning rows'] = static function (TestRunner $t) use ($plan48, $jsonb48): void {
    $plan = $plan48([['option_id' => 101, 'option_name' => 'plugin_alpha_settings', 'autoload' => 'yes', 'option_value' => $jsonb48(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => 5]])]], ['option_id', 'plugin_channel'], static fn (): bool => false);
    $t->same([], $plan['returning_covering_rows']);
};

$tests['jsonb table upsert covering current next48 where false has no index changes'] = static function (TestRunner $t) use ($plan48, $jsonb48): void {
    $plan = $plan48([['option_id' => 101, 'option_name' => 'plugin_alpha_settings', 'autoload' => 'yes', 'option_value' => $jsonb48(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => 5]])]], ['option_id', 'plugin_channel'], static fn (): bool => false);
    $t->same([], $plan['index_changes']['delete_entries']);
};

$tests['jsonb table upsert covering current next48 noncovering chosen plan skips covering row'] = static function (TestRunner $t) use ($indexes48, $generated48, $rows48, $assignments48, $jsonb48): void {
    $plan = SQLiteJsonbPatchGeneratedIndexPlan::planUpsertCoveringTable(
        $indexes48,
        $generated48,
        $rows48,
        [['option_id' => 101, 'option_name' => 'plugin_alpha_settings', 'autoload' => 'yes', 'option_value' => $jsonb48(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => 5]])]],
        ['option_name'],
        $assignments48,
        null,
        [['option_name'], ['option_id']],
        'option_id',
        ['operator' => 'AND', 'terms' => [
            ['operator' => '=', 'left' => ['generatedColumn' => 'plugin_enabled'], 'right' => 1],
            ['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => 'yes'],
        ]],
        [],
        ['option_id', 'option_name', 'plugin_enabled'],
    );
    $t->same('chosen index is not covering', $plan['skipped_covering_rows'][0]['reason']);
};

$tests['jsonb table upsert covering current next48 missing generated projection is rejected'] = static function (TestRunner $t) use ($indexes48, $generated48, $rows48, $assignments48, $jsonb48): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonbPatchGeneratedIndexPlan::planUpsertCoveringTable(
        $indexes48,
        $generated48,
        $rows48,
        [['option_id' => 101, 'option_name' => 'plugin_alpha_settings', 'autoload' => 'yes', 'option_value' => $jsonb48(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => 5]])]],
        ['option_name'],
        $assignments48,
        null,
        [['option_name'], ['option_id']],
        'option_id',
        [],
        [],
        ['option_id', 'missing_generated'],
    ));
};

$tests['jsonb table upsert covering current next48 malformed incoming json is rejected'] = static function (TestRunner $t) use ($plan48): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan48([['option_id' => 101, 'option_name' => 'plugin_alpha_settings', 'autoload' => 'yes', 'option_value' => '{"plugin":']]));
};

$tests['jsonb table upsert covering current next48 unique option id conflict is rejected'] = static function (TestRunner $t) use ($plan48, $jsonb48): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan48([['option_id' => 102, 'option_name' => 'plugin_new_settings', 'autoload' => 'yes', 'option_value' => $jsonb48(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => 5]])]]));
};

for ($i = 0; $i < 16; $i++) {
    $tests["jsonb table upsert covering current next48 inserted batch row {$i}"] = static function (TestRunner $t) use ($plan48, $jsonb48, $i): void {
        $rowid = 200 + $i;
        $plan = $plan48([['option_id' => $rowid, 'option_name' => "plugin_batch_{$i}_settings", 'autoload' => 'yes', 'option_value' => $jsonb48(['plugin' => ['enabled' => $i % 2 === 0, 'channel' => 'stable', 'priority' => $i]])]]);
        $t->same($rowid, $plan['returning_covering_rows'][0]['option_id']);
    };
}

/**
 * @param list<array<string,mixed>> $entries
 */
function jsonb_upsert48_entry(array $entries, int|string $rowid, string $index, mixed $value): bool
{
    foreach ($entries as $entry) {
        if (($entry['rowid'] ?? null) === $rowid && ($entry['index'] ?? null) === $index && ($entry['value'] ?? null) === $value) {
            return true;
        }
    }

    return false;
}

return $tests;
