<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows166 = [
    ['key_name' => 'base_url', 'key_value' => 'https://example.test', 'load_policy' => 'yes', 'parent_name' => null, 'priority' => 0],
    ['key_name' => 'module_alpha', 'key_value' => 'enabled', 'load_policy' => 'yes', 'parent_name' => 'base_url', 'priority' => 10],
    ['key_name' => 'module_beta', 'key_value' => 'disabled', 'load_policy' => 'yes', 'parent_name' => 'base_url', 'priority' => 20],
    ['key_name' => 'module_alpha_child', 'key_value' => 'child', 'load_policy' => 'no', 'parent_name' => 'module_alpha', 'priority' => 30],
    ['key_name' => 'module_beta_child', 'key_value' => 'queued', 'load_policy' => 'no', 'parent_name' => 'module_beta', 'priority' => 40],
];

$currentView166 = [
    'name' => 'app_recursive_load_policy_view',
    'source' => 'main@view-cookie-166-current',
    'trigger' => 'app_recursive_load_policy_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-166-current',
    'root_key' => 'root_name',
    'parent_key' => 'parent_name',
    'columns' => ['key_name', 'key_value', 'load_policy', 'parent_name', 'priority'],
    'where' => static fn (array $row, string $root, int $depth): bool => $depth <= 2 && str_starts_with((string) $row['key_name'], 'module_'),
    'order_by' => 'priority',
];
$nextView166 = $currentView166;
$nextView166['source'] = 'main@view-cookie-166-next';
$nextView166['trigger_source'] = 'main@trigger-cookie-166-next';
$nextView166['where'] = static fn (array $row, string $root, int $depth): bool => $depth <= 1 && str_starts_with((string) $row['key_name'], 'module_');

$returning166 = [
    'key_name',
    'load_policy',
    ['expr' => 'key_value', 'as' => 'value'],
    ['expr' => 'root', 'as' => 'root_name'],
    ['expr' => 'depth', 'as' => 'depth'],
    ['expr' => 'trigger_source', 'as' => 'trigger_cookie'],
];

$run166 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeStagedSourceReleaseAfterDrain(
    $rows166,
    [['root_name' => 'base_url']],
    [['root_name' => 'audit:current:base_url:module_alpha']],
    $currentView166,
    $nextView166,
    $returning166,
    $options + [
        'savepoint' => 'app_recursive_view_next166',
        'current_generation' => 'app-import-current-166',
        'next_generation' => 'app-import-next-166',
        'trigger_child_prefix' => 'module_generated',
    ],
);

$release166 = static fn (): array => $run166(['release_next_source' => true]);
$held166 = static fn (): array => $run166(['release_next_source' => false]);
$limited166 = static fn (): array => $run166(['release_next_source' => true, 'max_depth' => 1]);

$currentKeys166 = [
    'app-import-current-166:audit:current:base_url:module_alpha',
    'app-import-current-166:audit:current:base_url:module_beta',
    'app-import-current-166:audit:current:base_url:module_alpha_child',
    'app-import-current-166:audit:current:base_url:module_beta_child',
];
$nextKey166 = 'app-import-next-166:audit:next:audit:current:base_url:module_alpha:module_generated:audit:current:base_url:module_alpha';

$cases166 = [
    'release status' => [static fn (): mixed => $release166()['status'], 'trigger-recursive-view-returning-current-drain-before-next-source-next166'],
    'held status' => [static fn (): mixed => $held166()['status'], 'trigger-recursive-view-returning-current-drain-holds-next-source-next166'],
    'release yield boundary' => [static fn (): mixed => $release166()['yield_boundary'], 'current-returning-drain-then-trigger-generated-next-source-next166'],
    'held yield boundary' => [static fn (): mixed => $held166()['yield_boundary'], 'current-returning-drain-with-next-source-held-next166'],
    'savepoint retained' => [static fn (): mixed => $release166()['savepoint'], 'app_recursive_view_next166'],
    'current generation retained' => [static fn (): mixed => $release166()['returning_drain']['current_generation'], 'app-import-current-166'],
    'next generation retained' => [static fn (): mixed => $release166()['returning_drain']['next_generation'], 'app-import-next-166'],
    'current source retained' => [static fn (): mixed => $release166()['returning_drain']['current_source'], 'main@view-cookie-166-current'],
    'next source retained' => [static fn (): mixed => $release166()['returning_drain']['next_source'], 'main@view-cookie-166-next'],
    'current visible count' => [static fn (): mixed => $release166()['returning_drain']['current_visible_count'], 4],
    'release next visible count' => [static fn (): mixed => $release166()['returning_drain']['next_visible_count'], 1],
    'release next suppressed count' => [static fn (): mixed => $release166()['returning_drain']['next_suppressed_count'], 0],
    'held next visible count' => [static fn (): mixed => $held166()['returning_drain']['next_visible_count'], 0],
    'held next suppressed count' => [static fn (): mixed => $held166()['returning_drain']['next_suppressed_count'], 1],
    'current last ordinal' => [static fn (): mixed => $release166()['returning_drain']['current_last_ordinal'], 3],
    'next first ordinal' => [static fn (): mixed => $release166()['returning_drain']['next_first_ordinal'], 4],
    'next follows current drain' => [static fn (): mixed => $release166()['returning_drain']['next_after_current_drain'], true],
    'release visible keys' => [static fn (): mixed => $release166()['returning_drain']['visible_keys'], array_merge($currentKeys166, [$nextKey166])],
    'held visible keys current only' => [static fn (): mixed => $held166()['returning_drain']['visible_keys'], $currentKeys166],
    'held suppressed key' => [static fn (): mixed => $held166()['returning_drain']['suppressed_keys'], [$nextKey166]],
    'release timeline phases' => [static fn (): mixed => array_column($release166()['returning_drain']['timeline'], 'phase'), [
        'current-returning-drain',
        'current-returning-drain',
        'current-returning-drain',
        'current-returning-drain',
        'next-source-after-current-drain',
    ]],
    'held timeline phases' => [static fn (): mixed => array_column($held166()['returning_drain']['timeline'], 'phase'), [
        'current-returning-drain',
        'current-returning-drain',
        'current-returning-drain',
        'current-returning-drain',
        'next-source-held-until-current-drain',
    ]],
    'release timeline ordinals' => [static fn (): mixed => array_column($release166()['returning_drain']['timeline'], 'ordinal'), [0, 1, 2, 3, 4]],
    'release timeline generations' => [static fn (): mixed => array_values(array_unique(array_column($release166()['returning_drain']['timeline'], 'generation'))), ['app-import-current-166', 'app-import-next-166']],
    'release current trigger cookie' => [static fn (): mixed => $release166()['returning_drain']['timeline'][0]['trigger_cookie'], 'main@trigger-cookie-166-current'],
    'release next trigger cookie' => [static fn (): mixed => $release166()['returning_drain']['timeline'][4]['trigger_cookie'], 'main@trigger-cookie-166-next'],
    'release current root' => [static fn (): mixed => $release166()['returning_drain']['timeline'][0]['root_name'], 'base_url'],
    'release next root' => [static fn (): mixed => $release166()['returning_drain']['timeline'][4]['root_name'], 'audit:current:base_url:module_alpha'],
    'release seeded by generated rows' => [static fn (): mixed => $release166()['next_source_admission']['seeded_by_trigger_generated_rows'], ['module_generated:audit:current:base_url:module_alpha']],
    'held seeded rows empty' => [static fn (): mixed => $held166()['next_source_admission']['seeded_by_trigger_generated_rows'], []],
    'held probe seeded rows empty' => [static fn (): mixed => $release166()['next_source_admission']['held_probe_seeded_names'], []],
    'held probe visible keys empty' => [static fn (): mixed => $release166()['next_source_admission']['held_probe_visible_keys'], []],
    'release admission reason' => [static fn (): mixed => $release166()['next_source_admission']['admission_reason'], 'current RETURNING drain completed before next source trigger seed admission'],
    'held admission reason' => [static fn (): mixed => $held166()['next_source_admission']['admission_reason'], 'next source remains held while current RETURNING rows are visible'],
    'release admission flag' => [static fn (): mixed => $release166()['next_source_admission']['released'], true],
    'held admission flag' => [static fn (): mixed => $held166()['next_source_admission']['released'], false],
    'release statement rows' => [static fn (): mixed => $release166()['statement_rows'], 5],
    'held statement rows' => [static fn (): mixed => $held166()['statement_rows'], 4],
    'release changes' => [static fn (): mixed => $release166()['changes'], 5],
    'held changes' => [static fn (): mixed => $held166()['changes'], 0],
    'limited current visible count' => [static fn (): mixed => $limited166()['returning_drain']['current_visible_count'], 2],
    'limited current last ordinal' => [static fn (): mixed => $limited166()['returning_drain']['current_last_ordinal'], 1],
    'limited next first ordinal' => [static fn (): mixed => $limited166()['returning_drain']['next_first_ordinal'], 2],
    'limited visible keys' => [static fn (): mixed => $limited166()['returning_drain']['visible_keys'], [
        'app-import-current-166:audit:current:base_url:module_alpha',
        'app-import-current-166:audit:current:base_url:module_beta',
        $nextKey166,
    ]],
    'generated rows stay hidden from current snapshot' => [static fn (): mixed => $release166()['current_snapshot_guard']['reentrant_suppressed'], true],
    'generated row count' => [static fn (): mixed => $release166()['current_snapshot_guard']['generated_rows'], 4],
    'current recursive names original only' => [static fn (): mixed => $release166()['current_snapshot_guard']['current_recursive_names'], ['module_alpha', 'module_beta', 'module_alpha_child', 'module_beta_child']],
    'next seed names' => [static fn (): mixed => $release166()['next_source_seed']['seeded_names'], ['module_generated:audit:current:base_url:module_alpha']],
    'next seed returning keys' => [static fn (): mixed => $release166()['next_source_seed']['seeded_returning_keys'], [$nextKey166]],
    'dependency next166 marker' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next166', $release166()['dependencies'], true), true],
    'dependency drain marker' => [static fn (): mixed => in_array('sqlite-returning-current-source-drain-before-next-source-admission', $release166()['dependencies'], true), true],
    'dependency trigger seed marker' => [static fn (): mixed => in_array('sqlite-view-trigger-generated-rows-hidden-until-current-returning-drain', $release166()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => $release166()['dependency_closure'], 'reuses-native-recursive-view-returning-source-barriers'],
    'bad current generation rejected' => [static fn (): mixed => $run166(['current_generation' => 'bad generation']), InvalidArgumentException::class],
    'bad next generation rejected' => [static fn (): mixed => $run166(['next_generation' => 'bad generation']), InvalidArgumentException::class],
    'bad savepoint rejected' => [static fn (): mixed => $run166(['savepoint' => 'bad savepoint']), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases166 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next166 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
