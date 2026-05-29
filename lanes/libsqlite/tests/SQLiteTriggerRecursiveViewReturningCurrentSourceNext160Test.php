<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows160 = [
    ['option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'parent_name' => null, 'priority' => 0],
    ['option_name' => 'plugin_alpha', 'option_value' => 'enabled', 'autoload' => 'yes', 'parent_name' => 'siteurl', 'priority' => 10],
    ['option_name' => 'plugin_alpha_child', 'option_value' => 'child-on', 'autoload' => 'no', 'parent_name' => 'plugin_alpha', 'priority' => 20],
    ['option_name' => 'plugin_beta', 'option_value' => 'disabled', 'autoload' => 'yes', 'parent_name' => 'siteurl', 'priority' => 15],
    ['option_name' => 'plugin_beta_child', 'option_value' => 'queued', 'autoload' => 'no', 'parent_name' => 'plugin_beta', 'priority' => 30],
    ['option_name' => 'plugin_next_only', 'option_value' => 'next-only', 'autoload' => 'yes', 'parent_name' => 'plugin_beta_child', 'priority' => 40],
];

$currentView160 = [
    'name' => 'wp_recursive_autoload_view',
    'source' => 'main@view-cookie-160-current',
    'trigger' => 'wp_recursive_autoload_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-160-current',
    'root_key' => 'root_name',
    'parent_key' => 'parent_name',
    'columns' => ['option_name', 'option_value', 'autoload', 'parent_name', 'priority'],
    'where' => static fn (array $row, string $root, int $depth): bool => $depth <= 2 && str_starts_with((string) $row['option_name'], 'plugin_'),
    'order_by' => 'priority',
];
$nextView160 = [
    'name' => 'wp_recursive_autoload_view',
    'source' => 'main@view-cookie-160-next',
    'trigger' => 'wp_recursive_autoload_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-160-next',
    'root_key' => 'root_name',
    'parent_key' => 'parent_name',
    'columns' => ['option_name', 'option_value', 'autoload', 'parent_name', 'priority'],
    'where' => static fn (array $row, string $root, int $depth): bool => $depth <= 3 && str_starts_with((string) $row['option_name'], 'plugin_'),
    'order_by' => 'priority',
];
$returning160 = [
    'option_name',
    ['expr' => 'option_value', 'as' => 'value'],
    ['expr' => 'root', 'as' => 'root_name'],
    ['expr' => 'depth', 'as' => 'depth'],
    ['expr' => 'trigger_source', 'as' => 'trigger_cookie'],
    static fn (array $incoming, array $viewRow, string $triggerSource, int $ordinal): string => $triggerSource . ':' . $viewRow['_root'] . ':' . $ordinal . ':' . $incoming['option_name'],
];

$run160 = static fn (array $options = [], ?array $currentRoots = null, ?array $nextRoots = null): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext160(
    $rows160,
    $currentRoots ?? [['root_name' => 'siteurl']],
    $nextRoots ?? [['root_name' => 'plugin_beta']],
    $currentView160,
    $nextView160,
    $returning160,
    $options + [
        'savepoint' => 'wp_recursive_view_next160',
        'current_generation' => 'wp-import-current-160',
        'next_generation' => 'wp-import-next-160',
    ],
);

$barrier160 = static fn (): array => $run160();
$release160 = static fn (): array => $run160(['release_next_source' => true]);
$limited160 = static fn (): array => $run160(['max_depth' => 1]);

$cases160 = [
    'barrier status' => [static fn (): mixed => $barrier160()['status'], 'trigger-recursive-view-returning-current-source-barrier-next160'],
    'savepoint retained' => [static fn (): mixed => $barrier160()['savepoint'], 'wp_recursive_view_next160'],
    'current generation retained' => [static fn (): mixed => $barrier160()['current_generation'], 'wp-import-current-160'],
    'next generation retained' => [static fn (): mixed => $barrier160()['next_generation'], 'wp-import-next-160'],
    'current source retained' => [static fn (): mixed => $barrier160()['source_barrier']['current_source'], 'main@view-cookie-160-current'],
    'next source retained' => [static fn (): mixed => $barrier160()['source_barrier']['next_source'], 'main@view-cookie-160-next'],
    'visible before release current' => [static fn (): mixed => $barrier160()['source_barrier']['visible_source_before_release'], 'main@view-cookie-160-current'],
    'visible after barrier remains current' => [static fn (): mixed => $barrier160()['source_barrier']['visible_source_after_release'], 'main@view-cookie-160-current'],
    'release flag false' => [static fn (): mixed => $barrier160()['source_barrier']['released'], false],
    'release required' => [static fn (): mixed => $barrier160()['source_barrier']['release_required_for_next_source'], true],
    'current returning drained count' => [static fn (): mixed => $barrier160()['source_barrier']['current_returning_drained'], 4],
    'next returning attempted count' => [static fn (): mixed => $barrier160()['source_barrier']['next_returning_attempted'], 2],
    'next returning visible count barrier' => [static fn (): mixed => $barrier160()['source_barrier']['next_returning_visible'], 0],
    'current recursive names' => [static fn (): mixed => array_column($barrier160()['current_recursive_rows'], 'option_name'), ['plugin_alpha', 'plugin_beta', 'plugin_alpha_child', 'plugin_beta_child']],
    'current recursive depths' => [static fn (): mixed => array_column($barrier160()['current_recursive_rows'], '_depth'), [1, 1, 2, 2]],
    'attempted next recursive names' => [static fn (): mixed => array_column($barrier160()['attempted_next_recursive_rows'], 'option_name'), ['plugin_beta_child', 'plugin_next_only']],
    'attempted next recursive depths' => [static fn (): mixed => array_column($barrier160()['attempted_next_recursive_rows'], '_depth'), [1, 2]],
    'next recursive suppressed at barrier' => [static fn (): mixed => $barrier160()['next_recursive_rows'], []],
    'current returning visibility keys' => [static fn (): mixed => array_column($barrier160()['current_returning_rows'], 'visibility_key'), [
        'wp-import-current-160:audit:current:siteurl:plugin_alpha',
        'wp-import-current-160:audit:current:siteurl:plugin_beta',
        'wp-import-current-160:audit:current:siteurl:plugin_alpha_child',
        'wp-import-current-160:audit:current:siteurl:plugin_beta_child',
    ]],
    'attempted next visibility keys' => [static fn (): mixed => array_column($barrier160()['attempted_next_returning_rows'], 'visibility_key'), [
        'wp-import-next-160:audit:next:plugin_beta:plugin_beta_child',
        'wp-import-next-160:audit:next:plugin_beta:plugin_next_only',
    ]],
    'visible returning current only' => [static fn (): mixed => $barrier160()['returning_visibility']['visible'], [
        'wp-import-current-160:audit:current:siteurl:plugin_alpha',
        'wp-import-current-160:audit:current:siteurl:plugin_beta',
        'wp-import-current-160:audit:current:siteurl:plugin_alpha_child',
        'wp-import-current-160:audit:current:siteurl:plugin_beta_child',
    ]],
    'suppressed returning next only' => [static fn (): mixed => $barrier160()['returning_visibility']['suppressed'], [
        'wp-import-next-160:audit:next:plugin_beta:plugin_beta_child',
        'wp-import-next-160:audit:next:plugin_beta:plugin_next_only',
    ]],
    'current rows include audit rows' => [static fn (): mixed => array_slice(array_column($barrier160()['current_rows'], 'option_name'), -4), [
        'audit:current:siteurl:plugin_alpha',
        'audit:current:siteurl:plugin_beta',
        'audit:current:siteurl:plugin_alpha_child',
        'audit:current:siteurl:plugin_beta_child',
    ]],
    'after savepoint restores base while held' => [static fn (): mixed => array_column($barrier160()['after_savepoint'], 'option_name'), array_column($rows160, 'option_name')],
    'changes hidden while held' => [static fn (): mixed => $barrier160()['changes'], 0],
    'statement rows current only while held' => [static fn (): mixed => $barrier160()['statement_rows'], 4],
    'attempted statement rows include next' => [static fn (): mixed => $barrier160()['attempted_statement_rows'], 6],
    'yield boundary barrier' => [static fn (): mixed => $barrier160()['yield_boundary'], 'current-source-returning-drained-next-source-held-at-barrier-next160'],
    'dependency marker next160' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next160', $barrier160()['dependencies'], true), true],
    'dependency marker generation barrier' => [static fn (): mixed => in_array('sqlite-returning-source-generation-barrier', $barrier160()['dependencies'], true), true],
    'dependency marker release required' => [static fn (): mixed => in_array('sqlite-next-source-release-required-after-current-returning', $barrier160()['dependencies'], true), true],

    'release status' => [static fn (): mixed => $release160()['status'], 'trigger-recursive-view-returning-current-source-release-next160'],
    'release flag true' => [static fn (): mixed => $release160()['source_barrier']['released'], true],
    'visible after release next' => [static fn (): mixed => $release160()['source_barrier']['visible_source_after_release'], 'main@view-cookie-160-next'],
    'next returning visible release' => [static fn (): mixed => $release160()['source_barrier']['next_returning_visible'], 2],
    'release next returning rows visible' => [static fn (): mixed => array_column($release160()['next_returning_rows'], 'visibility'), ['next-returning-released', 'next-returning-released']],
    'release next rows include audit tail' => [static fn (): mixed => array_slice(array_column($release160()['after_savepoint'], 'option_name'), -2), ['audit:next:plugin_beta:plugin_beta_child', 'audit:next:plugin_beta:plugin_next_only']],
    'release changes include both generations' => [static fn (): mixed => $release160()['changes'], 6],
    'release statement rows include both generations' => [static fn (): mixed => $release160()['statement_rows'], 6],
    'release visible contains both generations' => [static fn (): mixed => $release160()['returning_visibility']['visible'], [
        'wp-import-current-160:audit:current:siteurl:plugin_alpha',
        'wp-import-current-160:audit:current:siteurl:plugin_beta',
        'wp-import-current-160:audit:current:siteurl:plugin_alpha_child',
        'wp-import-current-160:audit:current:siteurl:plugin_beta_child',
        'wp-import-next-160:audit:next:plugin_beta:plugin_beta_child',
        'wp-import-next-160:audit:next:plugin_beta:plugin_next_only',
    ]],
    'release suppressed empty' => [static fn (): mixed => $release160()['returning_visibility']['suppressed'], []],
    'release yield boundary' => [static fn (): mixed => $release160()['yield_boundary'], 'current-source-returning-drained-release-admits-next-source-next160'],

    'limited current names' => [static fn (): mixed => array_column($limited160()['current_recursive_rows'], 'option_name'), ['plugin_alpha', 'plugin_beta']],
    'limited next attempted names' => [static fn (): mixed => array_column($limited160()['attempted_next_recursive_rows'], 'option_name'), ['plugin_beta_child']],
    'custom generations accepted' => [static fn (): mixed => $run160(['current_generation' => 'custom.current@160', 'next_generation' => 'custom.next@160'])['source_barrier']['next_generation'], 'custom.next@160'],
    'bad current generation rejected' => [static fn (): mixed => $run160(['current_generation' => 'bad generation']), InvalidArgumentException::class],
    'bad next generation rejected' => [static fn (): mixed => $run160(['next_generation' => 'bad generation']), InvalidArgumentException::class],
    'bad savepoint rejected through executor' => [static fn (): mixed => $run160(['savepoint' => 'bad savepoint']), InvalidArgumentException::class],
    'bad max depth rejected through executor' => [static fn (): mixed => $run160(['max_depth' => 0]), InvalidArgumentException::class],
    'missing current root rejected' => [static fn (): mixed => $run160([], [['missing' => 'siteurl']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases160 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next160 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
