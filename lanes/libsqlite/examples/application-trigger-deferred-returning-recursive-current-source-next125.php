<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTriggerDeferredReturningRecursiveCurrentSourceNextPlan;

$parents = [
    ['option_id' => 1, 'next_id' => 2, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'revision' => 1],
    ['option_id' => 2, 'next_id' => null, 'option_name' => 'home', 'option_value' => 'https://old.test/home', 'revision' => 1],
];
$children = [
    ['meta_id' => 11, 'option_id' => 1, 'meta_key' => '_origin'],
    ['meta_id' => 12, 'option_id' => 2, 'meta_key' => '_origin'],
];

$plan = SQLiteTriggerDeferredReturningRecursiveCurrentSourceNextPlan::sourceBarrier(
    $parents,
    $children,
    ['parent_key' => 'option_id', 'child_key' => 'option_id', 'deferred' => true],
    [
        'savepoint' => 'wp_options_rekey',
        'current_source' => 'main@cookie-125',
        'next_source' => 'main@cookie-126',
        'where' => static fn (array $row): bool => $row['option_id'] === 1,
        'assignments' => [
            'option_id' => static fn (array $row, int $depth, string $source): int => (int) $row['option_id'] + 100 + $depth,
            'revision' => static fn (array $row, int $depth, string $source): int => (int) $row['revision'] + 1 + $depth,
        ],
        'returning' => [
            ['expr' => 'old.option_id', 'as' => 'old_id'],
            ['expr' => 'new.option_id', 'as' => 'new_id'],
            ['expr' => 'context.source', 'as' => 'source_token'],
            'option_name',
        ],
        'trigger' => ['name' => 'wp_options_recursive_rekey', 'match_column' => 'option_id', 'match_value' => 'old.next_id'],
        'rollback_on_deferred_violation' => true,
    ],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'rolled-back');
    assert($plan['source_transition']['barrier'] === 'rollback-to-current-source');
    assert(count($plan['current_source_stream']) === 1);
    assert(count($plan['suppressed_next_source_stream']) === 1);
    assert($plan['admitted_next_source_stream'] === []);
    echo "application-trigger-deferred-returning-recursive-current-source-next125 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
