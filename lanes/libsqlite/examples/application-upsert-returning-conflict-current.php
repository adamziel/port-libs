<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteUpsertDoUpdateWherePlan.php';

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$existing = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'option_value' => 'https://old.test', 'slot' => 'primary', 'revision' => 1],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'no', 'option_value' => 'https://home.test', 'slot' => 'secondary', 'revision' => 3],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'maybe', 'option_value' => 'Old Blog', 'slot' => 'display', 'revision' => 7],
];

$assignments = [
    'option_id' => static fn (array $current, array $excluded): mixed => $excluded['option_id'],
    'autoload' => static fn (array $current, array $excluded): mixed => $excluded['autoload'],
    'option_value' => static fn (array $current, array $excluded): mixed => $excluded['option_value'],
    'slot' => static fn (array $current, array $excluded): mixed => $excluded['slot'],
    'revision' => static fn (array $current, array $excluded): int => (int) $current['revision'] + (int) $excluded['revision'],
];

$safe = SQLiteUpsertDoUpdateWherePlan::execute(
    $existing,
    [
        ['option_id' => 10, 'option_name' => 'siteurl', 'autoload' => 'manual', 'option_value' => 'https://new.test', 'slot' => 'canonical', 'revision' => 4],
        ['option_id' => 11, 'option_name' => 'fresh_plugin', 'autoload' => 'plugin', 'option_value' => 'enabled', 'slot' => 'plugin', 'revision' => 1],
    ],
    ['option_name'],
    $assignments,
    null,
    [['option_name'], ['autoload'], ['slot']],
);

$conflictMessage = null;
try {
    SQLiteUpsertDoUpdateWherePlan::execute(
        $existing,
        [
            ['option_id' => 12, 'option_name' => 'siteurl', 'autoload' => 'no', 'option_value' => 'bad', 'slot' => 'canonical', 'revision' => 1],
        ],
        ['option_name'],
        $assignments,
        null,
        [['option_name'], ['autoload'], ['slot']],
    );
} catch (InvalidArgumentException $exception) {
    $conflictMessage = $exception->getMessage();
}

$payload = [
    'applicationUse' => 'Preview copied wp_options INSERT ... ON CONFLICT DO UPDATE RETURNING rows while aborting updates that would collide with another current UNIQUE option column, without requiring ext/sqlite.',
    'safeReturning' => SQLiteUpsertDoUpdateWherePlan::returningRows($safe['returning_rows'], [
        'name' => 'option_name',
        'autoload' => 'autoload',
        'slot' => 'slot',
        'revision' => 'revision',
    ]),
    'safeChanges' => $safe['changes'],
    'currentConflict' => $conflictMessage,
];

if (($argv[1] ?? null) === '--self-test') {
    assert($payload['safeChanges'] === 2);
    assert(array_column($payload['safeReturning'], 'name') === ['siteurl', 'fresh_plugin']);
    assert($payload['safeReturning'][0]['revision'] === 5);
    assert($payload['currentConflict'] === 'SQLite UPSERT update produced a unique constraint conflict');
}

return $payload;
