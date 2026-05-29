<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$rows244 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView244 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-244-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-244-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-upsert-trigger-244',
];
$nextView244 = $currentView244;
$nextView244['source'] = 'main@view-cookie-244-next';
$nextView244['trigger_source'] = 'main@trigger-cookie-244-next';
$postResetView244 = $currentView244;
$postResetView244['source'] = 'main@view-cookie-244-post-reset';
$postResetView244['trigger_source'] = 'main@trigger-cookie-244-post-reset';
$followingView244 = $currentView244;
$followingView244['source'] = 'main@view-cookie-244-following';
$followingView244['trigger_source'] = 'main@trigger-cookie-244-following';
$currentInput244 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput244 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning244 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$baseOptions244 = [
    'key' => 'option_name',
    'savepoint' => 'wp_recursive_view_244',
    'cursor_name' => 'wp_recursive_view_returning_cursor_244',
    'admit_next_source' => true,
    'rollback_token' => 'wp.rollback.current.244',
    'reset_generation' => 'wp-current-reset-244',
    'post_reset_current_source_token' => 'wp.current.source.postreset.244',
    'post_reset_cursor' => 'wp.returning.postreset.cursor.244',
    'post_reset_view' => $postResetView244,
    'post_reset_input' => [
        ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
        ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
    ],
    'fresh_acknowledged_ordinals' => [0, 1],
    'next_source_token' => 'wp.next.source.244',
    'next_cursor' => 'wp.returning.next.cursor.244',
    'next_acknowledged_ordinals' => [0, 1],
    'close_next_cursor' => 'wp.returning.next.cursor.244',
    'following_current_source_token' => 'wp.current.source.following.244',
    'following_cursor' => 'wp.returning.following.cursor.244',
    'following_current_view' => $followingView244,
    'following_current_input' => [
        ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
        ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ],
    'following_generation' => 'wp-following-current-244',
    'recursive_child_acknowledged_ordinals' => [0, 1],
    'recursive_child_source_token' => 'wp.current.source.recursive.child.244',
    'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.244',
    'recursive_child_generation' => 'wp-recursive-child-current-244',
    'current_generation_next203' => 'wp.current.recursive.returning.generation.244',
    'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.244',
    'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.244',
    'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.244',
    'auto_ack_current_generation_receipts_next203' => true,
    'current_source_drain_token_next209' => 'wp.current.source.drain.244',
    'current_view_cookie_next209' => 'main@view-cookie-244-current',
    'current_trigger_cookie_next209' => 'main@trigger-cookie-244-current',
    'auto_ack_current_source_watermarks_next209' => true,
    'current_source_yield_token_next212' => 'wp.current.source.yield.244',
    'current_view_yield_cursor_next212' => 'wp.returning.view.yield.cursor.244',
    'current_trigger_yield_cursor_next212' => 'wp.returning.trigger.yield.cursor.244',
    'auto_ack_current_source_yields_next212' => true,
    'current_source_epoch_next218' => 'wp.current.source.epoch.244',
    'current_view_epoch_next218' => 'wp.returning.view.epoch.cursor.244',
    'current_trigger_epoch_next218' => 'wp.returning.trigger.epoch.cursor.244',
    'auto_ack_current_source_epochs_next218' => true,
    'current_source_ticket_next222' => 'wp.current.source.ticket.244',
    'current_view_source_next222' => 'main@view-cookie-244-current',
    'current_trigger_source_next222' => 'main@trigger-cookie-244-current',
    'auto_ack_current_source_tickets_next222' => true,
    'current_source_cursor_next231' => 'wp.returning.current.cursor.244',
    'current_source_close_token_next231' => 'wp.current.source.close.244',
    'current_view_cookie_next231' => 'main@view-cookie-244-current',
    'current_trigger_cookie_next231' => 'main@trigger-cookie-244-current',
    'auto_ack_current_source_closures_next231' => true,
    'current_source_upsert_token_next234' => 'wp.current.source.upsert.244',
    'current_upsert_view_cookie_next234' => 'main@view-cookie-244-current',
    'current_upsert_trigger_cookie_next234' => 'main@trigger-cookie-244-current',
    'auto_ack_current_source_upserts_next234' => true,
    'current_source_upsert_action_token_next237' => 'wp.current.source.upsert.action.244',
    'current_upsert_action_view_cookie_next237' => 'main@view-cookie-244-current',
    'current_upsert_action_trigger_cookie_next237' => 'main@trigger-cookie-244-current',
    'auto_ack_current_source_upsert_actions_next237' => true,
    'current_source_upsert_close_token_next241' => 'wp.current.source.upsert.close.244',
    'current_source_upsert_generation_next241' => 'main@source-generation-244-current',
    'current_upsert_close_view_cookie_next241' => 'main@view-cookie-244-current',
    'current_upsert_close_trigger_cookie_next241' => 'main@trigger-cookie-244-current',
    'auto_ack_current_source_upsert_closes_next241' => true,
    'current_source_upsert_statement_id_next244' => 'wp.current.source.upsert.statement.244',
    'current_source_upsert_commit_watermark_next244' => 'wp.current.source.upsert.commit.244',
    'current_upsert_commit_view_cookie_next244' => 'main@view-cookie-244-current',
    'current_upsert_commit_trigger_cookie_next244' => 'main@trigger-cookie-244-current',
];

$plan244 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentCommitReceipt(
    $rows244,
    $currentInput244,
    $nextInput244,
    $currentView244,
    $nextView244,
    $returning244,
    $options + $baseOptions244,
);

$receipts244 = static fn (): array => $plan244()['required_current_source_upsert_commit_receipts_next244'];
$released244 = static fn (): array => $plan244(['auto_ack_current_source_upsert_commits_next244' => true]);
$missing244 = static fn (): array => $plan244(['acknowledged_current_source_upsert_commit_receipts_next244' => array_slice($receipts244(), 0, 1)]);
$unexpectedReceipt244 = 'abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdefab';
$unexpected244 = static fn (): array => $plan244(['acknowledged_current_source_upsert_commit_receipts_next244' => array_merge($receipts244(), [$unexpectedReceipt244])]);
$reversed244 = static fn (): array => $plan244(['acknowledged_current_source_upsert_commit_receipts_next244' => array_reverse($receipts244())]);
$unordered244 = static fn (): array => $plan244(['require_current_source_upsert_commit_order_next244' => false, 'acknowledged_current_source_upsert_commit_receipts_next244' => array_reverse($receipts244())]);
$statementHeld244 = static fn (): array => $plan244(['auto_ack_current_source_upsert_commits_next244' => true, 'expected_current_source_upsert_statement_id_next244' => 'wp.current.source.upsert.statement.stale.244']);
$watermarkHeld244 = static fn (): array => $plan244(['auto_ack_current_source_upsert_commits_next244' => true, 'expected_current_source_upsert_commit_watermark_next244' => 'wp.current.source.upsert.commit.stale.244']);
$viewHeld244 = static fn (): array => $plan244(['auto_ack_current_source_upsert_commits_next244' => true, 'expected_current_upsert_commit_view_cookie_next244' => 'main@view-cookie-244-stale']);
$triggerHeld244 = static fn (): array => $plan244(['auto_ack_current_source_upsert_commits_next244' => true, 'expected_current_upsert_commit_trigger_cookie_next244' => 'main@trigger-cookie-244-stale']);
$baseHeld244 = static fn (): array => $plan244(['auto_ack_current_source_upsert_commits_next244' => true, 'auto_ack_current_source_upsert_closes_next241' => false]);
$custom244 = static fn (): array => $plan244([
    'auto_ack_current_source_upsert_commits_next244' => true,
    'current_source_upsert_statement_id_next244' => 'wp.current.source.upsert.statement.custom.244',
    'current_source_upsert_commit_watermark_next244' => 'wp.current.source.upsert.commit.custom.244',
    'current_upsert_commit_view_cookie_next244' => 'main@view-cookie-244-custom',
    'current_upsert_commit_trigger_cookie_next244' => 'main@trigger-cookie-244-custom',
]);

$cases244 = [
    'released status' => [static fn (): mixed => $released244()['status_next244'], 'trigger-recursive-view-upsert-current-source-next244-commit-released'],
    'missing status' => [static fn (): mixed => $missing244()['status_next244'], 'trigger-recursive-view-upsert-current-source-next244-commit-held'],
    'unexpected status' => [static fn (): mixed => $unexpected244()['status_next244'], 'trigger-recursive-view-upsert-current-source-next244-commit-held'],
    'reversed status' => [static fn (): mixed => $reversed244()['status_next244'], 'trigger-recursive-view-upsert-current-source-next244-commit-order-held'],
    'unordered reversed releases' => [static fn (): mixed => $unordered244()['status_next244'], 'trigger-recursive-view-upsert-current-source-next244-commit-released'],
    'statement held status' => [static fn (): mixed => $statementHeld244()['status_next244'], 'trigger-recursive-view-upsert-current-source-next244-statement-held'],
    'watermark held status' => [static fn (): mixed => $watermarkHeld244()['status_next244'], 'trigger-recursive-view-upsert-current-source-next244-watermark-held'],
    'view held status' => [static fn (): mixed => $viewHeld244()['status_next244'], 'trigger-recursive-view-upsert-current-source-next244-view-cookie-held'],
    'trigger held status' => [static fn (): mixed => $triggerHeld244()['status_next244'], 'trigger-recursive-view-upsert-current-source-next244-trigger-cookie-held'],
    'base held status' => [static fn (): mixed => $baseHeld244()['status_next244'], 'trigger-recursive-view-upsert-current-source-next244-base-held'],
    'savepoint retained' => [static fn (): mixed => $released244()['savepoint'], 'wp_recursive_view_244'],
    'base next241 released' => [static fn (): mixed => $released244()['base']['status_next241'], 'trigger-recursive-view-upsert-current-source-next241-close-released'],
    'base next241 held' => [static fn (): mixed => $baseHeld244()['base']['status_next241'], 'trigger-recursive-view-upsert-current-source-next241-close-held'],
    'base visible released' => [static fn (): mixed => $released244()['base_next_source_visible_next244'], true],
    'base visible held' => [static fn (): mixed => $baseHeld244()['base_next_source_visible_next244'], false],
    'statement id retained' => [static fn (): mixed => $released244()['current_source_upsert_statement_id_next244'], 'wp.current.source.upsert.statement.244'],
    'custom statement id retained' => [static fn (): mixed => $custom244()['current_source_upsert_statement_id_next244'], 'wp.current.source.upsert.statement.custom.244'],
    'watermark retained' => [static fn (): mixed => $released244()['current_source_upsert_commit_watermark_next244'], 'wp.current.source.upsert.commit.244'],
    'custom watermark retained' => [static fn (): mixed => $custom244()['current_source_upsert_commit_watermark_next244'], 'wp.current.source.upsert.commit.custom.244'],
    'view cookie retained' => [static fn (): mixed => $released244()['current_upsert_commit_view_cookie_next244'], 'main@view-cookie-244-current'],
    'custom view cookie retained' => [static fn (): mixed => $custom244()['current_upsert_commit_view_cookie_next244'], 'main@view-cookie-244-custom'],
    'trigger cookie retained' => [static fn (): mixed => $released244()['current_upsert_commit_trigger_cookie_next244'], 'main@trigger-cookie-244-current'],
    'custom trigger cookie retained' => [static fn (): mixed => $custom244()['current_upsert_commit_trigger_cookie_next244'], 'main@trigger-cookie-244-custom'],
    'statement matches released' => [static fn (): mixed => $released244()['current_source_upsert_statement_id_matches_next244'], true],
    'statement mismatch detected' => [static fn (): mixed => $statementHeld244()['current_source_upsert_statement_id_matches_next244'], false],
    'watermark matches released' => [static fn (): mixed => $released244()['current_source_upsert_commit_watermark_matches_next244'], true],
    'watermark mismatch detected' => [static fn (): mixed => $watermarkHeld244()['current_source_upsert_commit_watermark_matches_next244'], false],
    'view matches released' => [static fn (): mixed => $released244()['current_upsert_commit_view_cookie_matches_next244'], true],
    'view mismatch detected' => [static fn (): mixed => $viewHeld244()['current_upsert_commit_view_cookie_matches_next244'], false],
    'trigger matches released' => [static fn (): mixed => $released244()['current_upsert_commit_trigger_cookie_matches_next244'], true],
    'trigger mismatch detected' => [static fn (): mixed => $triggerHeld244()['current_upsert_commit_trigger_cookie_matches_next244'], false],
    'required receipt count' => [static fn (): mixed => count($released244()['required_current_source_upsert_commit_receipts_next244']), 2],
    'receipts are fifty six hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{56}$/', $v), $released244()['required_current_source_upsert_commit_receipts_next244']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released244()['acknowledged_current_source_upsert_commit_receipts_next244'], $receipts244()],
    'missing acknowledged count' => [static fn (): mixed => count($missing244()['acknowledged_current_source_upsert_commit_receipts_next244']), 1],
    'missing receipt recorded' => [static fn (): mixed => $missing244()['missing_current_source_upsert_commit_receipts_next244'], [array_slice($receipts244(), -1)[0]]],
    'unexpected receipt recorded' => [static fn (): mixed => $unexpected244()['unexpected_current_source_upsert_commit_receipts_next244'], [$unexpectedReceipt244]],
    'released missing empty' => [static fn (): mixed => $released244()['missing_current_source_upsert_commit_receipts_next244'], []],
    'released unexpected empty' => [static fn (): mixed => $released244()['unexpected_current_source_upsert_commit_receipts_next244'], []],
    'require order default' => [static fn (): mixed => $released244()['require_current_source_upsert_commit_order_next244'], true],
    'order matches released' => [static fn (): mixed => $released244()['current_source_upsert_commit_order_matches_next244'], true],
    'order mismatch reversed' => [static fn (): mixed => $reversed244()['current_source_upsert_commit_order_matches_next244'], false],
    'unordered disables order' => [static fn (): mixed => $unordered244()['require_current_source_upsert_commit_order_next244'], false],
    'unordered order considered matched' => [static fn (): mixed => $unordered244()['current_source_upsert_commit_order_matches_next244'], true],
    'commit complete released' => [static fn (): mixed => $released244()['current_source_upsert_commit_complete_next244'], true],
    'commit complete missing false' => [static fn (): mixed => $missing244()['current_source_upsert_commit_complete_next244'], false],
    'commit complete unexpected false' => [static fn (): mixed => $unexpected244()['current_source_upsert_commit_complete_next244'], false],
    'commit complete reversed false' => [static fn (): mixed => $reversed244()['current_source_upsert_commit_complete_next244'], false],
    'next visible released' => [static fn (): mixed => $released244()['next_source_visible_after_current_source_upsert_commit_next244'], true],
    'next denied missing' => [static fn (): mixed => $missing244()['next_source_visible_after_current_source_upsert_commit_next244'], false],
    'next denied statement mismatch' => [static fn (): mixed => $statementHeld244()['next_source_visible_after_current_source_upsert_commit_next244'], false],
    'next denied watermark mismatch' => [static fn (): mixed => $watermarkHeld244()['next_source_visible_after_current_source_upsert_commit_next244'], false],
    'current row count' => [static fn (): mixed => $released244()['current_source_row_count_next244'], 2],
    'attempted next row count' => [static fn (): mixed => $released244()['attempted_next_source_row_count_next244'], 2],
    'visible released count' => [static fn (): mixed => $released244()['visible_row_count_next244'], 4],
    'held released count' => [static fn (): mixed => $released244()['held_next_row_count_next244'], 0],
    'visible missing count current only' => [static fn (): mixed => $missing244()['visible_row_count_next244'], 2],
    'held missing count next only' => [static fn (): mixed => $missing244()['held_next_row_count_next244'], 2],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released244()['current_source_rows_next244'], 'upsert_commit_phase_next244'))), ['current-commit']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released244()['attempted_next_source_rows_next244'], 'upsert_commit_phase_next244'))), ['next-source']],
    'current visible while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing244()['current_source_rows_next244'], 'visible_after_current_source_upsert_commit_next244'))), [true]],
    'next visible released' => [static fn (): mixed => array_values(array_unique(array_column($released244()['attempted_next_source_rows_next244'], 'visible_after_current_source_upsert_commit_next244'))), [true]],
    'next held missing' => [static fn (): mixed => array_values(array_unique(array_column($missing244()['attempted_next_source_rows_next244'], 'visible_after_current_source_upsert_commit_next244'))), [false]],
    'current commit receipts tagged' => [static fn (): mixed => array_column($released244()['current_source_rows_next244'], 'current_source_upsert_commit_receipt_next244'), $receipts244()],
    'next commit receipts null' => [static fn (): mixed => array_values(array_unique(array_column($released244()['attempted_next_source_rows_next244'], 'current_source_upsert_commit_receipt_next244'))), [null]],
    'current statement stamped' => [static fn (): mixed => array_values(array_unique(array_column($released244()['current_source_rows_next244'], 'current_source_upsert_statement_id_next244'))), ['wp.current.source.upsert.statement.244']],
    'next statement stamped' => [static fn (): mixed => array_values(array_unique(array_column($released244()['attempted_next_source_rows_next244'], 'current_source_upsert_statement_id_next244'))), ['wp.current.source.upsert.statement.244']],
    'current watermark stamped' => [static fn (): mixed => array_values(array_unique(array_column($released244()['current_source_rows_next244'], 'current_source_upsert_commit_watermark_next244'))), ['wp.current.source.upsert.commit.244']],
    'visible payload names released' => [static fn (): mixed => array_column($released244()['visible_returning_payloads_next244'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held payload names missing' => [static fn (): mixed => array_column($missing244()['held_next_returning_payloads_next244'], 'name'), ['home', 'next_plugin']],
    'blocked reasons missing' => [static fn (): mixed => $missing244()['blocked_reasons_next244'], ['current-source-upsert-commit-missing']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected244()['blocked_reasons_next244'], ['current-source-upsert-commit-unexpected']],
    'blocked reasons reversed' => [static fn (): mixed => $reversed244()['blocked_reasons_next244'], ['current-source-upsert-commit-order-mismatch']],
    'blocked reasons statement' => [static fn (): mixed => $statementHeld244()['blocked_reasons_next244'], ['current-source-upsert-statement-id-mismatch']],
    'blocked reasons watermark' => [static fn (): mixed => $watermarkHeld244()['blocked_reasons_next244'], ['current-source-upsert-commit-watermark-mismatch']],
    'blocked reasons view' => [static fn (): mixed => $viewHeld244()['blocked_reasons_next244'], ['current-source-upsert-commit-view-cookie-mismatch']],
    'blocked reasons trigger' => [static fn (): mixed => $triggerHeld244()['blocked_reasons_next244'], ['current-source-upsert-commit-trigger-cookie-mismatch']],
    'blocked reasons base held' => [static fn (): mixed => $baseHeld244()['blocked_reasons_next244'], ['current-source-upsert-close-missing']],
    'released reasons empty' => [static fn (): mixed => $released244()['blocked_reasons_next244'], []],
    'held next reason tagged' => [static fn (): mixed => $missing244()['attempted_next_source_rows_next244'][0]['held_by_current_source_upsert_commit_reasons_next244'], ['current-source-upsert-commit-missing']],
    'released next reason empty' => [static fn (): mixed => $released244()['attempted_next_source_rows_next244'][0]['held_by_current_source_upsert_commit_reasons_next244'], []],
    'plan decision released' => [static fn (): mixed => $released244()['current_source_upsert_commit_plan_next244']['decision'], 'publish-next-source-after-current-recursive-view-upsert-commit-watermark'],
    'plan decision missing' => [static fn (): mixed => $missing244()['current_source_upsert_commit_plan_next244']['decision'], 'hold-next-source-until-current-recursive-view-upsert-commit-watermark'],
    'plan base visible' => [static fn (): mixed => $released244()['current_source_upsert_commit_plan_next244']['base_next_source_visible'], true],
    'plan required echoed' => [static fn (): mixed => $released244()['current_source_upsert_commit_plan_next244']['required_commit_receipts'], $receipts244()],
    'plan acknowledged echoed' => [static fn (): mixed => $missing244()['current_source_upsert_commit_plan_next244']['acknowledged_commit_receipts'], array_slice($receipts244(), 0, 1)],
    'plan next visible echoed' => [static fn (): mixed => $released244()['current_source_upsert_commit_plan_next244']['next_source_visible'], true],
    'yield boundary released' => [static fn (): mixed => $released244()['yield_boundary_next244'], 'recursive-view-upsert-next244-current-commit-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing244()['yield_boundary_next244'], 'recursive-view-upsert-next244-current-commit-fence-next'],
    'dependency closure marker' => [static fn (): mixed => $released244()['dependency_closure_next244'], 'no-new-support-component-reuses-native-recursive-view-upsert-close-seals-and-adds-statement-commit-watermarks'],
    'dependency includes next244' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next244', $released244()['dependencies_next244'], true), true],
    'dependency includes watermark' => [static fn (): mixed => in_array('sqlite-instead-of-view-trigger-upsert-statement-commit-watermark', $released244()['dependencies_next244'], true), true],
    'dependency includes next241' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next241', $released244()['dependencies_next244'], true), true],
    'non overlap mentions next241' => [static fn (): mixed => str_contains($released244()['non_overlap_next244'], 'next241 current-source close seals'), true],
    'bad statement id rejected' => [static fn (): mixed => $plan244(['current_source_upsert_statement_id_next244' => 'bad statement']), InvalidArgumentException::class],
    'bad watermark rejected' => [static fn (): mixed => $plan244(['current_source_upsert_commit_watermark_next244' => 'bad watermark']), InvalidArgumentException::class],
    'bad view cookie rejected' => [static fn (): mixed => $plan244(['current_upsert_commit_view_cookie_next244' => 'bad cookie']), InvalidArgumentException::class],
    'bad trigger cookie rejected' => [static fn (): mixed => $plan244(['current_upsert_commit_trigger_cookie_next244' => 'bad cookie']), InvalidArgumentException::class],
    'bad receipt list rejected' => [static fn (): mixed => $plan244(['acknowledged_current_source_upsert_commit_receipts_next244' => ['x' => $unexpectedReceipt244]]), InvalidArgumentException::class],
    'bad short receipt rejected' => [static fn (): mixed => $plan244(['acknowledged_current_source_upsert_commit_receipts_next244' => ['abc']]), InvalidArgumentException::class],
    'bad non hex receipt rejected' => [static fn (): mixed => $plan244(['acknowledged_current_source_upsert_commit_receipts_next244' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases244 as $name => [$callback, $expected]) {
    $tests['trigger recursive view upsert current source next244 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
