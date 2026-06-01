<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows174 = [
    ['key_name' => 'base_url', 'key_value' => 'https://example.test', 'load_policy' => 'yes', 'parent_name' => null, 'priority' => 0],
    ['key_name' => 'theme_root', 'key_value' => 'theme', 'load_policy' => 'yes', 'parent_name' => 'base_url', 'priority' => 10],
    ['key_name' => 'theme_child', 'key_value' => 'theme-child', 'load_policy' => 'no', 'parent_name' => 'theme_root', 'priority' => 20],
    ['key_name' => 'module_root', 'key_value' => 'module', 'load_policy' => 'yes', 'parent_name' => 'base_url', 'priority' => 15],
    ['key_name' => 'module_child', 'key_value' => 'module-child', 'load_policy' => 'no', 'parent_name' => 'module_root', 'priority' => 30],
    ['key_name' => 'group_root', 'key_value' => 'group', 'load_policy' => 'yes', 'parent_name' => 'module_child', 'priority' => 40],
    ['key_name' => 'group_child', 'key_value' => 'group-child', 'load_policy' => 'no', 'parent_name' => 'group_root', 'priority' => 50],
];

$currentView174 = [
    'name' => 'app_recursive_load_policy_view',
    'source' => 'main@view-cookie-174-current',
    'trigger' => 'app_recursive_load_policy_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-174-current',
    'root_key' => 'root_name',
    'parent_key' => 'parent_name',
    'columns' => ['key_name', 'key_value', 'load_policy', 'parent_name', 'priority'],
    'where' => static fn (array $row, string $root, int $depth): bool => $depth <= 2 && !str_starts_with((string) $row['key_name'], 'network_'),
    'order_by' => 'priority',
];
$nextView174 = $currentView174;
$nextView174['source'] = 'main@view-cookie-174-next';
$nextView174['trigger_source'] = 'main@trigger-cookie-174-next';
$nextView174['where'] = static fn (array $row, string $root, int $depth): bool => $depth <= 3 && str_contains((string) $row['key_name'], '_');

$returning174 = [
    'key_name',
    ['expr' => 'root', 'as' => 'root_name'],
    ['expr' => 'depth', 'as' => 'depth'],
    ['expr' => 'trigger_source', 'as' => 'trigger_cookie'],
];

$run174 = static fn (array $options = [], ?array $nextView = null): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNextViewSourceHoldFence(
    $rows174,
    [['root_name' => 'base_url']],
    [['root_name' => 'theme_root']],
    [['root_name' => 'module_root']],
    $currentView174,
    $nextView ?? $nextView174,
    $returning174,
    $options + [
        'release_staged_sources' => 2,
        'savepoint' => 'app_recursive_view_next174',
        'cursor_name' => 'app_recursive_view_returning_cursor_174',
        'current_generation' => 'app-import-current-174',
        'first_next_generation' => 'app-import-next-174-a',
        'second_next_generation' => 'app-import-next-174-b',
        'current_schema_cookie' => 174,
        'next_schema_cookie' => 175,
        'reprepare_token' => 'app.reprepare.174',
        'expected_reprepare_token' => 'app.reprepare.174',
    ],
);

$conflict174 = static fn (): array => $run174();
$tokenHeld174 = static fn (): array => $run174(['expected_reprepare_token' => 'app.reprepare.174.expected']);
$noConflict174 = static function () use ($run174, $currentView174): array {
    $sameNext = $currentView174;
    $sameNext['where'] = static fn (array $row, string $root, int $depth): bool => $depth <= 3 && str_contains((string) $row['key_name'], '_');

    return $run174([
        'first_next_generation' => 'app-import-next-174-first',
        'second_next_generation' => 'app-import-next-174-second',
        'current_schema_cookie' => 174,
        'next_schema_cookie' => 174,
    ], $sameNext);
};

$cases174 = [
    'conflict status' => [static fn (): mixed => $conflict174()['status'], 'trigger-recursive-view-returning-current-source-watermark-held-next174'],
    'token held base status retained' => [static fn (): mixed => $tokenHeld174()['base']['status'], 'trigger-recursive-view-returning-current-source-reprepare-held-next170'],
    'same source duplicate status held' => [static fn (): mixed => $noConflict174()['status'], 'trigger-recursive-view-returning-current-source-watermark-held-next174'],
    'savepoint retained' => [static fn (): mixed => $conflict174()['savepoint'], 'app_recursive_view_next174'],
    'cursor retained' => [static fn (): mixed => $conflict174()['cursor'], 'app_recursive_view_returning_cursor_174'],
    'separator retained' => [static fn (): mixed => $conflict174()['conflict_key_separator'], ':'],
    'base source changed' => [static fn (): mixed => $conflict174()['base']['source_changed'], true],
    'base release allowed' => [static fn (): mixed => $conflict174()['base']['release_allowed'], true],
    'base token matches' => [static fn (): mixed => $conflict174()['base']['reprepare_token_matches'], true],
    'base current drained' => [static fn (): mixed => $conflict174()['base']['current_drained_before_next'], true],
    'attempted row count' => [static fn (): mixed => $conflict174()['attempted_statement_rows'], 8],
    'current row count' => [static fn (): mixed => $conflict174()['current_statement_rows'], 4],
    'staged row count' => [static fn (): mixed => $conflict174()['staged_statement_rows'], 4],
    'visible row count conflict' => [static fn (): mixed => $conflict174()['statement_rows'], 6],
    'held row count conflict' => [static fn (): mixed => count($conflict174()['held_watermark_steps']), 2],
    'visible row count token held' => [static fn (): mixed => $tokenHeld174()['statement_rows'], 4],
    'held row count token held' => [static fn (): mixed => count($tokenHeld174()['held_watermark_steps']), 4],
    'visible row count no conflict' => [static fn (): mixed => $noConflict174()['statement_rows'], 6],
    'conflict keys' => [static fn (): mixed => $conflict174()['conflict_keys'], ['theme_child', 'module_child']],
    'watermark current keys' => [static fn (): mixed => $conflict174()['current_source_watermark']['current_keys'], ['theme_root', 'module_root', 'theme_child', 'module_child']],
    'watermark staged conflict keys' => [static fn (): mixed => $conflict174()['current_source_watermark']['staged_conflict_keys'], ['theme_child', 'module_child']],
    'watermark reason conflict' => [static fn (): mixed => $conflict174()['current_source_watermark']['reason'], 'staged RETURNING rows reuse current-source keys and stay behind the current-source watermark'],
    'same source watermark reason conflict' => [static fn (): mixed => $noConflict174()['current_source_watermark']['reason'], 'staged RETURNING rows reuse current-source keys and stay behind the current-source watermark'],
    'watermark token matches' => [static fn (): mixed => $conflict174()['current_source_watermark']['reprepare_token_matches'], true],
    'watermark token mismatch' => [static fn (): mixed => $tokenHeld174()['current_source_watermark']['reprepare_token_matches'], false],
    'yield boundary conflict' => [static fn (): mixed => $conflict174()['yield_boundary'], 'recursive-view-returning-current-source-watermark-conflict-held-next174'],
    'yield boundary token held conflict' => [static fn (): mixed => $tokenHeld174()['yield_boundary'], 'recursive-view-returning-current-source-watermark-conflict-held-next174'],
    'visible keys conflict names' => [static fn (): mixed => array_map(static fn (string $key): string => substr($key, strrpos($key, ':') + 1), $conflict174()['visible_keys']), ['theme_root', 'module_root', 'theme_child', 'module_child', 'group_root', 'group_child']],
    'held keys conflict names' => [static fn (): mixed => array_map(static fn (string $key): string => substr($key, strrpos($key, ':') + 1), $conflict174()['held_keys']), ['theme_child', 'module_child']],
    'held keys token names' => [static fn (): mixed => array_map(static fn (string $key): string => substr($key, strrpos($key, ':') + 1), $tokenHeld174()['held_keys']), ['theme_child', 'module_child', 'group_root', 'group_child']],
    'conflicting phases' => [static fn (): mixed => array_column($conflict174()['conflicting_staged_steps'], 'phase'), ['first-next', 'second-next']],
    'conflicting ordinals' => [static fn (): mixed => array_column($conflict174()['conflicting_staged_steps'], 'watermark_ordinal'), [4, 5]],
    'conflicting visible false' => [static fn (): mixed => array_values(array_unique(array_column($conflict174()['conflicting_staged_steps'], 'visible_after_watermark'))), [false]],
    'conflicting held flag true' => [static fn (): mixed => array_values(array_unique(array_column($conflict174()['conflicting_staged_steps'], 'held_by_current_source_watermark'))), [true]],
    'current conflicts false' => [static fn (): mixed => array_values(array_unique(array_column(array_slice($conflict174()['watermark_steps'], 0, 4), 'conflicts_with_current_key'))), [false]],
    'current visible true' => [static fn (): mixed => array_values(array_unique(array_column(array_slice($conflict174()['watermark_steps'], 0, 4), 'visible_after_watermark'))), [true]],
    'group rows not conflicting' => [static fn (): mixed => array_column(array_slice($conflict174()['watermark_steps'], 6), 'conflicts_with_current_key'), [false, false]],
    'group rows visible' => [static fn (): mixed => array_column(array_slice($conflict174()['watermark_steps'], 6), 'visible_after_watermark'), [true, true]],
    'logical keys tail' => [static fn (): mixed => array_column(array_slice($conflict174()['watermark_steps'], 4), 'logical_key'), ['theme_child', 'module_child', 'group_root', 'group_child']],
    'dependency marker next174' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next174', $conflict174()['dependencies'], true), true],
    'dependency marker duplicate watermark' => [static fn (): mixed => in_array('sqlite-returning-current-source-duplicate-key-watermark', $conflict174()['dependencies'], true), true],
    'dependency marker application' => [static fn (): mixed => in_array('application-recursive-view-returning-current-source-next174', $conflict174()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => $conflict174()['dependency_closure'], 'no new support component needed; reuses native recursive view RETURNING current-source cursor and reprepare barriers'],
    'non overlap note' => [static fn (): mixed => str_contains($conflict174()['non_overlap'], 'does not repeat savepoint rollback'), true],
    'missing separator rejected' => [static fn (): mixed => $run174(['conflict_key_separator' => '|']), InvalidArgumentException::class],
    'empty separator rejected' => [static fn (): mixed => $run174(['conflict_key_separator' => '']), InvalidArgumentException::class],
    'bad token rejected' => [static fn (): mixed => $run174(['reprepare_token' => 'bad token']), InvalidArgumentException::class],
    'bad expected token rejected' => [static fn (): mixed => $run174(['expected_reprepare_token' => 'bad token']), InvalidArgumentException::class],
    'bad current cookie rejected' => [static fn (): mixed => $run174(['current_schema_cookie' => -1]), InvalidArgumentException::class],
    'bad next cookie rejected' => [static fn (): mixed => $run174(['next_schema_cookie' => -1]), InvalidArgumentException::class],
    'base dependency retained' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next170', $conflict174()['dependencies'], true), true],
    'base release requested two' => [static fn (): mixed => $conflict174()['base']['release_requested'], 2],
    'base statement rows all released' => [static fn (): mixed => $conflict174()['base']['statement_rows'], 8],
    'token held base statement rows current only' => [static fn (): mixed => $tokenHeld174()['base']['statement_rows'], 4],
    'token held watermark source changed' => [static fn (): mixed => $tokenHeld174()['current_source_watermark']['source_changed'], true],
];

$tests = [];
foreach ($cases174 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next174 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
