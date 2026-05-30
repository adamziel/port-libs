<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

$evidence = static fn (): SQLiteUpstreamSuiteEvidence => SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
$base = 'f5267a57d13e2beded8193e8f417d093885bf7af';
$dashboard = '103fc00c42f1ff0580cae8a7768e4a3da0979c2d';
$status = '5883f5e65ebfd2e9cf8c9acf617a2a818277909c';
$implementation = '21f1e38635e924df34f7be1aef3242b4b233710c';
$next = 'next102-source-head0000000000000000000000000000';
$focusedPath = 'lanes/libsqlite/tests/SQLiteUpstreamRunnerAdmissionTest.php';
$nonOverlap = 'next102 admits current-source upstream runner artifacts without repeating release-runner countability next99, suite denominator current-next68, or focused runner artifact admission';
$focusedOutput = "Focused test run: 1 selected test files (root lock skipped)\n"
    . "PASS next102 admission countable\n"
    . "PASS next102 preserved artifact\n"
    . "PASS next102 blocked artifacts\n"
    . "1 test files, 54 assertions, 0 failures\n";

$admittedRows = [
    'next102-json' => [
        'unit' => 'next102-json-malformed-current-source',
        'kind' => 'bounded-upstream-runner',
        'source_head' => $next,
        'launcher_base_head' => $base,
        'dashboard_source_head' => $dashboard,
        'status_source_head' => $status,
        'implementation_source_head' => $implementation,
        'artifact_path' => 'lanes/libsqlite/notes/suite-upstream-runner-admission-current-source-next102.md',
        'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick json101.test json102.test jsonb01.test',
        'scripts' => ['json102.test', 'json101.test', 'jsonb01.test'],
        'current_countable' => false,
        'next_countable' => true,
        'exit' => 0,
        'errors' => 0,
        'current_tests' => 0,
        'next_tests' => 650,
        'evidence' => 'Passed 3 scripts with 0 errors out of 650 tests in 00:00.',
    ],
    'next102-pager' => [
        'unit' => 'next102-pager-preserved-current-source',
        'kind' => 'bounded-upstream-runner',
        'source_head' => $next,
        'launcher_base_head' => $base,
        'dashboard_source_head' => $dashboard,
        'status_source_head' => $status,
        'implementation_source_head' => $implementation,
        'artifact_path' => 'lanes/libsqlite/notes/suite-upstream-runner-admission-current-source-next102-pager.md',
        'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick pager101.test',
        'scripts' => ['pager101.test'],
        'current_countable' => true,
        'next_countable' => true,
        'exit' => 0,
        'errors' => 0,
        'current_tests' => 240,
        'next_tests' => 240,
        'evidence' => 'Passed 1 scripts with 0 errors out of 240 tests in 00:00.',
    ],
];

$record = static fn (array $rows = null, ?int $expectedPassDelta = 3, string $processSnapshot = ''): array => $evidence()->upstreamRunnerAdmission(
    $rows ?? $admittedRows,
    587,
    39474,
    $base,
    $dashboard,
    $status,
    $implementation,
    $next,
    $focusedPath,
    $focusedOutput,
    $nonOverlap,
    $expectedPassDelta,
    $processSnapshot
);

$blockedRows = $admittedRows;
$blockedRows['stale'] = $blockedRows['next102-json'];
$blockedRows['stale']['unit'] = 'next102-stale-source';
$blockedRows['stale']['source_head'] = 'stale-source';
$blockedRows['stale']['artifact_path'] = '/tmp/stale.json';
$blockedRows['stale']['runner_command'] = './testfixture ../libsqlite/test/testrunner.tcl veryquick json101.test';
$blockedRows['stale']['exit'] = 1;
$blockedRows['stale']['errors'] = 2;
$blockedRows['stale']['next_tests'] = 0;
$blockedRows['stale']['scripts'] = [];
$blockedRows['stale']['evidence'] = '';
$blockedRows['stale']['counts_release_parity'] = true;
$blockedRows['stale']['blockers'] = ['manual-review-required'];

return [
    'next102 admission status is countable for fresh next source artifact' => static fn (TestRunner $t) => $t->same('current-source-next102-upstream-runner-admission-countable', $record()['status']),
    'next102 admission marks record countable' => static fn (TestRunner $t) => $t->same(true, $record()['countable']),
    'next102 admission maps exactly one new upstream runner row' => static fn (TestRunner $t) => $t->same(1, $record()['mapped_delta']),
    'next102 admission advances mapped count from current baseline' => static fn (TestRunner $t) => $t->same(588, $record()['next_mapped']),
    'next102 admission keeps current mapped source' => static fn (TestRunner $t) => $t->same(587, $record()['current_mapped']),
    'next102 admission counts exact focused pass delta' => static fn (TestRunner $t) => $t->same(3, $record()['php_pass_delta']),
    'next102 admission advances php pass by exact pass lines' => static fn (TestRunner $t) => $t->same(39477, $record()['next_php_pass']),
    'next102 admission preserves current php pass baseline' => static fn (TestRunner $t) => $t->same(39474, $record()['current_php_pass']),
    'next102 admission has two rows' => static fn (TestRunner $t) => $t->same(2, $record()['row_count']),
    'next102 admission admits new unit' => static fn (TestRunner $t) => $t->same(['next102-json-malformed-current-source'], $record()['admitted_units']),
    'next102 admission preserves already countable unit' => static fn (TestRunner $t) => $t->same(['next102-pager-preserved-current-source'], $record()['preserved_units']),
    'next102 admission has no blocked units' => static fn (TestRunner $t) => $t->same([], $record()['blocked_units']),
    'next102 admission records test delta only for newly admitted artifact' => static fn (TestRunner $t) => $t->same(650, $record()['tests_total_delta']),
    'next102 admission sorts target scripts' => static fn (TestRunner $t) => $t->same(['json101.test', 'json102.test', 'jsonb01.test', 'pager101.test'], $record()['target_scripts']),
    'next102 admission counts target scripts' => static fn (TestRunner $t) => $t->same(4, $record()['target_script_count']),
    'next102 admission records next source head' => static fn (TestRunner $t) => $t->same($next, $record()['next_source_head']),
    'next102 admission records artifact source heads' => static fn (TestRunner $t) => $t->same([$next], $record()['artifact_source_heads']),
    'next102 admission records launcher base head' => static fn (TestRunner $t) => $t->same($base, $record()['launcher_base_head']),
    'next102 admission records dashboard source head' => static fn (TestRunner $t) => $t->same($dashboard, $record()['dashboard_source_head']),
    'next102 admission records status source head' => static fn (TestRunner $t) => $t->same($status, $record()['status_source_head']),
    'next102 admission records implementation source head' => static fn (TestRunner $t) => $t->same($implementation, $record()['implementation_source_head']),
    'next102 admission active runner gate clear' => static fn (TestRunner $t) => $t->same('clear', $record()['active_runner_status']),
    'next102 admission active runner count zero' => static fn (TestRunner $t) => $t->same(0, $record()['active_runner_count']),
    'next102 admission has no blockers' => static fn (TestRunner $t) => $t->same(0, $record()['blocker_count']),
    'next102 admission does not claim release parity' => static fn (TestRunner $t) => $t->same(false, $record()['counts_release_parity']),
    'next102 admission suppresses next99 countability flag' => static fn (TestRunner $t) => $t->same(false, $record()['counts_release_runner_countability_current_source_next99']),
    'next102 admission suppresses next72 admission flag' => static fn (TestRunner $t) => $t->same(false, $record()['counts_release_runner_admission_current_next72']),
    'next102 admission exposes its own countability flag' => static fn (TestRunner $t) => $t->same(true, $record()['counts_upstream_runner_admission_current_source_next102']),
    'next102 admission next gate names current source' => static fn (TestRunner $t) => $t->contains('current-source next102 upstream-runner admission', $record()['next_gate']),
    'next102 admission dependency closure is support free' => static fn (TestRunner $t) => $t->contains('no new support component needed', $record()['dependency_closure']),
    'next102 admission non overlap note is preserved' => static fn (TestRunner $t) => $t->contains('without repeating release-runner countability next99', $record()['non_overlap_note']),
    'next102 admission first entry movement admitted' => static fn (TestRunner $t) => $t->same('next-source-admitted', $record()['entries'][0]['movement']),
    'next102 admission first entry scripts sorted' => static fn (TestRunner $t) => $t->same(['json101.test', 'json102.test', 'jsonb01.test'], $record()['entries'][0]['scripts']),
    'next102 admission first entry is next countable' => static fn (TestRunner $t) => $t->same(true, $record()['entries'][0]['next_countable']),
    'next102 admission first entry was not current countable' => static fn (TestRunner $t) => $t->same(false, $record()['entries'][0]['current_countable']),
    'next102 admission first entry has zero errors' => static fn (TestRunner $t) => $t->same(0, $record()['entries'][0]['errors']),
    'next102 admission first entry has lane-local artifact path' => static fn (TestRunner $t) => $t->contains('lanes/libsqlite/notes/', $record()['entries'][0]['artifact_path']),
    'next102 admission second entry movement preserved' => static fn (TestRunner $t) => $t->same('current-source-preserved', $record()['entries'][1]['movement']),
    'next102 admission second entry is current countable' => static fn (TestRunner $t) => $t->same(true, $record()['entries'][1]['current_countable']),
    'next102 admission second entry has no blocker ids' => static fn (TestRunner $t) => $t->same([], $record()['entries'][1]['blocker_ids']),
    'next102 admission php evidence uses current head' => static fn (TestRunner $t) => $t->same($base, $record()['php_pass_admission']['accepted_repository_head']),
    'next102 admission php evidence saw one test file' => static fn (TestRunner $t) => $t->same(1, $record()['php_pass_admission']['selected_test_files']),
    'next102 admission php evidence saw three pass lines' => static fn (TestRunner $t) => $t->same(3, $record()['php_pass_admission']['pass_lines_observed']),
    'next102 admission php evidence observed assertions' => static fn (TestRunner $t) => $t->same(54, $record()['php_pass_admission']['assertion_count_observed']),
    'next102 admission blocks stale artifacts' => static fn (TestRunner $t) => $t->same('blocked', $record($blockedRows)['status']),
    'next102 blocked artifact does not move mapped count' => static fn (TestRunner $t) => $t->same(0, $record($blockedRows)['mapped_delta']),
    'next102 blocked artifact does not move php pass' => static fn (TestRunner $t) => $t->same(0, $record($blockedRows)['php_pass_delta']),
    'next102 blocked artifact names stale unit' => static fn (TestRunner $t) => $t->true(in_array('next102-stale-source', $record($blockedRows)['blocked_units'], true)),
    'next102 blocked artifact reports source mismatch' => static fn (TestRunner $t) => $t->contains('next-source-head-mismatch', implode(' ', array_column($record($blockedRows)['blockers'], 'evidence'))),
    'next102 blocked artifact reports lane local path blocker' => static fn (TestRunner $t) => $t->contains('artifact-path-not-lane-local', implode(' ', array_column($record($blockedRows)['blockers'], 'evidence'))),
    'next102 blocked artifact reports guarded command blocker' => static fn (TestRunner $t) => $t->contains('guarded-runner-command-missing', implode(' ', array_column($record($blockedRows)['blockers'], 'evidence'))),
    'next102 blocked artifact reports release parity blocker' => static fn (TestRunner $t) => $t->contains('release-parity-claim-not-allowed', implode(' ', array_column($record($blockedRows)['blockers'], 'evidence'))),
    'next102 blocked artifact keeps countability false' => static fn (TestRunner $t) => $t->same(false, $record($blockedRows)['countable']),
    'next102 duplicate broad runner blocks admission' => static fn (TestRunner $t) => $t->same('blocked', $record(null, 3, "123 testfixture ../libsqlite/test/testrunner.tcl --stop-on-error all\n")['status']),
    'next102 duplicate broad runner exposes blocker' => static fn (TestRunner $t) => $t->same('duplicate-broad-runner-active', $record(null, 3, "123 testfixture ../libsqlite/test/testrunner.tcl --stop-on-error all\n")['blockers'][0]['id']),
    'next102 expected pass mismatch blocks admission' => static fn (TestRunner $t) => $t->same('blocked', $record(null, 4)['status']),
    'next102 expected pass mismatch reports php blocker' => static fn (TestRunner $t) => $t->same('focused-current-head-php-pass-blocked', $record(null, 4)['blockers'][0]['id']),
    'next102 rejects empty artifacts' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $evidence()->upstreamRunnerAdmission([], 587, 39474, $base, $dashboard, $status, $implementation, $next, $focusedPath, $focusedOutput, $nonOverlap, 3)),
    'next102 rejects missing next source head' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $evidence()->upstreamRunnerAdmission($admittedRows, 587, 39474, $base, $dashboard, $status, $implementation, '', $focusedPath, $focusedOutput, $nonOverlap, 3)),
];
