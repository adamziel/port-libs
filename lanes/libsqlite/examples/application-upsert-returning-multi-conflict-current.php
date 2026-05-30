<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteUpsertDoUpdateWherePlan.php';

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$existing = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'slot' => 'primary', 'option_value' => 'https://old.test', 'revision' => 1],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'no', 'slot' => 'secondary', 'option_value' => 'https://home.test', 'revision' => 3],
    ['option_id' => 3, 'option_name' => 'theme_mods', 'autoload' => null, 'slot' => 'json', 'option_value' => '{}', 'revision' => 2],
];

$nameAssignments = [
    'option_id' => static fn (array $current, array $excluded): mixed => $excluded['option_id'],
    'autoload' => static fn (array $current, array $excluded): mixed => $excluded['autoload'],
    'slot' => static fn (array $current, array $excluded): mixed => $excluded['slot'],
    'option_value' => static fn (array $current, array $excluded): mixed => $excluded['option_value'],
    'revision' => static fn (array $current, array $excluded): int => (int) $current['revision'] + (int) $excluded['revision'],
];

$autoloadAssignments = [
    'option_name' => static fn (array $current, array $excluded): mixed => $excluded['option_name'],
    'slot' => static fn (array $current, array $excluded): mixed => $excluded['slot'],
    'option_value' => static fn (array $current, array $excluded): string => 'autoload-conflict:' . $current['option_name'],
    'revision' => static fn (array $current, array $excluded): int => (int) $current['revision'] + 10 + (int) $excluded['revision'],
];

$slotAssignments = [
    'option_name' => static fn (array $current, array $excluded): mixed => $excluded['option_name'],
    'autoload' => static fn (array $current, array $excluded): mixed => $excluded['autoload'],
    'option_value' => static fn (array $current, array $excluded): string => 'slot-conflict:' . $current['option_name'],
    'revision' => static fn (array $current, array $excluded): int => (int) $current['revision'] + 20 + (int) $excluded['revision'],
];

$plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
    $existing,
    [
        ['option_id' => 10, 'option_name' => 'siteurl', 'autoload' => 'manual', 'slot' => 'canonical', 'option_value' => 'https://new.test', 'revision' => 4],
        ['option_id' => 11, 'option_name' => 'plugin_reuses_manual', 'autoload' => 'manual', 'slot' => 'plugin-slot', 'option_value' => 'plugin', 'revision' => 1],
        ['option_id' => 12, 'option_name' => 'skip_display_slot', 'autoload' => 'skip-auto', 'slot' => 'secondary', 'option_value' => 'ignored', 'revision' => 1],
        ['option_id' => 13, 'option_name' => 'theme_copy', 'autoload' => 'theme-auto', 'slot' => 'json', 'option_value' => 'catch-all', 'revision' => 2],
        ['option_id' => 14, 'option_name' => 'fresh_plugin', 'autoload' => 'fresh-auto', 'slot' => 'fresh-slot', 'option_value' => 'fresh', 'revision' => 1],
    ],
    [
        ['target' => ['option_name'], 'action' => 'update', 'assignments' => $nameAssignments],
        ['target' => ['autoload'], 'action' => 'update', 'assignments' => $autoloadAssignments],
        ['target' => ['slot'], 'action' => 'nothing'],
        ['target' => null, 'action' => 'update', 'assignments' => $slotAssignments],
    ],
    [['option_name'], ['autoload'], ['slot']],
);

$catchAll = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
    $existing,
    [
        ['option_id' => 20, 'option_name' => 'theme_copy', 'autoload' => 'theme-auto', 'slot' => 'json', 'option_value' => 'catch-all', 'revision' => 2],
    ],
    [
        ['target' => ['option_name'], 'action' => 'nothing'],
        ['target' => null, 'action' => 'update', 'assignments' => $slotAssignments],
    ],
    [['option_name'], ['autoload'], ['slot']],
);

$payload = [
    'applicationUse' => 'Preview copied wp_options INSERT ... ON CONFLICT clauses with RETURNING where the first matching conflict target wins, DO NOTHING omits RETURNING rows, and a catch-all arm handles another current UNIQUE conflict without ext/sqlite.',
    'returning' => SQLiteUpsertDoUpdateWherePlan::returningRows($plan['returning_rows'], [
        'name' => 'option_name',
        'value' => 'option_value',
        'revision' => 'revision',
    ]),
    'matchedTargets' => array_map(static fn (array $arm): ?array => $arm['target'], $plan['matched_arms']),
    'skipped' => array_column($plan['skipped_rows'], 'option_name'),
    'catchAllReturning' => SQLiteUpsertDoUpdateWherePlan::returningRows($catchAll['returning_rows'], ['name' => 'option_name', 'value' => 'option_value']),
    'changes' => $plan['changes'],
    'finalNames' => array_column($plan['after'], 'option_name'),
];

if (($argv[1] ?? null) === '--self-test') {
    assert($payload['changes'] === 3);
    assert(array_column($payload['returning'], 'name') === ['siteurl', 'plugin_reuses_manual', 'fresh_plugin']);
    assert($payload['matchedTargets'] === [['option_name'], ['autoload'], ['slot'], ['slot']]);
    assert($payload['skipped'] === ['skip_display_slot', 'theme_copy']);
    assert($payload['catchAllReturning'] === [['name' => 'theme_copy', 'value' => 'slot-conflict:theme_mods']]);
    assert($payload['finalNames'] === ['plugin_reuses_manual', 'home', 'theme_mods', 'fresh_plugin']);
}

return $payload;
