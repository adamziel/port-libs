<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonbPatchGeneratedIndexPlan;

$jsonb52 = static fn (array $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

$generated52 = [
    "plugin_channel TEXT GENERATED ALWAYS AS (json_extract(jsonb_patch(option_value, '{\"plugin\":{\"source\":\"wp-import\",\"enabled\":false}}'), '$.plugin.channel')) STORED",
    "plugin_enabled INTEGER GENERATED ALWAYS AS (json_extract(jsonb_patch(option_value, '{\"plugin\":{\"source\":\"wp-import\",\"enabled\":false}}'), '$.plugin.enabled')) VIRTUAL",
    "plugin_priority INTEGER GENERATED ALWAYS AS (json_extract(jsonb_patch(option_value, '{\"plugin\":{\"source\":\"wp-import\"}}'), '$.plugin.priority')) VIRTUAL",
];

$indexes52 = [
    [
        'name' => 'idx_wp_options_delete_channel_covering',
        'rootPage' => 131,
        'estimatedRows' => 180,
        'coveringColumns' => ['option_id', 'option_name', 'autoload', 'plugin_channel', 'plugin_priority'],
        'sql' => "CREATE INDEX idx_wp_options_delete_channel_covering ON wp_options(plugin_channel, option_name) WHERE autoload='yes'",
    ],
    [
        'name' => 'idx_wp_options_delete_enabled_covering',
        'rootPage' => 132,
        'estimatedRows' => 90,
        'coveringColumns' => ['option_id', 'option_name', 'autoload', 'plugin_enabled'],
        'sql' => "CREATE INDEX idx_wp_options_delete_enabled_covering ON wp_options(plugin_enabled DESC, option_name) WHERE autoload='yes'",
    ],
    [
        'name' => 'idx_wp_options_delete_priority_plain',
        'rootPage' => 133,
        'estimatedRows' => 60,
        'coveringColumns' => ['option_id', 'plugin_priority'],
        'sql' => "CREATE INDEX idx_wp_options_delete_priority_plain ON wp_options(plugin_priority) WHERE autoload='yes'",
    ],
];

$rows52 = [
    ['option_id' => 201, 'option_name' => 'plugin_alpha_settings', 'autoload' => 'yes', 'option_value' => $jsonb52(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => 90], 'delete' => true])],
    ['option_id' => 202, 'option_name' => 'plugin_beta_settings', 'autoload' => 'yes', 'option_value' => $jsonb52(['plugin' => ['enabled' => false, 'channel' => 'beta', 'priority' => 30], 'delete' => false])],
    ['option_id' => 203, 'option_name' => 'plugin_gamma_settings', 'autoload' => 'no', 'option_value' => $jsonb52(['plugin' => ['channel' => 'nightly', 'priority' => 50], 'delete' => true])],
    ['option_id' => 204, 'option_name' => 'plugin_delta_settings', 'autoload' => 'yes', 'option_value' => $jsonb52(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => 10], 'delete' => true])],
    ['option_id' => 205, 'option_name' => 'plugin_epsilon_settings', 'autoload' => 'yes', 'option_value' => $jsonb52(['plugin' => ['enabled' => true, 'channel' => 'edge', 'priority' => 20], 'delete' => false])],
    ['option_id' => 206, 'option_name' => 'plugin_zeta_settings', 'autoload' => 'yes', 'option_value' => $jsonb52(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => 70], 'delete' => true])],
];

$column52 = static fn (string $name): array => ['column' => $name];
$generatedColumn52 = static fn (string $name): array => ['generatedColumn' => $name];
$point52 = static fn (array $left, mixed $right): array => ['operator' => '=', 'left' => $left, 'right' => $right];
$and52 = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$deleteWhere52 = static function (array $row): bool {
    $decoded = SQLiteJsonB::decode($row['option_value']->bytes);

    return ($row['autoload'] ?? null) === 'yes'
        && ($decoded['delete'] ?? false) === true
        && (($decoded['plugin']['channel'] ?? null) === 'stable');
};

$plan52 = static fn (array $needed = ['option_id', 'option_name', 'autoload', 'plugin_channel', 'plugin_priority'], ?array $lookup = null): array => SQLiteJsonbPatchGeneratedIndexPlan::planDeleteCurrentCoveringTable(
    $indexes52,
    $generated52,
    $rows52,
    $deleteWhere52,
    'option_id',
    $lookup ?? $and52($point52($generatedColumn52('plugin_channel'), 'stable'), $point52($column52('autoload'), 'yes')),
    [['column' => 'plugin_channel'], ['column' => 'option_name']],
    $needed,
);

$tests = [
    'jsonb generated covering delete current next52 chooses channel covering index' => static function (TestRunner $t) use ($plan52): void {
        $t->same('idx_wp_options_delete_channel_covering', $plan52()['covering_plan']['name']);
    },
    'jsonb generated covering delete current next52 selected plan is covering' => static function (TestRunner $t) use ($plan52): void {
        $t->same(true, $plan52()['covering_plan']['covering']);
    },
    'jsonb generated covering delete current next52 selected plan satisfies order' => static function (TestRunner $t) use ($plan52): void {
        $t->same(true, $plan52()['covering_plan']['orderBySatisfied']);
    },
    'jsonb generated covering delete current next52 selected plan preserves partial flag' => static function (TestRunner $t) use ($plan52): void {
        $t->same(true, $plan52()['covering_plan']['partial']);
    },
    'jsonb generated covering delete current next52 selected plan has root page' => static function (TestRunner $t) use ($plan52): void {
        $t->same(131, $plan52()['covering_plan']['rootPage']);
    },
    'jsonb generated covering delete current next52 selected plan estimates point rows' => static function (TestRunner $t) use ($plan52): void {
        $t->same(9, $plan52()['covering_plan']['estimatedRows']);
    },
    'jsonb generated covering delete current next52 deletes current stable yes rows only' => static function (TestRunner $t) use ($plan52): void {
        $t->same([201, 204, 206], array_column($plan52()['deleted_rows'], 'option_id'));
    },
    'jsonb generated covering delete current next52 leaves nonmatching rows' => static function (TestRunner $t) use ($plan52): void {
        $t->same([202, 203, 205], array_column($plan52()['remaining_rows'], 'option_id'));
    },
    'jsonb generated covering delete current next52 reports changes count' => static function (TestRunner $t) use ($plan52): void {
        $t->same(3, $plan52()['changes']);
    },
    'jsonb generated covering delete current next52 returns covering rows' => static function (TestRunner $t) use ($plan52): void {
        $t->same(['plugin_alpha_settings', 'plugin_delta_settings', 'plugin_zeta_settings'], array_column($plan52()['returning_covering_rows'], 'option_name'));
    },
    'jsonb generated covering delete current next52 returns generated channel' => static function (TestRunner $t) use ($plan52): void {
        $t->same(['stable', 'stable', 'stable'], array_column($plan52()['returning_covering_rows'], 'plugin_channel'));
    },
    'jsonb generated covering delete current next52 returns generated priority' => static function (TestRunner $t) use ($plan52): void {
        $t->same([90, 10, 70], array_column($plan52()['returning_covering_rows'], 'plugin_priority'));
    },
    'jsonb generated covering delete current next52 returns autoload from covering payload' => static function (TestRunner $t) use ($plan52): void {
        $t->same(['yes', 'yes', 'yes'], array_column($plan52()['returning_covering_rows'], 'autoload'));
    },
    'jsonb generated covering delete current next52 delete entry count' => static function (TestRunner $t) use ($plan52): void {
        $t->same(9, count($plan52()['delete_entries']));
    },
    'jsonb generated covering delete current next52 delete channel entries' => static function (TestRunner $t) use ($plan52): void {
        $entries = array_values(array_filter($plan52()['delete_entries'], static fn (array $entry): bool => $entry['index'] === 'idx_wp_options_delete_channel_covering'));
        $t->same([201, 204, 206], array_column($entries, 'rowid'));
        $t->same(['stable', 'stable', 'stable'], array_column($entries, 'value'));
    },
    'jsonb generated covering delete current next52 delete enabled entries' => static function (TestRunner $t) use ($plan52): void {
        $entries = array_values(array_filter($plan52()['delete_entries'], static fn (array $entry): bool => $entry['index'] === 'idx_wp_options_delete_enabled_covering'));
        $t->same([0, 0, 0], array_column($entries, 'value'));
    },
    'jsonb generated covering delete current next52 delete priority entries' => static function (TestRunner $t) use ($plan52): void {
        $entries = array_values(array_filter($plan52()['delete_entries'], static fn (array $entry): bool => $entry['index'] === 'idx_wp_options_delete_priority_plain'));
        $t->same([90, 10, 70], array_column($entries, 'value'));
    },
    'jsonb generated covering delete current next52 autoload no row has no partial delete' => static function (TestRunner $t) use ($plan52): void {
        $t->same(false, jsonb_delete52_entry($plan52()['delete_entries'], 203, 'idx_wp_options_delete_channel_covering', 'nightly'));
    },
    'jsonb generated covering delete current next52 beta row is preserved' => static function (TestRunner $t) use ($plan52): void {
        $t->same(true, in_array(202, array_column($plan52()['remaining_rows'], 'option_id'), true));
    },
    'jsonb generated covering delete current next52 edge row is preserved' => static function (TestRunner $t) use ($plan52): void {
        $t->same(true, in_array(205, array_column($plan52()['remaining_rows'], 'option_id'), true));
    },
    'jsonb generated covering delete current next52 unchanged entries include preserved active rows' => static function (TestRunner $t) use ($plan52): void {
        $active = array_values(array_filter($plan52()['unchanged_entries'], static fn (array $entry): bool => $entry['active'] === true));
        $t->same([202, 202, 202, 205, 205, 205], array_column($active, 'rowid'));
    },
    'jsonb generated covering delete current next52 unchanged entries include inactive autoload no row' => static function (TestRunner $t) use ($plan52): void {
        $inactive = array_values(array_filter($plan52()['unchanged_entries'], static fn (array $entry): bool => $entry['rowid'] === 203));
        $t->same([false, false, false], array_column($inactive, 'active'));
    },
    'jsonb generated covering delete current next52 noncovering chosen plan skips returning rows' => static function (TestRunner $t) use ($indexes52, $generated52, $rows52, $deleteWhere52, $column52, $generatedColumn52, $point52, $and52): void {
        $plan = SQLiteJsonbPatchGeneratedIndexPlan::planDeleteCurrentCoveringTable(
            $indexes52,
            $generated52,
            $rows52,
            $deleteWhere52,
            'option_id',
            $and52($point52($generatedColumn52('plugin_priority'), 90), $point52($column52('autoload'), 'yes')),
            [],
            ['option_id', 'option_name', 'plugin_priority'],
        );
        $t->same('idx_wp_options_delete_priority_plain', $plan['covering_plan']['name']);
        $t->same(['chosen index is not covering', 'chosen index is not covering', 'chosen index is not covering'], array_column($plan['skipped_covering_rows'], 'reason'));
        $t->same([], $plan['returning_covering_rows']);
    },
    'jsonb generated covering delete current next52 missing generated projection is rejected' => static function (TestRunner $t) use ($plan52): void {
        $plan = $plan52(['option_id', 'missing_generated']);
        $t->same([], $plan['returning_covering_rows']);
        $t->same(['chosen index is not covering', 'chosen index is not covering', 'chosen index is not covering'], array_column($plan['skipped_covering_rows'], 'reason'));
    },
    'jsonb generated covering delete current next52 missing rowid is rejected' => static function (TestRunner $t) use ($indexes52, $generated52, $deleteWhere52): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonbPatchGeneratedIndexPlan::planDeleteCurrentCoveringTable($indexes52, $generated52, [['option_name' => 'plugin_missing']], $deleteWhere52, 'option_id'));
    },
    'jsonb generated covering delete current next52 malformed rowid is rejected' => static function (TestRunner $t) use ($indexes52, $generated52, $deleteWhere52, $jsonb52): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonbPatchGeneratedIndexPlan::planDeleteCurrentCoveringTable($indexes52, $generated52, [['option_id' => [], 'option_value' => $jsonb52([])]], $deleteWhere52, 'option_id'));
    },
];

foreach ([
    [201, 'idx_wp_options_delete_channel_covering', 'stable'],
    [204, 'idx_wp_options_delete_channel_covering', 'stable'],
    [206, 'idx_wp_options_delete_channel_covering', 'stable'],
    [201, 'idx_wp_options_delete_enabled_covering', 0],
    [204, 'idx_wp_options_delete_enabled_covering', 0],
    [206, 'idx_wp_options_delete_enabled_covering', 0],
    [201, 'idx_wp_options_delete_priority_plain', 90],
    [204, 'idx_wp_options_delete_priority_plain', 10],
    [206, 'idx_wp_options_delete_priority_plain', 70],
] as [$rowid, $index, $value]) {
    $tests["jsonb generated covering delete current next52 delete entry {$rowid} {$index}"] = static function (TestRunner $t) use ($plan52, $rowid, $index, $value): void {
        $t->same(true, jsonb_delete52_entry($plan52()['delete_entries'], $rowid, $index, $value));
    };
}

foreach ([
    ['option_id', [201, 204, 206]],
    ['option_name', ['plugin_alpha_settings', 'plugin_delta_settings', 'plugin_zeta_settings']],
    ['autoload', ['yes', 'yes', 'yes']],
    ['plugin_channel', ['stable', 'stable', 'stable']],
    ['plugin_priority', [90, 10, 70]],
] as [$column, $expected]) {
    $tests["jsonb generated covering delete current next52 covering projection {$column}"] = static function (TestRunner $t) use ($plan52, $column, $expected): void {
        $t->same($expected, array_column($plan52()['returning_covering_rows'], $column));
    };
}

foreach ([
    'plugin_alpha_settings' => 201,
    'plugin_delta_settings' => 204,
    'plugin_zeta_settings' => 206,
] as $name => $rowid) {
    $tests["jsonb generated covering delete current next52 deleted row {$name}"] = static function (TestRunner $t) use ($plan52, $name, $rowid): void {
        $deleted = array_column($plan52()['deleted_rows'], 'option_id', 'option_name');
        $t->same($rowid, $deleted[$name]);
    };
}

foreach ([
    'plugin_beta_settings' => 202,
    'plugin_gamma_settings' => 203,
    'plugin_epsilon_settings' => 205,
] as $name => $rowid) {
    $tests["jsonb generated covering delete current next52 remaining row {$name}"] = static function (TestRunner $t) use ($plan52, $name, $rowid): void {
        $remaining = array_column($plan52()['remaining_rows'], 'option_id', 'option_name');
        $t->same($rowid, $remaining[$name]);
    };
}

foreach ([
    ['plugin_channel', 'stable', 'idx_wp_options_delete_channel_covering'],
    ['plugin_enabled', 0, 'idx_wp_options_delete_enabled_covering'],
] as [$generatedColumn, $value, $expectedIndex]) {
    $tests["jsonb generated covering delete current next52 generated lookup {$generatedColumn}"] = static function (TestRunner $t) use ($indexes52, $generated52, $rows52, $deleteWhere52, $point52, $generatedColumn52, $column52, $and52, $generatedColumn, $value, $expectedIndex): void {
        $plan = SQLiteJsonbPatchGeneratedIndexPlan::planDeleteCurrentCoveringTable(
            $indexes52,
            $generated52,
            $rows52,
            $deleteWhere52,
            'option_id',
            $and52($point52($generatedColumn52($generatedColumn), $value), $point52($column52('autoload'), 'yes')),
            [],
            ['option_id', 'option_name'],
        );
        $t->same($expectedIndex, $plan['covering_plan']['name']);
    };
}

foreach ([
    'channel' => 'plugin_channel',
    'enabled' => 'plugin_enabled',
    'priority' => 'plugin_priority',
] as $label => $column) {
    $tests["jsonb generated covering delete current next52 delete entries expose {$label} path"] = static function (TestRunner $t) use ($plan52, $column): void {
        $entry = array_values(array_filter($plan52()['delete_entries'], static fn (array $candidate): bool => $candidate['generatedColumn'] === $column))[0];
        $t->same(true, str_starts_with($entry['path'], '$.plugin.'));
    };
}

foreach ([0, 1, 2, 3, 4, 5] as $offset) {
    $tests["jsonb generated covering delete current next52 stable delete predicate batch {$offset}"] = static function (TestRunner $t) use ($indexes52, $generated52, $jsonb52, $offset): void {
        $rowid = 300 + $offset;
        $rows = [[
            'option_id' => $rowid,
            'option_name' => "plugin_batch_{$offset}",
            'autoload' => 'yes',
            'option_value' => $jsonb52(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => $offset], 'delete' => true]),
        ]];
        $plan = SQLiteJsonbPatchGeneratedIndexPlan::planDeleteCurrentCoveringTable(
            $indexes52,
            $generated52,
            $rows,
            static fn (): bool => true,
            'option_id',
            [],
            [],
            ['option_id', 'plugin_channel', 'plugin_priority'],
        );
        $t->same([$rowid], array_column($plan['returning_covering_rows'], 'option_id'));
        $t->same($offset, $plan['returning_covering_rows'][0]['plugin_priority']);
    };
}

/**
 * @param list<array<string,mixed>> $entries
 */
function jsonb_delete52_entry(array $entries, int|string $rowid, string $index, mixed $value): bool
{
    foreach ($entries as $entry) {
        if (($entry['rowid'] ?? null) === $rowid && ($entry['index'] ?? null) === $index && ($entry['value'] ?? null) === $value) {
            return true;
        }
    }

    return false;
}

return $tests;
