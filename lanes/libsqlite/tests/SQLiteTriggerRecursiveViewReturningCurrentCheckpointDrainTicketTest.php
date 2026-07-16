<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows187 = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes'],
];
$currentView187 = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-187-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-187-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'audit_label' => 'current-recursive-view-trigger-187',
];
$nextView187 = $currentView187;
$nextView187['source'] = 'main@view-cookie-187-next';
$nextView187['trigger_source'] = 'main@trigger-cookie-187-next';
$nextView187['audit_label'] = 'next-recursive-view-trigger-187';
$currentInput187 = [
    ['import_id' => 10, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_module', 'value' => 'enabled', 'load_policy_flag' => 'no', 'spawn_child' => true],
];
$nextInput187 = [
    ['import_id' => 20, 'name' => 'landing_url', 'value' => 'https://next.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_module', 'value' => 'active', 'load_policy_flag' => 'no', 'spawn_child' => false],
];
$returning187 = [
    ['expr' => 'new.key_name', 'as' => 'name'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$run187 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentCheckpointDrainTicket(
    $rows187,
    $currentInput187,
    $nextInput187,
    $currentView187,
    $nextView187,
    $returning187,
    $options + [
        'key' => 'key_name',
        'savepoint' => 'app_recursive_view_187',
        'cursor_name' => 'app_recursive_view_returning_cursor_187',
        'current_generation' => 'app-current-returning-187',
        'next_generation' => 'app-next-returning-187',
        'checkpoint_name' => 'app_recursive_view_checkpoint_187',
        'page_size' => 3,
    ],
);
$held187 = static fn (): array => $run187();
$exposed187 = static fn (): array => $run187(['admit_next_source' => true, 'auto_ack_current' => true]);
$ticketHeld187 = static fn (): array => $run187(['admit_next_source' => true, 'auto_ack_current' => true, 'drain_ticket' => 'app.returning.current.source.drain.187:bad']);
$missingAck187 = static fn (): array => $run187([
    'admit_next_source' => true,
    'acknowledged_current_checkpoints' => ['app_recursive_view_checkpoint_187:app-current-returning-187:0'],
]);
$nonRecursive187 = static fn (): array => $run187(['admit_next_source' => true, 'auto_ack_current' => true, 'recursive_triggers' => false]);

$cases187 = [
    'held status' => [static fn (): mixed => $held187()['status'], 'trigger-recursive-view-returning-current-source-next187-checkpoint-handoff-held'],
    'exposed status' => [static fn (): mixed => $exposed187()['status'], 'trigger-recursive-view-returning-current-source-next187-next-exposed'],
    'ticket held status' => [static fn (): mixed => $ticketHeld187()['status'], 'trigger-recursive-view-returning-current-source-next187-drain-ticket-held'],
    'missing ack status' => [static fn (): mixed => $missingAck187()['status'], 'trigger-recursive-view-returning-current-source-next187-checkpoint-handoff-held'],
    'base held retained' => [static fn (): mixed => $held187()['base']['status'], 'trigger-recursive-view-returning-current-source-next184-current-ack-held'],
    'base exposed retained' => [static fn (): mixed => $exposed187()['base']['status'], 'trigger-recursive-view-returning-current-source-next184-next-exposed'],
    'ticket prefix retained' => [static fn (): mixed => $exposed187()['drain_ticket_prefix'], 'app.returning.current.source.drain.187'],
    'ticket matches exposed' => [static fn (): mixed => $exposed187()['drain_ticket_matches'], true],
    'ticket mismatch recorded' => [static fn (): mixed => $ticketHeld187()['drain_ticket_matches'], false],
    'expected ticket equals actual exposed' => [static fn (): mixed => $exposed187()['expected_drain_ticket'], $exposed187()['drain_ticket']],
    'expected ticket differs when stale' => [static fn (): mixed => $ticketHeld187()['expected_drain_ticket'] === $ticketHeld187()['drain_ticket'], false],
    'base next exposed before ticket' => [static fn (): mixed => $exposed187()['base_next_exposed_before_ticket'], true],
    'base next not exposed held' => [static fn (): mixed => $held187()['base_next_exposed_before_ticket'], false],
    'next exposed after ticket' => [static fn (): mixed => $exposed187()['next_source_exposed_after_drain_ticket'], true],
    'next held after ticket mismatch' => [static fn (): mixed => $ticketHeld187()['next_source_exposed_after_drain_ticket'], false],
    'current rows count' => [static fn (): mixed => count($exposed187()['current_source_rows']), 6],
    'attempted next rows count' => [static fn (): mixed => count($exposed187()['attempted_next_source_rows']), 4],
    'visible held current only count' => [static fn (): mixed => count($held187()['visible_rows']), 6],
    'visible exposed all rows count' => [static fn (): mixed => count($exposed187()['visible_rows']), 10],
    'held rows exposed empty' => [static fn (): mixed => $exposed187()['held_rows'], []],
    'held rows ticket mismatch count' => [static fn (): mixed => count($ticketHeld187()['held_rows']), 4],
    'visible names held' => [static fn (): mixed => array_column($held187()['visible_returning_rows'], 'name'), ['base_url', 'current_module', 'base_url:child', 'current_module:child', 'base_url:child:child', 'current_module:child:child']],
    'visible names exposed' => [static fn (): mixed => array_column($exposed187()['visible_returning_rows'], 'name'), ['base_url', 'current_module', 'base_url:child', 'current_module:child', 'base_url:child:child', 'current_module:child:child', 'landing_url', 'next_module', 'landing_url:child', 'landing_url:child:child']],
    'held names mismatch' => [static fn (): mixed => array_column($ticketHeld187()['held_returning_rows'], 'name'), ['landing_url', 'next_module', 'landing_url:child', 'landing_url:child:child']],
    'current rows visible unique' => [static fn (): mixed => array_values(array_unique(array_column($exposed187()['current_source_rows'], 'visible_after_drain_ticket'))), [true]],
    'next rows visible exposed unique' => [static fn (): mixed => array_values(array_unique(array_column($exposed187()['attempted_next_source_rows'], 'visible_after_drain_ticket'))), [true]],
    'next rows held mismatch unique' => [static fn (): mixed => array_values(array_unique(array_column($ticketHeld187()['attempted_next_source_rows'], 'visible_after_drain_ticket'))), [false]],
    'held block reasons' => [static fn (): mixed => $held187()['block_reasons'], ['current-checkpoint-ack-missing', 'next-checkpoints-still-pending']],
    'ticket mismatch block reasons' => [static fn (): mixed => $ticketHeld187()['block_reasons'], ['current-source-drain-ticket-mismatch']],
    'missing ack block reasons' => [static fn (): mixed => $missingAck187()['block_reasons'], ['current-checkpoint-ack-missing']],
    'exposed block reasons empty' => [static fn (): mixed => $exposed187()['block_reasons'], []],
    'held next row reasons' => [static fn (): mixed => $ticketHeld187()['attempted_next_source_rows'][0]['held_by_drain_ticket_reasons'], ['current-source-drain-ticket-mismatch']],
    'exposed next row reasons empty' => [static fn (): mixed => $exposed187()['attempted_next_source_rows'][0]['held_by_drain_ticket_reasons'], []],
    'ticket plan required count' => [static fn (): mixed => $exposed187()['ticket_plan']['required_checkpoint_count'], 2],
    'ticket plan acknowledged count' => [static fn (): mixed => $exposed187()['ticket_plan']['acknowledged_checkpoint_count'], 2],
    'ticket plan held acknowledged count' => [static fn (): mixed => $held187()['ticket_plan']['acknowledged_checkpoint_count'], 0],
    'ticket plan held next count' => [static fn (): mixed => $held187()['ticket_plan']['held_next_row_count'], 4],
    'ticket plan exposed held next zero' => [static fn (): mixed => $exposed187()['ticket_plan']['held_next_row_count'], 0],
    'ticket plan resume token' => [static fn (): mixed => $exposed187()['ticket_plan']['resume_after_token'], 'app_recursive_view_returning_cursor_187:app-current-returning-187:5'],
    'ticket plan blocked token mismatch' => [static fn (): mixed => $ticketHeld187()['ticket_plan']['blocked_at_token'], 'app_recursive_view_returning_cursor_187:app-next-returning-187:6'],
    'ticket plan blocked exposed null' => [static fn (): mixed => $exposed187()['ticket_plan']['blocked_at_token'], null],
    'counts current rows' => [static fn (): mixed => $exposed187()['counts']['current_rows'], 6],
    'counts next rows' => [static fn (): mixed => $exposed187()['counts']['attempted_next_rows'], 4],
    'counts visible exposed' => [static fn (): mixed => $exposed187()['counts']['visible_rows'], 10],
    'counts held mismatch' => [static fn (): mixed => $ticketHeld187()['counts']['held_rows'], 4],
    'yield boundary held' => [static fn (): mixed => $held187()['yield_boundary'], 'recursive-view-returning-current-source-next187-drain-ticket-held'],
    'yield boundary exposed' => [static fn (): mixed => $exposed187()['yield_boundary'], 'recursive-view-returning-current-source-next187-drain-ticket-next-exposed'],
    'dependency next187' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next187', $exposed187()['dependencies'], true), true],
    'dependency drain ticket' => [static fn (): mixed => in_array('sqlite-returning-current-source-drain-ticket', $exposed187()['dependencies'], true), true],
    'dependency next184 retained' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next184', $exposed187()['dependencies'], true), true],
    'dependency closure note' => [static fn (): mixed => $exposed187()['dependency_closure'], 'no new support component needed; reuses recursive view trigger RETURNING checkpoint handoff and adds current-source drain ticket validation'],
    'non overlap mentions next184' => [static fn (): mixed => str_contains($exposed187()['non_overlap'], 'next184'), true],
    'non recursive visible names' => [static fn (): mixed => array_column($nonRecursive187()['visible_returning_rows'], 'name'), ['base_url', 'current_module', 'landing_url', 'next_module']],
    'non recursive required checkpoint count' => [static fn (): mixed => $nonRecursive187()['ticket_plan']['required_checkpoint_count'], 1],
    'custom prefix accepted' => [static fn (): mixed => $run187(['admit_next_source' => true, 'auto_ack_current' => true, 'drain_ticket_prefix' => 'app.custom.drain.187'])['drain_ticket_prefix'], 'app.custom.drain.187'],
    'bad drain ticket rejected' => [static fn (): mixed => $run187(['drain_ticket' => 'bad token']), InvalidArgumentException::class],
    'bad expected drain ticket rejected' => [static fn (): mixed => $run187(['expected_drain_ticket' => 'bad token']), InvalidArgumentException::class],
    'bad drain prefix rejected' => [static fn (): mixed => $run187(['drain_ticket_prefix' => 'bad token']), InvalidArgumentException::class],
    'bad ack list rejected by base' => [static fn (): mixed => $run187(['acknowledged_current_checkpoints' => ['ok' => 'bad']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases187 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next187 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
