<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows162 = [
    ['option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'parent_name' => null, 'priority' => 0],
    ['option_name' => 'theme_root', 'option_value' => 'theme', 'autoload' => 'yes', 'parent_name' => 'siteurl', 'priority' => 10],
    ['option_name' => 'theme_child', 'option_value' => 'theme-child', 'autoload' => 'no', 'parent_name' => 'theme_root', 'priority' => 20],
    ['option_name' => 'plugin_root', 'option_value' => 'plugin', 'autoload' => 'yes', 'parent_name' => 'siteurl', 'priority' => 15],
    ['option_name' => 'plugin_child', 'option_value' => 'plugin-child', 'autoload' => 'no', 'parent_name' => 'plugin_root', 'priority' => 30],
    ['option_name' => 'network_root', 'option_value' => 'network', 'autoload' => 'yes', 'parent_name' => 'plugin_child', 'priority' => 40],
    ['option_name' => 'network_child', 'option_value' => 'network-child', 'autoload' => 'no', 'parent_name' => 'network_root', 'priority' => 50],
];

$currentView162 = [
    'name' => 'wp_recursive_autoload_view',
    'source' => 'main@view-cookie-162-current',
    'trigger' => 'wp_recursive_autoload_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-162-current',
    'root_key' => 'root_name',
    'parent_key' => 'parent_name',
    'columns' => ['option_name', 'option_value', 'autoload', 'parent_name', 'priority'],
    'where' => static fn (array $row, string $root, int $depth): bool => $depth <= 2 && !str_starts_with((string) $row['option_name'], 'network_'),
    'order_by' => 'priority',
];
$nextView162 = $currentView162;
$nextView162['source'] = 'main@view-cookie-162-next';
$nextView162['trigger_source'] = 'main@trigger-cookie-162-next';
$nextView162['where'] = static fn (array $row, string $root, int $depth): bool => $depth <= 3 && str_contains((string) $row['option_name'], '_');

$returning162 = [
    'option_name',
    ['expr' => 'root', 'as' => 'root_name'],
    ['expr' => 'depth', 'as' => 'depth'],
    ['expr' => 'trigger_source', 'as' => 'trigger_cookie'],
    static fn (array $incoming, array $viewRow, string $triggerSource, int $ordinal): string => $triggerSource . ':' . $viewRow['_root'] . ':' . $ordinal . ':' . $incoming['option_name'],
];

$run162 = static fn (array $options = [], ?array $firstRoots = null, ?array $secondRoots = null): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext162(
    $rows162,
    [['root_name' => 'siteurl']],
    $firstRoots ?? [['root_name' => 'theme_root']],
    $secondRoots ?? [['root_name' => 'plugin_root']],
    $currentView162,
    $nextView162,
    $returning162,
    $options + [
        'savepoint' => 'wp_recursive_view_next162',
        'current_generation' => 'wp-import-current-162',
        'first_next_generation' => 'wp-import-next-162-a',
        'second_next_generation' => 'wp-import-next-162-b',
    ],
);

$held162 = static fn (): array => $run162();
$firstRelease162 = static fn (): array => $run162(['release_staged_sources' => 1]);
$allRelease162 = static fn (): array => $run162(['release_staged_sources' => 2]);
$limited162 = static fn (): array => $run162(['max_depth' => 1]);
$emptySecond162 = static fn (): array => $run162([], null, [['root_name' => 'network_child']]);

$cases162 = [
    'held status' => [static fn (): mixed => $held162()['status'], 'trigger-recursive-view-returning-current-source-queue-held-next162'],
    'savepoint retained' => [static fn (): mixed => $held162()['savepoint'], 'wp_recursive_view_next162'],
    'current generation retained' => [static fn (): mixed => $held162()['current_generation'], 'wp-import-current-162'],
    'staged generations retained' => [static fn (): mixed => $held162()['staged_generations'], ['wp-import-next-162-a', 'wp-import-next-162-b']],
    'visible generation remains current while held' => [static fn (): mixed => $held162()['visible_generation'], 'wp-import-current-162'],
    'current source retained' => [static fn (): mixed => $held162()['current_source'], 'main@view-cookie-162-current'],
    'next source recorded' => [static fn (): mixed => $held162()['next_source'], 'main@view-cookie-162-next'],
    'first queue generation' => [static fn (): mixed => $held162()['next_source_queue'][0]['generation'], 'wp-import-next-162-a'],
    'second queue generation' => [static fn (): mixed => $held162()['next_source_queue'][1]['generation'], 'wp-import-next-162-b'],
    'first queue roots retained' => [static fn (): mixed => $held162()['next_source_queue'][0]['roots'], [['root_name' => 'theme_root']]],
    'second queue roots retained' => [static fn (): mixed => $held162()['next_source_queue'][1]['roots'], [['root_name' => 'plugin_root']]],
    'first queue invisible held' => [static fn (): mixed => $held162()['next_source_queue'][0]['visible'], false],
    'second queue invisible held' => [static fn (): mixed => $held162()['next_source_queue'][1]['visible'], false],
    'current first stage rows' => [static fn (): mixed => array_column($held162()['first_stage']['current_recursive_rows'], 'option_name'), ['theme_root', 'plugin_root', 'theme_child', 'plugin_child']],
    'current first stage depths' => [static fn (): mixed => array_column($held162()['first_stage']['current_recursive_rows'], '_depth'), [1, 1, 2, 2]],
    'first next attempted rows' => [static fn (): mixed => array_column($held162()['first_stage']['attempted_next_recursive_rows'], 'option_name'), ['theme_child']],
    'second next attempted rows' => [static fn (): mixed => array_column($held162()['second_stage']['attempted_next_recursive_rows'], 'option_name'), ['plugin_child', 'network_root', 'network_child']],
    'first attempted count' => [static fn (): mixed => $held162()['next_source_queue'][0]['attempted_returning'], 1],
    'second attempted count' => [static fn (): mixed => $held162()['next_source_queue'][1]['attempted_returning'], 3],
    'visible count held current only' => [static fn (): mixed => $held162()['returning_visibility']['visible_count'], 4],
    'suppressed count held both nexts' => [static fn (): mixed => $held162()['returning_visibility']['suppressed_count'], 4],
    'statement rows held current only' => [static fn (): mixed => $held162()['statement_rows'], 4],
    'attempted rows include queued nexts' => [static fn (): mixed => $held162()['attempted_statement_rows'], 8],
    'changes hidden while held' => [static fn (): mixed => $held162()['changes'], 0],
    'after savepoint restores base held' => [static fn (): mixed => array_column($held162()['after_savepoint'], 'option_name'), array_column($rows162, 'option_name')],
    'held visible keys current generation only' => [static fn (): mixed => array_values(array_unique(array_map(static fn (string $key): string => explode(':', $key, 2)[0], $held162()['returning_visibility']['visible']))), ['wp-import-current-162']],
    'held suppressed keys both next generations' => [static fn (): mixed => array_values(array_unique(array_map(static fn (string $key): string => explode(':', $key, 2)[0], $held162()['returning_visibility']['suppressed']))), ['wp-import-next-162-a', 'wp-import-next-162-b']],
    'held boundary' => [static fn (): mixed => $held162()['yield_boundary'], 'recursive-view-returning-current-source-two-next-yields-held-next162'],
    'dependency marker next162' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next162', $held162()['dependencies'], true), true],
    'dependency marker fifo' => [static fn (): mixed => in_array('sqlite-recursive-view-returning-next-source-fifo-queue', $held162()['dependencies'], true), true],
    'dependency closure marker' => [static fn (): mixed => $held162()['dependency_closure'], 'reuses-native-recursive-view-returning-current-source-barriers'],

    'first release status' => [static fn (): mixed => $firstRelease162()['status'], 'trigger-recursive-view-returning-current-source-first-next-released-next162'],
    'first release visible generation' => [static fn (): mixed => $firstRelease162()['visible_generation'], 'wp-import-next-162-a'],
    'first release first queue visible' => [static fn (): mixed => $firstRelease162()['next_source_queue'][0]['visible'], true],
    'first release second queue held' => [static fn (): mixed => $firstRelease162()['next_source_queue'][1]['visible'], false],
    'first release visible count' => [static fn (): mixed => $firstRelease162()['returning_visibility']['visible_count'], 5],
    'first release suppressed count' => [static fn (): mixed => $firstRelease162()['returning_visibility']['suppressed_count'], 3],
    'first release changes include first stage' => [static fn (): mixed => $firstRelease162()['changes'], 5],
    'first release after savepoint includes first audit' => [static fn (): mixed => array_slice(array_column($firstRelease162()['after_savepoint'], 'option_name'), -1), ['audit:next:theme_root:theme_child']],
    'first release boundary' => [static fn (): mixed => $firstRelease162()['yield_boundary'], 'recursive-view-returning-current-source-first-next-yield-released-next162'],

    'all release status' => [static fn (): mixed => $allRelease162()['status'], 'trigger-recursive-view-returning-current-source-all-next-released-next162'],
    'all release visible generation' => [static fn (): mixed => $allRelease162()['visible_generation'], 'wp-import-next-162-b'],
    'all release queues visible' => [static fn (): mixed => array_column($allRelease162()['next_source_queue'], 'visible'), [true, true]],
    'all release visible count' => [static fn (): mixed => $allRelease162()['returning_visibility']['visible_count'], 8],
    'all release suppressed count' => [static fn (): mixed => $allRelease162()['returning_visibility']['suppressed_count'], 0],
    'all release statement rows' => [static fn (): mixed => $allRelease162()['statement_rows'], 8],
    'all release changes include released nexts' => [static fn (): mixed => $allRelease162()['changes'], 8],
    'all release after savepoint audit tail' => [static fn (): mixed => array_slice(array_column($allRelease162()['after_savepoint'], 'option_name'), -3), ['audit:next:plugin_root:plugin_child', 'audit:next:plugin_root:network_root', 'audit:next:plugin_root:network_child']],
    'all release visible generations in order' => [static fn (): mixed => array_values(array_unique(array_map(static fn (string $key): string => explode(':', $key, 2)[0], $allRelease162()['returning_visibility']['visible']))), ['wp-import-current-162', 'wp-import-next-162-a', 'wp-import-next-162-b']],
    'all release boundary' => [static fn (): mixed => $allRelease162()['yield_boundary'], 'recursive-view-returning-current-source-all-next-yields-released-next162'],

    'limited current rows' => [static fn (): mixed => array_column($limited162()['first_stage']['current_recursive_rows'], 'option_name'), ['theme_root', 'plugin_root']],
    'limited second attempted rows' => [static fn (): mixed => array_column($limited162()['second_stage']['attempted_next_recursive_rows'], 'option_name'), ['plugin_child']],
    'empty second next has zero attempted' => [static fn (): mixed => $emptySecond162()['next_source_queue'][1]['attempted_returning'], 0],
    'custom first generation accepted' => [static fn (): mixed => $run162(['first_next_generation' => 'custom.next.a@162'])['staged_generations'][0], 'custom.next.a@162'],
    'custom second generation accepted' => [static fn (): mixed => $run162(['second_next_generation' => 'custom.next.b@162'])['staged_generations'][1], 'custom.next.b@162'],
    'bad release count negative rejected' => [static fn (): mixed => $run162(['release_staged_sources' => -1]), InvalidArgumentException::class],
    'bad release count too large rejected' => [static fn (): mixed => $run162(['release_staged_sources' => 3]), InvalidArgumentException::class],
    'bad current generation rejected' => [static fn (): mixed => $run162(['current_generation' => 'bad generation']), InvalidArgumentException::class],
    'bad first next generation rejected' => [static fn (): mixed => $run162(['first_next_generation' => 'bad generation']), InvalidArgumentException::class],
    'bad second next generation rejected' => [static fn (): mixed => $run162(['second_next_generation' => 'bad generation']), InvalidArgumentException::class],
    'bad savepoint rejected' => [static fn (): mixed => $run162(['savepoint' => 'bad savepoint']), InvalidArgumentException::class],
    'bad max depth rejected through composed executor' => [static fn (): mixed => $run162(['max_depth' => 0]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases162 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next162 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
