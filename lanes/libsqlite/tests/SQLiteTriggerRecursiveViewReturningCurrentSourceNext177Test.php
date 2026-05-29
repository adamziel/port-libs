<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows177 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView177 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-177-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-177-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
    'audit_label' => 'current-recursive-view-trigger-177',
];
$nextView177 = $currentView177;
$nextView177['source'] = 'main@view-cookie-177-next';
$nextView177['trigger_source'] = 'main@trigger-cookie-177-next';
$nextView177['audit_label'] = 'next-recursive-view-trigger-177';
$currentInput177 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput177 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning177 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'old.option_value', 'as' => 'old_value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$run177 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext177(
    $rows177,
    $currentInput177,
    $nextInput177,
    $currentView177,
    $nextView177,
    $returning177,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_177',
        'cursor_name' => 'wp_recursive_view_returning_cursor_177',
        'current_generation' => 'wp-current-returning-177',
        'next_generation' => 'wp-next-returning-177',
        'page_size' => 3,
    ],
);
$held177 = static fn (): array => $run177();
$admitted177 = static fn (): array => $run177(['admit_next_source' => true]);
$tokenHeld177 = static fn (): array => $run177(['admit_next_source' => true, 'expected_reprepare_token' => 'wp.reprepare.177.expected']);
$nonRecursive177 = static fn (): array => $run177(['recursive_triggers' => false]);

$cases177 = [
    'held status' => [static fn (): mixed => $held177()['status'], 'trigger-recursive-view-returning-current-source-next177-current-drained-next-held'],
    'admitted status' => [static fn (): mixed => $admitted177()['status'], 'trigger-recursive-view-returning-current-source-next177-next-admitted'],
    'token held status' => [static fn (): mixed => $tokenHeld177()['status'], 'trigger-recursive-view-returning-current-source-next177-reprepare-held'],
    'savepoint retained' => [static fn (): mixed => $held177()['savepoint'], 'wp_recursive_view_177'],
    'cursor retained' => [static fn (): mixed => $held177()['cursor'], 'wp_recursive_view_returning_cursor_177'],
    'current generation retained' => [static fn (): mixed => $held177()['current_generation'], 'wp-current-returning-177'],
    'next generation retained' => [static fn (): mixed => $held177()['next_generation'], 'wp-next-returning-177'],
    'token matches held default' => [static fn (): mixed => $held177()['reprepare_token_matches'], true],
    'token mismatch recorded' => [static fn (): mixed => $tokenHeld177()['reprepare_token_matches'], false],
    'page size retained' => [static fn (): mixed => $held177()['page_size'], 3],
    'base status pinned' => [static fn (): mixed => $held177()['base']['status'], 'trigger-recursive-view-returning-current-source-next172-current-pinned'],
    'base status admitted' => [static fn (): mixed => $admitted177()['base']['status'], 'trigger-recursive-view-returning-current-source-next172-next-admitted'],
    'current source rows count' => [static fn (): mixed => count($held177()['current_source_rows']), 6],
    'attempted next rows count' => [static fn (): mixed => count($held177()['attempted_next_source_rows']), 4],
    'visible count held current only' => [static fn (): mixed => count($held177()['visible_rows']), 6],
    'held count held next only' => [static fn (): mixed => count($held177()['held_rows']), 4],
    'visible count admitted' => [static fn (): mixed => count($admitted177()['visible_rows']), 10],
    'held count admitted' => [static fn (): mixed => count($admitted177()['held_rows']), 0],
    'token held visible count current only' => [static fn (): mixed => count($tokenHeld177()['visible_rows']), 6],
    'token held next rows held' => [static fn (): mixed => count($tokenHeld177()['held_rows']), 4],
    'visible returning names held' => [static fn (): mixed => array_column($held177()['visible_returning_rows'], 'name'), ['siteurl', 'current_plugin', 'siteurl:child', 'current_plugin:child', 'siteurl:child:child', 'current_plugin:child:child']],
    'held returning names' => [static fn (): mixed => array_column($held177()['held_returning_rows'], 'name'), ['home', 'next_plugin', 'home:child', 'home:child:child']],
    'admitted visible returning names' => [static fn (): mixed => array_column($admitted177()['visible_returning_rows'], 'name'), ['siteurl', 'current_plugin', 'siteurl:child', 'current_plugin:child', 'siteurl:child:child', 'current_plugin:child:child', 'home', 'next_plugin', 'home:child', 'home:child:child']],
    'current resume token first' => [static fn (): mixed => $held177()['current_resume_tokens'][0], 'wp_recursive_view_returning_cursor_177:wp-current-returning-177:0'],
    'current resume token last' => [static fn (): mixed => $held177()['current_last_resume_token'], 'wp_recursive_view_returning_cursor_177:wp-current-returning-177:5'],
    'next first token' => [static fn (): mixed => $held177()['next_first_resume_token'], 'wp_recursive_view_returning_cursor_177:wp-next-returning-177:6'],
    'attempted next tokens' => [static fn (): mixed => $held177()['attempted_next_resume_tokens'], [
        'wp_recursive_view_returning_cursor_177:wp-next-returning-177:6',
        'wp_recursive_view_returning_cursor_177:wp-next-returning-177:7',
        'wp_recursive_view_returning_cursor_177:wp-next-returning-177:8',
        'wp_recursive_view_returning_cursor_177:wp-next-returning-177:9',
    ]],
    'visible tokens held exclude next' => [static fn (): mixed => $held177()['visible_resume_tokens'], $held177()['current_resume_tokens']],
    'held tokens are next tokens' => [static fn (): mixed => $held177()['held_resume_tokens'], $held177()['attempted_next_resume_tokens']],
    'admitted visible tokens include all' => [static fn (): mixed => $admitted177()['visible_resume_tokens'], array_merge($admitted177()['current_resume_tokens'], $admitted177()['attempted_next_resume_tokens'])],
    'current source phases' => [static fn (): mixed => array_unique(array_column($held177()['current_source_rows'], 'phase')), ['current']],
    'next source phases' => [static fn (): mixed => array_unique(array_column($held177()['attempted_next_source_rows'], 'phase')), ['next']],
    'current source generation unique' => [static fn (): mixed => array_unique(array_column($held177()['current_source_rows'], 'generation')), ['wp-current-returning-177']],
    'next source generation unique' => [static fn (): mixed => array_unique(array_column($held177()['attempted_next_source_rows'], 'generation')), ['wp-next-returning-177']],
    'current trigger source unique' => [static fn (): mixed => array_unique(array_column($held177()['current_source_rows'], 'trigger_source')), ['main@trigger-cookie-177-current']],
    'next trigger source unique' => [static fn (): mixed => array_unique(array_column($held177()['attempted_next_source_rows'], 'trigger_source')), ['main@trigger-cookie-177-next']],
    'current pages' => [static fn (): mixed => array_column($held177()['current_source_rows'], 'resume_page'), [0, 0, 0, 1, 1, 1]],
    'next pages continue after current' => [static fn (): mixed => array_column($held177()['attempted_next_source_rows'], 'resume_page'), [2, 2, 2, 3]],
    'resume ordinals attempted sequence' => [static fn (): mixed => array_column(array_merge($held177()['current_source_rows'], $held177()['attempted_next_source_rows']), 'resume_ordinal'), range(0, 9)],
    'current rows visible true' => [static fn (): mixed => array_values(array_unique(array_column($held177()['current_source_rows'], 'visible_after_current_source'))), [true]],
    'held next rows visible false' => [static fn (): mixed => array_values(array_unique(array_column($held177()['attempted_next_source_rows'], 'visible_after_current_source'))), [false]],
    'admitted next rows visible true' => [static fn (): mixed => array_values(array_unique(array_column($admitted177()['attempted_next_source_rows'], 'visible_after_current_source'))), [true]],
    'boundary current drained before next' => [static fn (): mixed => $held177()['resume_boundary']['current_drained_before_next'], true],
    'boundary next held true' => [static fn (): mixed => $held177()['resume_boundary']['next_held'], true],
    'boundary next admitted false' => [static fn (): mixed => $held177()['resume_boundary']['next_admitted'], false],
    'boundary admitted next true' => [static fn (): mixed => $admitted177()['resume_boundary']['next_admitted'], true],
    'boundary admitted held false' => [static fn (): mixed => $admitted177()['resume_boundary']['next_held'], false],
    'held reason drain' => [static fn (): mixed => $held177()['resume_boundary']['held_reason'], 'next source waits for current RETURNING cursor drain'],
    'token held reason reprepare' => [static fn (): mixed => $tokenHeld177()['resume_boundary']['held_reason'], 'next source waits for matching reprepare token'],
    'admitted held reason null' => [static fn (): mixed => $admitted177()['resume_boundary']['held_reason'], null],
    'counts current' => [static fn (): mixed => $held177()['counts']['current'], 6],
    'counts attempted next' => [static fn (): mixed => $held177()['counts']['attempted_next'], 4],
    'counts visible' => [static fn (): mixed => $held177()['counts']['visible'], 6],
    'counts held' => [static fn (): mixed => $held177()['counts']['held'], 4],
    'counts pages' => [static fn (): mixed => $held177()['counts']['pages'], 4],
    'yield boundary held' => [static fn (): mixed => $held177()['yield_boundary'], 'recursive-view-returning-current-source-resume-next177-next-held'],
    'yield boundary admitted' => [static fn (): mixed => $admitted177()['yield_boundary'], 'recursive-view-returning-current-source-resume-next177-next-visible'],
    'dependency next177' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next177', $held177()['dependencies'], true), true],
    'dependency resume token' => [static fn (): mixed => in_array('sqlite-returning-current-source-resume-token-boundary', $held177()['dependencies'], true), true],
    'dependency base next172 retained' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next172', $held177()['dependencies'], true), true],
    'dependency closure note' => [static fn (): mixed => $held177()['dependency_closure'], 'no new support component needed; reuses recursive view trigger RETURNING current-source cursor modeling'],
    'non overlap note mentions next172' => [static fn (): mixed => str_contains($held177()['non_overlap'], 'next172'), true],
    'non recursive current rows' => [static fn (): mixed => array_column($nonRecursive177()['visible_returning_rows'], 'name'), ['siteurl', 'current_plugin']],
    'non recursive held next rows' => [static fn (): mixed => array_column($nonRecursive177()['held_returning_rows'], 'name'), ['home', 'next_plugin']],
    'custom page size pages' => [static fn (): mixed => $run177(['page_size' => 4])['counts']['pages'], 3],
    'custom page size next pages' => [static fn (): mixed => array_column($run177(['page_size' => 4])['attempted_next_source_rows'], 'resume_page'), [1, 1, 2, 2]],
    'custom child suffix visible names' => [static fn (): mixed => array_column($run177(['child_suffix' => ':shadow'])['visible_returning_rows'], 'name'), ['siteurl', 'current_plugin', 'siteurl:shadow', 'current_plugin:shadow', 'siteurl:shadow:shadow', 'current_plugin:shadow:shadow']],
    'bad cursor rejected' => [static fn (): mixed => $run177(['cursor_name' => 'bad cursor']), InvalidArgumentException::class],
    'bad current generation rejected' => [static fn (): mixed => $run177(['current_generation' => 'bad generation']), InvalidArgumentException::class],
    'bad next generation rejected' => [static fn (): mixed => $run177(['next_generation' => 'bad generation']), InvalidArgumentException::class],
    'bad token rejected' => [static fn (): mixed => $run177(['reprepare_token' => 'bad token']), InvalidArgumentException::class],
    'bad expected token rejected' => [static fn (): mixed => $run177(['expected_reprepare_token' => 'bad token']), InvalidArgumentException::class],
    'bad page size rejected' => [static fn (): mixed => $run177(['page_size' => 0]), InvalidArgumentException::class],
    'base bad max depth rejected' => [static fn (): mixed => $run177(['max_depth' => 33]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases177 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next177 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
