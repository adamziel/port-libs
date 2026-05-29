<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows176 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'source' => 'seed'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'source' => 'seed'],
];
$currentView176 = [
    'name' => 'wp_option_import_view',
    'source' => 'main@view-cookie-176-current',
    'trigger' => 'wp_option_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-176-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_retry',
    'audit_label' => 'current-recursive-trigger-page-acks-176',
];
$nextView176 = [
    'name' => 'wp_option_import_view',
    'source' => 'main@view-cookie-176-next',
    'trigger' => 'wp_option_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-176-next',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'origin'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'origin' => 'source'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_next_retry',
    'audit_label' => 'next-recursive-trigger-page-acks-176',
];
$currentInput176 = [
    ['import_id' => 10, 'name' => 'plugin_seed', 'value' => 'enabled', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'skip_me', 'value' => 'disabled', 'autoload_flag' => 'skip', 'spawn_child' => true],
    ['import_id' => 12, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
];
$nextInput176 = [
    ['import_id' => 20, 'name' => 'rewrite_rules', 'value' => 'cached', 'autoload_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'home', 'value' => 'https://next-home.test', 'autoload_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => false],
    ['import_id' => 22, 'name' => 'next_skip', 'value' => 'ignored', 'autoload_flag' => 'skip', 'origin' => 'next-import', 'spawn_child' => true],
];
$returning176 = [
    'new.option_name',
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'old.option_value', 'as' => 'old_value'],
    ['expr' => 'view.name', 'as' => 'view_name'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$plan176 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext176(
    $rows176,
    $currentInput176,
    $nextInput176,
    $currentView176,
    $nextView176,
    $returning176,
    $options + ['key' => 'option_name', 'savepoint' => 'wp_recursive_view_176', 'max_depth' => 2, 'page_size' => 2],
);

$all176 = static fn (): array => $plan176(['admit_next_source' => true, 'acknowledged_current_page_indexes' => [0, 1]]);
$partial176 = static fn (): array => $plan176(['admit_next_source' => true, 'acknowledged_current_page_indexes' => [0]]);
$gap176 = static fn (): array => $plan176(['admit_next_source' => true, 'acknowledged_current_page_indexes' => [1]]);
$duplicate176 = static fn (): array => $plan176(['admit_next_source' => true, 'acknowledged_current_page_indexes' => [0, 0]]);
$none176 = static fn (): array => $plan176(['admit_next_source' => true, 'acknowledged_current_page_indexes' => []]);
$notRequested176 = static fn (): array => $plan176(['acknowledged_current_page_indexes' => [0, 1]]);
$stale176 = static fn (): array => $plan176(['admit_next_source' => true, 'acknowledged_current_page_indexes' => [0, 1], 'resume_source_signature' => 'stale|view|token']);
$wide176 = static fn (): array => $plan176(['admit_next_source' => true, 'page_size' => 3, 'acknowledged_current_page_indexes' => [0, 1]]);

$cases176 = [
    'all status admits next source' => [static fn (): mixed => $all176()['status_next176'], 'trigger-recursive-view-returning-current-pages-contiguous-next-source-admitted-next176'],
    'all acknowledged indexes' => [static fn (): mixed => $all176()['acknowledged_current_page_indexes_next176'], [0, 1]],
    'all missing indexes empty' => [static fn (): mixed => $all176()['missing_current_page_indexes_next176'], []],
    'all acknowledgements contiguous' => [static fn (): mixed => $all176()['current_page_acknowledgements_contiguous_next176'], true],
    'all acknowledgements duplicate free' => [static fn (): mixed => $all176()['current_page_acknowledgements_duplicate_free_next176'], true],
    'all acknowledgements valid' => [static fn (): mixed => $all176()['current_page_acknowledgements_valid_next176'], true],
    'all admits next source' => [static fn (): mixed => $all176()['next_source_admitted_next176'], true],
    'all next173 also admits' => [static fn (): mixed => $all176()['next_source_admitted_next173'], true],
    'all visible phases include next' => [static fn (): mixed => array_column($all176()['visible_returning_pages_next173'], 'phase'), ['current', 'current', 'next', 'next']],
    'all next page names' => [static fn (): mixed => $all176()['visible_returning_pages_next173'][2]['names'], ['rewrite_rules', 'home']],
    'all cursor state total current pages' => [static fn (): mixed => $all176()['returning_cursor_state_next176']['total_current_pages'], 2],
    'all cursor state drained current pages' => [static fn (): mixed => $all176()['returning_cursor_state_next176']['drained_current_pages'], 2],
    'all cursor state admits' => [static fn (): mixed => $all176()['returning_cursor_state_next176']['next_source_admitted'], true],
    'all boundary' => [static fn (): mixed => $all176()['yield_boundary_next176'], 'recursive-view-returning-next176-contiguous-current-page-acks-release-next-source'],
    'all dependency marker' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next176', $all176()['dependencies_next176'], true), true],

    'partial status fences next source' => [static fn (): mixed => $partial176()['status_next176'], 'trigger-recursive-view-returning-current-page-acknowledgement-fences-next-source-next176'],
    'partial acknowledged indexes' => [static fn (): mixed => $partial176()['acknowledged_current_page_indexes_next176'], [0]],
    'partial missing indexes' => [static fn (): mixed => $partial176()['missing_current_page_indexes_next176'], [1]],
    'partial contiguous prefix' => [static fn (): mixed => $partial176()['current_page_acknowledgements_contiguous_next176'], true],
    'partial duplicate free' => [static fn (): mixed => $partial176()['current_page_acknowledgements_duplicate_free_next176'], true],
    'partial valid but not exhausted' => [static fn (): mixed => $partial176()['current_page_acknowledgements_valid_next176'], true],
    'partial next not admitted' => [static fn (): mixed => $partial176()['next_source_admitted_next176'], false],
    'partial next173 block reason' => [static fn (): mixed => $partial176()['next_source_block_reasons_next176'], ['current-returning-cursor-not-exhausted']],
    'partial visible pages current only' => [static fn (): mixed => array_column($partial176()['visible_returning_pages_next173'], 'phase'), ['current']],
    'partial pending page names' => [static fn (): mixed => $partial176()['pending_current_pages'][0]['names'], ['plugin_seed_retry', 'plugin_seed_retry_retry']],
    'partial cursor state drained pages' => [static fn (): mixed => $partial176()['returning_cursor_state_next176']['drained_current_pages'], 1],

    'gap status fences next source' => [static fn (): mixed => $gap176()['status_next176'], 'trigger-recursive-view-returning-current-page-acknowledgement-fences-next-source-next176'],
    'gap acknowledged indexes' => [static fn (): mixed => $gap176()['acknowledged_current_page_indexes_next176'], [1]],
    'gap missing indexes' => [static fn (): mixed => $gap176()['missing_current_page_indexes_next176'], [0]],
    'gap is not contiguous' => [static fn (): mixed => $gap176()['current_page_acknowledgements_contiguous_next176'], false],
    'gap duplicate free' => [static fn (): mixed => $gap176()['current_page_acknowledgements_duplicate_free_next176'], true],
    'gap invalid' => [static fn (): mixed => $gap176()['current_page_acknowledgements_valid_next176'], false],
    'gap next not admitted' => [static fn (): mixed => $gap176()['next_source_admitted_next176'], false],
    'gap reasons include current cursor and gap' => [static fn (): mixed => $gap176()['next_source_block_reasons_next176'], ['current-returning-cursor-not-exhausted', 'current-returning-page-acknowledgement-gap']],
    'gap cursor state contiguous false' => [static fn (): mixed => $gap176()['returning_cursor_state_next176']['contiguous_prefix'], false],
    'gap visible pages empty because no prefix drained' => [static fn (): mixed => $gap176()['visible_returning_pages_next173'], []],

    'duplicate acknowledged indexes' => [static fn (): mixed => $duplicate176()['acknowledged_current_page_indexes_next176'], [0, 0]],
    'duplicate not duplicate free' => [static fn (): mixed => $duplicate176()['current_page_acknowledgements_duplicate_free_next176'], false],
    'duplicate not contiguous' => [static fn (): mixed => $duplicate176()['current_page_acknowledgements_contiguous_next176'], false],
    'duplicate invalid' => [static fn (): mixed => $duplicate176()['current_page_acknowledgements_valid_next176'], false],
    'duplicate not admitted' => [static fn (): mixed => $duplicate176()['next_source_admitted_next176'], false],
    'duplicate reasons' => [static fn (): mixed => $duplicate176()['next_source_block_reasons_next176'], ['current-returning-cursor-not-exhausted', 'current-returning-page-acknowledgement-duplicate', 'current-returning-page-acknowledgement-gap']],

    'none acknowledged indexes' => [static fn (): mixed => $none176()['acknowledged_current_page_indexes_next176'], []],
    'none missing all pages' => [static fn (): mixed => $none176()['missing_current_page_indexes_next176'], [0, 1]],
    'none contiguous but not exhausted' => [static fn (): mixed => $none176()['current_page_acknowledgements_contiguous_next176'], true],
    'none visible pages empty' => [static fn (): mixed => $none176()['visible_returning_pages_next173'], []],
    'none cursor state drained zero' => [static fn (): mixed => $none176()['returning_cursor_state_next176']['drained_current_pages'], 0],

    'not requested keeps contiguous valid' => [static fn (): mixed => $notRequested176()['current_page_acknowledgements_valid_next176'], true],
    'not requested does not admit' => [static fn (): mixed => $notRequested176()['next_source_admitted_next176'], false],
    'not requested reason' => [static fn (): mixed => $notRequested176()['next_source_block_reasons_next176'], ['next-source-not-requested']],
    'not requested visible current pages only' => [static fn (): mixed => array_column($notRequested176()['visible_returning_pages_next173'], 'phase'), ['current', 'current']],

    'stale token keeps page acks valid' => [static fn (): mixed => $stale176()['current_page_acknowledgements_valid_next176'], true],
    'stale token does not admit' => [static fn (): mixed => $stale176()['next_source_admitted_next176'], false],
    'stale token reason' => [static fn (): mixed => $stale176()['next_source_block_reasons_next176'], ['current-source-resume-signature-mismatch']],
    'stale token cursor mismatch' => [static fn (): mixed => $stale176()['returning_cursor_state_next173']['resume_source_matches_current'], false],

    'wide current pages still two' => [static fn (): mixed => $wide176()['returning_cursor_state_next176']['total_current_pages'], 2],
    'wide admitted with contiguous indexes' => [static fn (): mixed => $wide176()['next_source_admitted_next176'], true],
    'wide first current page names' => [static fn (): mixed => $wide176()['drained_current_pages'][0]['names'], ['plugin_seed', 'siteurl', 'plugin_seed_retry']],
    'wide second current page names' => [static fn (): mixed => $wide176()['drained_current_pages'][1]['names'], ['plugin_seed_retry_retry']],

    'negative acknowledged index throws' => [static fn (): mixed => $plan176(['acknowledged_current_page_indexes' => [-1]]), InvalidArgumentException::class],
    'out of range acknowledged index throws' => [static fn (): mixed => $plan176(['acknowledged_current_page_indexes' => [2]]), InvalidArgumentException::class],
    'malformed acknowledged index list throws' => [static fn (): mixed => $plan176(['acknowledged_current_page_indexes' => ['first' => 0]]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases176 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next176 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
