<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$rows232 = [
    ['option_name' => 'siteurl', 'option_value' => 'https://old.test'],
    ['option_name' => 'home', 'option_value' => 'https://old-home.test'],
    ['option_name' => 'rewrite_rules', 'option_value' => 'old-rules'],
];
$view232 = [
    'name' => 'wp_option_import_view',
    'source' => 'main@cookie232-current',
    'mapping' => ['name' => 'option_name', 'value' => 'option_value'],
];
$triggers232 = [
    ['name' => 'wp_options_au_home', 'when' => 'siteurl', 'target' => 'home', 'value' => '{value}/home'],
    ['name' => 'wp_options_au_rewrite', 'when' => 'home', 'target' => 'rewrite_rules', 'value' => 'flushed:{value}'],
];
$current232 = [
    ['name' => 'siteurl', 'value' => 'https://current.test'],
    ['name' => 'blogname', 'value' => 'Current Blog'],
];
$next232 = [
    ['name' => 'siteurl', 'value' => 'https://next.test'],
    ['name' => 'fresh_plugin', 'value' => 'enabled'],
];

$plan232 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentUpsertConflictSeal(
    $rows232,
    $current232,
    $next232,
    $view232,
    ['option_name'],
    $triggers232,
    $options + [
        'savepoint' => 'wp_view_recursive_232',
        'current_upsert_source_next232' => 'wp.current.upsert.source.232',
        'current_view_source_next232' => 'main@cookie232-current',
        'current_trigger_program_next232' => 'wp.current.recursive.trigger.program.232',
    ],
);

$seals232 = static fn (): array => $plan232()['required_current_upsert_conflict_seals_next232'];
$released232 = static fn (): array => $plan232(['auto_ack_current_upsert_conflict_seals_next232' => true]);
$missing232 = static fn (): array => $plan232(['acknowledged_current_upsert_conflict_seals_next232' => array_slice($seals232(), 0, 3)]);
$unexpectedSeal232 = 'abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcd';
$unexpected232 = static fn (): array => $plan232(['acknowledged_current_upsert_conflict_seals_next232' => array_merge($seals232(), [$unexpectedSeal232])]);
$reversed232 = static fn (): array => $plan232(['acknowledged_current_upsert_conflict_seals_next232' => array_reverse($seals232())]);
$unordered232 = static fn (): array => $plan232(['require_current_upsert_conflict_order_next232' => false, 'acknowledged_current_upsert_conflict_seals_next232' => array_reverse($seals232())]);
$viewHeld232 = static fn (): array => $plan232(['auto_ack_current_upsert_conflict_seals_next232' => true, 'expected_current_view_source_next232' => 'main@cookie232-stale']);
$triggerHeld232 = static fn (): array => $plan232(['auto_ack_current_upsert_conflict_seals_next232' => true, 'expected_current_trigger_program_next232' => 'wp.current.recursive.trigger.program.stale.232']);
$custom232 = static fn (): array => $plan232([
    'auto_ack_current_upsert_conflict_seals_next232' => true,
    'current_upsert_source_next232' => 'wp.current.upsert.source.custom.232',
    'current_view_source_next232' => 'main@cookie232-custom',
    'current_trigger_program_next232' => 'wp.current.recursive.trigger.program.custom.232',
]);

$cases232 = [
    'released status' => [static fn (): mixed => $released232()['status_next232'], 'trigger-recursive-view-upsert-current-source-next232-conflict-released'],
    'missing status' => [static fn (): mixed => $missing232()['status_next232'], 'trigger-recursive-view-upsert-current-source-next232-conflict-seal-held'],
    'unexpected status' => [static fn (): mixed => $unexpected232()['status_next232'], 'trigger-recursive-view-upsert-current-source-next232-conflict-seal-held'],
    'reversed status' => [static fn (): mixed => $reversed232()['status_next232'], 'trigger-recursive-view-upsert-current-source-next232-conflict-order-held'],
    'unordered reversed releases' => [static fn (): mixed => $unordered232()['status_next232'], 'trigger-recursive-view-upsert-current-source-next232-conflict-released'],
    'view held status' => [static fn (): mixed => $viewHeld232()['status_next232'], 'trigger-recursive-view-upsert-current-source-next232-view-source-held'],
    'trigger held status' => [static fn (): mixed => $triggerHeld232()['status_next232'], 'trigger-recursive-view-upsert-current-source-next232-trigger-program-held'],
    'savepoint retained' => [static fn (): mixed => $released232()['savepoint'], 'wp_view_recursive_232'],
    'base retained status' => [static fn (): mixed => $released232()['base']['status'], 'trigger-upsert-recursive-view-current-source-retained-next148'],
    'released base status' => [static fn (): mixed => $released232()['released_base_next232']['status'], 'trigger-upsert-recursive-view-next-source-admitted-next148'],
    'upsert source retained' => [static fn (): mixed => $released232()['current_upsert_source_next232'], 'wp.current.upsert.source.232'],
    'custom upsert source retained' => [static fn (): mixed => $custom232()['current_upsert_source_next232'], 'wp.current.upsert.source.custom.232'],
    'view source retained' => [static fn (): mixed => $released232()['current_view_source_next232'], 'main@cookie232-current'],
    'custom view source retained' => [static fn (): mixed => $custom232()['current_view_source_next232'], 'main@cookie232-custom'],
    'trigger program retained' => [static fn (): mixed => $released232()['current_trigger_program_next232'], 'wp.current.recursive.trigger.program.232'],
    'custom trigger program retained' => [static fn (): mixed => $custom232()['current_trigger_program_next232'], 'wp.current.recursive.trigger.program.custom.232'],
    'view source matches released' => [static fn (): mixed => $released232()['current_view_source_matches_next232'], true],
    'view mismatch detected' => [static fn (): mixed => $viewHeld232()['current_view_source_matches_next232'], false],
    'trigger program matches released' => [static fn (): mixed => $released232()['current_trigger_program_matches_next232'], true],
    'trigger mismatch detected' => [static fn (): mixed => $triggerHeld232()['current_trigger_program_matches_next232'], false],
    'seal count includes recursive upserts' => [static fn (): mixed => count($released232()['required_current_upsert_conflict_seals_next232']), 4],
    'seals are forty six hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{46}$/', $v), $released232()['required_current_upsert_conflict_seals_next232']), [1, 1, 1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released232()['acknowledged_current_upsert_conflict_seals_next232'], $seals232()],
    'missing acknowledged count' => [static fn (): mixed => count($missing232()['acknowledged_current_upsert_conflict_seals_next232']), 3],
    'missing seal recorded' => [static fn (): mixed => $missing232()['missing_current_upsert_conflict_seals_next232'], [array_slice($seals232(), -1)[0]]],
    'unexpected seal recorded' => [static fn (): mixed => $unexpected232()['unexpected_current_upsert_conflict_seals_next232'], [$unexpectedSeal232]],
    'released missing empty' => [static fn (): mixed => $released232()['missing_current_upsert_conflict_seals_next232'], []],
    'released unexpected empty' => [static fn (): mixed => $released232()['unexpected_current_upsert_conflict_seals_next232'], []],
    'require order default' => [static fn (): mixed => $released232()['require_current_upsert_conflict_order_next232'], true],
    'order matches released' => [static fn (): mixed => $released232()['current_upsert_conflict_order_matches_next232'], true],
    'order mismatch reversed' => [static fn (): mixed => $reversed232()['current_upsert_conflict_order_matches_next232'], false],
    'unordered disables order' => [static fn (): mixed => $unordered232()['require_current_upsert_conflict_order_next232'], false],
    'unordered order considered matched' => [static fn (): mixed => $unordered232()['current_upsert_conflict_order_matches_next232'], true],
    'conflict complete released' => [static fn (): mixed => $released232()['current_upsert_conflict_complete_next232'], true],
    'conflict incomplete missing' => [static fn (): mixed => $missing232()['current_upsert_conflict_complete_next232'], false],
    'conflict incomplete unexpected' => [static fn (): mixed => $unexpected232()['current_upsert_conflict_complete_next232'], false],
    'conflict incomplete reversed' => [static fn (): mixed => $reversed232()['current_upsert_conflict_complete_next232'], false],
    'conflict incomplete view mismatch' => [static fn (): mixed => $viewHeld232()['current_upsert_conflict_complete_next232'], false],
    'conflict incomplete trigger mismatch' => [static fn (): mixed => $triggerHeld232()['current_upsert_conflict_complete_next232'], false],
    'next visible released' => [static fn (): mixed => $released232()['next_source_visible_after_current_upsert_conflict_next232'], true],
    'next denied missing' => [static fn (): mixed => $missing232()['next_source_visible_after_current_upsert_conflict_next232'], false],
    'current yield count' => [static fn (): mixed => count($released232()['current_yield_stream_next232']), 4],
    'attempted next yield count' => [static fn (): mixed => count($released232()['attempted_next_yield_stream_next232']), 4],
    'visible yield released count' => [static fn (): mixed => count($released232()['visible_yield_stream_next232']), 8],
    'visible yield missing current only' => [static fn (): mixed => count($missing232()['visible_yield_stream_next232']), 4],
    'held yield missing next only' => [static fn (): mixed => count($missing232()['held_next_yield_stream_next232']), 4],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released232()['current_yield_stream_next232'], 'upsert_conflict_phase_next232'))), ['current']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released232()['attempted_next_yield_stream_next232'], 'upsert_conflict_phase_next232'))), ['next']],
    'current visible while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing232()['current_yield_stream_next232'], 'visible_after_current_upsert_conflict_next232'))), [true]],
    'next held while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing232()['attempted_next_yield_stream_next232'], 'visible_after_current_upsert_conflict_next232'))), [false]],
    'current seals tagged' => [static fn (): mixed => array_column($released232()['current_yield_stream_next232'], 'current_upsert_conflict_seal_next232'), $seals232()],
    'next seals null' => [static fn (): mixed => array_values(array_unique(array_column($released232()['attempted_next_yield_stream_next232'], 'current_upsert_conflict_seal_next232'))), [null]],
    'yield events retained' => [static fn (): mixed => array_column($released232()['current_yield_stream_next232'], 'event'), ['update', 'update', 'update', 'insert']],
    'yield depths retained' => [static fn (): mixed => array_column($released232()['current_yield_stream_next232'], 'depth'), [0, 1, 2, 0]],
    'yield trigger chain retained' => [static fn (): mixed => array_column($released232()['current_yield_stream_next232'], 'trigger'), [null, 'wp_options_au_home', 'wp_options_au_rewrite', null]],
    'current returning names' => [static fn (): mixed => array_column($released232()['current_returning_rows_next232'], 'option_name'), ['siteurl', 'home', 'rewrite_rules', 'blogname']],
    'visible returning released names' => [static fn (): mixed => array_column($released232()['visible_returning_rows_next232'], 'option_name'), ['siteurl', 'home', 'rewrite_rules', 'blogname', 'siteurl', 'home', 'rewrite_rules', 'fresh_plugin']],
    'held returning missing names' => [static fn (): mixed => array_column($missing232()['held_next_returning_rows_next232'], 'option_name'), ['siteurl', 'home', 'rewrite_rules', 'fresh_plugin']],
    'visible change count released' => [static fn (): mixed => $released232()['visible_change_count_next232'], 8],
    'visible change count held' => [static fn (): mixed => $missing232()['visible_change_count_next232'], 4],
    'after savepoint released names' => [static fn (): mixed => array_column($released232()['after_savepoint_next232'], 'option_name'), ['siteurl', 'home', 'rewrite_rules', 'blogname', 'fresh_plugin']],
    'after savepoint held restores base names' => [static fn (): mixed => array_column($missing232()['after_savepoint_next232'], 'option_name'), ['siteurl', 'home', 'rewrite_rules']],
    'released final siteurl is next' => [static fn (): mixed => $released232()['after_savepoint_next232'][0]['option_value'], 'https://next.test'],
    'held final siteurl is old' => [static fn (): mixed => $missing232()['after_savepoint_next232'][0]['option_value'], 'https://old.test'],
    'blocked reasons missing' => [static fn (): mixed => $missing232()['blocked_reasons_next232'], ['current-upsert-conflict-seal-missing']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected232()['blocked_reasons_next232'], ['current-upsert-conflict-seal-unexpected']],
    'blocked reasons reversed' => [static fn (): mixed => $reversed232()['blocked_reasons_next232'], ['current-upsert-conflict-seal-order-mismatch']],
    'blocked reasons view' => [static fn (): mixed => $viewHeld232()['blocked_reasons_next232'], ['current-upsert-view-source-mismatch']],
    'blocked reasons trigger' => [static fn (): mixed => $triggerHeld232()['blocked_reasons_next232'], ['current-upsert-trigger-program-mismatch']],
    'released reasons empty' => [static fn (): mixed => $released232()['blocked_reasons_next232'], []],
    'held next reason tagged' => [static fn (): mixed => $missing232()['attempted_next_yield_stream_next232'][0]['held_by_current_upsert_conflict_reasons_next232'], ['current-upsert-conflict-seal-missing']],
    'plan decision released' => [static fn (): mixed => $released232()['current_upsert_conflict_plan_next232']['decision'], 'publish-next-source-after-current-recursive-upsert-conflicts'],
    'plan decision missing' => [static fn (): mixed => $missing232()['current_upsert_conflict_plan_next232']['decision'], 'hold-next-source-until-current-recursive-upsert-conflicts'],
    'plan required echoed' => [static fn (): mixed => $released232()['current_upsert_conflict_plan_next232']['required_seals'], $seals232()],
    'plan next visible echoed' => [static fn (): mixed => $released232()['current_upsert_conflict_plan_next232']['conflict_complete'], true],
    'yield boundary released' => [static fn (): mixed => $released232()['yield_boundary_next232'], 'recursive-view-upsert-next232-current-conflict-sealed-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing232()['yield_boundary_next232'], 'recursive-view-upsert-next232-current-conflict-seal-fences-next'],
    'dependency closure marker' => [static fn (): mixed => $released232()['dependency_closure_next232'], 'no-new-support-component-reuses-native-recursive-view-upsert-current-source-yield-and-adds-conflict-seal'],
    'dependency includes next232' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next232', $released232()['dependencies_next232'], true), true],
    'dependency includes upsert conflict seal' => [static fn (): mixed => in_array('sqlite-current-upsert-conflict-seal', $released232()['dependencies_next232'], true), true],
    'non overlap mentions returning next229' => [static fn (): mixed => str_contains($released232()['non_overlap_next232'], 'next157-next229'), true],
    'bad upsert source rejected' => [static fn (): mixed => $plan232(['current_upsert_source_next232' => 'bad token']), InvalidArgumentException::class],
    'bad view source rejected' => [static fn (): mixed => $plan232(['current_view_source_next232' => 'bad source']), InvalidArgumentException::class],
    'bad trigger program rejected' => [static fn (): mixed => $plan232(['current_trigger_program_next232' => 'bad token']), InvalidArgumentException::class],
    'bad seal list rejected' => [static fn (): mixed => $plan232(['acknowledged_current_upsert_conflict_seals_next232' => ['x' => $unexpectedSeal232]]), InvalidArgumentException::class],
    'bad short seal rejected' => [static fn (): mixed => $plan232(['acknowledged_current_upsert_conflict_seals_next232' => ['abc']]), InvalidArgumentException::class],
    'bad non hex seal rejected' => [static fn (): mixed => $plan232(['acknowledged_current_upsert_conflict_seals_next232' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases232 as $name => [$callback, $expected]) {
    $tests['trigger recursive view upsert current source next232 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
