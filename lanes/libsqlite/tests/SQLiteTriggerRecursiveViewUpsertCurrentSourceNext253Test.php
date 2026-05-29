<?php

declare(strict_types=1);

foreach (glob(__DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext*.php') ?: [] as $file) {
    require_once $file;
}
foreach (glob(__DIR__ . '/../src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext*Plan.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$view253 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-253-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-253-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-upsert-materialization-253',
];
$nextView253 = $view253 + [];
$nextView253['source'] = 'main@view-cookie-253-next';
$nextView253['trigger_source'] = 'main@trigger-cookie-253-next';
$postResetView253 = $view253 + [];
$postResetView253['source'] = 'main@view-cookie-253-post-reset';
$postResetView253['trigger_source'] = 'main@trigger-cookie-253-post-reset';
$followingView253 = $view253 + [];
$followingView253['source'] = 'main@view-cookie-253-following';
$followingView253['trigger_source'] = 'main@trigger-cookie-253-following';

$baseRows253 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentInput253 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput253 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning253 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$baseOptions253 = [
    'key' => 'option_name',
    'savepoint' => 'wp_recursive_view_253',
    'cursor_name' => 'wp_recursive_view_returning_cursor_253',
    'admit_next_source' => true,
    'rollback_token' => 'wp.rollback.current.253',
    'reset_generation' => 'wp-current-reset-253',
    'post_reset_current_source_token' => 'wp.current.source.postreset.253',
    'post_reset_cursor' => 'wp.returning.postreset.cursor.253',
    'post_reset_view' => $postResetView253,
    'post_reset_input' => [
        ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
        ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
    ],
    'fresh_acknowledged_ordinals' => [0, 1],
    'next_source_token' => 'wp.next.source.253',
    'next_cursor' => 'wp.returning.next.cursor.253',
    'next_acknowledged_ordinals' => [0, 1],
    'close_next_cursor' => 'wp.returning.next.cursor.253',
    'following_current_source_token' => 'wp.current.source.following.253',
    'following_cursor' => 'wp.returning.following.cursor.253',
    'following_current_view' => $followingView253,
    'following_current_input' => [
        ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
        ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ],
    'following_generation' => 'wp-following-current-253',
    'recursive_child_acknowledged_ordinals' => [0, 1],
    'recursive_child_source_token' => 'wp.current.source.recursive.child.253',
    'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.253',
    'recursive_child_generation' => 'wp-recursive-child-current-253',
    'current_generation_next203' => 'wp.current.recursive.returning.generation.253',
    'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.253',
    'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.253',
    'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.253',
    'auto_ack_current_generation_receipts_next203' => true,
    'current_source_drain_token_next209' => 'wp.current.source.drain.253',
    'current_view_cookie_next209' => 'main@view-cookie-253-current',
    'current_trigger_cookie_next209' => 'main@trigger-cookie-253-current',
    'auto_ack_current_source_watermarks_next209' => true,
    'current_source_yield_token_next212' => 'wp.current.source.yield.253',
    'current_view_yield_cursor_next212' => 'wp.returning.view.yield.cursor.253',
    'current_trigger_yield_cursor_next212' => 'wp.returning.trigger.yield.cursor.253',
    'auto_ack_current_source_yields_next212' => true,
    'current_source_epoch_next218' => 'wp.current.source.epoch.253',
    'current_view_epoch_next218' => 'wp.returning.view.epoch.cursor.253',
    'current_trigger_epoch_next218' => 'wp.returning.trigger.epoch.cursor.253',
    'auto_ack_current_source_epochs_next218' => true,
    'current_source_ticket_next222' => 'wp.current.source.ticket.253',
    'current_view_source_next222' => 'main@view-cookie-253-current',
    'current_trigger_source_next222' => 'main@trigger-cookie-253-current',
    'auto_ack_current_source_tickets_next222' => true,
    'current_source_cursor_next231' => 'wp.returning.current.cursor.253',
    'current_source_close_token_next231' => 'wp.current.source.close.253',
    'current_view_cookie_next231' => 'main@view-cookie-253-current',
    'current_trigger_cookie_next231' => 'main@trigger-cookie-253-current',
    'auto_ack_current_source_closures_next231' => true,
    'current_source_upsert_token_next234' => 'wp.current.source.upsert.253',
    'current_upsert_view_cookie_next234' => 'main@view-cookie-253-current',
    'current_upsert_trigger_cookie_next234' => 'main@trigger-cookie-253-current',
    'auto_ack_current_source_upserts_next234' => true,
    'current_source_upsert_action_token_next237' => 'wp.current.source.upsert.action.253',
    'current_upsert_action_view_cookie_next237' => 'main@view-cookie-253-current',
    'current_upsert_action_trigger_cookie_next237' => 'main@trigger-cookie-253-current',
    'auto_ack_current_source_upsert_actions_next237' => true,
    'current_source_upsert_close_token_next241' => 'wp.current.source.upsert.close.253',
    'current_source_upsert_generation_next241' => 'main@source-generation-253-current',
    'current_upsert_close_view_cookie_next241' => 'main@view-cookie-253-current',
    'current_upsert_close_trigger_cookie_next241' => 'main@trigger-cookie-253-current',
    'auto_ack_current_source_upsert_closes_next241' => true,
    'current_source_upsert_statement_id_next244' => 'wp.current.source.upsert.statement.253',
    'current_source_upsert_commit_watermark_next244' => 'wp.current.source.upsert.commit.253',
    'current_upsert_commit_view_cookie_next244' => 'main@view-cookie-253-current',
    'current_upsert_commit_trigger_cookie_next244' => 'main@trigger-cookie-253-current',
    'auto_ack_current_source_upsert_commits_next244' => true,
    'current_source_statement_sequence_next247' => 253,
    'next_source_statement_sequence_next247' => 254,
    'current_source_sequence_view_cookie_next247' => 'main@view-cookie-253-current',
    'current_source_sequence_trigger_cookie_next247' => 'main@trigger-cookie-253-current',
    'current_source_sequence_cursor_next247' => 'wp.returning.current.sequence.cursor.253',
    'auto_ack_current_source_statement_sequences_next247' => true,
    'current_source_rowid_provenance_token_next250' => 'wp.current.source.rowid.provenance.253',
    'auto_ack_current_source_rowid_provenance_next250' => true,
    'current_source_view_materialization_token_next253' => 'wp.current.source.view.materialization.253',
    'current_source_view_cookie_next253' => 'main@view-cookie-253-current',
    'current_source_trigger_cookie_next253' => 'main@trigger-cookie-253-current',
    'current_source_materialization_cursor_next253' => 'wp.returning.materialized.cursor.253',
];

$plan253 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeNext253(
    $baseRows253,
    $currentInput253,
    $nextInput253,
    $view253,
    $nextView253,
    $returning253,
    $options + $baseOptions253,
);

$receipts253 = static fn (): array => $plan253()['required_current_source_view_materialization_receipts_next253'];
$released253 = static fn (): array => $plan253(['auto_ack_current_source_view_materialization_next253' => true]);
$missing253 = static fn (): array => $plan253(['acknowledged_current_source_view_materialization_receipts_next253' => array_slice($receipts253(), 0, 1)]);
$unexpected253 = static fn (): array => $plan253(['acknowledged_current_source_view_materialization_receipts_next253' => array_merge($receipts253(), ['abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdefab'])]);
$tokenHeld253 = static fn (): array => $plan253(['auto_ack_current_source_view_materialization_next253' => true, 'expected_current_source_view_materialization_token_next253' => 'wp.current.source.view.materialization.stale.253']);
$orderHeld253 = static fn (): array => $plan253(['acknowledged_current_source_view_materialization_receipts_next253' => array_reverse($receipts253())]);
$orderIgnored253 = static fn (): array => $plan253(['acknowledged_current_source_view_materialization_receipts_next253' => array_reverse($receipts253()), 'require_current_source_view_materialization_order_next253' => false]);
$baseHeld253 = static fn (): array => $plan253(['auto_ack_current_source_view_materialization_next253' => true, 'auto_ack_current_source_rowid_provenance_next250' => false]);
$custom253 = static fn (): array => $plan253(['auto_ack_current_source_view_materialization_next253' => true, 'materialized_returning_columns_next253' => ['name', 'value'], 'current_source_materialization_cursor_next253' => 'wp.returning.materialized.custom.253']);

$cases253 = [
    'released status' => [static fn (): mixed => $released253()['status_next253'], 'trigger-recursive-view-upsert-current-source-next253-view-materialization-released'],
    'missing status' => [static fn (): mixed => $missing253()['status_next253'], 'trigger-recursive-view-upsert-current-source-next253-materialization-missing-held'],
    'unexpected status' => [static fn (): mixed => $unexpected253()['status_next253'], 'trigger-recursive-view-upsert-current-source-next253-materialization-unexpected-held'],
    'token held status' => [static fn (): mixed => $tokenHeld253()['status_next253'], 'trigger-recursive-view-upsert-current-source-next253-materialization-token-held'],
    'order held status' => [static fn (): mixed => $orderHeld253()['status_next253'], 'trigger-recursive-view-upsert-current-source-next253-materialization-order-held'],
    'base held status' => [static fn (): mixed => $baseHeld253()['status_next253'], 'trigger-recursive-view-upsert-current-source-next253-base-held'],
    'savepoint retained' => [static fn (): mixed => $released253()['savepoint'], 'wp_recursive_view_253'],
    'base next250 released' => [static fn (): mixed => $released253()['base']['status_next250'], 'trigger-recursive-view-upsert-current-source-next250-rowid-provenance-released'],
    'base visible released' => [static fn (): mixed => $released253()['base_next_source_visible_next253'], true],
    'base visible held' => [static fn (): mixed => $baseHeld253()['base_next_source_visible_next253'], false],
    'token retained' => [static fn (): mixed => $released253()['current_source_view_materialization_token_next253'], 'wp.current.source.view.materialization.253'],
    'expected token retained' => [static fn (): mixed => $released253()['expected_current_source_view_materialization_token_next253'], 'wp.current.source.view.materialization.253'],
    'token matches released' => [static fn (): mixed => $released253()['current_source_view_materialization_token_matches_next253'], true],
    'token mismatch detected' => [static fn (): mixed => $tokenHeld253()['current_source_view_materialization_token_matches_next253'], false],
    'view cookie retained' => [static fn (): mixed => $released253()['current_source_view_cookie_next253'], 'main@view-cookie-253-current'],
    'trigger cookie retained' => [static fn (): mixed => $released253()['current_source_trigger_cookie_next253'], 'main@trigger-cookie-253-current'],
    'cursor retained' => [static fn (): mixed => $released253()['current_source_materialization_cursor_next253'], 'wp.returning.materialized.cursor.253'],
    'custom cursor retained' => [static fn (): mixed => $custom253()['current_source_materialization_cursor_next253'], 'wp.returning.materialized.custom.253'],
    'projection columns retained' => [static fn (): mixed => $released253()['materialized_returning_columns_next253'], ['name', 'value', 'event_name', 'depth_value', 'ordinal_value']],
    'custom projection columns retained' => [static fn (): mixed => $custom253()['materialized_returning_columns_next253'], ['name', 'value']],
    'materialized row count' => [static fn (): mixed => count($released253()['current_source_view_materialization_rows_next253']), 2],
    'first materialized name' => [static fn (): mixed => $released253()['current_source_view_materialization_rows_next253'][0]['projection']['name'], 'blogdescription_child'],
    'second materialized name' => [static fn (): mixed => $released253()['current_source_view_materialization_rows_next253'][1]['projection']['name'], 'template_child'],
    'materialized view cookie copied' => [static fn (): mixed => $released253()['current_source_view_materialization_rows_next253'][0]['view_cookie'], 'main@view-cookie-253-current'],
    'materialized trigger cookie copied' => [static fn (): mixed => $released253()['current_source_view_materialization_rows_next253'][0]['trigger_cookie'], 'main@trigger-cookie-253-current'],
    'materialized cursor copied' => [static fn (): mixed => $released253()['current_source_view_materialization_rows_next253'][0]['cursor'], 'wp.returning.materialized.cursor.253'],
    'projection hash is forty eight hex' => [static fn (): mixed => preg_match('/^[a-f0-9]{48}$/', $released253()['current_source_view_materialization_rows_next253'][0]['projection_hash']), 1],
    'rowid receipt threaded into materialization' => [static fn (): mixed => is_string($released253()['current_source_view_materialization_rows_next253'][0]['rowid_receipt_next250']), true],
    'required receipt count' => [static fn (): mixed => count($released253()['required_current_source_view_materialization_receipts_next253']), 2],
    'receipts are fifty hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{50}$/', $v), $released253()['required_current_source_view_materialization_receipts_next253']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released253()['acknowledged_current_source_view_materialization_receipts_next253'], $receipts253()],
    'missing receipt recorded' => [static fn (): mixed => $missing253()['missing_current_source_view_materialization_receipts_next253'], [array_slice($receipts253(), -1)[0]]],
    'unexpected receipt count' => [static fn (): mixed => count($unexpected253()['unexpected_current_source_view_materialization_receipts_next253']), 1],
    'released missing empty' => [static fn (): mixed => $released253()['missing_current_source_view_materialization_receipts_next253'], []],
    'released unexpected empty' => [static fn (): mixed => $released253()['unexpected_current_source_view_materialization_receipts_next253'], []],
    'order required default' => [static fn (): mixed => $released253()['require_current_source_view_materialization_order_next253'], true],
    'order mismatch detected' => [static fn (): mixed => $orderHeld253()['current_source_view_materialization_order_matches_next253'], false],
    'order ignored released' => [static fn (): mixed => $orderIgnored253()['status_next253'], 'trigger-recursive-view-upsert-current-source-next253-view-materialization-released'],
    'complete released' => [static fn (): mixed => $released253()['current_source_view_materialization_complete_next253'], true],
    'complete missing false' => [static fn (): mixed => $missing253()['current_source_view_materialization_complete_next253'], false],
    'next visible released' => [static fn (): mixed => $released253()['next_source_visible_after_current_source_view_materialization_next253'], true],
    'next denied missing' => [static fn (): mixed => $missing253()['next_source_visible_after_current_source_view_materialization_next253'], false],
    'visible released count' => [static fn (): mixed => $released253()['visible_row_count_next253'], 4],
    'held released count' => [static fn (): mixed => $released253()['held_next_row_count_next253'], 0],
    'visible missing current only' => [static fn (): mixed => $missing253()['visible_row_count_next253'], 2],
    'held missing next only' => [static fn (): mixed => $missing253()['held_next_row_count_next253'], 2],
    'current phase' => [static fn (): mixed => array_values(array_unique(array_column($released253()['current_source_rows_next253'], 'view_materialization_phase_next253'))), ['current-view-materialized']],
    'next phase' => [static fn (): mixed => array_values(array_unique(array_column($released253()['attempted_next_source_rows_next253'], 'view_materialization_phase_next253'))), ['next-source']],
    'current receipts tagged' => [static fn (): mixed => array_column($released253()['current_source_rows_next253'], 'current_source_view_materialization_receipt_next253'), $receipts253()],
    'next receipts null' => [static fn (): mixed => array_values(array_unique(array_column($released253()['attempted_next_source_rows_next253'], 'current_source_view_materialization_receipt_next253'))), [null]],
    'visible payload names released' => [static fn (): mixed => array_column($released253()['visible_returning_payloads_next253'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held payload names missing' => [static fn (): mixed => array_column($missing253()['held_next_returning_payloads_next253'], 'name'), ['home', 'next_plugin']],
    'blocked reasons released' => [static fn (): mixed => $released253()['blocked_reasons_next253'], []],
    'blocked reasons missing' => [static fn (): mixed => $missing253()['blocked_reasons_next253'], ['current-source-view-materialization-missing']],
    'blocked reasons token' => [static fn (): mixed => $tokenHeld253()['blocked_reasons_next253'], ['current-source-view-materialization-token-mismatch']],
    'blocked reasons order' => [static fn (): mixed => $orderHeld253()['blocked_reasons_next253'], ['current-source-view-materialization-order-mismatch']],
    'held row reason copied' => [static fn (): mixed => $missing253()['held_next_source_rows_next253'][0]['held_by_current_source_view_materialization_reasons_next253'], ['current-source-view-materialization-missing']],
    'plan decision released' => [static fn (): mixed => $released253()['current_source_view_materialization_plan_next253']['decision'], 'publish-next-source-after-current-recursive-view-upsert-materialization'],
    'plan decision held' => [static fn (): mixed => $missing253()['current_source_view_materialization_plan_next253']['decision'], 'hold-next-source-until-current-recursive-view-upsert-materialization'],
    'yield boundary released' => [static fn (): mixed => $released253()['yield_boundary_next253'], 'recursive-view-upsert-next253-current-materialized-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing253()['yield_boundary_next253'], 'recursive-view-upsert-next253-current-materialized-fence-next'],
    'dependency closure marker' => [static fn (): mixed => $released253()['dependency_closure_next253'], 'no-new-support-component-reuses-native-recursive-view-upsert-rowid-provenance-and-adds-current-view-materialization-receipts'],
    'dependency includes next253' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next253', $released253()['dependencies_next253'], true), true],
    'dependency includes materialization' => [static fn (): mixed => in_array('sqlite-instead-of-view-trigger-upsert-materialization-receipts', $released253()['dependencies_next253'], true), true],
    'dependency includes next250' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next250', $released253()['dependencies_next253'], true), true],
    'non overlap mentions next250' => [static fn (): mixed => str_contains($released253()['non_overlap_next253'], 'next250 rowid'), true],
    'bad token rejected' => [static fn (): mixed => $plan253(['current_source_view_materialization_token_next253' => 'bad token']), InvalidArgumentException::class],
    'bad expected token rejected' => [static fn (): mixed => $plan253(['expected_current_source_view_materialization_token_next253' => 'bad token']), InvalidArgumentException::class],
    'bad cursor rejected' => [static fn (): mixed => $plan253(['current_source_materialization_cursor_next253' => 'bad cursor']), InvalidArgumentException::class],
    'bad projection rejected' => [static fn (): mixed => $plan253(['materialized_returning_columns_next253' => ['bad-column']]), InvalidArgumentException::class],
    'bad receipt list rejected' => [static fn (): mixed => $plan253(['acknowledged_current_source_view_materialization_receipts_next253' => ['x' => 'abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdefab']]), InvalidArgumentException::class],
    'bad short receipt rejected' => [static fn (): mixed => $plan253(['acknowledged_current_source_view_materialization_receipts_next253' => ['abc']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases253 as $name => [$callback, $expected]) {
    $tests['trigger recursive view upsert current source next253 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
