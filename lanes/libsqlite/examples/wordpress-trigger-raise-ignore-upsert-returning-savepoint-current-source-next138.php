<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerUpsertSavepointReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerUpsertSavepointReturningCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'revision' => 1, 'source' => 'seed'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'revision' => 1, 'source' => 'seed'],
];
$incoming = [
    ['option_id' => 3, 'option_name' => 'siteurl', 'option_value' => 'https://blocked.test', 'autoload' => 'yes', 'revision' => 2, 'source' => 'import'],
    ['option_id' => 4, 'option_name' => '_transient_import_lock', 'option_value' => 'lock', 'autoload' => 'no', 'revision' => 1, 'source' => 'import'],
    ['option_id' => 5, 'option_name' => 'blogname', 'option_value' => 'Imported Blog', 'autoload' => 'yes', 'revision' => 1, 'source' => 'import'],
];

$plan = SQLiteTriggerUpsertSavepointReturningCurrentSourceNextPlan::executeWithinSavepoint(
    'wp_import_ignore',
    $rows,
    $incoming,
    ['option_name'],
    [
        'option_id' => static fn (array $old, array $incoming): int => $old['option_id'],
        'option_value' => static fn (array $old, array $incoming): string => $incoming['option_value'],
        'autoload' => static fn (array $old, array $incoming): string => $incoming['autoload'],
        'revision' => static fn (array $old, array $incoming): int => (int) $incoming['revision'],
        'source' => static fn (array $old, array $incoming): string => $incoming['source'],
    ],
    [
        [
            'name' => 'wp_options_bu_ignore_siteurl_import',
            'timing' => 'before',
            'event' => 'update',
            'when' => ['new.option_name', '=', 'siteurl'],
            'raise' => 'ignore',
            'reason' => 'siteurl is protected during import',
            'values' => ['name' => 'new.option_name', 'incoming' => 'new.option_value'],
        ],
        [
            'name' => 'wp_options_bi_ignore_transient_lock',
            'timing' => 'before',
            'event' => 'insert',
            'when' => ['new.option_name', '=', '_transient_import_lock'],
            'raise' => 'ignore',
            'reason' => 'transient import lock is statement-local',
            'values' => ['name' => 'new.option_name'],
        ],
    ],
    ['option_id', 'option_name', 'option_value', 'source'],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['rolled_back_to_savepoint'] === false);
    assert(array_column($plan['ignored_rows'], 'action') === ['update', 'insert']);
    assert(array_column($plan['current_returning'], 'option_name') === ['blogname']);
    assert(array_column($plan['after_savepoint'], 'option_value', 'option_name')['siteurl'] === 'https://old.test');
    assert(!in_array('_transient_import_lock', array_column($plan['after_savepoint'], 'option_name'), true));
}

echo json_encode([
    'status' => 'wordpress-trigger-raise-ignore-upsert-returning-savepoint-current-source-next138',
    'ignored' => array_column($plan['ignored_rows'], 'reason'),
    'returning' => $plan['current_returning'],
    'after' => $plan['after_savepoint'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
