<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

return [
    'blocks duplicate top level test runner map candidates after hydrated map closure' => static function (TestRunner $t): void {
        $upstreamTestDirectory = '/home/claude/port-libs/.upstream-cache/libsqlite/test';
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $plan = $evidence->upstreamRunnerMapGapClosurePlan($upstreamTestDirectory, 1000);

        $t->same('exhausted', $plan['status']);
        $t->same('hydrated-upstream-test-directory', $plan['source']);
        $t->same($upstreamTestDirectory, $plan['upstream_test_directory']);
        $t->true($plan['real_script_count'] >= 1189, 'Expected hydrated SQLite test directory to expose the real upstream test corpus');
        $t->true($plan['already_selected_script_count'] >= 1189, 'Expected manifest mapped-script evidence to exclude already mapped top-level scripts');
        $t->same(0, $plan['candidate_count']);
        $t->same(1000, $plan['candidate_limit']);
        $t->same(0, $plan['mapped_delta']);
        $t->same(117, $plan['remaining_denominator']);
        $t->same(false, $plan['counts_runner_map_gap_closure']);
        $t->contains('testrunner.tcl --jobs 1 --stop-on-error veryquick', $plan['command']);
        $t->contains('top-level hydrated .test runner-map rows are already mapped', $plan['next_gate']);
        $t->contains('remaining non-.test harness, helper, mptest, and tool denominator units', $plan['next_gate']);
        $t->contains('manifest mapped-script evidence', $plan['dependency_closure']);

        $scripts = $plan['candidate_scripts'];
        $t->same(0, count($scripts));
        $t->same($scripts, array_values(array_unique($scripts)));
        $t->same($scripts, array_values($scripts));

        $t->same(false, in_array('json101.test', $scripts, true), 'Expected already-selected manifest scripts to be excluded');
        $t->same(false, in_array('btree01.test', $scripts, true), 'Expected already-selected manifest scripts to be excluded');
    },
    'rejects non positive map gap closure limits' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');

        $t->throws(InvalidArgumentException::class, static fn (): array => $evidence->upstreamRunnerMapGapClosurePlan('/home/claude/port-libs/.upstream-cache/libsqlite/test', 0));
    },
];
