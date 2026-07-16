<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows230 = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes'],
];
$currentView230 = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-230-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-230-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-230',
];
$nextView230 = $currentView230;
$nextView230['source'] = 'main@view-cookie-230-next';
$nextView230['trigger_source'] = 'main@trigger-cookie-230-next';
$postResetView230 = $currentView230;
$postResetView230['source'] = 'main@view-cookie-230-post-reset';
$postResetView230['trigger_source'] = 'main@trigger-cookie-230-post-reset';
$followingView230 = $currentView230;
$followingView230['source'] = 'main@view-cookie-230-following';
$followingView230['trigger_source'] = 'main@trigger-cookie-230-following';
$subsequentNextView230 = $nextView230;
$subsequentNextView230['source'] = 'main@view-cookie-230-subsequent-next';
$subsequentNextView230['trigger_source'] = 'main@trigger-cookie-230-subsequent-next';
$currentInput230 = [
    ['import_id' => 10, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_module', 'value' => 'enabled', 'load_policy_flag' => 'no', 'spawn_child' => true],
];
$nextInput230 = [
    ['import_id' => 20, 'name' => 'landing_url', 'value' => 'https://next.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_module', 'value' => 'active', 'load_policy_flag' => 'no', 'spawn_child' => false],
];
$returning230 = [
    ['expr' => 'new.key_name', 'as' => 'name'],
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$plan230 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceEpochReceipt(
    $rows230,
    $currentInput230,
    $nextInput230,
    $currentView230,
    $nextView230,
    $returning230,
    $options + [
        'key' => 'key_name',
        'savepoint' => 'app_recursive_view_230',
        'cursor_name' => 'app_recursive_view_returning_cursor_230',
        'admit_next_source' => true,
        'rollback_token' => 'app.rollback.current.230',
        'reset_generation' => 'app-current-reset-230',
        'post_reset_current_source_token' => 'app.current.source.postreset.230',
        'post_reset_cursor' => 'app.returning.postreset.cursor.230',
        'post_reset_view' => $postResetView230,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'base_url', 'value' => 'https://fresh.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'routing_rules', 'value' => 'fresh-rules', 'load_policy_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'app.next.source.230',
        'next_cursor' => 'app.returning.next.cursor.230',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'app.returning.next.cursor.230',
        'following_current_source_token' => 'app.current.source.following.base.230',
        'following_cursor' => 'app.returning.following.cursor.230',
        'following_current_view' => $followingView230,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'app_summary', 'value' => 'after-next', 'load_policy_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'theme_style_key', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'app-following-current-230',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'app.current.source.recursive.child.230',
        'recursive_child_cursor' => 'app.returning.recursive.child.cursor.230',
        'recursive_child_generation' => 'app-recursive-child-current-230',
        'current_generation_next203' => 'app.current.recursive.returning.generation.230',
        'expected_current_generation_next203' => 'app.current.recursive.returning.generation.230',
        'current_handoff_cursor_next203' => 'app.returning.current.handoff.cursor.230',
        'current_generation_commit_marker_next203' => 'app.current.recursive.returning.commit.230',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'app.current.source.drain.230',
        'current_view_cookie_next209' => 'main@view-cookie-230-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-230-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'app.current.source.yield.230',
        'current_view_yield_cursor_next212' => 'app.returning.view.yield.cursor.230',
        'current_trigger_yield_cursor_next212' => 'app.returning.trigger.yield.cursor.230',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_provenance_token_next217' => 'app.current.source.provenance.230',
        'auto_ack_current_source_provenance_next217' => true,
        'next_source_reset_token_next219' => 'app.next.source.reset.230',
        'next_source_reset_cursor_next219' => 'app.returning.next.reset.cursor.230',
        'following_current_source_token_next219' => 'app.current.source.following.230',
        'following_current_view_next219' => $followingView230,
        'following_current_input_next219' => [
            ['import_id' => 50, 'name' => 'active_modules', 'value' => 'module-a/module.php', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 51, 'name' => 'routing_rules', 'value' => 'post-name-rules', 'load_policy_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 52, 'name' => 'theme_mods_modern_theme', 'value' => 'serialized-theme-mods', 'load_policy_flag' => 'no', 'spawn_child' => false],
        ],
        'auto_ack_next_source_reset_next219' => true,
        'following_current_seal_token_next226' => 'app.following.current.seal.230',
        'following_current_seal_cursor_next226' => 'app.returning.following.current.cursor.230',
        'auto_ack_following_current_seal_next226' => true,
        'subsequent_next_source_token_next226' => 'app.subsequent.next.source.230',
        'subsequent_next_view_next226' => $subsequentNextView230,
        'subsequent_next_input_next226' => [
            ['import_id' => 60, 'name' => 'cron', 'value' => 'sealed-next-cron', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 61, 'name' => 'widget_block', 'value' => 'sealed-widget-state', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ],
        'current_source_epoch' => 'app.current.source.epoch.230',
        'current_source_epoch_cursor' => 'app.returning.current.epoch.cursor.230',
    ],
);

$receipts230 = static fn (): array => $plan230()['required_current_source_epoch_receipts'];
$released230 = static fn (): array => $plan230(['auto_ack_current_source_epoch' => true]);
$missing230 = static fn (): array => $plan230(['acknowledged_current_source_epoch_receipts' => array_slice($receipts230(), 0, 1)]);
$unexpected230 = static fn (): array => $plan230(['acknowledged_current_source_epoch_receipts' => array_merge($receipts230(), ['abcdefabcdefabcdefabcdefabcdefabcd'])]);
$reversed230 = static fn (): array => $plan230(['acknowledged_current_source_epoch_receipts' => array_reverse($receipts230())]);
$unordered230 = static fn (): array => $plan230(['require_current_source_epoch_order' => false, 'acknowledged_current_source_epoch_receipts' => array_reverse($receipts230())]);
$baseHeld230 = static fn (): array => $plan230(['auto_ack_following_current_seal_next226' => false, 'auto_ack_current_source_epoch' => true]);
$badEpoch230 = static fn (): array => $plan230(['auto_ack_current_source_epoch' => true, 'expected_current_source_epoch' => 'app.current.source.epoch.other.230']);
$badCursor230 = static fn (): array => $plan230(['auto_ack_current_source_epoch' => true, 'expected_current_source_epoch_cursor' => 'app.returning.current.epoch.cursor.other.230']);
$custom230 = static fn (): array => $plan230(['auto_ack_current_source_epoch' => true, 'current_source_epoch' => 'app.current.source.epoch.custom.230']);

$cases230 = [
    'released status' => [static fn (): mixed => $released230()['status_current_source_epoch'], 'trigger-recursive-view-returning-current-source-epoch-subsequent-next-visible'],
    'missing status' => [static fn (): mixed => $missing230()['status_current_source_epoch'], 'trigger-recursive-view-returning-current-source-epoch-epoch-receipt-held'],
    'unexpected status' => [static fn (): mixed => $unexpected230()['status_current_source_epoch'], 'trigger-recursive-view-returning-current-source-epoch-epoch-receipt-held'],
    'reversed status' => [static fn (): mixed => $reversed230()['status_current_source_epoch'], 'trigger-recursive-view-returning-current-source-epoch-epoch-order-held'],
    'unordered released status' => [static fn (): mixed => $unordered230()['status_current_source_epoch'], 'trigger-recursive-view-returning-current-source-epoch-subsequent-next-visible'],
    'base held status' => [static fn (): mixed => $baseHeld230()['status_current_source_epoch'], 'trigger-recursive-view-returning-current-source-epoch-base-held'],
    'bad epoch status' => [static fn (): mixed => $badEpoch230()['status_current_source_epoch'], 'trigger-recursive-view-returning-current-source-epoch-epoch-held'],
    'bad cursor status' => [static fn (): mixed => $badCursor230()['status_current_source_epoch'], 'trigger-recursive-view-returning-current-source-epoch-cursor-held'],
    'base next226 released' => [static fn (): mixed => $released230()['base']['status_next226'], 'trigger-recursive-view-returning-current-source-next226-subsequent-next-visible'],
    'base next226 held' => [static fn (): mixed => $baseHeld230()['base']['status_next226'], 'trigger-recursive-view-returning-current-source-next226-seal-held'],
    'savepoint retained' => [static fn (): mixed => $released230()['savepoint'], 'app_recursive_view_230'],
    'base subsequent visible true' => [static fn (): mixed => $released230()['base_subsequent_next_visible'], true],
    'base subsequent visible false' => [static fn (): mixed => $baseHeld230()['base_subsequent_next_visible'], false],
    'epoch retained' => [static fn (): mixed => $released230()['current_source_epoch'], 'app.current.source.epoch.230'],
    'custom epoch retained' => [static fn (): mixed => $custom230()['current_source_epoch'], 'app.current.source.epoch.custom.230'],
    'expected epoch retained' => [static fn (): mixed => $released230()['expected_current_source_epoch'], 'app.current.source.epoch.230'],
    'epoch matches released' => [static fn (): mixed => $released230()['current_source_epoch_matches'], true],
    'epoch mismatch false' => [static fn (): mixed => $badEpoch230()['current_source_epoch_matches'], false],
    'cursor retained' => [static fn (): mixed => $released230()['current_source_epoch_cursor'], 'app.returning.current.epoch.cursor.230'],
    'cursor matches released' => [static fn (): mixed => $released230()['current_source_epoch_cursor_matches'], true],
    'cursor mismatch false' => [static fn (): mixed => $badCursor230()['current_source_epoch_cursor_matches'], false],
    'required receipt count' => [static fn (): mixed => count($released230()['required_current_source_epoch_receipts']), 3],
    'receipts are 34 hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{34}$/', $v), $receipts230()), [1, 1, 1]],
    'auto ack equals required' => [static fn (): mixed => $released230()['acknowledged_current_source_epoch_receipts'], $receipts230()],
    'missing ack count' => [static fn (): mixed => count($missing230()['acknowledged_current_source_epoch_receipts']), 1],
    'missing receipts recorded' => [static fn (): mixed => $missing230()['missing_current_source_epoch_receipts'], array_slice($receipts230(), 1)],
    'unexpected receipt recorded' => [static fn (): mixed => $unexpected230()['unexpected_current_source_epoch_receipts'], ['abcdefabcdefabcdefabcdefabcdefabcd']],
    'released missing empty' => [static fn (): mixed => $released230()['missing_current_source_epoch_receipts'], []],
    'released unexpected empty' => [static fn (): mixed => $released230()['unexpected_current_source_epoch_receipts'], []],
    'require order default' => [static fn (): mixed => $released230()['require_current_source_epoch_order'], true],
    'order matches released' => [static fn (): mixed => $released230()['current_source_epoch_order_matches'], true],
    'order mismatch reversed' => [static fn (): mixed => $reversed230()['current_source_epoch_order_matches'], false],
    'unordered considered matched' => [static fn (): mixed => $unordered230()['current_source_epoch_order_matches'], true],
    'epoch complete released' => [static fn (): mixed => $released230()['current_source_epoch_complete'], true],
    'epoch incomplete missing' => [static fn (): mixed => $missing230()['current_source_epoch_complete'], false],
    'epoch incomplete unexpected' => [static fn (): mixed => $unexpected230()['current_source_epoch_complete'], false],
    'subsequent visible released' => [static fn (): mixed => $released230()['subsequent_next_source_visible_after_epoch'], true],
    'subsequent held missing' => [static fn (): mixed => $missing230()['subsequent_next_source_visible_after_epoch'], false],
    'following row count' => [static fn (): mixed => $released230()['following_current_row_count'], 3],
    'subsequent row count' => [static fn (): mixed => $released230()['subsequent_next_row_count'], 2],
    'visible released count' => [static fn (): mixed => $released230()['visible_row_count'], 9],
    'visible held count' => [static fn (): mixed => $missing230()['visible_row_count'], 7],
    'held missing count' => [static fn (): mixed => $missing230()['held_subsequent_next_row_count'], 2],
    'held released count' => [static fn (): mixed => $released230()['held_subsequent_next_row_count'], 0],
    'following phase stamped' => [static fn (): mixed => array_values(array_unique(array_column($released230()['following_current_rows'], 'source_epoch_phase'))), ['following-current']],
    'subsequent phase stamped' => [static fn (): mixed => array_values(array_unique(array_column($released230()['subsequent_next_rows'], 'source_epoch_phase'))), ['subsequent-next']],
    'following visible stamped' => [static fn (): mixed => array_values(array_unique(array_column($released230()['following_current_rows'], 'visible_after_current_source_epoch'))), [true]],
    'subsequent visible stamped' => [static fn (): mixed => array_values(array_unique(array_column($released230()['subsequent_next_rows'], 'visible_after_current_source_epoch'))), [true]],
    'subsequent held stamped' => [static fn (): mixed => array_values(array_unique(array_column($missing230()['subsequent_next_rows'], 'visible_after_current_source_epoch'))), [false]],
    'following receipt stamped' => [static fn (): mixed => array_column($released230()['following_current_rows'], 'current_source_epoch_receipt'), $receipts230()],
    'subsequent receipt null' => [static fn (): mixed => array_values(array_unique(array_column($released230()['subsequent_next_rows'], 'current_source_epoch_receipt'))), [null]],
    'visible names released' => [static fn (): mixed => array_slice(array_column($released230()['visible_returning_payloads'], 'name'), -2), ['cron', 'widget_block']],
    'visible names held excludes subsequent' => [static fn (): mixed => in_array('cron', array_column($missing230()['visible_returning_payloads'], 'name'), true), false],
    'held names missing' => [static fn (): mixed => array_column($missing230()['held_subsequent_next_payloads'], 'name'), ['cron', 'widget_block']],
    'blocked missing' => [static fn (): mixed => $missing230()['blocked_reasons'], ['current-source-epoch-missing']],
    'blocked unexpected' => [static fn (): mixed => $unexpected230()['blocked_reasons'], ['current-source-epoch-unexpected']],
    'blocked reversed' => [static fn (): mixed => $reversed230()['blocked_reasons'], ['current-source-epoch-order-mismatch']],
    'blocked epoch' => [static fn (): mixed => $badEpoch230()['blocked_reasons'], ['current-source-epoch-mismatch']],
    'blocked cursor' => [static fn (): mixed => $badCursor230()['blocked_reasons'], ['current-source-epoch-cursor-mismatch']],
    'blocked released empty' => [static fn (): mixed => $released230()['blocked_reasons'], []],
    'held row reasons copied' => [static fn (): mixed => $missing230()['subsequent_next_rows'][0]['held_by_current_source_epoch_reasons'], ['current-source-epoch-missing']],
    'plan decision released' => [static fn (): mixed => $released230()['current_source_epoch_plan']['decision'], 'publish-subsequent-next-source-after-current-epoch'],
    'plan decision held' => [static fn (): mixed => $missing230()['current_source_epoch_plan']['decision'], 'hold-subsequent-next-source-until-current-epoch'],
    'plan epoch complete echoed' => [static fn (): mixed => $released230()['current_source_epoch_plan']['epoch_complete'], true],
    'yield boundary released' => [static fn (): mixed => $released230()['yield_boundary'], 'recursive-view-returning-current-source-epoch-then-subsequent-next'],
    'yield boundary held' => [static fn (): mixed => $missing230()['yield_boundary'], 'recursive-view-returning-current-source-epoch-fences-subsequent-next'],
    'dependency closure marker' => [static fn (): mixed => $released230()['dependency_closure'], 'no-new-support-component-reuses-native-recursive-view-returning-next226-and-adds-current-source-epoch-admission'],
    'dependency includes current-source-epoch' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-epoch', $released230()['dependencies'], true), true],
    'dependency includes epoch fence' => [static fn (): mixed => in_array('sqlite-returning-current-source-epoch-fence', $released230()['dependencies'], true), true],
    'non overlap mentions next226' => [static fn (): mixed => str_contains($released230()['non_overlap'], 'next226 following-current seal'), true],
    'bad epoch rejected' => [static fn (): mixed => $plan230(['current_source_epoch' => 'bad epoch']), InvalidArgumentException::class],
    'bad expected epoch rejected' => [static fn (): mixed => $plan230(['expected_current_source_epoch' => 'bad epoch']), InvalidArgumentException::class],
    'bad cursor rejected' => [static fn (): mixed => $plan230(['current_source_epoch_cursor' => 'bad cursor']), InvalidArgumentException::class],
    'bad receipt list rejected' => [static fn (): mixed => $plan230(['acknowledged_current_source_epoch_receipts' => ['x' => 'abcdefabcdefabcdefabcdefabcdefabcd']]), InvalidArgumentException::class],
    'bad receipt value rejected' => [static fn (): mixed => $plan230(['acknowledged_current_source_epoch_receipts' => ['abc']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases230 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source epoch ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
