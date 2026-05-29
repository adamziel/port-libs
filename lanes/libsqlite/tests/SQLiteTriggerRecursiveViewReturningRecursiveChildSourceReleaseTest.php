<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows163 = [
    ['option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'parent_name' => null, 'priority' => 0],
    ['option_name' => 'plugin_alpha', 'option_value' => 'enabled', 'autoload' => 'yes', 'parent_name' => 'siteurl', 'priority' => 10],
    ['option_name' => 'plugin_beta', 'option_value' => 'disabled', 'autoload' => 'yes', 'parent_name' => 'siteurl', 'priority' => 20],
    ['option_name' => 'plugin_alpha_child', 'option_value' => 'child', 'autoload' => 'no', 'parent_name' => 'plugin_alpha', 'priority' => 30],
    ['option_name' => 'plugin_beta_child', 'option_value' => 'queued', 'autoload' => 'no', 'parent_name' => 'plugin_beta', 'priority' => 40],
];

$currentView163 = [
    'name' => 'wp_recursive_autoload_view',
    'source' => 'main@view-cookie-163-current',
    'trigger' => 'wp_recursive_autoload_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-163-current',
    'root_key' => 'root_name',
    'parent_key' => 'parent_name',
    'columns' => ['option_name', 'option_value', 'autoload', 'parent_name', 'priority'],
    'where' => static fn (array $row, string $root, int $depth): bool => $depth <= 2 && str_starts_with((string) $row['option_name'], 'plugin_'),
    'order_by' => 'priority',
];
$nextView163 = $currentView163;
$nextView163['source'] = 'main@view-cookie-163-next';
$nextView163['trigger_source'] = 'main@trigger-cookie-163-next';
$nextView163['where'] = static fn (array $row, string $root, int $depth): bool => $depth <= 1 && str_starts_with((string) $row['option_name'], 'plugin_');

$returning163 = [
    'option_name',
    'autoload',
    ['expr' => 'option_value', 'as' => 'value'],
    ['expr' => 'root', 'as' => 'root_name'],
    ['expr' => 'depth', 'as' => 'depth'],
    ['expr' => 'trigger_source', 'as' => 'trigger_cookie'],
    static fn (array $incoming, array $viewRow, string $triggerSource, int $ordinal): string => $triggerSource . ':' . $viewRow['_root'] . ':' . $ordinal . ':' . $incoming['option_name'],
];

$run163 = static fn (array $options = [], ?array $currentRoots = null, ?array $nextRoots = null, ?array $currentView = null, ?array $nextView = null): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeRecursiveChildSourceRelease(
    $rows163,
    $currentRoots ?? [['root_name' => 'siteurl']],
    $nextRoots ?? [['root_name' => 'audit:current:siteurl:plugin_alpha']],
    $currentView ?? $currentView163,
    $nextView ?? $nextView163,
    $returning163,
    $options + [
        'savepoint' => 'wp_recursive_view_next163',
        'current_generation' => 'wp-import-current-163',
        'next_generation' => 'wp-import-next-163',
        'trigger_child_prefix' => 'plugin_generated',
    ],
);

$barrier163 = static fn (): array => $run163();
$release163 = static fn (): array => $run163(['release_next_source' => true]);
$limited163 = static fn (): array => $run163(['max_depth' => 1]);

$currentGenerated163 = [
    'plugin_generated:audit:current:siteurl:plugin_alpha',
    'plugin_generated:audit:current:siteurl:plugin_beta',
    'plugin_generated:audit:current:siteurl:plugin_alpha_child',
    'plugin_generated:audit:current:siteurl:plugin_beta_child',
];

$cases163 = [
    'barrier status' => [static fn (): mixed => $barrier163()['status'], 'trigger-recursive-view-returning-snapshot-barrier-next163'],
    'release status' => [static fn (): mixed => $release163()['status'], 'trigger-recursive-view-returning-snapshot-release-next163'],
    'savepoint retained' => [static fn (): mixed => $barrier163()['savepoint'], 'wp_recursive_view_next163'],
    'current generation retained' => [static fn (): mixed => $barrier163()['current_generation'], 'wp-import-current-163'],
    'next generation retained' => [static fn (): mixed => $barrier163()['next_generation'], 'wp-import-next-163'],
    'current source retained' => [static fn (): mixed => $barrier163()['source_barrier']['current_source'], 'main@view-cookie-163-current'],
    'next source retained' => [static fn (): mixed => $barrier163()['source_barrier']['next_source'], 'main@view-cookie-163-next'],
    'snapshot source matches current' => [static fn (): mixed => $barrier163()['current_snapshot_guard']['source'], 'main@view-cookie-163-current'],
    'snapshot taken before writes' => [static fn (): mixed => $barrier163()['current_snapshot_guard']['snapshot_taken_before_trigger_writes'], true],
    'snapshot reason' => [static fn (): mixed => $barrier163()['current_snapshot_guard']['reason'], 'current recursive view source is materialized before INSTEAD OF trigger RETURNING rows are drained'],
    'generated row count' => [static fn (): mixed => $barrier163()['current_snapshot_guard']['generated_rows'], 4],
    'generated names' => [static fn (): mixed => $barrier163()['trigger_generated_names'], $currentGenerated163],
    'snapshot generated names' => [static fn (): mixed => $barrier163()['current_snapshot_guard']['generated_names'], $currentGenerated163],
    'current recursive names original only' => [static fn (): mixed => $barrier163()['current_snapshot_guard']['current_recursive_names'], ['plugin_alpha', 'plugin_beta', 'plugin_alpha_child', 'plugin_beta_child']],
    'reentrant visible empty' => [static fn (): mixed => $barrier163()['current_snapshot_guard']['reentrant_visible_names'], []],
    'reentrant suppressed true' => [static fn (): mixed => $barrier163()['current_snapshot_guard']['reentrant_suppressed'], true],
    'generated parents follow returning audit rows' => [static fn (): mixed => array_column($barrier163()['trigger_generated_rows'], 'parent_name'), [
        'audit:current:siteurl:plugin_alpha',
        'audit:current:siteurl:plugin_beta',
        'audit:current:siteurl:plugin_alpha_child',
        'audit:current:siteurl:plugin_beta_child',
    ]],
    'generated values trace parents' => [static fn (): mixed => array_column($barrier163()['trigger_generated_rows'], 'option_value'), [
        'generated-from:audit:current:siteurl:plugin_alpha',
        'generated-from:audit:current:siteurl:plugin_beta',
        'generated-from:audit:current:siteurl:plugin_alpha_child',
        'generated-from:audit:current:siteurl:plugin_beta_child',
    ]],
    'generated source is trigger source' => [static fn (): mixed => array_values(array_unique(array_column($barrier163()['trigger_generated_rows'], 'source'))), ['main@trigger-cookie-163-current']],
    'generated priorities stable' => [static fn (): mixed => array_column($barrier163()['trigger_generated_rows'], 'priority'), [1000, 1001, 1002, 1003]],
    'barrier next seed unreleased' => [static fn (): mixed => $barrier163()['next_source_seed']['released'], false],
    'barrier seed rows zero' => [static fn (): mixed => $barrier163()['next_source_seed']['seeded_recursive_rows'], 0],
    'barrier seed names empty' => [static fn (): mixed => $barrier163()['next_source_seed']['seeded_names'], []],
    'barrier seed returning keys empty' => [static fn (): mixed => $barrier163()['next_source_seed']['seeded_returning_keys'], []],
    'barrier seed changes zero' => [static fn (): mixed => $barrier163()['next_source_seed']['seeded_changes'], 0],
    'barrier next returning visible zero' => [static fn (): mixed => $barrier163()['source_barrier']['next_returning_visible'], 0],
    'barrier statement rows current only' => [static fn (): mixed => $barrier163()['statement_rows'], 4],
    'barrier changes zero while held' => [static fn (): mixed => $barrier163()['changes'], 0],
    'barrier visible keys current only' => [static fn (): mixed => $barrier163()['returning_visibility']['visible'], [
        'wp-import-current-163:audit:current:siteurl:plugin_alpha',
        'wp-import-current-163:audit:current:siteurl:plugin_beta',
        'wp-import-current-163:audit:current:siteurl:plugin_alpha_child',
        'wp-import-current-163:audit:current:siteurl:plugin_beta_child',
    ]],
    'barrier yield boundary' => [static fn (): mixed => $barrier163()['yield_boundary'], 'current-source-snapshot-drained-trigger-writes-held-from-recursive-reentry-next163'],
    'release flag' => [static fn (): mixed => $release163()['next_source_seed']['released'], true],
    'release seed source' => [static fn (): mixed => $release163()['next_source_seed']['source'], 'main@view-cookie-163-next'],
    'release seed row count' => [static fn (): mixed => $release163()['next_source_seed']['seeded_recursive_rows'], 1],
    'release seed names' => [static fn (): mixed => $release163()['next_source_seed']['seeded_names'], ['plugin_generated:audit:current:siteurl:plugin_alpha']],
    'release seed changes' => [static fn (): mixed => $release163()['next_source_seed']['seeded_changes'], 1],
    'release seed returning keys' => [static fn (): mixed => $release163()['next_source_seed']['seeded_returning_keys'], ['wp-import-next-163:audit:next:audit:current:siteurl:plugin_alpha:plugin_generated:audit:current:siteurl:plugin_alpha']],
    'release next returning includes seed' => [static fn (): mixed => array_column($release163()['next_returning_rows'], 'visibility'), ['next-returning-released-from-trigger-generated-seed']],
    'release next returning visible flag' => [static fn (): mixed => array_column($release163()['next_returning_rows'], 'visible_to_statement'), [true]],
    'release next returning generation' => [static fn (): mixed => array_column($release163()['next_returning_rows'], 'source_generation'), ['wp-import-next-163']],
    'release visible includes seed' => [static fn (): mixed => array_slice($release163()['returning_visibility']['visible'], -1), ['wp-import-next-163:audit:next:audit:current:siteurl:plugin_alpha:plugin_generated:audit:current:siteurl:plugin_alpha']],
    'release statement rows include seed' => [static fn (): mixed => $release163()['statement_rows'], 5],
    'release changes include seed' => [static fn (): mixed => $release163()['changes'], 5],
    'release yield boundary' => [static fn (): mixed => $release163()['yield_boundary'], 'current-source-snapshot-drained-trigger-writes-seed-released-next-source-next163'],
    'limited current generated count' => [static fn (): mixed => $limited163()['current_snapshot_guard']['generated_rows'], 2],
    'limited current generated names' => [static fn (): mixed => $limited163()['trigger_generated_names'], [
        'plugin_generated:audit:current:siteurl:plugin_alpha',
        'plugin_generated:audit:current:siteurl:plugin_beta',
    ]],
    'limited recursive names' => [static fn (): mixed => $limited163()['current_snapshot_guard']['current_recursive_names'], ['plugin_alpha', 'plugin_beta']],
    'custom child prefix accepted' => [static fn (): mixed => $run163(['trigger_child_prefix' => 'plugin_seed'])['trigger_generated_names'][0], 'plugin_seed:audit:current:siteurl:plugin_alpha'],
    'custom generations accepted' => [static fn (): mixed => $run163(['current_generation' => 'custom.current@163', 'next_generation' => 'custom.next@163'])['next_generation'], 'custom.next@163'],
    'dependency next163 marker' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next163', $barrier163()['dependencies'], true), true],
    'dependency snapshot marker' => [static fn (): mixed => in_array('sqlite-recursive-view-source-snapshot-before-trigger-writes', $barrier163()['dependencies'], true), true],
    'dependency next seed marker' => [static fn (): mixed => in_array('sqlite-trigger-generated-rows-seed-next-source-only-after-release', $barrier163()['dependencies'], true), true],
    'bad child prefix rejected' => [static fn (): mixed => $run163(['trigger_child_prefix' => 'bad prefix']), InvalidArgumentException::class],
    'bad current view name rejected' => [static fn (): mixed => $run163([], null, null, ['name' => 'bad name', 'source' => 'ok', 'trigger' => 'trg', 'trigger_source' => 'ok', 'root_key' => 'root_name', 'parent_key' => 'parent_name', 'columns' => ['option_name']]), InvalidArgumentException::class],
    'bad next view source rejected' => [static fn (): mixed => $run163([], null, null, null, ['name' => 'v', 'source' => 'bad source', 'trigger' => 'trg', 'trigger_source' => 'ok', 'root_key' => 'root_name', 'parent_key' => 'parent_name', 'columns' => ['option_name']]), InvalidArgumentException::class],
    'missing option name rejected' => [static fn (): mixed => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeRecursiveChildSourceRelease([['option_value' => 'x']], [], [], $currentView163, $nextView163, $returning163), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases163 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next163 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
