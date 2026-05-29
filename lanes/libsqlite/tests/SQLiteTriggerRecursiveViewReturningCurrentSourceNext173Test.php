<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows173 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'source' => 'seed'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'source' => 'seed'],
];
$currentView173 = [
    'name' => 'wp_option_import_view',
    'source' => 'main@view-cookie-173-current',
    'trigger' => 'wp_option_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-173-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_retry',
    'audit_label' => 'current-recursive-trigger-drain-173',
];
$nextView173 = [
    'name' => 'wp_option_import_view',
    'source' => 'main@view-cookie-173-next',
    'trigger' => 'wp_option_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-173-next',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'origin'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'origin' => 'source'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_next_retry',
    'audit_label' => 'next-recursive-trigger-drain-173',
];
$currentInput173 = [
    ['import_id' => 10, 'name' => 'plugin_seed', 'value' => 'enabled', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'skip_me', 'value' => 'disabled', 'autoload_flag' => 'skip', 'spawn_child' => true],
    ['import_id' => 12, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
];
$nextInput173 = [
    ['import_id' => 20, 'name' => 'rewrite_rules', 'value' => 'cached', 'autoload_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'home', 'value' => 'https://next-home.test', 'autoload_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => false],
    ['import_id' => 22, 'name' => 'next_skip', 'value' => 'ignored', 'autoload_flag' => 'skip', 'origin' => 'next-import', 'spawn_child' => true],
];
$returning173 = [
    'new.option_name',
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'old.option_value', 'as' => 'old_value'],
    ['expr' => 'view.name', 'as' => 'view_name'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$plan173 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext173(
    $rows173,
    $currentInput173,
    $nextInput173,
    $currentView173,
    $nextView173,
    $returning173,
    $options + ['key' => 'option_name', 'savepoint' => 'wp_recursive_view_173', 'max_depth' => 2, 'page_size' => 2],
);

$partial173 = static fn (): array => $plan173(['admit_next_source' => true, 'drained_current_pages' => 1]);
$exhausted173 = static fn (): array => $plan173(['admit_next_source' => true, 'drained_current_pages' => 2]);
$token173 = static fn (): string => $exhausted173()['current_source_signature_next173'];
$stale173 = static fn (): array => $plan173(['admit_next_source' => true, 'drained_current_pages' => 2, 'resume_source_signature' => 'stale|view|source|token']);
$notRequested173 = static fn (): array => $plan173(['drained_current_pages' => 2]);
$zero173 = static fn (): array => $plan173(['admit_next_source' => true, 'drained_current_pages' => 0]);
$wide173 = static fn (): array => $plan173(['admit_next_source' => true, 'page_size' => 3, 'drained_current_pages' => 1]);

$cases173 = [
    'partial status fences next source' => [static fn (): mixed => $partial173()['status_next173'], 'trigger-recursive-view-returning-current-source-cursor-fences-next-source-next173'],
    'partial requested admission is recorded' => [static fn (): mixed => $partial173()['requested_next_source_admission'], true],
    'partial resume token matches current' => [static fn (): mixed => $partial173()['resume_source_matches_current'], true],
    'partial current cursor is not exhausted' => [static fn (): mixed => $partial173()['current_cursor_exhausted'], false],
    'partial drained count' => [static fn (): mixed => $partial173()['current_pages_drained_count'], 1],
    'partial total current page count' => [static fn (): mixed => $partial173()['current_pages_total_count'], 2],
    'partial drained page names' => [static fn (): mixed => $partial173()['drained_current_pages'][0]['names'], ['plugin_seed', 'siteurl']],
    'partial pending page names' => [static fn (): mixed => $partial173()['pending_current_pages'][0]['names'], ['plugin_seed_retry', 'plugin_seed_retry_retry']],
    'partial visible pages are drained current only' => [static fn (): mixed => array_column($partial173()['visible_returning_pages_next173'], 'phase'), ['current']],
    'partial next pages remain blocked' => [static fn (): mixed => array_column($partial173()['blocked_next_source_pages_next173'], 'phase'), ['next', 'next']],
    'partial blocked next names zero' => [static fn (): mixed => $partial173()['blocked_next_source_pages_next173'][0]['names'], ['rewrite_rules', 'home']],
    'partial block reason is current cursor' => [static fn (): mixed => $partial173()['next_source_block_reasons_next173'], ['current-returning-cursor-not-exhausted']],
    'partial cursor state pending count' => [static fn (): mixed => $partial173()['returning_cursor_state_next173']['pending_current_pages'], 1],
    'partial cursor state visible page count' => [static fn (): mixed => $partial173()['returning_cursor_state_next173']['visible_pages'], 1],
    'partial cursor state blocked next count' => [static fn (): mixed => $partial173()['returning_cursor_state_next173']['blocked_next_pages'], 2],
    'partial boundary' => [static fn (): mixed => $partial173()['yield_boundary_next173'], 'recursive-view-returning-next173-next-source-held-by-current-cursor-or-token'],
    'partial preserves next167 admitted preparation' => [static fn (): mixed => $partial173()['status_next167'], 'trigger-recursive-view-returning-next-source-admitted-after-current-drain-next167'],
    'partial keeps prepared next rows available' => [static fn (): mixed => array_column(array_column($partial173()['next_returning_rows'], 'returning'), 'option_name'), ['rewrite_rules', 'home', 'rewrite_rules_next_retry', 'rewrite_rules_next_retry_next_retry']],

    'exhausted status admits next source' => [static fn (): mixed => $exhausted173()['status_next173'], 'trigger-recursive-view-returning-next-source-admitted-after-exhausted-current-cursor-next173'],
    'exhausted cursor is exhausted' => [static fn (): mixed => $exhausted173()['current_cursor_exhausted'], true],
    'exhausted next source admitted' => [static fn (): mixed => $exhausted173()['next_source_admitted_next173'], true],
    'exhausted no pending current pages' => [static fn (): mixed => $exhausted173()['pending_current_pages'], []],
    'exhausted no blocked next pages' => [static fn (): mixed => $exhausted173()['blocked_next_source_pages_next173'], []],
    'exhausted no block reasons' => [static fn (): mixed => $exhausted173()['next_source_block_reasons_next173'], []],
    'exhausted visible phases include next after current' => [static fn (): mixed => array_column($exhausted173()['visible_returning_pages_next173'], 'phase'), ['current', 'current', 'next', 'next']],
    'exhausted next page names one' => [static fn (): mixed => $exhausted173()['visible_returning_pages_next173'][2]['names'], ['rewrite_rules', 'home']],
    'exhausted next page names two' => [static fn (): mixed => $exhausted173()['visible_returning_pages_next173'][3]['names'], ['rewrite_rules_next_retry', 'rewrite_rules_next_retry_next_retry']],
    'exhausted cursor visible page count' => [static fn (): mixed => $exhausted173()['returning_cursor_state_next173']['visible_pages'], 4],
    'exhausted cursor blocked page count' => [static fn (): mixed => $exhausted173()['returning_cursor_state_next173']['blocked_next_pages'], 0],
    'exhausted boundary' => [static fn (): mixed => $exhausted173()['yield_boundary_next173'], 'recursive-view-returning-next173-current-cursor-exhausted-source-token-matched'],
    'exhausted dependency marker' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next173', $exhausted173()['dependencies_next173'], true), true],
    'exhausted source signature equals token helper' => [static fn (): mixed => $exhausted173()['resume_source_signature'], $token173()],

    'stale token status fences next source' => [static fn (): mixed => $stale173()['status_next173'], 'trigger-recursive-view-returning-current-source-cursor-fences-next-source-next173'],
    'stale token mismatch recorded' => [static fn (): mixed => $stale173()['resume_source_matches_current'], false],
    'stale token current cursor exhausted' => [static fn (): mixed => $stale173()['current_cursor_exhausted'], true],
    'stale token does not admit next' => [static fn (): mixed => $stale173()['next_source_admitted_next173'], false],
    'stale token visible pages current only' => [static fn (): mixed => array_column($stale173()['visible_returning_pages_next173'], 'phase'), ['current', 'current']],
    'stale token blocks next pages' => [static fn (): mixed => count($stale173()['blocked_next_source_pages_next173']), 2],
    'stale token block reason' => [static fn (): mixed => $stale173()['next_source_block_reasons_next173'], ['current-source-resume-signature-mismatch']],
    'stale token cursor state mismatch' => [static fn (): mixed => $stale173()['returning_cursor_state_next173']['resume_source_matches_current'], false],

    'not requested does not admit next' => [static fn (): mixed => $notRequested173()['next_source_admitted_next173'], false],
    'not requested block reason' => [static fn (): mixed => $notRequested173()['next_source_block_reasons_next173'], ['next-source-not-requested']],
    'not requested visible pages current only' => [static fn (): mixed => array_column($notRequested173()['visible_returning_pages_next173'], 'phase'), ['current', 'current']],
    'not requested blocks prepared next pages' => [static fn (): mixed => count($notRequested173()['blocked_next_source_pages_next173']), 2],

    'zero drain has no visible pages' => [static fn (): mixed => $zero173()['visible_returning_pages_next173'], []],
    'zero drain holds all current pages pending' => [static fn (): mixed => count($zero173()['pending_current_pages']), 2],
    'zero drain blocks next and pending current reason' => [static fn (): mixed => $zero173()['next_source_block_reasons_next173'], ['current-returning-cursor-not-exhausted']],
    'zero drain cursor state pending count' => [static fn (): mixed => $zero173()['returning_cursor_state_next173']['pending_current_pages'], 2],

    'wide partial total current page count' => [static fn (): mixed => $wide173()['current_pages_total_count'], 2],
    'wide partial drained names' => [static fn (): mixed => $wide173()['drained_current_pages'][0]['names'], ['plugin_seed', 'siteurl', 'plugin_seed_retry']],
    'wide partial pending names' => [static fn (): mixed => $wide173()['pending_current_pages'][0]['names'], ['plugin_seed_retry_retry']],

    'negative drained count throws' => [static fn (): mixed => $plan173(['drained_current_pages' => -1]), InvalidArgumentException::class],
    'too many drained pages throws' => [static fn (): mixed => $plan173(['drained_current_pages' => 3]), InvalidArgumentException::class],
    'empty resume source signature throws' => [static fn (): mixed => $plan173(['resume_source_signature' => '']), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases173 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next173 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
