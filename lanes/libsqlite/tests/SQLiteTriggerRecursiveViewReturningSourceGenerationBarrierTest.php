<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$sourceGenerationRows = [
    ['key_name' => 'base_url', 'key_value' => 'https://example.test', 'load_policy' => 'yes', 'parent_name' => null, 'priority' => 0],
    ['key_name' => 'module_alpha', 'key_value' => 'enabled', 'load_policy' => 'yes', 'parent_name' => 'base_url', 'priority' => 10],
    ['key_name' => 'module_alpha_child', 'key_value' => 'child-on', 'load_policy' => 'no', 'parent_name' => 'module_alpha', 'priority' => 20],
    ['key_name' => 'module_beta', 'key_value' => 'disabled', 'load_policy' => 'yes', 'parent_name' => 'base_url', 'priority' => 15],
    ['key_name' => 'module_beta_child', 'key_value' => 'queued', 'load_policy' => 'no', 'parent_name' => 'module_beta', 'priority' => 30],
    ['key_name' => 'module_next_only', 'key_value' => 'next-only', 'load_policy' => 'yes', 'parent_name' => 'module_beta_child', 'priority' => 40],
];

$sourceGenerationCurrentView = [
    'name' => 'app_recursive_load_policy_view',
    'source' => 'main@view-cookie-source-generation-current',
    'trigger' => 'app_recursive_load_policy_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-source-generation-current',
    'root_key' => 'root_name',
    'parent_key' => 'parent_name',
    'columns' => ['key_name', 'key_value', 'load_policy', 'parent_name', 'priority'],
    'where' => static fn (array $row, string $root, int $depth): bool => $depth <= 2 && str_starts_with((string) $row['key_name'], 'module_'),
    'order_by' => 'priority',
];
$sourceGenerationNextView = [
    'name' => 'app_recursive_load_policy_view',
    'source' => 'main@view-cookie-source-generation-next',
    'trigger' => 'app_recursive_load_policy_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-source-generation-next',
    'root_key' => 'root_name',
    'parent_key' => 'parent_name',
    'columns' => ['key_name', 'key_value', 'load_policy', 'parent_name', 'priority'],
    'where' => static fn (array $row, string $root, int $depth): bool => $depth <= 3 && str_starts_with((string) $row['key_name'], 'module_'),
    'order_by' => 'priority',
];
$sourceGenerationReturning = [
    'key_name',
    ['expr' => 'key_value', 'as' => 'value'],
    ['expr' => 'root', 'as' => 'root_name'],
    ['expr' => 'depth', 'as' => 'depth'],
    ['expr' => 'trigger_source', 'as' => 'trigger_cookie'],
    static fn (array $incoming, array $viewRow, string $triggerSource, int $ordinal): string => $triggerSource . ':' . $viewRow['_root'] . ':' . $ordinal . ':' . $incoming['key_name'],
];

$runSourceGeneration = static fn (array $options = [], ?array $currentRoots = null, ?array $nextRoots = null): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeSourceGenerationBarrier(
    $sourceGenerationRows,
    $currentRoots ?? [['root_name' => 'base_url']],
    $nextRoots ?? [['root_name' => 'module_beta']],
    $sourceGenerationCurrentView,
    $sourceGenerationNextView,
    $sourceGenerationReturning,
    $options + [
        'savepoint' => 'app_recursive_view_source-generation',
        'current_generation' => 'app-import-current-source-generation',
        'next_generation' => 'app-import-next-source-generation',
    ],
);

$barrierSourceGeneration = static fn (): array => $runSourceGeneration();
$releaseSourceGeneration = static fn (): array => $runSourceGeneration(['release_next_source' => true]);
$limitedSourceGeneration = static fn (): array => $runSourceGeneration(['max_depth' => 1]);

$sourceGenerationCases = [
    'barrier status' => [static fn (): mixed => $barrierSourceGeneration()['status'], 'trigger-recursive-view-returning-current-source-barrier-source-generation'],
    'savepoint retained' => [static fn (): mixed => $barrierSourceGeneration()['savepoint'], 'app_recursive_view_source-generation'],
    'current generation retained' => [static fn (): mixed => $barrierSourceGeneration()['current_generation'], 'app-import-current-source-generation'],
    'next generation retained' => [static fn (): mixed => $barrierSourceGeneration()['next_generation'], 'app-import-next-source-generation'],
    'current source retained' => [static fn (): mixed => $barrierSourceGeneration()['source_barrier']['current_source'], 'main@view-cookie-source-generation-current'],
    'next source retained' => [static fn (): mixed => $barrierSourceGeneration()['source_barrier']['next_source'], 'main@view-cookie-source-generation-next'],
    'visible before release current' => [static fn (): mixed => $barrierSourceGeneration()['source_barrier']['visible_source_before_release'], 'main@view-cookie-source-generation-current'],
    'visible after barrier remains current' => [static fn (): mixed => $barrierSourceGeneration()['source_barrier']['visible_source_after_release'], 'main@view-cookie-source-generation-current'],
    'release flag false' => [static fn (): mixed => $barrierSourceGeneration()['source_barrier']['released'], false],
    'release required' => [static fn (): mixed => $barrierSourceGeneration()['source_barrier']['release_required_for_next_source'], true],
    'current returning drained count' => [static fn (): mixed => $barrierSourceGeneration()['source_barrier']['current_returning_drained'], 4],
    'next returning attempted count' => [static fn (): mixed => $barrierSourceGeneration()['source_barrier']['next_returning_attempted'], 2],
    'next returning visible count barrier' => [static fn (): mixed => $barrierSourceGeneration()['source_barrier']['next_returning_visible'], 0],
    'current recursive names' => [static fn (): mixed => array_column($barrierSourceGeneration()['current_recursive_rows'], 'key_name'), ['module_alpha', 'module_beta', 'module_alpha_child', 'module_beta_child']],
    'current recursive depths' => [static fn (): mixed => array_column($barrierSourceGeneration()['current_recursive_rows'], '_depth'), [1, 1, 2, 2]],
    'attempted next recursive names' => [static fn (): mixed => array_column($barrierSourceGeneration()['attempted_next_recursive_rows'], 'key_name'), ['module_beta_child', 'module_next_only']],
    'attempted next recursive depths' => [static fn (): mixed => array_column($barrierSourceGeneration()['attempted_next_recursive_rows'], '_depth'), [1, 2]],
    'next recursive suppressed at barrier' => [static fn (): mixed => $barrierSourceGeneration()['next_recursive_rows'], []],
    'current returning visibility keys' => [static fn (): mixed => array_column($barrierSourceGeneration()['current_returning_rows'], 'visibility_key'), [
        'app-import-current-source-generation:audit:current:base_url:module_alpha',
        'app-import-current-source-generation:audit:current:base_url:module_beta',
        'app-import-current-source-generation:audit:current:base_url:module_alpha_child',
        'app-import-current-source-generation:audit:current:base_url:module_beta_child',
    ]],
    'attempted next visibility keys' => [static fn (): mixed => array_column($barrierSourceGeneration()['attempted_next_returning_rows'], 'visibility_key'), [
        'app-import-next-source-generation:audit:next:module_beta:module_beta_child',
        'app-import-next-source-generation:audit:next:module_beta:module_next_only',
    ]],
    'visible returning current only' => [static fn (): mixed => $barrierSourceGeneration()['returning_visibility']['visible'], [
        'app-import-current-source-generation:audit:current:base_url:module_alpha',
        'app-import-current-source-generation:audit:current:base_url:module_beta',
        'app-import-current-source-generation:audit:current:base_url:module_alpha_child',
        'app-import-current-source-generation:audit:current:base_url:module_beta_child',
    ]],
    'suppressed returning next only' => [static fn (): mixed => $barrierSourceGeneration()['returning_visibility']['suppressed'], [
        'app-import-next-source-generation:audit:next:module_beta:module_beta_child',
        'app-import-next-source-generation:audit:next:module_beta:module_next_only',
    ]],
    'current rows include audit rows' => [static fn (): mixed => array_slice(array_column($barrierSourceGeneration()['current_rows'], 'key_name'), -4), [
        'audit:current:base_url:module_alpha',
        'audit:current:base_url:module_beta',
        'audit:current:base_url:module_alpha_child',
        'audit:current:base_url:module_beta_child',
    ]],
    'after savepoint restores base while held' => [static fn (): mixed => array_column($barrierSourceGeneration()['after_savepoint'], 'key_name'), array_column($sourceGenerationRows, 'key_name')],
    'changes hidden while held' => [static fn (): mixed => $barrierSourceGeneration()['changes'], 0],
    'statement rows current only while held' => [static fn (): mixed => $barrierSourceGeneration()['statement_rows'], 4],
    'attempted statement rows include next' => [static fn (): mixed => $barrierSourceGeneration()['attempted_statement_rows'], 6],
    'yield boundary barrier' => [static fn (): mixed => $barrierSourceGeneration()['yield_boundary'], 'current-source-returning-drained-next-source-held-at-barrier-source-generation'],
    'dependency marker source-generation' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-source-generation', $barrierSourceGeneration()['dependencies'], true), true],
    'dependency marker generation barrier' => [static fn (): mixed => in_array('sqlite-returning-source-generation-barrier', $barrierSourceGeneration()['dependencies'], true), true],
    'dependency marker release required' => [static fn (): mixed => in_array('sqlite-next-source-release-required-after-current-returning', $barrierSourceGeneration()['dependencies'], true), true],

    'release status' => [static fn (): mixed => $releaseSourceGeneration()['status'], 'trigger-recursive-view-returning-current-source-release-source-generation'],
    'release flag true' => [static fn (): mixed => $releaseSourceGeneration()['source_barrier']['released'], true],
    'visible after release next' => [static fn (): mixed => $releaseSourceGeneration()['source_barrier']['visible_source_after_release'], 'main@view-cookie-source-generation-next'],
    'next returning visible release' => [static fn (): mixed => $releaseSourceGeneration()['source_barrier']['next_returning_visible'], 2],
    'release next returning rows visible' => [static fn (): mixed => array_column($releaseSourceGeneration()['next_returning_rows'], 'visibility'), ['next-returning-released', 'next-returning-released']],
    'release next rows include audit tail' => [static fn (): mixed => array_slice(array_column($releaseSourceGeneration()['after_savepoint'], 'key_name'), -2), ['audit:next:module_beta:module_beta_child', 'audit:next:module_beta:module_next_only']],
    'release changes include both generations' => [static fn (): mixed => $releaseSourceGeneration()['changes'], 6],
    'release statement rows include both generations' => [static fn (): mixed => $releaseSourceGeneration()['statement_rows'], 6],
    'release visible contains both generations' => [static fn (): mixed => $releaseSourceGeneration()['returning_visibility']['visible'], [
        'app-import-current-source-generation:audit:current:base_url:module_alpha',
        'app-import-current-source-generation:audit:current:base_url:module_beta',
        'app-import-current-source-generation:audit:current:base_url:module_alpha_child',
        'app-import-current-source-generation:audit:current:base_url:module_beta_child',
        'app-import-next-source-generation:audit:next:module_beta:module_beta_child',
        'app-import-next-source-generation:audit:next:module_beta:module_next_only',
    ]],
    'release suppressed empty' => [static fn (): mixed => $releaseSourceGeneration()['returning_visibility']['suppressed'], []],
    'release yield boundary' => [static fn (): mixed => $releaseSourceGeneration()['yield_boundary'], 'current-source-returning-drained-release-admits-next-source-source-generation'],

    'limited current names' => [static fn (): mixed => array_column($limitedSourceGeneration()['current_recursive_rows'], 'key_name'), ['module_alpha', 'module_beta']],
    'limited next attempted names' => [static fn (): mixed => array_column($limitedSourceGeneration()['attempted_next_recursive_rows'], 'key_name'), ['module_beta_child']],
    'custom generations accepted' => [static fn (): mixed => $runSourceGeneration(['current_generation' => 'custom.current@source-generation', 'next_generation' => 'custom.next@source-generation'])['source_barrier']['next_generation'], 'custom.next@source-generation'],
    'bad current generation rejected' => [static fn (): mixed => $runSourceGeneration(['current_generation' => 'bad generation']), InvalidArgumentException::class],
    'bad next generation rejected' => [static fn (): mixed => $runSourceGeneration(['next_generation' => 'bad generation']), InvalidArgumentException::class],
    'bad savepoint rejected through executor' => [static fn (): mixed => $runSourceGeneration(['savepoint' => 'bad savepoint']), InvalidArgumentException::class],
    'bad max depth rejected through executor' => [static fn (): mixed => $runSourceGeneration(['max_depth' => 0]), InvalidArgumentException::class],
    'missing current root rejected' => [static fn (): mixed => $runSourceGeneration([], [['missing' => 'base_url']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($sourceGenerationCases as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source source-generation ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
