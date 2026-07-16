<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteUpsertDoUpdateWherePlan.php';

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$existing = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'hits' => 5, 'note' => 'seed'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'hits' => 2, 'note' => 'seed'],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Old Blog', 'autoload' => 'no', 'hits' => 7, 'note' => 'seed'],
];

$incoming = [
    ['option_id' => 10, 'option_name' => 'siteurl', 'option_value' => 'https://new.test', 'autoload' => 'no', 'hits' => 3, 'note' => 'update-site'],
    ['option_id' => 11, 'option_name' => 'new_plugin', 'option_value' => 'enabled', 'autoload' => 'no', 'hits' => 1, 'note' => 'insert-plugin'],
    ['option_id' => 12, 'option_name' => 'home', 'option_value' => 'https://new-home.test', 'autoload' => 'yes', 'hits' => 4, 'note' => 'update-home'],
];

$result = SQLiteUpsertDoUpdateWherePlan::execute(
    $existing,
    $incoming,
    ['option_name'],
    [
        'option_id' => static fn (array $current, array $excluded): mixed => $excluded['option_id'],
        'option_value' => static fn (array $current, array $excluded): mixed => $excluded['option_value'],
        'autoload' => static fn (array $current, array $excluded): mixed => $excluded['autoload'],
        'hits' => static fn (array $current, array $excluded): int => (int) $current['hits'] + (int) $excluded['hits'],
        'note' => static fn (array $current, array $excluded): string => $current['note'] . '->' . $excluded['note'],
    ],
    static fn (): bool => true,
);

$payload = [
    'applicationUse' => 'Preview copied wp_options INSERT ... ON CONFLICT DO UPDATE RETURNING rows in statement order, including mixed updates/inserts and projected RETURNING aliases without requiring ext/sqlite.',
    'changes' => $result['changes'],
    'returning' => SQLiteUpsertDoUpdateWherePlan::returningRows($result['returning_rows'], [
        'id' => 'option_id',
        'name' => 'option_name',
        'value' => 'option_value',
        'hit_count' => 'hits',
        'source' => static fn (array $row): string => str_contains($row['note'], '->') ? 'updated' : 'inserted',
    ]),
];

if (($argv[1] ?? null) === '--self-test') {
    assert($payload['changes'] === 3);
    assert(array_column($payload['returning'], 'name') === ['siteurl', 'new_plugin', 'home']);
    assert(array_column($payload['returning'], 'source') === ['updated', 'inserted', 'updated']);
}

return $payload;
