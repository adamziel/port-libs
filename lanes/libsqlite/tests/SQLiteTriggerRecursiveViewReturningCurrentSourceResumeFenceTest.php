<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows190 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView190 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-190-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-190-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
    'audit_label' => 'current-recursive-view-trigger-190',
];
$nextView190 = $currentView190;
$nextView190['source'] = 'main@view-cookie-190-next';
$nextView190['trigger_source'] = 'main@trigger-cookie-190-next';
$nextView190['audit_label'] = 'next-recursive-view-trigger-190';
$currentInput190 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput190 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning190 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$run190 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceResumeFence(
    $rows190,
    $currentInput190,
    $nextInput190,
    $currentView190,
    $nextView190,
    $returning190,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_190',
        'cursor_name' => 'wp_recursive_view_returning_cursor_190',
        'current_generation' => 'wp-current-returning-190',
        'next_generation' => 'wp-next-returning-190',
        'checkpoint_name' => 'wp_recursive_view_checkpoint_190',
        'page_size' => 3,
    ],
);
$held190 = static fn (): array => $run190();
$exposed190 = static fn (): array => $run190(['admit_next_source' => true, 'auto_ack_current' => true]);
$resumeHeld190 = static fn (): array => $run190(['admit_next_source' => true, 'auto_ack_current' => true, 'resume_source_token' => 'wp.returning.current.source.resume.190:stale']);
$nextResumeHeld190 = static fn (): array => $run190(['admit_next_source' => true, 'auto_ack_current' => true, 'next_source_resume_token' => 'wp_recursive_view_returning_cursor_190:wp-next-returning-190:stale']);
$signatureHeld190 = static fn (): array => $run190(['admit_next_source' => true, 'auto_ack_current' => true, 'source_signature' => 'sig190:stale']);
$ticketHeld190 = static fn (): array => $run190(['admit_next_source' => true, 'auto_ack_current' => true, 'drain_ticket' => 'wp.returning.current.source.drain.190:bad']);
$nonRecursive190 = static fn (): array => $run190(['admit_next_source' => true, 'auto_ack_current' => true, 'recursive_triggers' => false]);

$cases190 = [
    'held status' => [static fn (): mixed => $held190()['status'], 'trigger-recursive-view-returning-current-source-next190-drain-ticket-held'],
    'exposed status' => [static fn (): mixed => $exposed190()['status'], 'trigger-recursive-view-returning-current-source-next190-next-exposed'],
    'resume held status' => [static fn (): mixed => $resumeHeld190()['status'], 'trigger-recursive-view-returning-current-source-next190-resume-token-held'],
    'next resume held status' => [static fn (): mixed => $nextResumeHeld190()['status'], 'trigger-recursive-view-returning-current-source-next190-next-resume-held'],
    'signature held status' => [static fn (): mixed => $signatureHeld190()['status'], 'trigger-recursive-view-returning-current-source-next190-source-signature-held'],
    'ticket held status' => [static fn (): mixed => $ticketHeld190()['status'], 'trigger-recursive-view-returning-current-source-next190-drain-ticket-held'],
    'base next187 retained' => [static fn (): mixed => $exposed190()['base']['status'], 'trigger-recursive-view-returning-current-source-next187-next-exposed'],
    'base ticket mismatch retained' => [static fn (): mixed => $ticketHeld190()['base']['status'], 'trigger-recursive-view-returning-current-source-next187-drain-ticket-held'],
    'resume prefix retained' => [static fn (): mixed => $exposed190()['resume_source_prefix'], 'wp.returning.current.source.resume.190'],
    'resume token matches exposed' => [static fn (): mixed => $exposed190()['resume_source_matches'], true],
    'resume token mismatch recorded' => [static fn (): mixed => $resumeHeld190()['resume_source_matches'], false],
    'next resume matches exposed' => [static fn (): mixed => $exposed190()['next_source_resume_matches'], true],
    'next resume mismatch recorded' => [static fn (): mixed => $nextResumeHeld190()['next_source_resume_matches'], false],
    'source signature matches exposed' => [static fn (): mixed => $exposed190()['source_signature_matches'], true],
    'source signature mismatch recorded' => [static fn (): mixed => $signatureHeld190()['source_signature_matches'], false],
    'expected resume equals actual exposed' => [static fn (): mixed => $exposed190()['expected_resume_source_token'], $exposed190()['resume_source_token']],
    'expected resume differs stale' => [static fn (): mixed => $resumeHeld190()['expected_resume_source_token'] === $resumeHeld190()['resume_source_token'], false],
    'expected next resume equals actual exposed' => [static fn (): mixed => $exposed190()['expected_next_source_resume_token'], $exposed190()['next_source_resume_token']],
    'expected source signature equals actual exposed' => [static fn (): mixed => $exposed190()['expected_source_signature'], $exposed190()['source_signature']],
    'last current resume token' => [static fn (): mixed => $exposed190()['last_current_resume_token'], 'wp_recursive_view_returning_cursor_190:wp-current-returning-190:5'],
    'first next resume token' => [static fn (): mixed => $exposed190()['first_next_resume_token'], 'wp_recursive_view_returning_cursor_190:wp-next-returning-190:6'],
    'base next exposed before resume' => [static fn (): mixed => $exposed190()['base_next_exposed_before_resume_source'], true],
    'base next held before resume' => [static fn (): mixed => $held190()['base_next_exposed_before_resume_source'], false],
    'next exposed after resume' => [static fn (): mixed => $exposed190()['next_source_exposed_after_resume_source'], true],
    'next held after resume mismatch' => [static fn (): mixed => $resumeHeld190()['next_source_exposed_after_resume_source'], false],
    'next held after source mismatch' => [static fn (): mixed => $signatureHeld190()['next_source_exposed_after_resume_source'], false],
    'current row count' => [static fn (): mixed => count($exposed190()['current_source_rows']), 6],
    'attempted next row count' => [static fn (): mixed => count($exposed190()['attempted_next_source_rows']), 4],
    'visible held current only' => [static fn (): mixed => count($held190()['visible_rows']), 6],
    'visible exposed all rows' => [static fn (): mixed => count($exposed190()['visible_rows']), 10],
    'held rows exposed empty' => [static fn (): mixed => $exposed190()['held_rows'], []],
    'held rows resume mismatch count' => [static fn (): mixed => count($resumeHeld190()['held_rows']), 4],
    'held rows next mismatch count' => [static fn (): mixed => count($nextResumeHeld190()['held_rows']), 4],
    'held rows signature mismatch count' => [static fn (): mixed => count($signatureHeld190()['held_rows']), 4],
    'visible names held' => [static fn (): mixed => array_column($held190()['visible_returning_rows'], 'name'), ['siteurl', 'current_plugin', 'siteurl:child', 'current_plugin:child', 'siteurl:child:child', 'current_plugin:child:child']],
    'visible names exposed' => [static fn (): mixed => array_column($exposed190()['visible_returning_rows'], 'name'), ['siteurl', 'current_plugin', 'siteurl:child', 'current_plugin:child', 'siteurl:child:child', 'current_plugin:child:child', 'home', 'next_plugin', 'home:child', 'home:child:child']],
    'held names resume mismatch' => [static fn (): mixed => array_column($resumeHeld190()['held_returning_rows'], 'name'), ['home', 'next_plugin', 'home:child', 'home:child:child']],
    'current visible unique' => [static fn (): mixed => array_values(array_unique(array_column($exposed190()['current_source_rows'], 'visible_after_resume_source'))), [true]],
    'next visible exposed unique' => [static fn (): mixed => array_values(array_unique(array_column($exposed190()['attempted_next_source_rows'], 'visible_after_resume_source'))), [true]],
    'next visible held unique' => [static fn (): mixed => array_values(array_unique(array_column($resumeHeld190()['attempted_next_source_rows'], 'visible_after_resume_source'))), [false]],
    'held block reasons' => [static fn (): mixed => $held190()['block_reasons'], ['current-checkpoint-ack-missing', 'next-checkpoints-still-pending']],
    'resume mismatch block reasons' => [static fn (): mixed => $resumeHeld190()['block_reasons'], ['current-source-resume-token-mismatch']],
    'next resume mismatch block reasons' => [static fn (): mixed => $nextResumeHeld190()['block_reasons'], ['next-source-resume-token-mismatch']],
    'signature mismatch block reasons' => [static fn (): mixed => $signatureHeld190()['block_reasons'], ['current-source-signature-mismatch']],
    'ticket mismatch block reason retained' => [static fn (): mixed => $ticketHeld190()['block_reasons'], ['current-source-drain-ticket-mismatch']],
    'exposed block reasons empty' => [static fn (): mixed => $exposed190()['block_reasons'], []],
    'held next row reasons' => [static fn (): mixed => $resumeHeld190()['attempted_next_source_rows'][0]['held_by_resume_source_reasons'], ['current-source-resume-token-mismatch']],
    'exposed next row reasons empty' => [static fn (): mixed => $exposed190()['attempted_next_source_rows'][0]['held_by_resume_source_reasons'], []],
    'resume plan current rows' => [static fn (): mixed => $exposed190()['resume_plan']['current_row_count'], 6],
    'resume plan next rows' => [static fn (): mixed => $exposed190()['resume_plan']['attempted_next_row_count'], 4],
    'resume plan visible rows' => [static fn (): mixed => $exposed190()['resume_plan']['visible_row_count'], 10],
    'resume plan held rows exposed' => [static fn (): mixed => $exposed190()['resume_plan']['held_next_row_count'], 0],
    'resume plan held rows mismatch' => [static fn (): mixed => $resumeHeld190()['resume_plan']['held_next_row_count'], 4],
    'resume plan decision exposed' => [static fn (): mixed => $exposed190()['resume_plan']['decision'], 'admit-next-source-returning'],
    'resume plan decision held' => [static fn (): mixed => $resumeHeld190()['resume_plan']['decision'], 'hold-next-source-returning'],
    'resume plan blocked token exposed' => [static fn (): mixed => $exposed190()['resume_plan']['blocked_at_token'], null],
    'resume plan blocked token mismatch' => [static fn (): mixed => $resumeHeld190()['resume_plan']['blocked_at_token'], 'wp_recursive_view_returning_cursor_190:wp-next-returning-190:6'],
    'counts current rows' => [static fn (): mixed => $exposed190()['counts']['current_rows'], 6],
    'counts next rows' => [static fn (): mixed => $exposed190()['counts']['attempted_next_rows'], 4],
    'counts visible exposed' => [static fn (): mixed => $exposed190()['counts']['visible_rows'], 10],
    'counts held mismatch' => [static fn (): mixed => $resumeHeld190()['counts']['held_rows'], 4],
    'yield boundary held' => [static fn (): mixed => $held190()['yield_boundary'], 'recursive-view-returning-current-source-next190-resume-source-held'],
    'yield boundary exposed' => [static fn (): mixed => $exposed190()['yield_boundary'], 'recursive-view-returning-current-source-next190-resume-source-next-exposed'],
    'dependency next190' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next190', $exposed190()['dependencies'], true), true],
    'dependency resume token' => [static fn (): mixed => in_array('sqlite-returning-current-source-resume-token', $exposed190()['dependencies'], true), true],
    'dependency next187 retained' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next187', $exposed190()['dependencies'], true), true],
    'dependency closure note' => [static fn (): mixed => $exposed190()['dependency_closure'], 'no new support component needed; reuses recursive view trigger RETURNING drain-ticket rows and adds current-source resume token validation'],
    'non overlap mentions next187' => [static fn (): mixed => str_contains($exposed190()['non_overlap'], 'next187'), true],
    'non recursive visible names' => [static fn (): mixed => array_column($nonRecursive190()['visible_returning_rows'], 'name'), ['siteurl', 'current_plugin', 'home', 'next_plugin']],
    'non recursive last current resume' => [static fn (): mixed => $nonRecursive190()['last_current_resume_token'], 'wp_recursive_view_returning_cursor_190:wp-current-returning-190:1'],
    'custom prefix accepted' => [static fn (): mixed => $run190(['admit_next_source' => true, 'auto_ack_current' => true, 'resume_source_prefix' => 'wp.custom.resume.190'])['resume_source_prefix'], 'wp.custom.resume.190'],
    'explicit expected resume accepted' => [static fn (): mixed => $run190(['admit_next_source' => true, 'auto_ack_current' => true, 'expected_resume_source_token' => $exposed190()['resume_source_token']])['resume_source_matches'], true],
    'bad resume token rejected' => [static fn (): mixed => $run190(['resume_source_token' => 'bad token']), InvalidArgumentException::class],
    'bad expected resume token rejected' => [static fn (): mixed => $run190(['expected_resume_source_token' => 'bad token']), InvalidArgumentException::class],
    'bad resume prefix rejected' => [static fn (): mixed => $run190(['resume_source_prefix' => 'bad token']), InvalidArgumentException::class],
    'bad next resume token rejected' => [static fn (): mixed => $run190(['next_source_resume_token' => 'bad token']), InvalidArgumentException::class],
    'bad source signature rejected' => [static fn (): mixed => $run190(['source_signature' => 'bad token']), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases190 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next190 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
