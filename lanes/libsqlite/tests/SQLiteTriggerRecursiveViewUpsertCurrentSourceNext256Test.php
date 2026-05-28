<?php

declare(strict_types=1);

$existing253Tests = require __DIR__ . '/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext253Test.php';
unset($existing253Tests);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNext256Plan;

$plan256 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewUpsertCurrentSourceNext256Plan::execute(
    $baseRows253,
    $currentInput253,
    $nextInput253,
    $view253,
    $nextView253,
    $returning253,
    $options + $baseOptions253 + [
        'auto_ack_current_source_view_materialization_next253' => true,
        'current_source_view_upsert_handoff_token_next256' => 'wp.current.source.view.upsert.handoff.256',
    ],
);

$receipts256 = static fn (array $options = []): array => $plan256($options)['required_current_source_view_upsert_handoff_receipts_next256'];
$released256 = static fn (array $options = []): array => $plan256($options + ['auto_ack_current_source_view_upsert_handoff_next256' => true]);
$missing256 = static fn (): array => $plan256(['acknowledged_current_source_view_upsert_handoff_receipts_next256' => array_slice($receipts256(), 0, 1)]);
$unexpected256 = static fn (): array => $plan256(['acknowledged_current_source_view_upsert_handoff_receipts_next256' => array_merge($receipts256(), ['abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdefabcd'])]);
$tokenHeld256 = static fn (): array => $plan256(['auto_ack_current_source_view_upsert_handoff_next256' => true, 'expected_current_source_view_upsert_handoff_token_next256' => 'wp.current.source.view.upsert.handoff.stale.256']);
$orderHeld256 = static fn (): array => $plan256(['acknowledged_current_source_view_upsert_handoff_receipts_next256' => array_reverse($receipts256())]);
$orderIgnored256 = static fn (): array => $plan256(['acknowledged_current_source_view_upsert_handoff_receipts_next256' => array_reverse($receipts256()), 'require_current_source_view_upsert_handoff_order_next256' => false]);
$baseHeld256 = static fn (): array => $plan256(['auto_ack_current_source_view_upsert_handoff_next256' => true, 'auto_ack_current_source_view_materialization_next253' => false]);
$batched256 = static fn (): array => $released256(['current_source_view_upsert_handoff_batch_size_next256' => 2]);

$cases256 = [
    'released status' => [static fn (): mixed => $released256()['status_next256'], 'trigger-recursive-view-upsert-current-source-next256-handoff-released'],
    'missing status' => [static fn (): mixed => $missing256()['status_next256'], 'trigger-recursive-view-upsert-current-source-next256-handoff-missing-held'],
    'unexpected status' => [static fn (): mixed => $unexpected256()['status_next256'], 'trigger-recursive-view-upsert-current-source-next256-handoff-unexpected-held'],
    'token held status' => [static fn (): mixed => $tokenHeld256()['status_next256'], 'trigger-recursive-view-upsert-current-source-next256-handoff-token-held'],
    'order held status' => [static fn (): mixed => $orderHeld256()['status_next256'], 'trigger-recursive-view-upsert-current-source-next256-handoff-order-held'],
    'base held status' => [static fn (): mixed => $baseHeld256()['status_next256'], 'trigger-recursive-view-upsert-current-source-next256-base-held'],
    'savepoint retained' => [static fn (): mixed => $released256()['savepoint'], 'wp_recursive_view_253'],
    'base status retained' => [static fn (): mixed => $released256()['base']['status_next253'], 'trigger-recursive-view-upsert-current-source-next253-view-materialization-released'],
    'base visible released' => [static fn (): mixed => $released256()['base_next_source_visible_next256'], true],
    'base visible held' => [static fn (): mixed => $baseHeld256()['base_next_source_visible_next256'], false],
    'token retained' => [static fn (): mixed => $released256()['current_source_view_upsert_handoff_token_next256'], 'wp.current.source.view.upsert.handoff.256'],
    'expected token retained' => [static fn (): mixed => $released256()['expected_current_source_view_upsert_handoff_token_next256'], 'wp.current.source.view.upsert.handoff.256'],
    'token matches released' => [static fn (): mixed => $released256()['current_source_view_upsert_handoff_token_matches_next256'], true],
    'token mismatch detected' => [static fn (): mixed => $tokenHeld256()['current_source_view_upsert_handoff_token_matches_next256'], false],
    'batch size default' => [static fn (): mixed => $released256()['current_source_view_upsert_handoff_batch_size_next256'], 1],
    'batch size custom' => [static fn (): mixed => $batched256()['current_source_view_upsert_handoff_batch_size_next256'], 2],
    'default batch count' => [static fn (): mixed => count($released256()['current_source_view_upsert_handoff_batches_next256']), 2],
    'custom batch count' => [static fn (): mixed => count($batched256()['current_source_view_upsert_handoff_batches_next256']), 1],
    'first batch row count' => [static fn (): mixed => $released256()['current_source_view_upsert_handoff_batches_next256'][0]['row_count'], 1],
    'batched row count' => [static fn (): mixed => $batched256()['current_source_view_upsert_handoff_batches_next256'][0]['row_count'], 2],
    'first batch first ordinal' => [static fn (): mixed => $released256()['current_source_view_upsert_handoff_batches_next256'][0]['first_ordinal'], 0],
    'second batch last ordinal' => [static fn (): mixed => $released256()['current_source_view_upsert_handoff_batches_next256'][1]['last_ordinal'], 1],
    'batch projection hashes count' => [static fn (): mixed => count($batched256()['current_source_view_upsert_handoff_batches_next256'][0]['projection_hashes']), 2],
    'batch rowid receipts count' => [static fn (): mixed => count($batched256()['current_source_view_upsert_handoff_batches_next256'][0]['rowid_receipts_next250']), 2],
    'required receipt count' => [static fn (): mixed => count($released256()['required_current_source_view_upsert_handoff_receipts_next256']), 2],
    'custom required receipt count' => [static fn (): mixed => count($batched256()['required_current_source_view_upsert_handoff_receipts_next256']), 1],
    'receipts are fifty two hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{52}$/', $v), $released256()['required_current_source_view_upsert_handoff_receipts_next256']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released256()['acknowledged_current_source_view_upsert_handoff_receipts_next256'], $receipts256()],
    'missing receipt recorded' => [static fn (): mixed => $missing256()['missing_current_source_view_upsert_handoff_receipts_next256'], [array_slice($receipts256(), -1)[0]]],
    'unexpected receipt count' => [static fn (): mixed => count($unexpected256()['unexpected_current_source_view_upsert_handoff_receipts_next256']), 1],
    'released missing empty' => [static fn (): mixed => $released256()['missing_current_source_view_upsert_handoff_receipts_next256'], []],
    'released unexpected empty' => [static fn (): mixed => $released256()['unexpected_current_source_view_upsert_handoff_receipts_next256'], []],
    'order required default' => [static fn (): mixed => $released256()['require_current_source_view_upsert_handoff_order_next256'], true],
    'order mismatch detected' => [static fn (): mixed => $orderHeld256()['current_source_view_upsert_handoff_order_matches_next256'], false],
    'order ignored released' => [static fn (): mixed => $orderIgnored256()['status_next256'], 'trigger-recursive-view-upsert-current-source-next256-handoff-released'],
    'complete released' => [static fn (): mixed => $released256()['current_source_view_upsert_handoff_complete_next256'], true],
    'complete missing false' => [static fn (): mixed => $missing256()['current_source_view_upsert_handoff_complete_next256'], false],
    'next visible released' => [static fn (): mixed => $released256()['next_source_visible_after_current_source_view_upsert_handoff_next256'], true],
    'next denied missing' => [static fn (): mixed => $missing256()['next_source_visible_after_current_source_view_upsert_handoff_next256'], false],
    'visible released count' => [static fn (): mixed => $released256()['visible_row_count_next256'], 4],
    'held released count' => [static fn (): mixed => $released256()['held_next_row_count_next256'], 0],
    'visible missing current only' => [static fn (): mixed => $missing256()['visible_row_count_next256'], 2],
    'held missing next only' => [static fn (): mixed => $missing256()['held_next_row_count_next256'], 2],
    'current handoff receipts tagged' => [static fn (): mixed => array_column($released256()['current_source_rows_next256'], 'current_source_view_upsert_handoff_receipt_next256'), $receipts256()],
    'next handoff receipt null' => [static fn (): mixed => array_values(array_unique(array_column($released256()['attempted_next_source_rows_next256'], 'current_source_view_upsert_handoff_receipt_next256'))), [null]],
    'visible payload names released' => [static fn (): mixed => array_column($released256()['visible_returning_payloads_next256'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held payload names missing' => [static fn (): mixed => array_column($missing256()['held_next_returning_payloads_next256'], 'name'), ['home', 'next_plugin']],
    'blocked reasons released' => [static fn (): mixed => $released256()['blocked_reasons_next256'], []],
    'blocked reasons missing' => [static fn (): mixed => $missing256()['blocked_reasons_next256'], ['current-source-view-upsert-handoff-missing']],
    'blocked reasons token' => [static fn (): mixed => $tokenHeld256()['blocked_reasons_next256'], ['current-source-view-upsert-handoff-token-mismatch']],
    'blocked reasons order' => [static fn (): mixed => $orderHeld256()['blocked_reasons_next256'], ['current-source-view-upsert-handoff-order-mismatch']],
    'held row reason copied' => [static fn (): mixed => $missing256()['held_next_source_rows_next256'][0]['held_by_current_source_view_upsert_handoff_reasons_next256'], ['current-source-view-upsert-handoff-missing']],
    'plan decision released' => [static fn (): mixed => $released256()['current_source_view_upsert_handoff_plan_next256']['decision'], 'publish-next-source-after-current-recursive-view-upsert-handoff'],
    'plan decision held' => [static fn (): mixed => $missing256()['current_source_view_upsert_handoff_plan_next256']['decision'], 'hold-next-source-until-current-recursive-view-upsert-handoff'],
    'plan batch count' => [static fn (): mixed => $released256()['current_source_view_upsert_handoff_plan_next256']['batch_count'], 2],
    'yield boundary released' => [static fn (): mixed => $released256()['yield_boundary_next256'], 'recursive-view-upsert-next256-current-handoff-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing256()['yield_boundary_next256'], 'recursive-view-upsert-next256-current-handoff-fence-next'],
    'dependency closure marker' => [static fn (): mixed => $released256()['dependency_closure_next256'], 'no-new-support-component-reuses-native-recursive-view-upsert-materialization-and-adds-current-source-handoff-batch-receipts'],
    'dependency includes next256' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next256', $released256()['dependencies_next256'], true), true],
    'dependency includes handoff' => [static fn (): mixed => in_array('sqlite-instead-of-view-trigger-upsert-current-source-handoff', $released256()['dependencies_next256'], true), true],
    'dependency includes next253' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next253', $released256()['dependencies_next256'], true), true],
    'non overlap mentions next253' => [static fn (): mixed => str_contains($released256()['non_overlap_next256'], 'next253 materialized'), true],
    'non overlap mentions row value' => [static fn (): mixed => str_contains($released256()['non_overlap_next256'], 'row-value'), true],
    'bad token rejected' => [static fn (): mixed => $plan256(['current_source_view_upsert_handoff_token_next256' => 'bad token']), InvalidArgumentException::class],
    'bad expected token rejected' => [static fn (): mixed => $plan256(['expected_current_source_view_upsert_handoff_token_next256' => 'bad token']), InvalidArgumentException::class],
    'bad batch rejected' => [static fn (): mixed => $plan256(['current_source_view_upsert_handoff_batch_size_next256' => 0]), InvalidArgumentException::class],
    'bad receipt list rejected' => [static fn (): mixed => $plan256(['acknowledged_current_source_view_upsert_handoff_receipts_next256' => ['x' => 'abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdefabcd']]), InvalidArgumentException::class],
    'bad short receipt rejected' => [static fn (): mixed => $plan256(['acknowledged_current_source_view_upsert_handoff_receipts_next256' => ['abc']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases256 as $name => [$callback, $expected]) {
    $tests['trigger recursive view upsert current source next256 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
