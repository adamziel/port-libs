<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows175 = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'source' => 'seed'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes', 'source' => 'seed'],
];
$currentView175 = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-175-current',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-175-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_retry',
    'audit_label' => 'current-recursive-trigger-drain-175',
];
$nextView175 = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-175-next',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-175-next',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'origin'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'origin' => 'source'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_next_retry',
    'audit_label' => 'next-recursive-trigger-drain-175',
];
$currentInput175 = [
    ['import_id' => 10, 'name' => 'module_seed', 'value' => 'enabled', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'skip_me', 'value' => 'disabled', 'load_policy_flag' => 'skip', 'spawn_child' => true],
    ['import_id' => 12, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
];
$nextInput175 = [
    ['import_id' => 20, 'name' => 'routing_rules', 'value' => 'cached', 'load_policy_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'landing_url', 'value' => 'https://next-landing_url.test', 'load_policy_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => false],
    ['import_id' => 22, 'name' => 'next_skip', 'value' => 'ignored', 'load_policy_flag' => 'skip', 'origin' => 'next-import', 'spawn_child' => true],
];
$returning175 = [
    'new.key_name',
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'old.key_value', 'as' => 'old_value'],
    ['expr' => 'view.name', 'as' => 'view_name'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$plan175 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceCheckpointFence(
    $rows175,
    $currentInput175,
    $nextInput175,
    $currentView175,
    $nextView175,
    $returning175,
    $options + [
        'key' => 'key_name',
        'savepoint' => 'app_recursive_view_175',
        'max_depth' => 2,
        'page_size' => 2,
        'current_source_epoch' => 7,
        'restart_cursor' => 'app-recursive-view-returning-restart-175',
    ],
);

$hold175 = static fn (): array => $plan175(['savepoint_action' => 'hold', 'drained_current_pages' => 2]);
$release175 = static fn (): array => $plan175(['savepoint_action' => 'release', 'drained_current_pages' => 2]);
$rollback175 = static fn (): array => $plan175(['savepoint_action' => 'rollback', 'drained_current_pages' => 2]);
$partial175 = static fn (): array => $plan175(['savepoint_action' => 'release', 'drained_current_pages' => 1]);
$stale175 = static fn (): array => $plan175(['savepoint_action' => 'release', 'drained_current_pages' => 2, 'resume_source_signature' => 'stale|source|token']);
$zero175 = static fn (): array => $plan175(['savepoint_action' => 'release', 'drained_current_pages' => 0]);
$wide175 = static fn (): array => $plan175(['savepoint_action' => 'release', 'page_size' => 3, 'drained_current_pages' => 2]);

$cases175 = [
    'hold status fences next source' => [static fn (): mixed => $hold175()['status_next175'], 'trigger-recursive-view-returning-savepoint-holds-next-source-next175'],
    'hold action retained' => [static fn (): mixed => $hold175()['savepoint_action_next175'], 'hold'],
    'hold source epoch retained' => [static fn (): mixed => $hold175()['current_source_epoch_next175'], 7],
    'hold restart cursor retained' => [static fn (): mixed => $hold175()['restart_cursor_next175'], 'app-recursive-view-returning-restart-175'],
    'hold release not allowed' => [static fn (): mixed => $hold175()['savepoint_release_allowed_next175'], false],
    'hold rollback false' => [static fn (): mixed => $hold175()['savepoint_rolled_back_next175'], false],
    'hold next is held by savepoint' => [static fn (): mixed => $hold175()['next_source_held_by_savepoint_next175'], true],
    'hold visible pages are current only' => [static fn (): mixed => array_column($hold175()['visible_returning_pages_next175'], 'phase'), ['current', 'current']],
    'hold queued next page phases' => [static fn (): mixed => array_column($hold175()['queued_next_source_pages_next175'], 'phase'), ['next', 'next']],
    'hold queued first page names' => [static fn (): mixed => $hold175()['queued_next_source_pages_next175'][0]['names'], ['routing_rules', 'landing_url']],
    'hold block reason savepoint' => [static fn (): mixed => $hold175()['blocked_reasons_next175'], ['savepoint-release-not-requested']],
    'hold release decision' => [static fn (): mixed => $hold175()['release_plan_next175']['decision'], 'hold-next-source'],
    'hold release visible count' => [static fn (): mixed => $hold175()['release_plan_next175']['visible_pages'], 2],
    'hold release queued count' => [static fn (): mixed => $hold175()['release_plan_next175']['queued_pages'], 2],
    'hold restart from queue' => [static fn (): mixed => $hold175()['restart_plan_next175']['restart_from'], 'next-source-queue'],
    'hold restart not required' => [static fn (): mixed => $hold175()['restart_plan_next175']['restart_required'], false],

    'release status admits next source' => [static fn (): mixed => $release175()['status_next175'], 'trigger-recursive-view-returning-savepoint-released-next-source-next175'],
    'release action retained' => [static fn (): mixed => $release175()['savepoint_action_next175'], 'release'],
    'release allowed true' => [static fn (): mixed => $release175()['savepoint_release_allowed_next175'], true],
    'release next not held' => [static fn (): mixed => $release175()['next_source_held_by_savepoint_next175'], false],
    'release visible phases include next' => [static fn (): mixed => array_column($release175()['visible_returning_pages_next175'], 'phase'), ['current', 'current', 'next', 'next']],
    'release queued next empty' => [static fn (): mixed => $release175()['queued_next_source_pages_next175'], []],
    'release no block reasons' => [static fn (): mixed => $release175()['blocked_reasons_next175'], []],
    'release decision' => [static fn (): mixed => $release175()['release_plan_next175']['decision'], 'release-next-source'],
    'release prepared next page count' => [static fn (): mixed => $release175()['release_plan_next175']['next_source_prepared_pages'], 2],
    'release visible page count' => [static fn (): mixed => $release175()['release_plan_next175']['visible_pages'], 4],
    'release queued page count' => [static fn (): mixed => $release175()['release_plan_next175']['queued_pages'], 0],
    'release restart cursor source' => [static fn (): mixed => $release175()['restart_plan_next175']['restart_from'], 'next-source-queue'],
    'release yield boundary' => [static fn (): mixed => $release175()['yield_boundary_next175'], 'recursive-view-returning-next175-savepoint-release-after-current-source-drain'],

    'rollback status retains current source' => [static fn (): mixed => $rollback175()['status_next175'], 'trigger-recursive-view-returning-savepoint-rollback-retains-current-source-next175'],
    'rollback action retained' => [static fn (): mixed => $rollback175()['savepoint_action_next175'], 'rollback'],
    'rollback flag true' => [static fn (): mixed => $rollback175()['savepoint_rolled_back_next175'], true],
    'rollback release denied' => [static fn (): mixed => $rollback175()['savepoint_release_allowed_next175'], false],
    'rollback visible current only' => [static fn (): mixed => array_column($rollback175()['visible_returning_pages_next175'], 'phase'), ['current', 'current']],
    'rollback queues next pages for discard' => [static fn (): mixed => count($rollback175()['queued_next_source_pages_next175']), 2],
    'rollback block reason' => [static fn (): mixed => $rollback175()['blocked_reasons_next175'], ['savepoint-rolled-back-before-next-source-yield']],
    'rollback decision' => [static fn (): mixed => $rollback175()['release_plan_next175']['decision'], 'rollback-next-source'],
    'rollback restart required' => [static fn (): mixed => $rollback175()['restart_plan_next175']['restart_required'], true],
    'rollback restart from current image' => [static fn (): mixed => $rollback175()['restart_plan_next175']['restart_from'], 'current-source-savepoint-image'],
    'rollback yield boundary' => [static fn (): mixed => $rollback175()['yield_boundary_next175'], 'recursive-view-returning-next175-current-source-savepoint-fences-next-source'],

    'partial status holds despite release action' => [static fn (): mixed => $partial175()['status_next175'], 'trigger-recursive-view-returning-savepoint-holds-next-source-next175'],
    'partial current cursor not exhausted' => [static fn (): mixed => $partial175()['release_plan_next175']['current_cursor_exhausted'], false],
    'partial pending current page count' => [static fn (): mixed => count($partial175()['pending_current_pages_next175']), 1],
    'partial visible first current page only' => [static fn (): mixed => $partial175()['visible_returning_pages_next175'][0]['names'], ['module_seed', 'base_url']],
    'partial block reason current cursor' => [static fn (): mixed => $partial175()['blocked_reasons_next175'], ['current-returning-cursor-not-exhausted']],
    'partial queued next page count' => [static fn (): mixed => $partial175()['release_plan_next175']['queued_pages'], 2],

    'stale token status holds' => [static fn (): mixed => $stale175()['status_next175'], 'trigger-recursive-view-returning-savepoint-holds-next-source-next175'],
    'stale token mismatch recorded' => [static fn (): mixed => $stale175()['release_plan_next175']['resume_source_matches_current'], false],
    'stale restart required' => [static fn (): mixed => $stale175()['restart_plan_next175']['restart_required'], true],
    'stale restart from token' => [static fn (): mixed => $stale175()['restart_plan_next175']['restart_from'], 'current-source-resume-token'],
    'stale block reason' => [static fn (): mixed => $stale175()['blocked_reasons_next175'], ['current-source-resume-signature-mismatch']],

    'zero drain visible pages empty' => [static fn (): mixed => $zero175()['visible_returning_pages_next175'], []],
    'zero drain pending current pages' => [static fn (): mixed => count($zero175()['pending_current_pages_next175']), 2],
    'zero drain queued next pages' => [static fn (): mixed => count($zero175()['queued_next_source_pages_next175']), 2],
    'zero drain release decision holds' => [static fn (): mixed => $zero175()['release_plan_next175']['decision'], 'hold-next-source'],

    'wide release current first page names' => [static fn (): mixed => $wide175()['visible_returning_pages_next175'][0]['names'], ['module_seed', 'base_url', 'module_seed_retry']],
    'wide release current second page names' => [static fn (): mixed => $wide175()['visible_returning_pages_next175'][1]['names'], ['module_seed_retry_retry']],
    'wide release next page names' => [static fn (): mixed => $wide175()['visible_returning_pages_next175'][2]['names'], ['routing_rules', 'landing_url', 'routing_rules_next_retry']],
    'wide release visible page count' => [static fn (): mixed => $wide175()['release_plan_next175']['visible_pages'], 4],

    'dependency closure marker' => [static fn (): mixed => $release175()['dependency_closure_next175'], 'no-new-support-component-reuses-native-recursive-view-returning-current-source-savepoint-model'],
    'dependency includes next175' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next175', $release175()['dependencies_next175'], true), true],
    'dependency includes savepoint release' => [static fn (): mixed => in_array('sqlite-returning-savepoint-release-after-current-source-drain', $release175()['dependencies_next175'], true), true],
    'dependency includes savepoint rollback restart' => [static fn (): mixed => in_array('sqlite-returning-savepoint-rollback-restarts-current-source-cursor', $release175()['dependencies_next175'], true), true],

    'bad savepoint action throws' => [static fn (): mixed => $plan175(['savepoint_action' => 'commit']), InvalidArgumentException::class],
    'bad restart cursor throws' => [static fn (): mixed => $plan175(['restart_cursor' => 'bad cursor']), InvalidArgumentException::class],
    'negative epoch throws' => [static fn (): mixed => $plan175(['current_source_epoch' => -1]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases175 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next175 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
