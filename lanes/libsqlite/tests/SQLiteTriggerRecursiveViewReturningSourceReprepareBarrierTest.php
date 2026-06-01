<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows170 = [
    ['key_name' => 'base_url', 'key_value' => 'https://example.test', 'load_policy' => 'yes', 'parent_name' => null, 'priority' => 0],
    ['key_name' => 'theme_root', 'key_value' => 'theme', 'load_policy' => 'yes', 'parent_name' => 'base_url', 'priority' => 10],
    ['key_name' => 'theme_child', 'key_value' => 'theme-child', 'load_policy' => 'no', 'parent_name' => 'theme_root', 'priority' => 20],
    ['key_name' => 'module_root', 'key_value' => 'module', 'load_policy' => 'yes', 'parent_name' => 'base_url', 'priority' => 15],
    ['key_name' => 'module_child', 'key_value' => 'module-child', 'load_policy' => 'no', 'parent_name' => 'module_root', 'priority' => 30],
    ['key_name' => 'group_root', 'key_value' => 'group', 'load_policy' => 'yes', 'parent_name' => 'module_child', 'priority' => 40],
    ['key_name' => 'group_child', 'key_value' => 'group-child', 'load_policy' => 'no', 'parent_name' => 'group_root', 'priority' => 50],
];

$currentView170 = [
    'name' => 'app_recursive_load_policy_view',
    'source' => 'main@view-cookie-170-current',
    'trigger' => 'app_recursive_load_policy_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-170-current',
    'root_key' => 'root_name',
    'parent_key' => 'parent_name',
    'columns' => ['key_name', 'key_value', 'load_policy', 'parent_name', 'priority'],
    'where' => static fn (array $row, string $root, int $depth): bool => $depth <= 2 && !str_starts_with((string) $row['key_name'], 'network_'),
    'order_by' => 'priority',
];
$nextView170 = $currentView170;
$nextView170['source'] = 'main@view-cookie-170-next';
$nextView170['trigger_source'] = 'main@trigger-cookie-170-next';
$nextView170['where'] = static fn (array $row, string $root, int $depth): bool => $depth <= 3 && str_contains((string) $row['key_name'], '_');

$returning170 = [
    'key_name',
    ['expr' => 'root', 'as' => 'root_name'],
    ['expr' => 'depth', 'as' => 'depth'],
    ['expr' => 'trigger_source', 'as' => 'trigger_cookie'],
    static fn (array $incoming, array $viewRow, string $triggerSource, int $ordinal): string => $triggerSource . ':' . $viewRow['_root'] . ':' . $ordinal . ':' . $incoming['key_name'],
];

$run170 = static fn (array $options = [], ?array $nextView = null): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeSourceReprepareBarrier(
    $rows170,
    [['root_name' => 'base_url']],
    [['root_name' => 'theme_root']],
    [['root_name' => 'module_root']],
    $currentView170,
    $nextView ?? $nextView170,
    $returning170,
    $options + [
        'savepoint' => 'app_recursive_view_next170',
        'cursor_name' => 'app_recursive_view_returning_cursor_170',
        'current_generation' => 'app-import-current-170',
        'first_next_generation' => 'app-import-next-170-a',
        'second_next_generation' => 'app-import-next-170-b',
        'current_schema_cookie' => 17,
        'next_schema_cookie' => 18,
        'reprepare_token' => 'app.reprepare.170',
        'expected_reprepare_token' => 'app.reprepare.170.expected',
    ],
);

$held170 = static fn (): array => $run170(['release_staged_sources' => 2]);
$reprepared170 = static fn (): array => $run170(['release_staged_sources' => 2, 'expected_reprepare_token' => 'app.reprepare.170']);
$drained170 = static function () use ($run170, $currentView170): array {
    $sameNext = $currentView170;
    $sameNext['where'] = static fn (array $row, string $root, int $depth): bool => $depth <= 3 && str_contains((string) $row['key_name'], '_');

    return $run170(['release_staged_sources' => 0, 'next_schema_cookie' => 17], $sameNext);
};

$cases170 = [
    'held status' => [static fn (): mixed => $held170()['status'], 'trigger-recursive-view-returning-current-source-reprepare-held-next170'],
    'reprepared status' => [static fn (): mixed => $reprepared170()['status'], 'trigger-recursive-view-returning-current-source-reprepared-next170'],
    'drained status' => [static fn (): mixed => $drained170()['status'], 'trigger-recursive-view-returning-current-source-drained-next170'],
    'savepoint retained' => [static fn (): mixed => $held170()['savepoint'], 'app_recursive_view_next170'],
    'cursor retained' => [static fn (): mixed => $held170()['cursor'], 'app_recursive_view_returning_cursor_170'],
    'source changed by view cookie' => [static fn (): mixed => $held170()['source_changed'], true],
    'reprepare required when source changed' => [static fn (): mixed => $held170()['reprepare_required'], true],
    'token mismatch held' => [static fn (): mixed => $held170()['reprepare_token_matches'], false],
    'token match released' => [static fn (): mixed => $reprepared170()['reprepare_token_matches'], true],
    'release requested all' => [static fn (): mixed => $held170()['release_requested'], 2],
    'release not allowed on token mismatch' => [static fn (): mixed => $held170()['release_allowed'], false],
    'release allowed on token match' => [static fn (): mixed => $reprepared170()['release_allowed'], true],
    'current schema cookie retained' => [static fn (): mixed => $held170()['current_schema_cookie'], 17],
    'next schema cookie retained' => [static fn (): mixed => $held170()['next_schema_cookie'], 18],
    'current drained before next held' => [static fn (): mixed => $held170()['current_drained_before_next'], true],
    'current drained before next released' => [static fn (): mixed => $reprepared170()['current_drained_before_next'], true],
    'held attempted rows' => [static fn (): mixed => $held170()['attempted_statement_rows'], 8],
    'held visible rows current only' => [static fn (): mixed => $held170()['statement_rows'], 4],
    'reprepared visible rows all' => [static fn (): mixed => $reprepared170()['statement_rows'], 8],
    'drained visible rows current only' => [static fn (): mixed => $drained170()['statement_rows'], 4],
    'held barrier current count' => [static fn (): mixed => $held170()['returning_barrier']['current_source_visible'], 4],
    'held barrier staged attempted' => [static fn (): mixed => $held170()['returning_barrier']['staged_source_attempted'], 4],
    'held barrier staged visible zero' => [static fn (): mixed => $held170()['returning_barrier']['staged_source_visible'], 0],
    'held barrier staged held four' => [static fn (): mixed => $held170()['returning_barrier']['staged_source_held'], 4],
    'reprepared staged visible four' => [static fn (): mixed => $reprepared170()['returning_barrier']['staged_source_visible'], 4],
    'reprepared staged held zero' => [static fn (): mixed => $reprepared170()['returning_barrier']['staged_source_held'], 0],
    'held reason token mismatch' => [static fn (): mixed => $held170()['returning_barrier']['reason'], 'next view or trigger source changed before matching reprepare token'],
    'reprepared reason drained' => [static fn (): mixed => $reprepared170()['returning_barrier']['reason'], 'current RETURNING stream drained before next source visibility'],
    'yield boundary reprepare' => [static fn (): mixed => $held170()['yield_boundary'], 'recursive-view-returning-current-source-reprepare-barrier-next170'],
    'yield boundary drain' => [static fn (): mixed => $drained170()['yield_boundary'], 'recursive-view-returning-current-source-drain-barrier-next170'],
    'current phases first four' => [static fn (): mixed => array_column(array_slice($held170()['barrier_steps'], 0, 4), 'phase'), ['current', 'current', 'current', 'current']],
    'staged phases tail' => [static fn (): mixed => array_column(array_slice($held170()['barrier_steps'], 4), 'phase'), ['first-next', 'second-next', 'second-next', 'second-next']],
    'current cookies first four' => [static fn (): mixed => array_values(array_unique(array_column(array_slice($held170()['barrier_steps'], 0, 4), 'schema_cookie'))), [17]],
    'staged cookies tail' => [static fn (): mixed => array_values(array_unique(array_column(array_slice($held170()['barrier_steps'], 4), 'schema_cookie'))), [18]],
    'current token null' => [static fn (): mixed => array_values(array_unique(array_column(array_slice($held170()['barrier_steps'], 0, 4), 'reprepare_token'))), [null]],
    'staged token retained' => [static fn (): mixed => array_values(array_unique(array_column(array_slice($held170()['barrier_steps'], 4), 'reprepare_token'))), ['app.reprepare.170']],
    'staged expected token retained' => [static fn (): mixed => array_values(array_unique(array_column(array_slice($held170()['barrier_steps'], 4), 'expected_reprepare_token'))), ['app.reprepare.170.expected']],
    'held flags staged true' => [static fn (): mixed => array_values(array_unique(array_column(array_slice($held170()['barrier_steps'], 4), 'held_by_reprepare_barrier'))), [true]],
    'reprepared flags staged false' => [static fn (): mixed => array_values(array_unique(array_column(array_slice($reprepared170()['barrier_steps'], 4), 'held_by_reprepare_barrier'))), [false]],
    'current flags visible' => [static fn (): mixed => array_values(array_unique(array_column(array_slice($held170()['barrier_steps'], 0, 4), 'visible_after_barrier'))), [true]],
    'held staged flags hidden' => [static fn (): mixed => array_values(array_unique(array_column(array_slice($held170()['barrier_steps'], 4), 'visible_after_barrier'))), [false]],
    'reprepared staged flags visible' => [static fn (): mixed => array_values(array_unique(array_column(array_slice($reprepared170()['barrier_steps'], 4), 'visible_after_barrier'))), [true]],
    'held visible keys current generation' => [static fn (): mixed => array_values(array_unique(array_map(static fn (string $key): string => explode(':', $key, 2)[0], $held170()['visible_keys']))), ['app-import-current-170']],
    'held keys staged generations' => [static fn (): mixed => array_values(array_unique(array_map(static fn (string $key): string => explode(':', $key, 2)[0], $held170()['held_keys']))), ['app-import-next-170-a', 'app-import-next-170-b']],
    'reprepared visible generations' => [static fn (): mixed => array_values(array_unique(array_map(static fn (string $key): string => explode(':', $key, 2)[0], $reprepared170()['visible_keys']))), ['app-import-current-170', 'app-import-next-170-a', 'app-import-next-170-b']],
    'reprepared held keys empty' => [static fn (): mixed => $reprepared170()['held_keys'], []],
    'current keys names' => [static fn (): mixed => array_map(static fn (string $key): string => substr($key, strrpos($key, ':') + 1), $held170()['current_keys']), ['theme_root', 'module_root', 'theme_child', 'module_child']],
    'staged keys names' => [static fn (): mixed => array_map(static fn (string $key): string => substr($key, strrpos($key, ':') + 1), $held170()['staged_keys']), ['theme_child', 'module_child', 'group_root', 'group_child']],
    'base visible generation held current' => [static fn (): mixed => $held170()['base']['source_next_plan']['visible_generation'], 'app-import-next-170-b'],
    'barrier overrides base held count' => [static fn (): mixed => count($held170()['held_barrier_steps']), 4],
    'dependency marker next170' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next170', $held170()['dependencies'], true), true],
    'dependency marker reprepare barrier' => [static fn (): mixed => in_array('sqlite-recursive-view-returning-current-source-drain-reprepare-barrier', $held170()['dependencies'], true), true],
    'dependency marker token admission' => [static fn (): mixed => in_array('sqlite-returning-current-source-next-token-admission', $held170()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => $held170()['dependency_closure'], 'reuses-native-recursive-view-returning-current-source-cursor-model'],
    'custom cursor accepted' => [static fn (): mixed => $run170(['cursor_name' => 'custom.cursor@170'])['cursor'], 'custom.cursor@170'],
    'custom token accepted' => [static fn (): mixed => $run170(['reprepare_token' => 'custom.token@170'])['reprepare_token'], 'custom.token@170'],
    'bad token rejected' => [static fn (): mixed => $run170(['reprepare_token' => 'bad token']), InvalidArgumentException::class],
    'bad expected token rejected' => [static fn (): mixed => $run170(['expected_reprepare_token' => 'bad token']), InvalidArgumentException::class],
    'bad current cookie rejected' => [static fn (): mixed => $run170(['current_schema_cookie' => -1]), InvalidArgumentException::class],
    'bad next cookie rejected' => [static fn (): mixed => $run170(['next_schema_cookie' => -1]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases170 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next170 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
