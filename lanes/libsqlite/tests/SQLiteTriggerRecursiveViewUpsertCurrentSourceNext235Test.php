<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$rows235 = [
    ['option_name' => 'siteurl', 'option_value' => 'https://old.test'],
    ['option_name' => 'home', 'option_value' => 'https://old-home.test'],
    ['option_name' => 'rewrite_rules', 'option_value' => 'old-rules'],
];
$view235 = [
    'name' => 'wp_option_import_view',
    'source' => 'main@cookie235-current',
    'mapping' => ['name' => 'option_name', 'value' => 'option_value'],
];
$triggers235 = [
    ['name' => 'wp_options_au_home', 'when' => 'siteurl', 'target' => 'home', 'value' => '{value}/home'],
    ['name' => 'wp_options_au_rewrite', 'when' => 'home', 'target' => 'rewrite_rules', 'value' => 'flushed:{value}'],
];
$current235 = [
    ['name' => 'siteurl', 'value' => 'https://current.test'],
    ['name' => 'blogname', 'value' => 'Current Blog'],
];
$next235 = [
    ['name' => 'siteurl', 'value' => 'https://next.test'],
    ['name' => 'fresh_plugin', 'value' => 'enabled'],
];

$plan235 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeNext235(
    $rows235,
    $current235,
    $next235,
    $view235,
    ['option_name'],
    $triggers235,
    $options + [
        'savepoint' => 'wp_view_recursive_235',
        'current_upsert_source_next232' => 'wp.current.upsert.source.235',
        'current_view_source_next232' => 'main@cookie235-current',
        'current_trigger_program_next232' => 'wp.current.recursive.trigger.program.235',
        'current_yield_ticket_source_next235' => 'wp.current.yield.ticket.source.235',
        'current_yield_resume_cursor_next235' => 'wp.current.yield.cursor.235',
    ],
);

$tickets235 = static fn (): array => $plan235()['required_current_yield_tickets_next235'];
$released235 = static fn (): array => $plan235(['auto_ack_current_yield_tickets_next235' => true]);
$missing235 = static fn (): array => $plan235(['acknowledged_current_yield_tickets_next235' => array_slice($tickets235(), 0, 3)]);
$unexpectedTicket235 = 'abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdefab';
$unexpected235 = static fn (): array => $plan235(['acknowledged_current_yield_tickets_next235' => array_merge($tickets235(), [$unexpectedTicket235])]);
$reversed235 = static fn (): array => $plan235(['acknowledged_current_yield_tickets_next235' => array_reverse($tickets235())]);
$unordered235 = static fn (): array => $plan235(['require_current_yield_ticket_order_next235' => false, 'acknowledged_current_yield_tickets_next235' => array_reverse($tickets235())]);
$sourceHeld235 = static fn (): array => $plan235(['auto_ack_current_yield_tickets_next235' => true, 'expected_current_yield_ticket_source_next235' => 'wp.current.yield.ticket.source.stale.235']);
$cursorHeld235 = static fn (): array => $plan235(['auto_ack_current_yield_tickets_next235' => true, 'expected_current_yield_resume_cursor_next235' => 'wp.current.yield.cursor.stale.235']);
$baseHeld235 = static fn (): array => $plan235(['auto_ack_current_yield_tickets_next235' => true, 'expected_current_view_source_next232' => 'main@cookie235-stale']);
$custom235 = static fn (): array => $plan235([
    'auto_ack_current_yield_tickets_next235' => true,
    'current_yield_ticket_source_next235' => 'wp.current.yield.ticket.source.custom.235',
    'current_yield_resume_cursor_next235' => 'wp.current.yield.cursor.custom.235',
]);

$cases235 = [
    'released status' => [static fn (): mixed => $released235()['status_next235'], 'trigger-recursive-view-upsert-current-source-next235-yield-released'],
    'missing status' => [static fn (): mixed => $missing235()['status_next235'], 'trigger-recursive-view-upsert-current-source-next235-yield-ticket-held'],
    'unexpected status' => [static fn (): mixed => $unexpected235()['status_next235'], 'trigger-recursive-view-upsert-current-source-next235-yield-ticket-held'],
    'reversed status' => [static fn (): mixed => $reversed235()['status_next235'], 'trigger-recursive-view-upsert-current-source-next235-yield-order-held'],
    'unordered reversed releases' => [static fn (): mixed => $unordered235()['status_next235'], 'trigger-recursive-view-upsert-current-source-next235-yield-released'],
    'source held status' => [static fn (): mixed => $sourceHeld235()['status_next235'], 'trigger-recursive-view-upsert-current-source-next235-ticket-source-held'],
    'cursor held status' => [static fn (): mixed => $cursorHeld235()['status_next235'], 'trigger-recursive-view-upsert-current-source-next235-resume-cursor-held'],
    'base held status' => [static fn (): mixed => $baseHeld235()['status_next235'], 'trigger-recursive-view-upsert-current-source-next235-base-conflict-held'],
    'savepoint retained' => [static fn (): mixed => $released235()['savepoint'], 'wp_view_recursive_235'],
    'base next232 released' => [static fn (): mixed => $released235()['base_conflict_released_next235'], true],
    'base mismatch not released' => [static fn (): mixed => $baseHeld235()['base_conflict_released_next235'], false],
    'ticket source retained' => [static fn (): mixed => $released235()['current_yield_ticket_source_next235'], 'wp.current.yield.ticket.source.235'],
    'custom ticket source retained' => [static fn (): mixed => $custom235()['current_yield_ticket_source_next235'], 'wp.current.yield.ticket.source.custom.235'],
    'resume cursor retained' => [static fn (): mixed => $released235()['current_yield_resume_cursor_next235'], 'wp.current.yield.cursor.235'],
    'custom resume cursor retained' => [static fn (): mixed => $custom235()['current_yield_resume_cursor_next235'], 'wp.current.yield.cursor.custom.235'],
    'ticket source matches released' => [static fn (): mixed => $released235()['current_yield_ticket_source_matches_next235'], true],
    'ticket source mismatch detected' => [static fn (): mixed => $sourceHeld235()['current_yield_ticket_source_matches_next235'], false],
    'resume cursor matches released' => [static fn (): mixed => $released235()['current_yield_resume_cursor_matches_next235'], true],
    'resume cursor mismatch detected' => [static fn (): mixed => $cursorHeld235()['current_yield_resume_cursor_matches_next235'], false],
    'ticket count includes recursive upserts' => [static fn (): mixed => count($released235()['required_current_yield_tickets_next235']), 4],
    'tickets are fifty hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{50}$/', $v), $released235()['required_current_yield_tickets_next235']), [1, 1, 1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released235()['acknowledged_current_yield_tickets_next235'], $tickets235()],
    'missing acknowledged count' => [static fn (): mixed => count($missing235()['acknowledged_current_yield_tickets_next235']), 3],
    'missing ticket recorded' => [static fn (): mixed => $missing235()['missing_current_yield_tickets_next235'], [array_slice($tickets235(), -1)[0]]],
    'unexpected ticket recorded' => [static fn (): mixed => $unexpected235()['unexpected_current_yield_tickets_next235'], [$unexpectedTicket235]],
    'released missing empty' => [static fn (): mixed => $released235()['missing_current_yield_tickets_next235'], []],
    'released unexpected empty' => [static fn (): mixed => $released235()['unexpected_current_yield_tickets_next235'], []],
    'order required default' => [static fn (): mixed => $released235()['require_current_yield_ticket_order_next235'], true],
    'order matches released' => [static fn (): mixed => $released235()['current_yield_ticket_order_matches_next235'], true],
    'order mismatch reversed' => [static fn (): mixed => $reversed235()['current_yield_ticket_order_matches_next235'], false],
    'unordered disables order' => [static fn (): mixed => $unordered235()['require_current_yield_ticket_order_next235'], false],
    'unordered order considered matched' => [static fn (): mixed => $unordered235()['current_yield_ticket_order_matches_next235'], true],
    'tickets complete released' => [static fn (): mixed => $released235()['current_yield_ticket_complete_next235'], true],
    'tickets incomplete missing' => [static fn (): mixed => $missing235()['current_yield_ticket_complete_next235'], false],
    'tickets incomplete unexpected' => [static fn (): mixed => $unexpected235()['current_yield_ticket_complete_next235'], false],
    'tickets incomplete reversed' => [static fn (): mixed => $reversed235()['current_yield_ticket_complete_next235'], false],
    'tickets incomplete source mismatch' => [static fn (): mixed => $sourceHeld235()['current_yield_ticket_complete_next235'], false],
    'tickets incomplete cursor mismatch' => [static fn (): mixed => $cursorHeld235()['current_yield_ticket_complete_next235'], false],
    'next visible released' => [static fn (): mixed => $released235()['next_source_visible_after_current_yield_tickets_next235'], true],
    'next denied missing' => [static fn (): mixed => $missing235()['next_source_visible_after_current_yield_tickets_next235'], false],
    'current yield count' => [static fn (): mixed => count($released235()['current_yield_stream_next235']), 4],
    'attempted next yield count' => [static fn (): mixed => count($released235()['attempted_next_yield_stream_next235']), 4],
    'visible yield released count' => [static fn (): mixed => count($released235()['visible_yield_stream_next235']), 8],
    'visible yield missing current only' => [static fn (): mixed => count($missing235()['visible_yield_stream_next235']), 4],
    'held yield missing next only' => [static fn (): mixed => count($missing235()['held_next_yield_stream_next235']), 4],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released235()['current_yield_stream_next235'], 'yield_ticket_phase_next235'))), ['current']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released235()['attempted_next_yield_stream_next235'], 'yield_ticket_phase_next235'))), ['next']],
    'current visible while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing235()['current_yield_stream_next235'], 'visible_after_current_yield_ticket_next235'))), [true]],
    'next held while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing235()['attempted_next_yield_stream_next235'], 'visible_after_current_yield_ticket_next235'))), [false]],
    'current tickets tagged' => [static fn (): mixed => array_column($released235()['current_yield_stream_next235'], 'current_yield_ticket_next235'), $tickets235()],
    'next tickets null' => [static fn (): mixed => array_values(array_unique(array_column($released235()['attempted_next_yield_stream_next235'], 'current_yield_ticket_next235'))), [null]],
    'yield events retained' => [static fn (): mixed => array_column($released235()['current_yield_stream_next235'], 'event'), ['update', 'update', 'update', 'insert']],
    'yield depths retained' => [static fn (): mixed => array_column($released235()['current_yield_stream_next235'], 'depth'), [0, 1, 2, 0]],
    'yield trigger chain retained' => [static fn (): mixed => array_column($released235()['current_yield_stream_next235'], 'trigger'), [null, 'wp_options_au_home', 'wp_options_au_rewrite', null]],
    'current returning names' => [static fn (): mixed => array_column($released235()['current_returning_rows_next235'], 'option_name'), ['siteurl', 'home', 'rewrite_rules', 'blogname']],
    'visible returning released names' => [static fn (): mixed => array_column($released235()['visible_returning_rows_next235'], 'option_name'), ['siteurl', 'home', 'rewrite_rules', 'blogname', 'siteurl', 'home', 'rewrite_rules', 'fresh_plugin']],
    'held returning missing names' => [static fn (): mixed => array_column($missing235()['held_next_returning_rows_next235'], 'option_name'), ['siteurl', 'home', 'rewrite_rules', 'fresh_plugin']],
    'visible change count released' => [static fn (): mixed => $released235()['visible_change_count_next235'], 8],
    'visible change count held' => [static fn (): mixed => $missing235()['visible_change_count_next235'], 4],
    'after savepoint released names' => [static fn (): mixed => array_column($released235()['after_savepoint_next235'], 'option_name'), ['siteurl', 'home', 'rewrite_rules', 'blogname', 'fresh_plugin']],
    'after savepoint held restores base names' => [static fn (): mixed => array_column($missing235()['after_savepoint_next235'], 'option_name'), ['siteurl', 'home', 'rewrite_rules']],
    'released final siteurl is next' => [static fn (): mixed => $released235()['after_savepoint_next235'][0]['option_value'], 'https://next.test'],
    'held final siteurl is old' => [static fn (): mixed => $missing235()['after_savepoint_next235'][0]['option_value'], 'https://old.test'],
    'blocked reasons missing' => [static fn (): mixed => $missing235()['blocked_reasons_next235'], ['current-yield-ticket-missing']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected235()['blocked_reasons_next235'], ['current-yield-ticket-unexpected']],
    'blocked reasons reversed' => [static fn (): mixed => $reversed235()['blocked_reasons_next235'], ['current-yield-ticket-order-mismatch']],
    'blocked reasons source' => [static fn (): mixed => $sourceHeld235()['blocked_reasons_next235'], ['current-yield-ticket-source-mismatch']],
    'blocked reasons cursor' => [static fn (): mixed => $cursorHeld235()['blocked_reasons_next235'], ['current-yield-resume-cursor-mismatch']],
    'blocked reasons base' => [static fn (): mixed => $baseHeld235()['blocked_reasons_next235'], ['base-current-upsert-conflict-not-released']],
    'released reasons empty' => [static fn (): mixed => $released235()['blocked_reasons_next235'], []],
    'held next reason tagged' => [static fn (): mixed => $missing235()['attempted_next_yield_stream_next235'][0]['held_by_current_yield_ticket_reasons_next235'], ['current-yield-ticket-missing']],
    'plan decision released' => [static fn (): mixed => $released235()['current_yield_ticket_plan_next235']['decision'], 'publish-next-source-after-current-recursive-view-upsert-yields'],
    'plan decision missing' => [static fn (): mixed => $missing235()['current_yield_ticket_plan_next235']['decision'], 'hold-next-source-until-current-recursive-view-upsert-yields'],
    'plan required echoed' => [static fn (): mixed => $released235()['current_yield_ticket_plan_next235']['required_tickets'], $tickets235()],
    'plan next visible echoed' => [static fn (): mixed => $released235()['current_yield_ticket_plan_next235']['ticket_complete'], true],
    'yield boundary released' => [static fn (): mixed => $released235()['yield_boundary_next235'], 'recursive-view-upsert-next235-current-yield-tickets-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing235()['yield_boundary_next235'], 'recursive-view-upsert-next235-current-yield-tickets-fence-next'],
    'dependency closure marker' => [static fn (): mixed => $released235()['dependency_closure_next235'], 'no-new-support-component-reuses-native-recursive-view-upsert-current-source-conflict-seals-and-adds-yield-tickets'],
    'dependency includes next235' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next235', $released235()['dependencies_next235'], true), true],
    'dependency includes yield ticket' => [static fn (): mixed => in_array('sqlite-current-recursive-view-upsert-yield-ticket', $released235()['dependencies_next235'], true), true],
    'non overlap mentions next232' => [static fn (): mixed => str_contains($released235()['non_overlap_next235'], 'next232'), true],
    'bad ticket source rejected' => [static fn (): mixed => $plan235(['current_yield_ticket_source_next235' => 'bad token']), InvalidArgumentException::class],
    'bad resume cursor rejected' => [static fn (): mixed => $plan235(['current_yield_resume_cursor_next235' => 'bad cursor']), InvalidArgumentException::class],
    'bad ticket list rejected' => [static fn (): mixed => $plan235(['acknowledged_current_yield_tickets_next235' => ['x' => $unexpectedTicket235]]), InvalidArgumentException::class],
    'bad short ticket rejected' => [static fn (): mixed => $plan235(['acknowledged_current_yield_tickets_next235' => ['abc']]), InvalidArgumentException::class],
    'bad non hex ticket rejected' => [static fn (): mixed => $plan235(['acknowledged_current_yield_tickets_next235' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases235 as $name => [$callback, $expected]) {
    $tests['trigger recursive view upsert current source next235 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
