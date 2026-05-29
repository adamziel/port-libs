<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerDeferredReturningRecursiveCurrentSourceNextPlan;

$parents125 = [
    ['option_id' => 1, 'next_id' => 2, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'revision' => 1],
    ['option_id' => 2, 'next_id' => 3, 'option_name' => 'home', 'option_value' => 'https://old.test/home', 'revision' => 1],
    ['option_id' => 3, 'next_id' => null, 'option_name' => 'blogname', 'option_value' => 'Old Site', 'revision' => 1],
];
$children125 = [
    ['meta_id' => 11, 'option_id' => 1, 'meta_key' => '_origin'],
    ['meta_id' => 12, 'option_id' => 2, 'meta_key' => '_origin'],
    ['meta_id' => 13, 'option_id' => 3, 'meta_key' => '_origin'],
];
$fk125 = ['parent_key' => 'option_id', 'child_key' => 'option_id', 'deferred' => true];
$statement125 = [
    'savepoint' => 'wp_options_rekey',
    'current_source' => 'main@cookie-125',
    'next_source' => 'main@cookie-126',
    'where' => static fn (array $row): bool => $row['option_id'] === 1,
    'assignments' => [
        'option_id' => static fn (array $row, int $depth, string $source): int => (int) $row['option_id'] + 100 + $depth,
        'revision' => static fn (array $row, int $depth, string $source): int => (int) $row['revision'] + 1 + $depth,
        'option_value' => static fn (array $row, int $depth, string $source): string => (string) $row['option_value'] . ':' . $source . ':' . $depth,
    ],
    'returning' => [
        ['expr' => 'old.option_id', 'as' => 'old_id'],
        ['expr' => 'new.option_id', 'as' => 'new_id'],
        ['expr' => 'context.source', 'as' => 'source_token'],
        ['expr' => 'context.trigger_depth', 'as' => 'depth'],
        ['expr' => 'context.trigger_source', 'as' => 'trigger_source'],
        'option_name',
        'revision',
    ],
    'trigger' => ['name' => 'wp_options_recursive_rekey', 'match_column' => 'option_id', 'match_value' => 'old.next_id'],
    'rollback_on_deferred_violation' => true,
];

$plan125 = static fn (array $statement = [], array $children = null): array => SQLiteTriggerDeferredReturningRecursiveCurrentSourceNextPlan::sourceBarrier(
    $parents125,
    $children ?? $children125,
    $fk125,
    array_replace($statement125, $statement),
);

$rolled125 = static fn (): array => $plan125();
$blocked125 = static fn (): array => $plan125(['rollback_on_deferred_violation' => false]);
$committed125 = static fn (): array => $plan125([], []);
$nonRecursive125 = static fn (): array => $plan125(['recursive_triggers' => false]);

$cases125 = [
    'rollback status' => [static fn (): mixed => $rolled125()['status'], 'rolled-back'],
    'rollback source current' => [static fn (): mixed => $rolled125()['source_transition']['current'], 'main@cookie-125'],
    'rollback source recursive next' => [static fn (): mixed => $rolled125()['source_transition']['recursive_next'], 'main@cookie-126'],
    'rollback visible next remains current' => [static fn (): mixed => $rolled125()['source_transition']['visible_next'], 'main@cookie-125'],
    'rollback barrier name' => [static fn (): mixed => $rolled125()['source_transition']['barrier'], 'rollback-to-current-source'],
    'rollback barrier closed' => [static fn (): mixed => $rolled125()['deferred_barrier_open'], false],
    'rollback barrier reason' => [static fn (): mixed => $rolled125()['deferred_barrier_reason'], 'rollback-on-deferred-violation'],
    'rollback current stream count' => [static fn (): mixed => count($rolled125()['current_source_stream']), 1],
    'rollback current stream source' => [static fn (): mixed => $rolled125()['current_source_stream'][0]['source'], 'main@cookie-125'],
    'rollback current stream trigger source' => [static fn (): mixed => $rolled125()['current_source_stream'][0]['trigger_source'], 'statement'],
    'rollback current stream depth' => [static fn (): mixed => $rolled125()['current_source_stream'][0]['trigger_depth'], 0],
    'rollback current stream old key' => [static fn (): mixed => $rolled125()['current_source_stream'][0]['old_key'], 1],
    'rollback current stream new key' => [static fn (): mixed => $rolled125()['current_source_stream'][0]['new_key'], 101],
    'rollback current returning source token' => [static fn (): mixed => $rolled125()['current_source_stream'][0]['returning']['source_token'], 'main@cookie-125'],
    'rollback current returning trigger source' => [static fn (): mixed => $rolled125()['current_source_stream'][0]['returning']['trigger_source'], 'statement'],
    'rollback current returning revision' => [static fn (): mixed => $rolled125()['current_source_stream'][0]['returning']['revision'], 2],
    'rollback recursive stream count' => [static fn (): mixed => count($rolled125()['recursive_next_source_stream']), 2],
    'rollback recursive stream sources' => [static fn (): mixed => array_column($rolled125()['recursive_next_source_stream'], 'source'), ['main@cookie-126', 'main@cookie-126']],
    'rollback recursive depths' => [static fn (): mixed => array_column($rolled125()['recursive_next_source_stream'], 'trigger_depth'), [1, 2]],
    'rollback recursive trigger names' => [static fn (): mixed => array_column($rolled125()['recursive_next_source_stream'], 'trigger_source'), ['wp_options_recursive_rekey', 'wp_options_recursive_rekey']],
    'rollback recursive old keys' => [static fn (): mixed => array_column($rolled125()['recursive_next_source_stream'], 'old_key'), [2, 3]],
    'rollback recursive new keys' => [static fn (): mixed => array_column($rolled125()['recursive_next_source_stream'], 'new_key'), [103, 105]],
    'rollback recursive returning names' => [static fn (): mixed => array_column(array_column($rolled125()['recursive_next_source_stream'], 'returning'), 'option_name'), ['home', 'blogname']],
    'rollback admitted next empty' => [static fn (): mixed => $rolled125()['admitted_next_source_stream'], []],
    'rollback suppressed next count' => [static fn (): mixed => count($rolled125()['suppressed_next_source_stream']), 2],
    'rollback deferred queue count' => [static fn (): mixed => count($rolled125()['deferred_check_queue']), 3],
    'rollback deferred queue source' => [static fn (): mixed => array_values(array_unique(array_column($rolled125()['deferred_check_queue'], 'source'))), ['main@cookie-126']],
    'rollback deferred queue keys' => [static fn (): mixed => array_column($rolled125()['deferred_check_queue'], 'child_key'), [1, 2, 3]],
    'rollback deferred queue admitted flags' => [static fn (): mixed => array_column($rolled125()['deferred_check_queue'], 'admitted'), [false, false, false]],
    'rollback next returning suppressed' => [static fn (): mixed => $rolled125()['next_returning_rows'], []],
    'rollback attempted returning count' => [static fn (): mixed => count($rolled125()['attempted_returning_rows']), 3],
    'rollback next rowids restored' => [static fn (): mixed => $rolled125()['next_rowids'], [1, 2, 3]],
    'rollback dependencies include next125 marker' => [static fn (): mixed => in_array('sqlite-trigger-deferred-returning-recursive-current-source-next125', $rolled125()['dependencies'], true), true],
    'rollback dependencies include source barrier marker' => [static fn (): mixed => in_array('sqlite-recursive-trigger-returning-next-source-barrier', $rolled125()['dependencies'], true), true],

    'blocked status' => [static fn (): mixed => $blocked125()['status'], 'deferred-commit-blocked'],
    'blocked visible next advances' => [static fn (): mixed => $blocked125()['source_transition']['visible_next'], 'main@cookie-126'],
    'blocked barrier name' => [static fn (): mixed => $blocked125()['source_transition']['barrier'], 'deferred-blocked-before-next-source'],
    'blocked barrier reason' => [static fn (): mixed => $blocked125()['deferred_barrier_reason'], 'deferred-violation-blocks-next-source'],
    'blocked admitted next empty' => [static fn (): mixed => $blocked125()['admitted_next_source_stream'], []],
    'blocked suppressed next count' => [static fn (): mixed => count($blocked125()['suppressed_next_source_stream']), 2],
    'blocked current rowids changed' => [static fn (): mixed => $blocked125()['current_rowids'], [101, 103, 105]],
    'blocked next rowids changed' => [static fn (): mixed => $blocked125()['next_rowids'], [101, 103, 105]],
    'blocked visible returning keeps top level row' => [static fn (): mixed => count($blocked125()['next_returning_rows']), 1],

    'commit status' => [static fn (): mixed => $committed125()['status'], 'commit-ok'],
    'commit visible next advances' => [static fn (): mixed => $committed125()['source_transition']['visible_next'], 'main@cookie-126'],
    'commit barrier name' => [static fn (): mixed => $committed125()['source_transition']['barrier'], 'commit-admits-next-source'],
    'commit barrier open' => [static fn (): mixed => $committed125()['deferred_barrier_open'], true],
    'commit barrier reason' => [static fn (): mixed => $committed125()['deferred_barrier_reason'], 'no-deferred-violations'],
    'commit admitted next count' => [static fn (): mixed => count($committed125()['admitted_next_source_stream']), 2],
    'commit admitted next keys' => [static fn (): mixed => array_column($committed125()['admitted_next_source_stream'], 'new_key'), [103, 105]],
    'commit suppressed next empty' => [static fn (): mixed => $committed125()['suppressed_next_source_stream'], []],
    'commit deferred queue empty' => [static fn (): mixed => $committed125()['deferred_check_queue'], []],
    'commit visible returning top row' => [static fn (): mixed => $committed125()['next_returning_rows'][0]['option_name'], 'siteurl'],

    'non recursive status rolls back' => [static fn (): mixed => $nonRecursive125()['status'], 'rolled-back'],
    'non recursive recursive stream empty' => [static fn (): mixed => $nonRecursive125()['recursive_next_source_stream'], []],
    'non recursive current stream count' => [static fn (): mixed => count($nonRecursive125()['current_source_stream']), 1],
    'non recursive current rowids' => [static fn (): mixed => $nonRecursive125()['current_rowids'], [101, 2, 3]],

    'bad source is rejected' => [static fn (): mixed => $plan125(['next_source' => 'bad next']), InvalidArgumentException::class],
    'bad returning is rejected' => [static fn (): mixed => $plan125(['returning' => ['missing_column']]), InvalidArgumentException::class],
];

foreach ($cases125 as $name => [$callback, $expected]) {
    $tests['trigger deferred returning recursive current source next125 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
