<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$rows238 = [
    ['option_name' => 'siteurl', 'option_value' => 'https://old.test'],
    ['option_name' => 'home', 'option_value' => 'https://old-home.test'],
    ['option_name' => 'rewrite_rules', 'option_value' => 'old-rules'],
];
$view238 = [
    'name' => 'wp_option_import_view',
    'source' => 'main@cookie238-current',
    'mapping' => ['name' => 'option_name', 'value' => 'option_value'],
];
$triggers238 = [
    ['name' => 'wp_options_au_home', 'when' => 'siteurl', 'target' => 'home', 'value' => '{value}/home'],
    ['name' => 'wp_options_au_rewrite', 'when' => 'home', 'target' => 'rewrite_rules', 'value' => 'flushed:{value}'],
];
$current238 = [
    ['name' => 'siteurl', 'value' => 'https://current.test'],
    ['name' => 'blogname', 'value' => 'Current Blog'],
];
$next238 = [
    ['name' => 'siteurl', 'value' => 'https://next.test'],
    ['name' => 'fresh_plugin', 'value' => 'enabled'],
];

$plan238 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeNext238(
    $rows238,
    $current238,
    $next238,
    $view238,
    ['option_name'],
    $triggers238,
    $options + [
        'savepoint' => 'wp_view_recursive_238',
        'current_upsert_source_next232' => 'wp.current.upsert.source.238',
        'current_view_source_next232' => 'main@cookie238-current',
        'current_trigger_program_next232' => 'wp.current.recursive.trigger.program.238',
        'current_yield_ticket_source_next235' => 'wp.current.yield.ticket.source.238',
        'current_yield_resume_cursor_next235' => 'wp.current.yield.cursor.238',
        'current_resume_source_next238' => 'wp.current.resume.source.238',
        'current_resume_cursor_next238' => 'wp.current.resume.cursor.238',
        'current_resume_epoch_next238' => 'wp.current.resume.epoch.238',
    ],
);

$receipts238 = static fn (): array => $plan238()['required_current_resume_receipts_next238'];
$released238 = static fn (): array => $plan238(['auto_ack_current_resume_receipts_next238' => true]);
$missing238 = static fn (): array => $plan238(['acknowledged_current_resume_receipts_next238' => array_slice($receipts238(), 0, 3)]);
$unexpectedReceipt238 = 'abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdefabcd';
$unexpected238 = static fn (): array => $plan238(['acknowledged_current_resume_receipts_next238' => array_merge($receipts238(), [$unexpectedReceipt238])]);
$reversed238 = static fn (): array => $plan238(['acknowledged_current_resume_receipts_next238' => array_reverse($receipts238())]);
$unordered238 = static fn (): array => $plan238(['require_current_resume_receipt_order_next238' => false, 'acknowledged_current_resume_receipts_next238' => array_reverse($receipts238())]);
$sourceHeld238 = static fn (): array => $plan238(['auto_ack_current_resume_receipts_next238' => true, 'expected_current_resume_source_next238' => 'wp.current.resume.source.stale.238']);
$cursorHeld238 = static fn (): array => $plan238(['auto_ack_current_resume_receipts_next238' => true, 'expected_current_resume_cursor_next238' => 'wp.current.resume.cursor.stale.238']);
$epochHeld238 = static fn (): array => $plan238(['auto_ack_current_resume_receipts_next238' => true, 'expected_current_resume_epoch_next238' => 'wp.current.resume.epoch.stale.238']);
$baseHeld238 = static fn (): array => $plan238(['auto_ack_current_resume_receipts_next238' => true, 'expected_current_yield_ticket_source_next235' => 'wp.current.yield.ticket.source.stale.238']);
$custom238 = static fn (): array => $plan238([
    'auto_ack_current_resume_receipts_next238' => true,
    'current_resume_source_next238' => 'wp.current.resume.source.custom.238',
    'current_resume_cursor_next238' => 'wp.current.resume.cursor.custom.238',
    'current_resume_epoch_next238' => 'wp.current.resume.epoch.custom.238',
]);

$cases238 = [
    'released status' => [static fn (): mixed => $released238()['status_next238'], 'trigger-recursive-view-upsert-current-source-next238-resume-released'],
    'missing status' => [static fn (): mixed => $missing238()['status_next238'], 'trigger-recursive-view-upsert-current-source-next238-resume-receipt-held'],
    'unexpected status' => [static fn (): mixed => $unexpected238()['status_next238'], 'trigger-recursive-view-upsert-current-source-next238-resume-receipt-held'],
    'reversed status' => [static fn (): mixed => $reversed238()['status_next238'], 'trigger-recursive-view-upsert-current-source-next238-resume-order-held'],
    'unordered reversed releases' => [static fn (): mixed => $unordered238()['status_next238'], 'trigger-recursive-view-upsert-current-source-next238-resume-released'],
    'source held status' => [static fn (): mixed => $sourceHeld238()['status_next238'], 'trigger-recursive-view-upsert-current-source-next238-resume-source-held'],
    'cursor held status' => [static fn (): mixed => $cursorHeld238()['status_next238'], 'trigger-recursive-view-upsert-current-source-next238-resume-cursor-held'],
    'epoch held status' => [static fn (): mixed => $epochHeld238()['status_next238'], 'trigger-recursive-view-upsert-current-source-next238-resume-epoch-held'],
    'base held status' => [static fn (): mixed => $baseHeld238()['status_next238'], 'trigger-recursive-view-upsert-current-source-next238-base-yield-held'],
    'savepoint retained' => [static fn (): mixed => $released238()['savepoint'], 'wp_view_recursive_238'],
    'base next235 released' => [static fn (): mixed => $released238()['base_yield_ticket_released_next238'], true],
    'base mismatch not released' => [static fn (): mixed => $baseHeld238()['base_yield_ticket_released_next238'], false],
    'base status retained' => [static fn (): mixed => $released238()['base_next238']['status_next235'], 'trigger-recursive-view-upsert-current-source-next235-yield-released'],
    'resume source retained' => [static fn (): mixed => $released238()['current_resume_source_next238'], 'wp.current.resume.source.238'],
    'custom resume source retained' => [static fn (): mixed => $custom238()['current_resume_source_next238'], 'wp.current.resume.source.custom.238'],
    'resume cursor retained' => [static fn (): mixed => $released238()['current_resume_cursor_next238'], 'wp.current.resume.cursor.238'],
    'custom resume cursor retained' => [static fn (): mixed => $custom238()['current_resume_cursor_next238'], 'wp.current.resume.cursor.custom.238'],
    'resume epoch retained' => [static fn (): mixed => $released238()['current_resume_epoch_next238'], 'wp.current.resume.epoch.238'],
    'custom resume epoch retained' => [static fn (): mixed => $custom238()['current_resume_epoch_next238'], 'wp.current.resume.epoch.custom.238'],
    'resume source matches released' => [static fn (): mixed => $released238()['current_resume_source_matches_next238'], true],
    'resume source mismatch detected' => [static fn (): mixed => $sourceHeld238()['current_resume_source_matches_next238'], false],
    'resume cursor matches released' => [static fn (): mixed => $released238()['current_resume_cursor_matches_next238'], true],
    'resume cursor mismatch detected' => [static fn (): mixed => $cursorHeld238()['current_resume_cursor_matches_next238'], false],
    'resume epoch matches released' => [static fn (): mixed => $released238()['current_resume_epoch_matches_next238'], true],
    'resume epoch mismatch detected' => [static fn (): mixed => $epochHeld238()['current_resume_epoch_matches_next238'], false],
    'receipt count includes recursive upserts' => [static fn (): mixed => count($released238()['required_current_resume_receipts_next238']), 4],
    'receipts are fifty two hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{52}$/', $v), $released238()['required_current_resume_receipts_next238']), [1, 1, 1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released238()['acknowledged_current_resume_receipts_next238'], $receipts238()],
    'missing acknowledged count' => [static fn (): mixed => count($missing238()['acknowledged_current_resume_receipts_next238']), 3],
    'missing receipt recorded' => [static fn (): mixed => $missing238()['missing_current_resume_receipts_next238'], [array_slice($receipts238(), -1)[0]]],
    'unexpected receipt recorded' => [static fn (): mixed => $unexpected238()['unexpected_current_resume_receipts_next238'], [$unexpectedReceipt238]],
    'released missing empty' => [static fn (): mixed => $released238()['missing_current_resume_receipts_next238'], []],
    'released unexpected empty' => [static fn (): mixed => $released238()['unexpected_current_resume_receipts_next238'], []],
    'order required default' => [static fn (): mixed => $released238()['require_current_resume_receipt_order_next238'], true],
    'order matches released' => [static fn (): mixed => $released238()['current_resume_receipt_order_matches_next238'], true],
    'order mismatch reversed' => [static fn (): mixed => $reversed238()['current_resume_receipt_order_matches_next238'], false],
    'unordered disables order' => [static fn (): mixed => $unordered238()['require_current_resume_receipt_order_next238'], false],
    'unordered order considered matched' => [static fn (): mixed => $unordered238()['current_resume_receipt_order_matches_next238'], true],
    'resume complete released' => [static fn (): mixed => $released238()['current_resume_receipt_complete_next238'], true],
    'resume incomplete missing' => [static fn (): mixed => $missing238()['current_resume_receipt_complete_next238'], false],
    'resume incomplete unexpected' => [static fn (): mixed => $unexpected238()['current_resume_receipt_complete_next238'], false],
    'resume incomplete reversed' => [static fn (): mixed => $reversed238()['current_resume_receipt_complete_next238'], false],
    'resume incomplete source mismatch' => [static fn (): mixed => $sourceHeld238()['current_resume_receipt_complete_next238'], false],
    'resume incomplete cursor mismatch' => [static fn (): mixed => $cursorHeld238()['current_resume_receipt_complete_next238'], false],
    'resume incomplete epoch mismatch' => [static fn (): mixed => $epochHeld238()['current_resume_receipt_complete_next238'], false],
    'next visible released' => [static fn (): mixed => $released238()['next_source_visible_after_current_resume_receipts_next238'], true],
    'next denied missing' => [static fn (): mixed => $missing238()['next_source_visible_after_current_resume_receipts_next238'], false],
    'current resume count' => [static fn (): mixed => count($released238()['current_resume_stream_next238']), 4],
    'attempted next resume count' => [static fn (): mixed => count($released238()['attempted_next_resume_stream_next238']), 4],
    'visible resume released count' => [static fn (): mixed => count($released238()['visible_resume_stream_next238']), 8],
    'visible resume missing current only' => [static fn (): mixed => count($missing238()['visible_resume_stream_next238']), 4],
    'held resume missing next only' => [static fn (): mixed => count($missing238()['held_next_resume_stream_next238']), 4],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released238()['current_resume_stream_next238'], 'resume_receipt_phase_next238'))), ['current']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released238()['attempted_next_resume_stream_next238'], 'resume_receipt_phase_next238'))), ['next']],
    'current visible while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing238()['current_resume_stream_next238'], 'visible_after_current_resume_receipt_next238'))), [true]],
    'next held while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing238()['attempted_next_resume_stream_next238'], 'visible_after_current_resume_receipt_next238'))), [false]],
    'current receipts tagged' => [static fn (): mixed => array_column($released238()['current_resume_stream_next238'], 'current_resume_receipt_next238'), $receipts238()],
    'next receipts null' => [static fn (): mixed => array_values(array_unique(array_column($released238()['attempted_next_resume_stream_next238'], 'current_resume_receipt_next238'))), [null]],
    'resume events retained' => [static fn (): mixed => array_column($released238()['current_resume_stream_next238'], 'event'), ['update', 'update', 'update', 'insert']],
    'resume depths retained' => [static fn (): mixed => array_column($released238()['current_resume_stream_next238'], 'depth'), [0, 1, 2, 0]],
    'resume trigger chain retained' => [static fn (): mixed => array_column($released238()['current_resume_stream_next238'], 'trigger'), [null, 'wp_options_au_home', 'wp_options_au_rewrite', null]],
    'visible returning released names' => [static fn (): mixed => array_column($released238()['visible_returning_rows_next238'], 'option_name'), ['siteurl', 'home', 'rewrite_rules', 'blogname', 'siteurl', 'home', 'rewrite_rules', 'fresh_plugin']],
    'held returning missing names' => [static fn (): mixed => array_column($missing238()['held_next_returning_rows_next238'], 'option_name'), ['siteurl', 'home', 'rewrite_rules', 'fresh_plugin']],
    'visible change count released' => [static fn (): mixed => $released238()['visible_change_count_next238'], 8],
    'visible change count held' => [static fn (): mixed => $missing238()['visible_change_count_next238'], 4],
    'after savepoint released names' => [static fn (): mixed => array_column($released238()['after_savepoint_next238'], 'option_name'), ['siteurl', 'home', 'rewrite_rules', 'blogname', 'fresh_plugin']],
    'after savepoint held restores base names' => [static fn (): mixed => array_column($missing238()['after_savepoint_next238'], 'option_name'), ['siteurl', 'home', 'rewrite_rules']],
    'released final siteurl is next' => [static fn (): mixed => $released238()['after_savepoint_next238'][0]['option_value'], 'https://next.test'],
    'held final siteurl is old' => [static fn (): mixed => $missing238()['after_savepoint_next238'][0]['option_value'], 'https://old.test'],
    'blocked reasons missing' => [static fn (): mixed => $missing238()['blocked_reasons_next238'], ['current-resume-receipt-missing']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected238()['blocked_reasons_next238'], ['current-resume-receipt-unexpected']],
    'blocked reasons reversed' => [static fn (): mixed => $reversed238()['blocked_reasons_next238'], ['current-resume-receipt-order-mismatch']],
    'blocked reasons source' => [static fn (): mixed => $sourceHeld238()['blocked_reasons_next238'], ['current-resume-source-mismatch']],
    'blocked reasons cursor' => [static fn (): mixed => $cursorHeld238()['blocked_reasons_next238'], ['current-resume-cursor-mismatch']],
    'blocked reasons epoch' => [static fn (): mixed => $epochHeld238()['blocked_reasons_next238'], ['current-resume-epoch-mismatch']],
    'blocked reasons base' => [static fn (): mixed => $baseHeld238()['blocked_reasons_next238'], ['base-current-yield-ticket-not-released']],
    'released reasons empty' => [static fn (): mixed => $released238()['blocked_reasons_next238'], []],
    'held next reason tagged' => [static fn (): mixed => $missing238()['attempted_next_resume_stream_next238'][0]['held_by_current_resume_receipt_reasons_next238'], ['current-resume-receipt-missing']],
    'plan decision released' => [static fn (): mixed => $released238()['current_resume_receipt_plan_next238']['decision'], 'publish-next-source-after-current-recursive-view-upsert-resume'],
    'plan decision missing' => [static fn (): mixed => $missing238()['current_resume_receipt_plan_next238']['decision'], 'hold-next-source-until-current-recursive-view-upsert-resume'],
    'plan required echoed' => [static fn (): mixed => $released238()['current_resume_receipt_plan_next238']['required_receipts'], $receipts238()],
    'plan next visible echoed' => [static fn (): mixed => $released238()['current_resume_receipt_plan_next238']['resume_complete'], true],
    'yield boundary released' => [static fn (): mixed => $released238()['yield_boundary_next238'], 'recursive-view-upsert-next238-current-resume-receipts-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing238()['yield_boundary_next238'], 'recursive-view-upsert-next238-current-resume-receipts-fence-next'],
    'dependency closure marker' => [static fn (): mixed => $released238()['dependency_closure_next238'], 'no-new-support-component-reuses-native-recursive-view-upsert-yield-tickets-and-adds-current-resume-receipts'],
    'dependency includes next238' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next238', $released238()['dependencies_next238'], true), true],
    'dependency includes resume receipt' => [static fn (): mixed => in_array('sqlite-current-recursive-view-upsert-resume-receipt', $released238()['dependencies_next238'], true), true],
    'non overlap mentions next235' => [static fn (): mixed => str_contains($released238()['non_overlap_next238'], 'next235'), true],
    'bad resume source rejected' => [static fn (): mixed => $plan238(['current_resume_source_next238' => 'bad token']), InvalidArgumentException::class],
    'bad resume cursor rejected' => [static fn (): mixed => $plan238(['current_resume_cursor_next238' => 'bad cursor']), InvalidArgumentException::class],
    'bad resume epoch rejected' => [static fn (): mixed => $plan238(['current_resume_epoch_next238' => 'bad epoch']), InvalidArgumentException::class],
    'bad receipt list rejected' => [static fn (): mixed => $plan238(['acknowledged_current_resume_receipts_next238' => ['x' => $unexpectedReceipt238]]), InvalidArgumentException::class],
    'bad short receipt rejected' => [static fn (): mixed => $plan238(['acknowledged_current_resume_receipts_next238' => ['abc']]), InvalidArgumentException::class],
    'bad non hex receipt rejected' => [static fn (): mixed => $plan238(['acknowledged_current_resume_receipts_next238' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases238 as $name => [$callback, $expected]) {
    $tests['trigger recursive view upsert current source next238 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
