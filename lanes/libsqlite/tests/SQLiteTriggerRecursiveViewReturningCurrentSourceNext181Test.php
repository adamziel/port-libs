<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows181 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView181 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-181-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-181-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
    'audit_label' => 'current-recursive-view-trigger-181',
];
$nextView181 = $currentView181;
$nextView181['source'] = 'main@view-cookie-181-next';
$nextView181['trigger_source'] = 'main@trigger-cookie-181-next';
$nextView181['audit_label'] = 'next-recursive-view-trigger-181';
$currentInput181 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput181 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning181 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$run181 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext181(
    $rows181,
    $currentInput181,
    $nextInput181,
    $currentView181,
    $nextView181,
    $returning181,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_181',
        'cursor_name' => 'wp_recursive_view_returning_cursor_181',
        'current_generation' => 'wp-current-returning-181',
        'next_generation' => 'wp-next-returning-181',
        'checkpoint_name' => 'wp_recursive_view_checkpoint_181',
        'page_size' => 3,
    ],
);
$held181 = static fn (): array => $run181();
$admitted181 = static fn (): array => $run181(['admit_next_source' => true]);
$tokenHeld181 = static fn (): array => $run181(['admit_next_source' => true, 'expected_reprepare_token' => 'wp.reprepare.181.expected']);
$nonDurable181 = static fn (): array => $run181(['commit_visible_checkpoints' => false]);

$cases181 = [
    'held status' => [static fn (): mixed => $held181()['status'], 'trigger-recursive-view-returning-current-source-next181-current-checkpointed-next-pending'],
    'admitted status' => [static fn (): mixed => $admitted181()['status'], 'trigger-recursive-view-returning-current-source-next181-checkpoints-admitted'],
    'token held status' => [static fn (): mixed => $tokenHeld181()['status'], 'trigger-recursive-view-returning-current-source-next181-reprepare-checkpoint-pending'],
    'base held status retained' => [static fn (): mixed => $held181()['base']['status'], 'trigger-recursive-view-returning-current-source-next177-current-drained-next-held'],
    'base admitted status retained' => [static fn (): mixed => $admitted181()['base']['status'], 'trigger-recursive-view-returning-current-source-next177-next-admitted'],
    'checkpoint name retained' => [static fn (): mixed => $held181()['checkpoint_name'], 'wp_recursive_view_checkpoint_181'],
    'all checkpoint count held' => [static fn (): mixed => $held181()['counts']['checkpoints'], 4],
    'visible checkpoint count held' => [static fn (): mixed => $held181()['counts']['visible'], 2],
    'pending checkpoint count held' => [static fn (): mixed => $held181()['counts']['pending'], 2],
    'durable checkpoint count held' => [static fn (): mixed => $held181()['counts']['durable'], 2],
    'admitted checkpoint count' => [static fn (): mixed => $admitted181()['counts']['checkpoints'], 4],
    'admitted visible checkpoint count' => [static fn (): mixed => $admitted181()['counts']['visible'], 4],
    'admitted pending checkpoint count' => [static fn (): mixed => $admitted181()['counts']['pending'], 0],
    'admitted durable checkpoint count' => [static fn (): mixed => $admitted181()['counts']['durable'], 4],
    'non durable visible count' => [static fn (): mixed => $nonDurable181()['counts']['visible'], 2],
    'non durable durable count' => [static fn (): mixed => $nonDurable181()['counts']['durable'], 0],
    'rows visible held' => [static fn (): mixed => $held181()['counts']['rows_visible'], 6],
    'rows pending held' => [static fn (): mixed => $held181()['counts']['rows_pending'], 4],
    'rows visible admitted' => [static fn (): mixed => $admitted181()['counts']['rows_visible'], 10],
    'rows pending admitted' => [static fn (): mixed => $admitted181()['counts']['rows_pending'], 0],
    'checkpoint tokens held' => [static fn (): mixed => $held181()['checkpoint_tokens'], [
        'wp_recursive_view_checkpoint_181:wp-current-returning-181:0',
        'wp_recursive_view_checkpoint_181:wp-current-returning-181:1',
        'wp_recursive_view_checkpoint_181:wp-next-returning-181:2',
        'wp_recursive_view_checkpoint_181:wp-next-returning-181:3',
    ]],
    'visible checkpoint tokens held' => [static fn (): mixed => $held181()['visible_checkpoint_tokens'], [
        'wp_recursive_view_checkpoint_181:wp-current-returning-181:0',
        'wp_recursive_view_checkpoint_181:wp-current-returning-181:1',
    ]],
    'pending checkpoint tokens held' => [static fn (): mixed => $held181()['pending_checkpoint_tokens'], [
        'wp_recursive_view_checkpoint_181:wp-next-returning-181:2',
        'wp_recursive_view_checkpoint_181:wp-next-returning-181:3',
    ]],
    'admitted pending tokens empty' => [static fn (): mixed => $admitted181()['pending_checkpoint_tokens'], []],
    'admitted visible tokens all' => [static fn (): mixed => $admitted181()['visible_checkpoint_tokens'], $admitted181()['checkpoint_tokens']],
    'durable tokens held match visible' => [static fn (): mixed => $held181()['durable_checkpoint_tokens'], $held181()['visible_checkpoint_tokens']],
    'non durable tokens empty' => [static fn (): mixed => $nonDurable181()['durable_checkpoint_tokens'], []],
    'last visible checkpoint token' => [static fn (): mixed => $held181()['last_visible_checkpoint']['checkpoint_token'], 'wp_recursive_view_checkpoint_181:wp-current-returning-181:1'],
    'last visible last resume' => [static fn (): mixed => $held181()['last_visible_checkpoint']['last_resume_token'], 'wp_recursive_view_returning_cursor_181:wp-current-returning-181:5'],
    'first pending token' => [static fn (): mixed => $held181()['first_pending_checkpoint']['checkpoint_token'], 'wp_recursive_view_checkpoint_181:wp-next-returning-181:2'],
    'first pending first resume' => [static fn (): mixed => $held181()['first_pending_checkpoint']['first_resume_token'], 'wp_recursive_view_returning_cursor_181:wp-next-returning-181:6'],
    'current first page names' => [static fn (): mixed => $held181()['checkpoints'][0]['names'], ['siteurl', 'current_plugin', 'siteurl:child']],
    'current second page names' => [static fn (): mixed => $held181()['checkpoints'][1]['names'], ['current_plugin:child', 'siteurl:child:child', 'current_plugin:child:child']],
    'next first page names' => [static fn (): mixed => $held181()['checkpoints'][2]['names'], ['home', 'next_plugin', 'home:child']],
    'next second page names' => [static fn (): mixed => $held181()['checkpoints'][3]['names'], ['home:child:child']],
    'current checkpoint phases' => [static fn (): mixed => array_column($held181()['visible_checkpoints'], 'phase'), ['current', 'current']],
    'pending checkpoint phases' => [static fn (): mixed => array_column($held181()['pending_checkpoints'], 'phase'), ['next', 'next']],
    'current checkpoint generations' => [static fn (): mixed => array_unique(array_column($held181()['visible_checkpoints'], 'generation')), ['wp-current-returning-181']],
    'pending checkpoint generations' => [static fn (): mixed => array_unique(array_column($held181()['pending_checkpoints'], 'generation')), ['wp-next-returning-181']],
    'current checkpoint sources' => [static fn (): mixed => array_unique(array_column($held181()['visible_checkpoints'], 'source')), ['main@view-cookie-181-current']],
    'pending checkpoint sources' => [static fn (): mixed => array_unique(array_column($held181()['pending_checkpoints'], 'source')), ['main@view-cookie-181-next']],
    'current checkpoint trigger sources' => [static fn (): mixed => array_unique(array_column($held181()['visible_checkpoints'], 'trigger_source')), ['main@trigger-cookie-181-current']],
    'pending checkpoint trigger sources' => [static fn (): mixed => array_unique(array_column($held181()['pending_checkpoints'], 'trigger_source')), ['main@trigger-cookie-181-next']],
    'replay next held' => [static fn (): mixed => $held181()['replay_plan']['next_admitted'], false],
    'replay admitted' => [static fn (): mixed => $admitted181()['replay_plan']['next_admitted'], true],
    'replay pending requires reprepare' => [static fn (): mixed => $held181()['replay_plan']['pending_requires_reprepare'], true],
    'replay admitted requires no reprepare' => [static fn (): mixed => $admitted181()['replay_plan']['pending_requires_reprepare'], false],
    'replay resume after token' => [static fn (): mixed => $held181()['replay_plan']['resume_after_token'], 'wp_recursive_view_returning_cursor_181:wp-current-returning-181:5'],
    'replay blocked at token' => [static fn (): mixed => $held181()['replay_plan']['blocked_at_token'], 'wp_recursive_view_returning_cursor_181:wp-next-returning-181:6'],
    'replay admitted blocked null' => [static fn (): mixed => $admitted181()['replay_plan']['blocked_at_token'], null],
    'yield boundary held' => [static fn (): mixed => $held181()['yield_boundary'], 'recursive-view-returning-current-source-checkpoint-next181-next-pending'],
    'yield boundary admitted' => [static fn (): mixed => $admitted181()['yield_boundary'], 'recursive-view-returning-current-source-checkpoint-next181-all-visible'],
    'dependency next181' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next181', $held181()['dependencies'], true), true],
    'dependency checkpoint boundary' => [static fn (): mixed => in_array('sqlite-returning-cursor-checkpoint-source-boundary', $held181()['dependencies'], true), true],
    'dependency next177 retained' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next177', $held181()['dependencies'], true), true],
    'dependency closure note' => [static fn (): mixed => $held181()['dependency_closure'], 'no new support component needed; reuses recursive view trigger RETURNING cursor rows and checkpoint metadata'],
    'non overlap mentions next177' => [static fn (): mixed => str_contains($held181()['non_overlap'], 'next177'), true],
    'custom page size count' => [static fn (): mixed => $run181(['page_size' => 4])['counts']['checkpoints'], 4],
    'custom page size current names' => [static fn (): mixed => $run181(['page_size' => 4])['checkpoints'][0]['names'], ['siteurl', 'current_plugin', 'siteurl:child', 'current_plugin:child']],
    'custom page size next page numbers' => [static fn (): mixed => array_column($run181(['page_size' => 4])['pending_checkpoints'], 'page'), [1, 2]],
    'non recursive checkpoint count' => [static fn (): mixed => $run181(['recursive_triggers' => false])['counts']['checkpoints'], 3],
    'non recursive visible names' => [static fn (): mixed => $run181(['recursive_triggers' => false])['visible_checkpoints'][0]['names'], ['siteurl', 'current_plugin']],
    'non recursive first pending names' => [static fn (): mixed => $run181(['recursive_triggers' => false])['pending_checkpoints'][0]['names'], ['home']],
    'non recursive second pending names' => [static fn (): mixed => $run181(['recursive_triggers' => false])['pending_checkpoints'][1]['names'], ['next_plugin']],
    'bad checkpoint rejected' => [static fn (): mixed => $run181(['checkpoint_name' => 'bad checkpoint']), InvalidArgumentException::class],
    'bad cursor rejected from base' => [static fn (): mixed => $run181(['cursor_name' => 'bad cursor']), InvalidArgumentException::class],
    'bad page size rejected from base' => [static fn (): mixed => $run181(['page_size' => 0]), InvalidArgumentException::class],
    'bad max depth rejected from base' => [static fn (): mixed => $run181(['max_depth' => 33]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases181 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next181 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
