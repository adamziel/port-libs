<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows169 = [
    ['option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'parent_name' => null, 'priority' => 0],
    ['option_name' => 'theme_root', 'option_value' => 'theme', 'autoload' => 'yes', 'parent_name' => 'siteurl', 'priority' => 10],
    ['option_name' => 'theme_child', 'option_value' => 'theme-child', 'autoload' => 'no', 'parent_name' => 'theme_root', 'priority' => 20],
    ['option_name' => 'plugin_root', 'option_value' => 'plugin', 'autoload' => 'yes', 'parent_name' => 'siteurl', 'priority' => 15],
    ['option_name' => 'plugin_child', 'option_value' => 'plugin-child', 'autoload' => 'no', 'parent_name' => 'plugin_root', 'priority' => 30],
    ['option_name' => 'network_root', 'option_value' => 'network', 'autoload' => 'yes', 'parent_name' => 'plugin_child', 'priority' => 40],
    ['option_name' => 'network_child', 'option_value' => 'network-child', 'autoload' => 'no', 'parent_name' => 'network_root', 'priority' => 50],
];

$currentView169 = [
    'name' => 'wp_recursive_autoload_view',
    'source' => 'main@view-cookie-169-current',
    'trigger' => 'wp_recursive_autoload_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-169-current',
    'root_key' => 'root_name',
    'parent_key' => 'parent_name',
    'columns' => ['option_name', 'option_value', 'autoload', 'parent_name', 'priority'],
    'where' => static fn (array $row, string $root, int $depth): bool => $depth <= 2 && !str_starts_with((string) $row['option_name'], 'network_'),
    'order_by' => 'priority',
];
$nextView169 = $currentView169;
$nextView169['source'] = 'main@view-cookie-169-next';
$nextView169['trigger_source'] = 'main@trigger-cookie-169-next';
$nextView169['where'] = static fn (array $row, string $root, int $depth): bool => $depth <= 3 && str_contains((string) $row['option_name'], '_');

$returning169 = [
    'option_name',
    ['expr' => 'root', 'as' => 'root_name'],
    ['expr' => 'depth', 'as' => 'depth'],
    ['expr' => 'trigger_source', 'as' => 'trigger_cookie'],
    static fn (array $incoming, array $viewRow, string $triggerSource, int $ordinal): string => $triggerSource . ':' . $viewRow['_root'] . ':' . $ordinal . ':' . $incoming['option_name'],
];

$run169 = static fn (array $options = [], ?array $nestedRoots = null): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNestedCurrentDrainBeforeStagedSources(
    $rows169,
    [['root_name' => 'siteurl']],
    $nestedRoots ?? [['root_name' => 'theme_root']],
    [['root_name' => 'theme_root']],
    [['root_name' => 'plugin_root']],
    $currentView169,
    $nextView169,
    $returning169,
    $options + [
        'savepoint' => 'wp_recursive_view_next169',
        'cursor_name' => 'wp_recursive_view_returning_cursor_169',
        'current_generation' => 'wp-import-current-169',
        'nested_generation' => 'wp-import-current-169-nested',
        'first_next_generation' => 'wp-import-next-169-a',
        'second_next_generation' => 'wp-import-next-169-b',
    ],
);

$held169 = static fn (): array => $run169();
$firstRelease169 = static fn (): array => $run169(['release_staged_sources' => 1]);
$allRelease169 = static fn (): array => $run169(['release_staged_sources' => 2]);
$limited169 = static fn (): array => $run169(['max_depth' => 1]);
$emptyNested169 = static fn (): array => $run169([], [['root_name' => 'network_child']]);

$cases169 = [
    'held status' => [static fn (): mixed => $held169()['status'], 'trigger-recursive-view-returning-current-source-nested-held-next169'],
    'first release status' => [static fn (): mixed => $firstRelease169()['status'], 'trigger-recursive-view-returning-current-source-nested-first-released-next169'],
    'all release status' => [static fn (): mixed => $allRelease169()['status'], 'trigger-recursive-view-returning-current-source-nested-all-released-next169'],
    'savepoint retained' => [static fn (): mixed => $held169()['savepoint'], 'wp_recursive_view_next169'],
    'cursor retained' => [static fn (): mixed => $held169()['cursor'], 'wp_recursive_view_returning_cursor_169'],
    'base savepoint isolated' => [static fn (): mixed => $held169()['base']['savepoint'], 'wp_recursive_view_next169_base'],
    'nested savepoint isolated' => [static fn (): mixed => $held169()['nested']['savepoint'], 'wp_recursive_view_next169_nested'],
    'base cursor isolated' => [static fn (): mixed => $held169()['base']['cursor'], 'wp_recursive_view_returning_cursor_169_base'],
    'nested cursor isolated' => [static fn (): mixed => $held169()['nested']['cursor'], 'wp_recursive_view_returning_cursor_169_nested'],
    'current source steps' => [static fn (): mixed => $held169()['source_next_plan']['current_source_steps'], 4],
    'nested source steps' => [static fn (): mixed => $held169()['source_next_plan']['nested_current_source_steps'], 1],
    'combined current source steps' => [static fn (): mixed => $held169()['source_next_plan']['combined_current_source_steps'], 5],
    'staged source steps' => [static fn (): mixed => $held169()['source_next_plan']['staged_source_steps'], 4],
    'visible steps held include current and nested' => [static fn (): mixed => $held169()['source_next_plan']['visible_steps'], 5],
    'held steps held include staged only' => [static fn (): mixed => $held169()['source_next_plan']['held_steps'], 4],
    'visible steps first release' => [static fn (): mixed => $firstRelease169()['source_next_plan']['visible_steps'], 6],
    'held steps first release' => [static fn (): mixed => $firstRelease169()['source_next_plan']['held_steps'], 3],
    'visible steps all release' => [static fn (): mixed => $allRelease169()['source_next_plan']['visible_steps'], 9],
    'held steps all release' => [static fn (): mixed => $allRelease169()['source_next_plan']['held_steps'], 0],
    'current before nested' => [static fn (): mixed => $held169()['source_next_plan']['current_drained_before_nested'], true],
    'nested before staged' => [static fn (): mixed => $held169()['source_next_plan']['nested_drained_before_staged'], true],
    'current source pinned held' => [static fn (): mixed => $held169()['source_next_plan']['current_source_pinned_until_nested_drains'], true],
    'current source not pinned after first release' => [static fn (): mixed => $firstRelease169()['source_next_plan']['current_source_pinned_until_nested_drains'], false],
    'first next hidden held' => [static fn (): mixed => $held169()['source_next_plan']['first_next_visible'], false],
    'second next hidden held' => [static fn (): mixed => $held169()['source_next_plan']['second_next_visible'], false],
    'first next visible first release' => [static fn (): mixed => $firstRelease169()['source_next_plan']['first_next_visible'], true],
    'second next hidden first release' => [static fn (): mixed => $firstRelease169()['source_next_plan']['second_next_visible'], false],
    'both next visible all release' => [static fn (): mixed => [$allRelease169()['source_next_plan']['first_next_visible'], $allRelease169()['source_next_plan']['second_next_visible']], [true, true]],
    'phase order held' => [static fn (): mixed => array_column($held169()['cursor_steps'], 'phase'), ['current', 'current', 'current', 'current', 'nested-current', 'first-next', 'second-next', 'second-next', 'second-next']],
    'first five visible held' => [static fn (): mixed => array_column($held169()['visible_cursor_steps'], 'phase'), ['current', 'current', 'current', 'current', 'nested-current']],
    'held phases staged only' => [static fn (): mixed => array_values(array_unique(array_column($held169()['held_cursor_steps'], 'phase'))), ['first-next', 'second-next']],
    'statement ordinals stable' => [static fn (): mixed => array_column($held169()['cursor_steps'], 'statement_ordinal'), range(0, 8)],
    'all release has no held cursor steps' => [static fn (): mixed => $allRelease169()['held_cursor_steps'], []],
    'current returning names' => [static fn (): mixed => array_map(static fn (string $key): string => substr($key, strrpos($key, ':') + 1), $held169()['returning_visibility']['current']), ['theme_root', 'plugin_root', 'theme_child', 'plugin_child']],
    'nested returning names' => [static fn (): mixed => array_map(static fn (string $key): string => substr($key, strrpos($key, ':') + 1), $held169()['returning_visibility']['nested_current']), ['theme_child']],
    'first next returning names' => [static fn (): mixed => array_map(static fn (string $key): string => substr($key, strrpos($key, ':') + 1), $held169()['returning_visibility']['first_next']), ['theme_child']],
    'second next returning names' => [static fn (): mixed => array_map(static fn (string $key): string => substr($key, strrpos($key, ':') + 1), $held169()['returning_visibility']['second_next']), ['plugin_child', 'network_root', 'network_child']],
    'held visible keys include nested generation' => [static fn (): mixed => in_array('wp-import-current-169-nested', array_map(static fn (string $key): string => explode(':', $key, 2)[0], $held169()['returning_visibility']['visible']), true), true],
    'held held keys omit current generation' => [static fn (): mixed => array_values(array_unique(array_map(static fn (string $key): string => explode(':', $key, 2)[0], $held169()['returning_visibility']['held']))), ['wp-import-next-169-a', 'wp-import-next-169-b']],
    'first release held generation second only' => [static fn (): mixed => array_values(array_unique(array_map(static fn (string $key): string => explode(':', $key, 2)[0], $firstRelease169()['returning_visibility']['held']))), ['wp-import-next-169-b']],
    'all release visible generations' => [static fn (): mixed => array_values(array_unique(array_map(static fn (string $key): string => explode(':', $key, 2)[0], $allRelease169()['returning_visibility']['visible']))), ['wp-import-current-169', 'wp-import-current-169-nested', 'wp-import-next-169-a', 'wp-import-next-169-b']],
    'held statement rows' => [static fn (): mixed => $held169()['statement_rows'], 5],
    'held attempted statement rows' => [static fn (): mixed => $held169()['attempted_statement_rows'], 9],
    'first release statement rows' => [static fn (): mixed => $firstRelease169()['statement_rows'], 6],
    'all release statement rows' => [static fn (): mixed => $allRelease169()['statement_rows'], 9],
    'held changes current plus nested' => [static fn (): mixed => $held169()['changes'], 5],
    'first release changes visible rows' => [static fn (): mixed => $firstRelease169()['changes'], 6],
    'all release changes visible rows' => [static fn (): mixed => $allRelease169()['changes'], 9],
    'yield boundary held' => [static fn (): mixed => $held169()['yield_boundary'], 'recursive-view-returning-current-source-nested-drain-before-held-next169'],
    'yield boundary first release' => [static fn (): mixed => $firstRelease169()['yield_boundary'], 'recursive-view-returning-current-source-nested-drain-first-release-next169'],
    'yield boundary all release' => [static fn (): mixed => $allRelease169()['yield_boundary'], 'recursive-view-returning-current-source-nested-drain-all-release-next169'],
    'dependency marker next169' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next169', $held169()['dependencies'], true), true],
    'dependency marker reentrant drain' => [static fn (): mixed => in_array('sqlite-returning-current-source-reentrant-drain', $held169()['dependencies'], true), true],
    'dependency marker nested before staged' => [static fn (): mixed => in_array('sqlite-recursive-view-returning-nested-current-before-staged-next', $held169()['dependencies'], true), true],
    'dependency closure marker' => [static fn (): mixed => $held169()['dependency_closure'], 'reuses-native-recursive-view-returning-current-source-cursor-model-for-reentrant-drain'],
    'limited current source count' => [static fn (): mixed => $limited169()['source_next_plan']['combined_current_source_steps'], 3],
    'limited attempted count' => [static fn (): mixed => $limited169()['attempted_statement_rows'], 5],
    'empty nested source steps' => [static fn (): mixed => $emptyNested169()['source_next_plan']['nested_current_source_steps'], 0],
    'empty nested phase order' => [static fn (): mixed => array_column($emptyNested169()['cursor_steps'], 'phase'), ['current', 'current', 'current', 'current', 'first-next', 'second-next', 'second-next', 'second-next']],
    'custom cursor accepted' => [static fn (): mixed => $run169(['cursor_name' => 'custom.cursor@169'])['cursor'], 'custom.cursor@169'],
    'custom nested generation accepted' => [static fn (): mixed => $run169(['nested_generation' => 'custom.current.nested@169'])['nested']['queue']['current_generation'], 'custom.current.nested@169'],
    'bad cursor rejected' => [static fn (): mixed => $run169(['cursor_name' => 'bad cursor']), InvalidArgumentException::class],
    'bad nested generation rejected' => [static fn (): mixed => $run169(['nested_generation' => 'bad generation']), InvalidArgumentException::class],
    'bad release count rejected' => [static fn (): mixed => $run169(['release_staged_sources' => 3]), InvalidArgumentException::class],
    'bad savepoint rejected' => [static fn (): mixed => $run169(['savepoint' => 'bad savepoint']), InvalidArgumentException::class],
    'bad max depth rejected' => [static fn (): mixed => $run169(['max_depth' => 0]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases169 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next169 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
