<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteJson5Parser.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteJsonCanonical.php';
require_once __DIR__ . '/../src/SQLiteJsonSubtypeValue.php';
require_once __DIR__ . '/../src/SQLiteJsonInspection.php';
require_once __DIR__ . '/../src/SQLiteJsonPatch.php';
require_once __DIR__ . '/../src/SQLiteJsonPath.php';
require_once __DIR__ . '/../src/SQLiteJsonExtract.php';
require_once __DIR__ . '/../src/SQLiteCreateIndex.php';
require_once __DIR__ . '/../src/SQLiteIndexPredicate.php';
require_once __DIR__ . '/../src/SQLiteJsonbPatchGeneratedIndexPlan.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonbPatchGeneratedIndexPlan;

$jsonb = static fn (array $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

$generated = [
    "plugin_channel TEXT GENERATED ALWAYS AS (json_extract(jsonb_patch(option_value, '{\"plugin\":{\"source\":\"wp-import\",\"enabled\":false}}'), '$.plugin.channel')) STORED",
    "plugin_priority INTEGER GENERATED ALWAYS AS (json_extract(jsonb_patch(option_value, '{\"plugin\":{\"source\":\"wp-import\"}}'), '$.plugin.priority')) VIRTUAL",
];

$indexes = [
    [
        'name' => 'idx_wp_options_delete_channel_covering',
        'rootPage' => 131,
        'estimatedRows' => 180,
        'coveringColumns' => ['option_id', 'option_name', 'autoload', 'plugin_channel', 'plugin_priority'],
        'sql' => "CREATE INDEX idx_wp_options_delete_channel_covering ON wp_options(plugin_channel, option_name) WHERE autoload='yes'",
    ],
    [
        'name' => 'idx_wp_options_delete_priority_plain',
        'rootPage' => 133,
        'estimatedRows' => 60,
        'coveringColumns' => ['option_id', 'plugin_priority'],
        'sql' => "CREATE INDEX idx_wp_options_delete_priority_plain ON wp_options(plugin_priority) WHERE autoload='yes'",
    ],
];

$rows = [
    ['option_id' => 201, 'option_name' => 'plugin_alpha_settings', 'autoload' => 'yes', 'option_value' => $jsonb(['plugin' => ['channel' => 'stable', 'priority' => 90], 'delete' => true])],
    ['option_id' => 202, 'option_name' => 'plugin_beta_settings', 'autoload' => 'yes', 'option_value' => $jsonb(['plugin' => ['channel' => 'beta', 'priority' => 30], 'delete' => false])],
    ['option_id' => 204, 'option_name' => 'plugin_delta_settings', 'autoload' => 'yes', 'option_value' => $jsonb(['plugin' => ['channel' => 'stable', 'priority' => 10], 'delete' => true])],
];

$plan = SQLiteJsonbPatchGeneratedIndexPlan::planDeleteCurrentCoveringTable(
    $indexes,
    $generated,
    $rows,
    static function (array $row): bool {
        $decoded = SQLiteJsonB::decode($row['option_value']->bytes);

        return ($row['autoload'] ?? null) === 'yes'
            && ($decoded['delete'] ?? false) === true
            && (($decoded['plugin']['channel'] ?? null) === 'stable');
    },
    'option_id',
    [
        'operator' => 'AND',
        'terms' => [
            ['operator' => '=', 'left' => ['generatedColumn' => 'plugin_channel'], 'right' => 'stable'],
            ['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => 'yes'],
        ],
    ],
    [['column' => 'plugin_channel'], ['column' => 'option_name']],
    ['option_id', 'option_name', 'autoload', 'plugin_channel', 'plugin_priority'],
);

$summary = [
    'scenario' => 'application-jsonb-generated-covering-delete-current-next52',
    'applicationUse' => 'Delete copied wp_options JSONB plugin rows through a generated-column covering index while returning generated JSONB values and emitting current index-entry deletes without requiring ext/sqlite.',
    'coveringIndex' => $plan['covering_plan']['name'] ?? null,
    'deletedIds' => array_column($plan['deleted_rows'], 'option_id'),
    'remainingIds' => array_column($plan['remaining_rows'], 'option_id'),
    'returningRows' => $plan['returning_covering_rows'],
    'deleteEntryCount' => count($plan['delete_entries']),
];

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if ($summary['coveringIndex'] !== 'idx_wp_options_delete_channel_covering' || $summary['deletedIds'] !== [201, 204] || $summary['deleteEntryCount'] !== 4) {
        fwrite(STDERR, "application-jsonb-generated-covering-delete-current-next52 self-test failed\n");
        exit(1);
    }

    echo "application-jsonb-generated-covering-delete-current-next52 self-test passed\n";
}

return $summary;
