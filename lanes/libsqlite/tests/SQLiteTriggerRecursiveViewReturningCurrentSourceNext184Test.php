<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows184 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView184 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-184-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-184-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
    'audit_label' => 'current-recursive-view-trigger-184',
];
$nextView184 = $currentView184;
$nextView184['source'] = 'main@view-cookie-184-next';
$nextView184['trigger_source'] = 'main@trigger-cookie-184-next';
$nextView184['audit_label'] = 'next-recursive-view-trigger-184';
$currentInput184 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput184 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning184 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$run184 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentCheckpointHandoff(
    $rows184,
    $currentInput184,
    $nextInput184,
    $currentView184,
    $nextView184,
    $returning184,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_184',
        'cursor_name' => 'wp_recursive_view_returning_cursor_184',
        'current_generation' => 'wp-current-returning-184',
        'next_generation' => 'wp-next-returning-184',
        'checkpoint_name' => 'wp_recursive_view_checkpoint_184',
        'page_size' => 3,
    ],
);
$held184 = static fn (): array => $run184();
$ackHeld184 = static fn (): array => $run184([
    'acknowledged_current_checkpoints' => [
        'wp_recursive_view_checkpoint_184:wp-current-returning-184:0',
        'wp_recursive_view_checkpoint_184:wp-current-returning-184:1',
    ],
]);
$exposed184 = static fn (): array => $run184(['admit_next_source' => true, 'auto_ack_current' => true]);
$missingAck184 = static fn (): array => $run184(['admit_next_source' => true, 'acknowledged_current_checkpoints' => ['wp_recursive_view_checkpoint_184:wp-current-returning-184:0']]);
$unexpectedAck184 = static fn (): array => $run184([
    'admit_next_source' => true,
    'acknowledged_current_checkpoints' => [
        'wp_recursive_view_checkpoint_184:wp-current-returning-184:0',
        'wp_recursive_view_checkpoint_184:wp-current-returning-184:1',
        'wp_recursive_view_checkpoint_184:wp-current-returning-184:9',
    ],
]);
$badToken184 = static fn (): array => $run184(['admit_next_source' => true, 'auto_ack_current' => true, 'expected_handoff_token' => 'wp.returning.current.source.handoff.184.expected']);
$nonRecursive184 = static fn (): array => $run184(['admit_next_source' => true, 'auto_ack_current' => true, 'recursive_triggers' => false]);

$cases184 = [
    'held status no ack' => [static fn (): mixed => $held184()['status'], 'trigger-recursive-view-returning-current-source-next184-current-ack-held'],
    'ack held status no next admission' => [static fn (): mixed => $ackHeld184()['status'], 'trigger-recursive-view-returning-current-source-next184-next-admission-held'],
    'exposed status' => [static fn (): mixed => $exposed184()['status'], 'trigger-recursive-view-returning-current-source-next184-next-exposed'],
    'missing ack status' => [static fn (): mixed => $missingAck184()['status'], 'trigger-recursive-view-returning-current-source-next184-current-ack-held'],
    'unexpected ack status' => [static fn (): mixed => $unexpectedAck184()['status'], 'trigger-recursive-view-returning-current-source-next184-current-ack-held'],
    'token held status' => [static fn (): mixed => $badToken184()['status'], 'trigger-recursive-view-returning-current-source-next184-handoff-token-held'],
    'base checkpoint status held' => [static fn (): mixed => $held184()['base']['status'], 'trigger-recursive-view-returning-current-source-next181-current-checkpointed-next-pending'],
    'base checkpoint status exposed' => [static fn (): mixed => $exposed184()['base']['status'], 'trigger-recursive-view-returning-current-source-next181-checkpoints-admitted'],
    'handoff token retained' => [static fn (): mixed => $held184()['handoff_token'], 'wp.returning.current.source.handoff.184'],
    'handoff token match default' => [static fn (): mixed => $held184()['handoff_token_matches'], true],
    'handoff token mismatch recorded' => [static fn (): mixed => $badToken184()['handoff_token_matches'], false],
    'required checkpoint tokens' => [static fn (): mixed => $held184()['required_current_checkpoints'], [
        'wp_recursive_view_checkpoint_184:wp-current-returning-184:0',
        'wp_recursive_view_checkpoint_184:wp-current-returning-184:1',
    ]],
    'auto ack tokens' => [static fn (): mixed => $exposed184()['acknowledged_current_checkpoints'], $exposed184()['required_current_checkpoints']],
    'manual ack tokens' => [static fn (): mixed => $ackHeld184()['acknowledged_current_checkpoints'], $ackHeld184()['required_current_checkpoints']],
    'held missing tokens' => [static fn (): mixed => $held184()['missing_current_checkpoints'], $held184()['required_current_checkpoints']],
    'exposed missing tokens empty' => [static fn (): mixed => $exposed184()['missing_current_checkpoints'], []],
    'missing one ack' => [static fn (): mixed => $missingAck184()['missing_current_checkpoints'], ['wp_recursive_view_checkpoint_184:wp-current-returning-184:1']],
    'unexpected ack recorded' => [static fn (): mixed => $unexpectedAck184()['unexpected_current_checkpoints'], ['wp_recursive_view_checkpoint_184:wp-current-returning-184:9']],
    'current incomplete held' => [static fn (): mixed => $held184()['current_handoff_complete'], false],
    'current complete exposed' => [static fn (): mixed => $exposed184()['current_handoff_complete'], true],
    'next not exposed held' => [static fn (): mixed => $held184()['next_source_exposed_after_handoff'], false],
    'next not exposed without next admission' => [static fn (): mixed => $ackHeld184()['next_source_exposed_after_handoff'], false],
    'next exposed after ack and admission' => [static fn (): mixed => $exposed184()['next_source_exposed_after_handoff'], true],
    'current ack count held' => [static fn (): mixed => count($held184()['current_checkpoint_acks']), 2],
    'current ack count exposed' => [static fn (): mixed => count($exposed184()['current_checkpoint_acks']), 2],
    'current first ack false held' => [static fn (): mixed => $held184()['current_checkpoint_acks'][0]['acknowledged'], false],
    'current first ack true exposed' => [static fn (): mixed => $exposed184()['current_checkpoint_acks'][0]['acknowledged'], true],
    'current second ack token' => [static fn (): mixed => $exposed184()['current_checkpoint_acks'][1]['checkpoint_token'], 'wp_recursive_view_checkpoint_184:wp-current-returning-184:1'],
    'next pending ack count held' => [static fn (): mixed => count($held184()['next_checkpoint_acks']), 2],
    'next ack count exposed' => [static fn (): mixed => count($exposed184()['next_checkpoint_acks']), 0],
    'current rows count' => [static fn (): mixed => count($held184()['current_source_rows']), 6],
    'next rows count' => [static fn (): mixed => count($held184()['attempted_next_source_rows']), 4],
    'visible held current only' => [static fn (): mixed => count($held184()['visible_rows']), 6],
    'held next rows count' => [static fn (): mixed => count($held184()['held_rows']), 4],
    'visible exposed all rows' => [static fn (): mixed => count($exposed184()['visible_rows']), 10],
    'held exposed empty' => [static fn (): mixed => $exposed184()['held_rows'], []],
    'visible names held' => [static fn (): mixed => array_column($held184()['visible_returning_rows'], 'name'), ['siteurl', 'current_plugin', 'siteurl:child', 'current_plugin:child', 'siteurl:child:child', 'current_plugin:child:child']],
    'held names held' => [static fn (): mixed => array_column($held184()['held_returning_rows'], 'name'), ['home', 'next_plugin', 'home:child', 'home:child:child']],
    'visible names exposed' => [static fn (): mixed => array_column($exposed184()['visible_returning_rows'], 'name'), ['siteurl', 'current_plugin', 'siteurl:child', 'current_plugin:child', 'siteurl:child:child', 'current_plugin:child:child', 'home', 'next_plugin', 'home:child', 'home:child:child']],
    'current row handoff phase' => [static fn (): mixed => array_values(array_unique(array_column($held184()['current_source_rows'], 'handoff_phase'))), ['current']],
    'next row handoff phase' => [static fn (): mixed => array_values(array_unique(array_column($held184()['attempted_next_source_rows'], 'handoff_phase'))), ['next']],
    'current rows visible after handoff' => [static fn (): mixed => array_values(array_unique(array_column($held184()['current_source_rows'], 'visible_after_handoff'))), [true]],
    'held next rows invisible after handoff' => [static fn (): mixed => array_values(array_unique(array_column($held184()['attempted_next_source_rows'], 'visible_after_handoff'))), [false]],
    'exposed next rows visible after handoff' => [static fn (): mixed => array_values(array_unique(array_column($exposed184()['attempted_next_source_rows'], 'visible_after_handoff'))), [true]],
    'held block reason missing ack' => [static fn (): mixed => $held184()['block_reasons'], ['current-checkpoint-ack-missing', 'next-checkpoints-still-pending']],
    'ack held block reason next pending' => [static fn (): mixed => $ackHeld184()['block_reasons'], ['next-checkpoints-still-pending']],
    'missing block reason' => [static fn (): mixed => $missingAck184()['block_reasons'], ['current-checkpoint-ack-missing']],
    'unexpected block reason' => [static fn (): mixed => $unexpectedAck184()['block_reasons'], ['current-checkpoint-ack-unexpected']],
    'bad token block reason' => [static fn (): mixed => $badToken184()['block_reasons'], ['handoff-token-mismatch']],
    'exposed block reasons empty' => [static fn (): mixed => $exposed184()['block_reasons'], []],
    'held row copied reasons' => [static fn (): mixed => $held184()['held_rows'][0]['held_by_handoff_reasons'], ['current-checkpoint-ack-missing', 'next-checkpoints-still-pending']],
    'exposed next row reasons empty' => [static fn (): mixed => $exposed184()['attempted_next_source_rows'][0]['held_by_handoff_reasons'], []],
    'handoff current generation' => [static fn (): mixed => $held184()['handoff_plan']['current_generation'], 'wp-current-returning-184'],
    'handoff next generation' => [static fn (): mixed => $held184()['handoff_plan']['next_generation'], 'wp-next-returning-184'],
    'handoff resume after token' => [static fn (): mixed => $held184()['handoff_plan']['resume_after_token'], 'wp_recursive_view_returning_cursor_184:wp-current-returning-184:5'],
    'handoff blocked token held' => [static fn (): mixed => $held184()['handoff_plan']['blocked_at_token'], 'wp_recursive_view_returning_cursor_184:wp-next-returning-184:6'],
    'handoff blocked token exposed null' => [static fn (): mixed => $exposed184()['handoff_plan']['blocked_at_token'], null],
    'handoff current checkpoint count' => [static fn (): mixed => $held184()['handoff_plan']['current_checkpoint_count'], 2],
    'handoff acknowledged count exposed' => [static fn (): mixed => $exposed184()['handoff_plan']['acknowledged_checkpoint_count'], 2],
    'handoff next row count' => [static fn (): mixed => $held184()['handoff_plan']['next_row_count'], 4],
    'counts required current checkpoints' => [static fn (): mixed => $held184()['counts']['required_current_checkpoints'], 2],
    'counts ack held zero' => [static fn (): mixed => $held184()['counts']['acknowledged_current_checkpoints'], 0],
    'counts ack exposed two' => [static fn (): mixed => $exposed184()['counts']['acknowledged_current_checkpoints'], 2],
    'counts missing held two' => [static fn (): mixed => $held184()['counts']['missing_current_checkpoints'], 2],
    'counts visible exposed ten' => [static fn (): mixed => $exposed184()['counts']['visible_rows'], 10],
    'counts held exposed zero' => [static fn (): mixed => $exposed184()['counts']['held_rows'], 0],
    'yield boundary held' => [static fn (): mixed => $held184()['yield_boundary'], 'recursive-view-returning-current-source-next184-next-source-held'],
    'yield boundary exposed' => [static fn (): mixed => $exposed184()['yield_boundary'], 'recursive-view-returning-current-source-next184-next-source-exposed'],
    'dependency next184' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next184', $held184()['dependencies'], true), true],
    'dependency handoff ack' => [static fn (): mixed => in_array('sqlite-returning-current-source-handoff-ack', $held184()['dependencies'], true), true],
    'dependency next181 retained' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next181', $held184()['dependencies'], true), true],
    'dependency closure note' => [static fn (): mixed => $held184()['dependency_closure'], 'no new support component needed; reuses recursive view trigger RETURNING checkpoint and cursor metadata'],
    'non overlap mentions next181' => [static fn (): mixed => str_contains($held184()['non_overlap'], 'next181'), true],
    'non recursive visible names' => [static fn (): mixed => array_column($nonRecursive184()['visible_returning_rows'], 'name'), ['siteurl', 'current_plugin', 'home', 'next_plugin']],
    'non recursive current checkpoints' => [static fn (): mixed => $nonRecursive184()['counts']['required_current_checkpoints'], 1],
    'non recursive held rows empty' => [static fn (): mixed => $nonRecursive184()['held_rows'], []],
    'bad handoff token rejected' => [static fn (): mixed => $run184(['handoff_token' => 'bad token']), InvalidArgumentException::class],
    'bad expected handoff token rejected' => [static fn (): mixed => $run184(['expected_handoff_token' => 'bad token']), InvalidArgumentException::class],
    'bad ack token rejected' => [static fn (): mixed => $run184(['acknowledged_current_checkpoints' => ['bad token']]), InvalidArgumentException::class],
    'bad ack list rejected' => [static fn (): mixed => $run184(['acknowledged_current_checkpoints' => ['ok' => 'bad']]), InvalidArgumentException::class],
    'bad checkpoint name rejected from base' => [static fn (): mixed => $run184(['checkpoint_name' => 'bad checkpoint']), InvalidArgumentException::class],
    'bad page size rejected from base' => [static fn (): mixed => $run184(['page_size' => 0]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases184 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next184 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
