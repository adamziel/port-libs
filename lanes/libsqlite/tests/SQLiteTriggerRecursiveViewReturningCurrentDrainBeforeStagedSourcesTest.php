<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows165 = [
    ['key_name' => 'base_url', 'key_value' => 'https://example.test', 'load_policy' => 'yes', 'parent_name' => null, 'priority' => 0],
    ['key_name' => 'theme_root', 'key_value' => 'theme', 'load_policy' => 'yes', 'parent_name' => 'base_url', 'priority' => 10],
    ['key_name' => 'theme_child', 'key_value' => 'theme-child', 'load_policy' => 'no', 'parent_name' => 'theme_root', 'priority' => 20],
    ['key_name' => 'module_root', 'key_value' => 'module', 'load_policy' => 'yes', 'parent_name' => 'base_url', 'priority' => 15],
    ['key_name' => 'module_child', 'key_value' => 'module-child', 'load_policy' => 'no', 'parent_name' => 'module_root', 'priority' => 30],
    ['key_name' => 'group_root', 'key_value' => 'group', 'load_policy' => 'yes', 'parent_name' => 'module_child', 'priority' => 40],
    ['key_name' => 'group_child', 'key_value' => 'group-child', 'load_policy' => 'no', 'parent_name' => 'group_root', 'priority' => 50],
];

$currentView165 = [
    'name' => 'app_recursive_load_policy_view',
    'source' => 'main@view-cookie-165-current',
    'trigger' => 'app_recursive_load_policy_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-165-current',
    'root_key' => 'root_name',
    'parent_key' => 'parent_name',
    'columns' => ['key_name', 'key_value', 'load_policy', 'parent_name', 'priority'],
    'where' => static fn (array $row, string $root, int $depth): bool => $depth <= 2 && !str_starts_with((string) $row['key_name'], 'network_'),
    'order_by' => 'priority',
];
$nextView165 = $currentView165;
$nextView165['source'] = 'main@view-cookie-165-next';
$nextView165['trigger_source'] = 'main@trigger-cookie-165-next';
$nextView165['where'] = static fn (array $row, string $root, int $depth): bool => $depth <= 3 && str_contains((string) $row['key_name'], '_');

$returning165 = [
    'key_name',
    ['expr' => 'root', 'as' => 'root_name'],
    ['expr' => 'depth', 'as' => 'depth'],
    ['expr' => 'trigger_source', 'as' => 'trigger_cookie'],
    static fn (array $incoming, array $viewRow, string $triggerSource, int $ordinal): string => $triggerSource . ':' . $viewRow['_root'] . ':' . $ordinal . ':' . $incoming['key_name'],
];

$run165 = static fn (array $options = [], ?array $firstRoots = null, ?array $secondRoots = null): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentDrainBeforeStagedSources(
    $rows165,
    [['root_name' => 'base_url']],
    $firstRoots ?? [['root_name' => 'theme_root']],
    $secondRoots ?? [['root_name' => 'module_root']],
    $currentView165,
    $nextView165,
    $returning165,
    $options + [
        'savepoint' => 'app_recursive_view_next165',
        'cursor_name' => 'app_recursive_view_returning_cursor_165',
        'current_generation' => 'app-import-current-165',
        'first_next_generation' => 'app-import-next-165-a',
        'second_next_generation' => 'app-import-next-165-b',
    ],
);

$held165 = static fn (): array => $run165();
$firstRelease165 = static fn (): array => $run165(['release_staged_sources' => 1]);
$allRelease165 = static fn (): array => $run165(['release_staged_sources' => 2]);
$limited165 = static fn (): array => $run165(['max_depth' => 1]);
$emptySecond165 = static fn (): array => $run165([], null, [['root_name' => 'group_child']]);

$cases165 = [
    'held status' => [static fn (): mixed => $held165()['status'], 'trigger-recursive-view-returning-current-source-next-cursor-held-next165'],
    'first release status' => [static fn (): mixed => $firstRelease165()['status'], 'trigger-recursive-view-returning-current-source-next-cursor-first-released-next165'],
    'all release status' => [static fn (): mixed => $allRelease165()['status'], 'trigger-recursive-view-returning-current-source-next-cursor-all-released-next165'],
    'savepoint retained' => [static fn (): mixed => $held165()['savepoint'], 'app_recursive_view_next165'],
    'cursor retained' => [static fn (): mixed => $held165()['cursor'], 'app_recursive_view_returning_cursor_165'],
    'queue current generation retained' => [static fn (): mixed => $held165()['queue']['current_generation'], 'app-import-current-165'],
    'queue staged generations retained' => [static fn (): mixed => $held165()['queue']['staged_generations'], ['app-import-next-165-a', 'app-import-next-165-b']],
    'queue visible generation held current' => [static fn (): mixed => $held165()['queue']['visible_generation'], 'app-import-current-165'],
    'source plan visible generation held current' => [static fn (): mixed => $held165()['source_next_plan']['visible_generation'], 'app-import-current-165'],
    'source plan release count held' => [static fn (): mixed => $held165()['source_next_plan']['release_count'], 0],
    'source plan release count first' => [static fn (): mixed => $firstRelease165()['source_next_plan']['release_count'], 1],
    'source plan release count all' => [static fn (): mixed => $allRelease165()['source_next_plan']['release_count'], 2],
    'current source steps count' => [static fn (): mixed => $held165()['source_next_plan']['current_source_steps'], 4],
    'staged source steps count' => [static fn (): mixed => $held165()['source_next_plan']['staged_source_steps'], 4],
    'visible steps held current only' => [static fn (): mixed => $held165()['source_next_plan']['visible_steps'], 4],
    'held steps held both staged' => [static fn (): mixed => $held165()['source_next_plan']['held_steps'], 4],
    'visible steps first release' => [static fn (): mixed => $firstRelease165()['source_next_plan']['visible_steps'], 5],
    'held steps first release' => [static fn (): mixed => $firstRelease165()['source_next_plan']['held_steps'], 3],
    'visible steps all release' => [static fn (): mixed => $allRelease165()['source_next_plan']['visible_steps'], 8],
    'held steps all release' => [static fn (): mixed => $allRelease165()['source_next_plan']['held_steps'], 0],
    'current drained before staged held' => [static fn (): mixed => $held165()['source_next_plan']['current_drained_before_staged'], true],
    'current drained before staged all release' => [static fn (): mixed => $allRelease165()['source_next_plan']['current_drained_before_staged'], true],
    'first next invisible while held' => [static fn (): mixed => $held165()['source_next_plan']['first_next_visible'], false],
    'second next invisible while held' => [static fn (): mixed => $held165()['source_next_plan']['second_next_visible'], false],
    'first next visible after first release' => [static fn (): mixed => $firstRelease165()['source_next_plan']['first_next_visible'], true],
    'second next still hidden after first release' => [static fn (): mixed => $firstRelease165()['source_next_plan']['second_next_visible'], false],
    'both next visible after all release' => [static fn (): mixed => [$allRelease165()['source_next_plan']['first_next_visible'], $allRelease165()['source_next_plan']['second_next_visible']], [true, true]],
    'current cursor phases first four' => [static fn (): mixed => array_column(array_slice($held165()['cursor_steps'], 0, 4), 'phase'), ['current', 'current', 'current', 'current']],
    'staged cursor phases tail' => [static fn (): mixed => array_column(array_slice($held165()['cursor_steps'], 4), 'phase'), ['first-next', 'second-next', 'second-next', 'second-next']],
    'current cursor generations first four' => [static fn (): mixed => array_values(array_unique(array_column(array_slice($held165()['cursor_steps'], 0, 4), 'generation'))), ['app-import-current-165']],
    'held staged generations tail' => [static fn (): mixed => array_values(array_unique(array_column(array_slice($held165()['held_cursor_steps'], 0), 'generation'))), ['app-import-next-165-a', 'app-import-next-165-b']],
    'held visible keys current generation only' => [static fn (): mixed => array_values(array_unique(array_map(static fn (string $key): string => explode(':', $key, 2)[0], $held165()['source_next_plan']['visible_keys']))), ['app-import-current-165']],
    'held held keys staged generations' => [static fn (): mixed => array_values(array_unique(array_map(static fn (string $key): string => explode(':', $key, 2)[0], $held165()['source_next_plan']['held_keys']))), ['app-import-next-165-a', 'app-import-next-165-b']],
    'first release visible generations' => [static fn (): mixed => array_values(array_unique(array_map(static fn (string $key): string => explode(':', $key, 2)[0], $firstRelease165()['source_next_plan']['visible_keys']))), ['app-import-current-165', 'app-import-next-165-a']],
    'first release held generation second only' => [static fn (): mixed => array_values(array_unique(array_map(static fn (string $key): string => explode(':', $key, 2)[0], $firstRelease165()['source_next_plan']['held_keys']))), ['app-import-next-165-b']],
    'all release visible generations' => [static fn (): mixed => array_values(array_unique(array_map(static fn (string $key): string => explode(':', $key, 2)[0], $allRelease165()['source_next_plan']['visible_keys']))), ['app-import-current-165', 'app-import-next-165-a', 'app-import-next-165-b']],
    'all release held keys empty' => [static fn (): mixed => $allRelease165()['source_next_plan']['held_keys'], []],
    'returning current visible names' => [static fn (): mixed => array_map(static fn (string $key): string => substr($key, strrpos($key, ':') + 1), $held165()['returning_visibility']['current_visible']), ['theme_root', 'module_root', 'theme_child', 'module_child']],
    'returning first next names' => [static fn (): mixed => array_map(static fn (string $key): string => substr($key, strrpos($key, ':') + 1), $held165()['returning_visibility']['first_next']), ['theme_child']],
    'returning second next names' => [static fn (): mixed => array_map(static fn (string $key): string => substr($key, strrpos($key, ':') + 1), $held165()['returning_visibility']['second_next']), ['module_child', 'group_root', 'group_child']],
    'held statement rows' => [static fn (): mixed => $held165()['statement_rows'], 4],
    'held attempted statement rows' => [static fn (): mixed => $held165()['attempted_statement_rows'], 8],
    'first release statement rows' => [static fn (): mixed => $firstRelease165()['statement_rows'], 5],
    'all release statement rows' => [static fn (): mixed => $allRelease165()['statement_rows'], 8],
    'held changes hidden' => [static fn (): mixed => $held165()['changes'], 0],
    'first release changes include first next' => [static fn (): mixed => $firstRelease165()['changes'], 5],
    'all release changes include all visible' => [static fn (): mixed => $allRelease165()['changes'], 8],
    'held after savepoint base rows' => [static fn (): mixed => array_column($held165()['after_savepoint'], 'key_name'), array_column($rows165, 'key_name')],
    'first release after savepoint audit tail' => [static fn (): mixed => array_slice(array_column($firstRelease165()['after_savepoint'], 'key_name'), -1), ['audit:next:theme_root:theme_child']],
    'all release after savepoint audit tail' => [static fn (): mixed => array_slice(array_column($allRelease165()['after_savepoint'], 'key_name'), -3), ['audit:next:module_root:module_child', 'audit:next:module_root:group_root', 'audit:next:module_root:group_child']],
    'held yield boundary' => [static fn (): mixed => $held165()['yield_boundary'], 'recursive-view-returning-current-source-next-cursor-held-next165'],
    'first release yield boundary' => [static fn (): mixed => $firstRelease165()['yield_boundary'], 'recursive-view-returning-current-source-next-cursor-first-release-next165'],
    'all release yield boundary' => [static fn (): mixed => $allRelease165()['yield_boundary'], 'recursive-view-returning-current-source-next-cursor-all-release-next165'],
    'dependency marker next165' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next165', $held165()['dependencies'], true), true],
    'dependency marker cursor drain' => [static fn (): mixed => in_array('sqlite-returning-current-source-next-cursor-drain', $held165()['dependencies'], true), true],
    'dependency marker staged visibility' => [static fn (): mixed => in_array('sqlite-recursive-view-returning-staged-source-visibility', $held165()['dependencies'], true), true],
    'dependency closure marker' => [static fn (): mixed => $held165()['dependency_closure'], 'reuses-native-recursive-view-returning-current-source-queue-and-cursor-model'],
    'limited current rows' => [static fn (): mixed => array_column($limited165()['queue']['first_stage']['current_recursive_rows'], 'key_name'), ['theme_root', 'module_root']],
    'limited staged source steps' => [static fn (): mixed => $limited165()['source_next_plan']['staged_source_steps'], 2],
    'empty second next held steps omit second queue' => [static fn (): mixed => $emptySecond165()['source_next_plan']['held_steps'], 1],
    'custom cursor accepted' => [static fn (): mixed => $run165(['cursor_name' => 'custom.cursor@165'])['cursor'], 'custom.cursor@165'],
    'custom current generation accepted' => [static fn (): mixed => $run165(['current_generation' => 'custom.current@165'])['queue']['current_generation'], 'custom.current@165'],
    'custom first generation accepted' => [static fn (): mixed => $run165(['first_next_generation' => 'custom.next.a@165'])['queue']['staged_generations'][0], 'custom.next.a@165'],
    'custom second generation accepted' => [static fn (): mixed => $run165(['second_next_generation' => 'custom.next.b@165'])['queue']['staged_generations'][1], 'custom.next.b@165'],
    'bad cursor rejected' => [static fn (): mixed => $run165(['cursor_name' => 'bad cursor']), InvalidArgumentException::class],
    'bad release count rejected' => [static fn (): mixed => $run165(['release_staged_sources' => 4]), InvalidArgumentException::class],
    'bad savepoint rejected' => [static fn (): mixed => $run165(['savepoint' => 'bad savepoint']), InvalidArgumentException::class],
    'bad max depth rejected' => [static fn (): mixed => $run165(['max_depth' => 0]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases165 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next165 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
