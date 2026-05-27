<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonbPatchGeneratedIndexPlan;

$tests = [];

$generatedColumns = [
    "plugin_enabled INTEGER GENERATED ALWAYS AS (json_extract(jsonb_patch(option_value, '{plugin:{source:\"wp-import\"}}'), '$.plugin.enabled')) VIRTUAL",
    "plugin_channel TEXT GENERATED ALWAYS AS (json_extract(jsonb_patch(option_value, '{plugin:{source:\"wp-import\"}}'), '$.plugin.channel')) STORED",
    "plugin_priority INTEGER GENERATED ALWAYS AS (json_extract(jsonb_patch(option_value, '{plugin:{source:\"wp-import\"}}'), '$.plugin.priority')) VIRTUAL",
];

$indexes = [
    [
        'name' => 'idx_generated_enabled_autoload',
        'rootPage' => 71,
        'sql' => "CREATE INDEX idx_generated_enabled_autoload ON wp_options(plugin_enabled) WHERE autoload='yes'",
    ],
    [
        'name' => 'idx_generated_channel',
        'rootPage' => 72,
        'sql' => 'CREATE INDEX idx_generated_channel ON wp_options(plugin_channel DESC)',
    ],
    [
        'name' => 'idx_generated_priority_expr',
        'rootPage' => 73,
        'sql' => "CREATE INDEX idx_generated_priority_expr ON wp_options(json_extract(jsonb_patch(option_value, '{plugin:{source:\"wp-import\"}}'), '$.plugin.priority')) WHERE autoload='no'",
    ],
];

$jsonb = static fn (array $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

$baseRows = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_alpha_settings',
        'autoload' => 'yes',
        'option_value' => $jsonb(['plugin' => ['enabled' => false, 'channel' => 'beta', 'priority' => 3]]),
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_beta_settings',
        'autoload' => 'no',
        'option_value' => $jsonb(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => 9]]),
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_gamma_settings',
        'autoload' => 'yes',
        'option_value' => $jsonb(['plugin' => ['enabled' => true, 'channel' => 'nightly', 'priority' => 5]]),
    ],
];

$singleRowCases = [
    'enabled flip on autoload yes deletes false and inserts true' => [
        1,
        ['option_value' => $jsonb(['plugin' => ['enabled' => true, 'channel' => 'beta', 'priority' => 3]])],
        ['idx_generated_enabled_autoload', 0],
        ['idx_generated_enabled_autoload', 1],
    ],
    'channel update emits channel delete and insert on generated stored column' => [
        1,
        ['option_value' => $jsonb(['plugin' => ['enabled' => false, 'channel' => 'stable', 'priority' => 3]])],
        ['idx_generated_channel', 'beta'],
        ['idx_generated_channel', 'stable'],
    ],
    'priority update emits expression-index current and next keys' => [
        2,
        ['option_value' => $jsonb(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => 12]])],
        ['idx_generated_priority_expr', 9],
        ['idx_generated_priority_expr', 12],
    ],
    'partial index activation inserts generated enabled key' => [
        2,
        ['autoload' => 'yes'],
        ['idx_generated_priority_expr', 9],
        ['idx_generated_enabled_autoload', 1],
    ],
    'partial index deactivation deletes generated enabled key' => [
        3,
        ['autoload' => 'no'],
        ['idx_generated_enabled_autoload', 1],
        ['idx_generated_priority_expr', 5],
    ],
    'json text update is accepted for next generated value' => [
        1,
        ['option_value' => '{"plugin":{"enabled":true,"channel":"rc","priority":4}}'],
        ['idx_generated_channel', 'beta'],
        ['idx_generated_channel', 'rc'],
    ],
    'json5 text update is accepted for next generated value' => [
        1,
        ['option_value' => "{plugin:{enabled:true,channel:'edge',priority:4}}"],
        ['idx_generated_channel', 'beta'],
        ['idx_generated_channel', 'edge'],
    ],
    'sql null source update inserts null generated channel key' => [
        1,
        ['option_value' => null],
        ['idx_generated_channel', 'beta'],
        ['idx_generated_channel', null],
    ],
    'missing generated path after update inserts null priority key' => [
        2,
        ['option_value' => $jsonb(['plugin' => ['enabled' => true, 'channel' => 'stable']])],
        ['idx_generated_priority_expr', 9],
        ['idx_generated_priority_expr', null],
    ],
    'autoload no row keeps enabled partial inactive while channel changes' => [
        2,
        ['option_value' => $jsonb(['plugin' => ['enabled' => false, 'channel' => 'rc', 'priority' => 9]])],
        ['idx_generated_channel', 'stable'],
        ['idx_generated_channel', 'rc'],
    ],
];

foreach ($singleRowCases as $name => [$rowid, $assignment, $expectedDelete, $expectedInsert]) {
    $tests['jsonb generated update index current next37 ' . $name] = static function (TestRunner $t) use ($indexes, $generatedColumns, $baseRows, $rowid, $assignment, $expectedDelete, $expectedInsert): void {
        $plan = SQLiteJsonbPatchGeneratedIndexPlan::planUpdateIndexEntries(
            $indexes,
            $generatedColumns,
            $baseRows,
            [$rowid => $assignment],
            'option_id',
        );

        if ($expectedDelete === null) {
            $t->same(false, self_entry_exists($plan['delete_entries'], $rowid, null, null));
        } else {
            $t->same(true, self_entry_exists($plan['delete_entries'], $rowid, $expectedDelete[0], $expectedDelete[1]));
        }
        if ($expectedInsert === null) {
            $t->same(false, self_entry_exists($plan['insert_entries'], $rowid, null, null));
        } else {
            $t->same(true, self_entry_exists($plan['insert_entries'], $rowid, $expectedInsert[0], $expectedInsert[1]));
        }
        $t->same($rowid, $plan['updated_rows'][0]['rowid']);
    };
}

for ($priority = 10; $priority < 35; $priority++) {
    $tests["jsonb generated update index current next37 priority expression update {$priority}"] = static function (TestRunner $t) use ($indexes, $generatedColumns, $baseRows, $jsonb, $priority): void {
        $plan = SQLiteJsonbPatchGeneratedIndexPlan::planUpdateIndexEntries(
            $indexes,
            $generatedColumns,
            $baseRows,
            [2 => ['option_value' => $jsonb(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => $priority]])]],
            'option_id',
        );

        $t->same(true, self_entry_exists($plan['delete_entries'], 2, 'idx_generated_priority_expr', 9));
        $t->same(true, self_entry_exists($plan['insert_entries'], 2, 'idx_generated_priority_expr', $priority));
        $t->same(2, $plan['updated_rows'][0]['rowid']);
    };
}

foreach (['alpha', 'canary', 'dev', 'edge', 'rc', 'stable', 'trunk', 'wp', 'zeta'] as $channel) {
    $tests["jsonb generated update index current next37 channel update {$channel}"] = static function (TestRunner $t) use ($indexes, $generatedColumns, $baseRows, $jsonb, $channel): void {
        $plan = SQLiteJsonbPatchGeneratedIndexPlan::planUpdateIndexEntries(
            $indexes,
            $generatedColumns,
            $baseRows,
            [1 => ['option_value' => $jsonb(['plugin' => ['enabled' => false, 'channel' => $channel, 'priority' => 3]])]],
            'option_id',
        );

        $t->same(true, self_entry_exists($plan['delete_entries'], 1, 'idx_generated_channel', 'beta'));
        $t->same(true, self_entry_exists($plan['insert_entries'], 1, 'idx_generated_channel', $channel));
    };
}

$tests['jsonb generated update index current next37 multi row update preserves current and next rowids'] = static function (TestRunner $t) use ($indexes, $generatedColumns, $baseRows, $jsonb): void {
    $plan = SQLiteJsonbPatchGeneratedIndexPlan::planUpdateIndexEntries(
        $indexes,
        $generatedColumns,
        $baseRows,
        [
            1 => ['option_value' => $jsonb(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => 4]])],
            2 => ['autoload' => 'yes'],
            3 => ['autoload' => 'no'],
        ],
        'option_id',
    );

    $t->same([1, 2, 3], array_column($plan['updated_rows'], 'rowid'));
    $t->same(true, self_entry_exists($plan['delete_entries'], 1, 'idx_generated_enabled_autoload', 0));
    $t->same(true, self_entry_exists($plan['insert_entries'], 1, 'idx_generated_enabled_autoload', 1));
    $t->same(true, self_entry_exists($plan['insert_entries'], 2, 'idx_generated_enabled_autoload', 1));
    $t->same(true, self_entry_exists($plan['delete_entries'], 3, 'idx_generated_enabled_autoload', 1));
};

$tests['jsonb generated update index current next37 unchanged update keeps entries out of delete insert queues'] = static function (TestRunner $t) use ($indexes, $generatedColumns, $baseRows): void {
    $plan = SQLiteJsonbPatchGeneratedIndexPlan::planUpdateIndexEntries(
        $indexes,
        $generatedColumns,
        $baseRows,
        [1 => ['option_name' => 'plugin_alpha_settings_copy']],
        'option_id',
    );

    $t->same([], $plan['delete_entries']);
    $t->same([], $plan['insert_entries']);
    $t->same(true, count($plan['unchanged_entries']) > 0);
};

$tests['jsonb generated update index current next37 skipped rows report no update assignment'] = static function (TestRunner $t) use ($indexes, $generatedColumns, $baseRows): void {
    $plan = SQLiteJsonbPatchGeneratedIndexPlan::planUpdateIndexEntries($indexes, $generatedColumns, $baseRows, [2 => ['autoload' => 'yes']], 'option_id');

    $t->same([1, 3], array_column($plan['skipped_rows'], 'rowid'));
    $t->same('no update assignment', $plan['skipped_rows'][0]['reason']);
};

$tests['jsonb generated update index current next37 malformed next json is rejected before index insert'] = static function (TestRunner $t) use ($indexes, $generatedColumns, $baseRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonbPatchGeneratedIndexPlan::planUpdateIndexEntries(
        $indexes,
        $generatedColumns,
        $baseRows,
        [1 => ['option_value' => '{"plugin":']],
        'option_id',
    ));
};

$tests['jsonb generated update index current next37 missing rowid is rejected'] = static function (TestRunner $t) use ($indexes, $generatedColumns): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonbPatchGeneratedIndexPlan::planUpdateIndexEntries(
        $indexes,
        $generatedColumns,
        [['option_value' => '{}']],
        [1 => ['option_value' => '{}']],
        'option_id',
    ));
};

$tests['jsonb generated update index current next37 unsupported source value is rejected'] = static function (TestRunner $t) use ($indexes, $generatedColumns, $baseRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonbPatchGeneratedIndexPlan::planUpdateIndexEntries(
        $indexes,
        $generatedColumns,
        $baseRows,
        [1 => ['option_value' => ['not-json']]],
        'option_id',
    ));
};

$tests['jsonb generated update index current next37 duplicate non json indexes are ignored'] = static function (TestRunner $t) use ($generatedColumns, $baseRows, $jsonb): void {
    $plan = SQLiteJsonbPatchGeneratedIndexPlan::planUpdateIndexEntries(
        [
            ['name' => 'idx_plain_name', 'sql' => 'CREATE INDEX idx_plain_name ON wp_options(option_name)'],
            ['name' => 'idx_generated_channel', 'sql' => 'CREATE INDEX idx_generated_channel ON wp_options(plugin_channel)'],
        ],
        $generatedColumns,
        $baseRows,
        [1 => ['option_value' => $jsonb(['plugin' => ['enabled' => false, 'channel' => 'stable', 'priority' => 3]])]],
        'option_id',
    );

    $t->same(['idx_generated_channel'], array_values(array_unique(array_column($plan['delete_entries'], 'index'))));
    $t->same(['idx_generated_channel'], array_values(array_unique(array_column($plan['insert_entries'], 'index'))));
};

/**
 * @param list<array<string,mixed>> $entries
 */
function self_entry_exists(array $entries, int|string $rowid, ?string $index, mixed $value): bool
{
    foreach ($entries as $entry) {
        if (($entry['rowid'] ?? null) !== $rowid) {
            continue;
        }
        if ($index !== null && ($entry['index'] ?? null) !== $index) {
            continue;
        }
        if ($index === null || ($entry['value'] ?? null) === $value) {
            return true;
        }
    }

    return false;
}

return $tests;
