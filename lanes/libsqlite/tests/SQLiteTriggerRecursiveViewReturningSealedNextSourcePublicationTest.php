<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows193 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView193 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-193-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-193-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
    'audit_label' => 'current-recursive-view-trigger-193',
];
$nextView193 = $currentView193;
$nextView193['source'] = 'main@view-cookie-193-next';
$nextView193['trigger_source'] = 'main@trigger-cookie-193-next';
$nextView193['audit_label'] = 'next-recursive-view-trigger-193';
$postResetView193 = $currentView193;
$postResetView193['source'] = 'main@view-cookie-193-post-reset';
$postResetView193['trigger_source'] = 'main@trigger-cookie-193-post-reset';
$postResetView193['audit_label'] = 'post-reset-recursive-view-trigger-193';
$currentInput193 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput193 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$postResetInput193 = [
    ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
    ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning193 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'old.option_value', 'as' => 'old_value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$plan193 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeSealedNextSourcePublication(
    $rows193,
    $currentInput193,
    $nextInput193,
    $currentView193,
    $nextView193,
    $returning193,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_193',
        'cursor_name' => 'wp_recursive_view_returning_cursor_193',
        'current_generation' => 'wp-current-returning-193',
        'next_generation' => 'wp-next-returning-193',
        'page_size' => 3,
        'admit_next_source' => true,
        'current_source_token' => 'wp.current.source.193',
        'drain_ack_token' => 'wp.returning.drain.193',
        'rollback_token' => 'wp.rollback.current.193',
        'reset_generation' => 'wp-current-reset-193',
        'post_reset_current_source_token' => 'wp.current.source.postreset.193',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.193',
        'post_reset_view' => $postResetView193,
        'post_reset_input' => $postResetInput193,
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.193',
        'next_cursor' => 'wp.returning.next.cursor.193',
        'handoff_token' => 'wp.recursive.view.returning.handoff.193',
    ],
);

$published193 = static fn (): array => $plan193();
$partial193 = static fn (): array => $plan193(['fresh_acknowledged_ordinals' => [0]]);
$rowCountHeld193 = static fn (): array => $plan193(['expected_next_row_count' => 3]);
$signatureHeld193 = static fn (): array => $plan193(['expected_next_source_signature' => 'different-sign193']);
$handoffHeld193 = static fn (): array => $plan193(['expected_handoff_token' => 'wp.recursive.view.returning.expected.193']);
$sequenceHeld193 = static fn (): array => $plan193(['expected_source_sequence_token' => 'seq-different193']);
$tokenHeld193 = static fn (): array => $plan193(['expected_next_source_token' => 'wp.next.source.expected.193']);
$custom193 = static fn (): array => $plan193(['handoff_token' => 'wp.recursive.view.returning.custom.193', 'expected_handoff_token' => 'wp.recursive.view.returning.custom.193']);

$cases193 = [
    'published status' => [static fn (): mixed => $published193()['status_next193'], 'trigger-recursive-view-returning-current-source-next193-published'],
    'partial status' => [static fn (): mixed => $partial193()['status_next193'], 'trigger-recursive-view-returning-current-source-next193-awaiting-next189'],
    'row count held status' => [static fn (): mixed => $rowCountHeld193()['status_next193'], 'trigger-recursive-view-returning-current-source-next193-row-count-held'],
    'signature held status' => [static fn (): mixed => $signatureHeld193()['status_next193'], 'trigger-recursive-view-returning-current-source-next193-signature-held'],
    'handoff held status' => [static fn (): mixed => $handoffHeld193()['status_next193'], 'trigger-recursive-view-returning-current-source-next193-handoff-token-held'],
    'sequence held status' => [static fn (): mixed => $sequenceHeld193()['status_next193'], 'trigger-recursive-view-returning-current-source-next193-sequence-held'],
    'savepoint retained' => [static fn (): mixed => $published193()['savepoint'], 'wp_recursive_view_193'],
    'base next189 admitted' => [static fn (): mixed => $published193()['base']['status_next189'], 'trigger-recursive-view-returning-current-source-next189-next-source-visible'],
    'partial base held' => [static fn (): mixed => $partial193()['base']['status_next189'], 'trigger-recursive-view-returning-current-source-next189-awaiting-current-row-acks'],
    'base admitted flag' => [static fn (): mixed => $published193()['base_next_source_admitted_next193'], true],
    'partial base admitted flag' => [static fn (): mixed => $partial193()['base_next_source_admitted_next193'], false],
    'handoff token retained' => [static fn (): mixed => $published193()['handoff_token_next193'], 'wp.recursive.view.returning.handoff.193'],
    'expected handoff token retained' => [static fn (): mixed => $published193()['expected_handoff_token_next193'], 'wp.recursive.view.returning.handoff.193'],
    'custom handoff token retained' => [static fn (): mixed => $custom193()['handoff_token_next193'], 'wp.recursive.view.returning.custom.193'],
    'handoff token matches' => [static fn (): mixed => $published193()['handoff_token_matches_next193'], true],
    'handoff token mismatch' => [static fn (): mixed => $handoffHeld193()['handoff_token_matches_next193'], false],
    'sequence token generated' => [static fn (): mixed => str_starts_with($published193()['source_sequence_token_next193'], 'seq-'), true],
    'sequence token expected generated' => [static fn (): mixed => $published193()['expected_source_sequence_token_next193'], $published193()['source_sequence_token_next193']],
    'sequence token matches' => [static fn (): mixed => $published193()['source_sequence_matches_next193'], true],
    'sequence token mismatch' => [static fn (): mixed => $sequenceHeld193()['source_sequence_matches_next193'], false],
    'signature retained length' => [static fn (): mixed => strlen($published193()['next_source_signature_next193']), 16],
    'signature expected retained' => [static fn (): mixed => $published193()['expected_next_source_signature_next193'], $published193()['next_source_signature_next193']],
    'signature matches' => [static fn (): mixed => $published193()['next_source_signature_matches_next193'], true],
    'signature mismatch' => [static fn (): mixed => $signatureHeld193()['next_source_signature_matches_next193'], false],
    'expected row count retained' => [static fn (): mixed => $published193()['expected_next_row_count_next193'], 2],
    'row count matches' => [static fn (): mixed => $published193()['next_row_count_matches_next193'], true],
    'row count mismatch' => [static fn (): mixed => $rowCountHeld193()['next_row_count_matches_next193'], false],
    'published row count' => [static fn (): mixed => $published193()['published_next_source_row_count_next193'], 2],
    'partial row count hidden' => [static fn (): mixed => $partial193()['published_next_source_row_count_next193'], 0],
    'row count held hides rows' => [static fn (): mixed => $rowCountHeld193()['published_next_source_rows_next193'], []],
    'signature held hides rows' => [static fn (): mixed => $signatureHeld193()['published_next_source_rows_next193'], []],
    'handoff held hides rows' => [static fn (): mixed => $handoffHeld193()['published_next_source_rows_next193'], []],
    'sequence held hides rows' => [static fn (): mixed => $sequenceHeld193()['published_next_source_rows_next193'], []],
    'token held hides rows' => [static fn (): mixed => $tokenHeld193()['published_next_source_rows_next193'], []],
    'published names' => [static fn (): mixed => array_column($published193()['published_next_source_payloads_next193'], 'name'), ['home', 'next_plugin']],
    'published values' => [static fn (): mixed => array_column($published193()['published_next_source_payloads_next193'], 'value'), ['https://next.test', 'active']],
    'published old values null' => [static fn (): mixed => array_column($published193()['published_next_source_payloads_next193'], 'old_value'), [null, null]],
    'published events' => [static fn (): mixed => array_values(array_unique(array_column($published193()['published_next_source_payloads_next193'], 'event_name'))), ['next-source']],
    'published ordinals' => [static fn (): mixed => array_column($published193()['published_next_source_payloads_next193'], 'ordinal_value'), [0, 1]],
    'published trigger source' => [static fn (): mixed => array_values(array_unique(array_column($published193()['published_next_source_payloads_next193'], 'trigger_source_alias'))), ['main@trigger-cookie-193-next']],
    'published flag stamped' => [static fn (): mixed => array_column($published193()['published_next_source_rows_next193'], 'published_next193'), [true, true]],
    'publish ordinals stamped' => [static fn (): mixed => array_column($published193()['published_next_source_rows_next193'], 'publish_ordinal_next193'), [0, 1]],
    'handoff token stamped' => [static fn (): mixed => array_values(array_unique(array_column($published193()['published_next_source_rows_next193'], 'handoff_token_next193'))), ['wp.recursive.view.returning.handoff.193']],
    'sequence token stamped' => [static fn (): mixed => array_values(array_unique(array_column($published193()['published_next_source_rows_next193'], 'source_sequence_token_next193'))), [$published193()['source_sequence_token_next193']]],
    'statement sources retained' => [static fn (): mixed => array_column($published193()['published_next_source_rows_next193'], 'statement_source'), ['next-source', 'next-source']],
    'next189 cursor retained' => [static fn (): mixed => array_values(array_unique(array_column($published193()['published_next_source_rows_next193'], 'next_cursor_next189'))), ['wp.returning.next.cursor.193']],
    'next189 token retained' => [static fn (): mixed => array_values(array_unique(array_column($published193()['published_next_source_rows_next193'], 'next_source_token_next189'))), ['wp.next.source.193']],
    'next189 generation retained' => [static fn (): mixed => array_values(array_unique(array_column($published193()['published_next_source_rows_next193'], 'next_generation_next189'))), ['wp-next-returning-193']],
    'blocked reasons empty' => [static fn (): mixed => $published193()['blocked_reasons_next193'], []],
    'partial blocked reasons' => [static fn (): mixed => $partial193()['blocked_reasons_next193'], ['fresh-current-returning-rows-not-acknowledged', 'next189-next-source-not-admitted']],
    'row count blocked reasons' => [static fn (): mixed => $rowCountHeld193()['blocked_reasons_next193'], ['next-source-row-count-mismatch']],
    'signature blocked reasons' => [static fn (): mixed => $signatureHeld193()['blocked_reasons_next193'], ['next-source-signature-mismatch']],
    'handoff blocked reasons' => [static fn (): mixed => $handoffHeld193()['blocked_reasons_next193'], ['handoff-token-mismatch']],
    'sequence blocked reasons' => [static fn (): mixed => $sequenceHeld193()['blocked_reasons_next193'], ['source-sequence-token-mismatch']],
    'handoff current rows required' => [static fn (): mixed => $published193()['current_source_returning_handoff_next193']['fresh_current_rows_required'], 2],
    'handoff current rows acknowledged' => [static fn (): mixed => $published193()['current_source_returning_handoff_next193']['fresh_current_rows_acknowledged'], 2],
    'handoff candidate rows' => [static fn (): mixed => $published193()['current_source_returning_handoff_next193']['candidate_next_rows'], 2],
    'handoff published rows' => [static fn (): mixed => $published193()['current_source_returning_handoff_next193']['published_next_rows'], 2],
    'partial handoff published rows' => [static fn (): mixed => $partial193()['current_source_returning_handoff_next193']['published_next_rows'], 0],
    'handoff decision publish' => [static fn (): mixed => $published193()['current_source_returning_handoff_next193']['decision'], 'publish-sealed-next-source-after-current-drain'],
    'partial handoff decision' => [static fn (): mixed => $partial193()['current_source_returning_handoff_next193']['decision'], 'hold-next-source-until-next189-admission'],
    'row count decision' => [static fn (): mixed => $rowCountHeld193()['current_source_returning_handoff_next193']['decision'], 'hold-next-source-row-count'],
    'signature decision' => [static fn (): mixed => $signatureHeld193()['current_source_returning_handoff_next193']['decision'], 'hold-next-source-signature'],
    'handoff token decision' => [static fn (): mixed => $handoffHeld193()['current_source_returning_handoff_next193']['decision'], 'hold-next-source-handoff-token'],
    'sequence decision' => [static fn (): mixed => $sequenceHeld193()['current_source_returning_handoff_next193']['decision'], 'hold-next-source-sequence-token'],
    'yield boundary published' => [static fn (): mixed => $published193()['yield_boundary_next193'], 'recursive-view-returning-next193-next-source-sealed-after-current-drain'],
    'yield boundary quarantined' => [static fn (): mixed => $partial193()['yield_boundary_next193'], 'recursive-view-returning-next193-next-source-quarantined'],
    'dependency closure marker' => [static fn (): mixed => $published193()['dependency_closure_next193'], 'no new support component needed; reuses next189 current-row acknowledgements and adds source-signature handoff sealing'],
    'dependency includes next193' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next193', $published193()['dependencies_next193'], true), true],
    'dependency includes seal' => [static fn (): mixed => in_array('sqlite-returning-current-source-handoff-seal', $published193()['dependencies_next193'], true), true],
    'dependency includes next189' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next189', $published193()['dependencies_next193'], true), true],
    'non overlap mentions next189' => [static fn (): mixed => str_contains($published193()['non_overlap_next193'], 'next189 row-ack'), true],
    'bad handoff token rejected' => [static fn (): mixed => $plan193(['handoff_token' => 'bad token']), InvalidArgumentException::class],
    'bad expected handoff token rejected' => [static fn (): mixed => $plan193(['expected_handoff_token' => 'bad token']), InvalidArgumentException::class],
    'bad sequence token rejected' => [static fn (): mixed => $plan193(['source_sequence_token' => 'bad token']), InvalidArgumentException::class],
    'bad expected sequence token rejected' => [static fn (): mixed => $plan193(['expected_source_sequence_token' => 'bad token']), InvalidArgumentException::class],
    'bad expected row count rejected' => [static fn (): mixed => $plan193(['expected_next_row_count' => -1]), InvalidArgumentException::class],
    'bad signature rejected' => [static fn (): mixed => $plan193(['expected_next_source_signature' => 'bad signature']), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases193 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning sealed next source publication ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
