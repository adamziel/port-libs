<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

return [
    'builds a bulk runner map gap closure plan from real upstream scripts' => static function (TestRunner $t): void {
        $upstreamTestDirectory = '/home/claude/port-libs/.upstream-cache/libsqlite/test';
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $plan = $evidence->upstreamRunnerMapGapClosurePlan($upstreamTestDirectory, 1000);

        $t->same('ready', $plan['status']);
        $t->same('hydrated-upstream-test-directory', $plan['source']);
        $t->same($upstreamTestDirectory, $plan['upstream_test_directory']);
        $t->true($plan['real_script_count'] >= 1189, 'Expected hydrated SQLite test directory to expose the real upstream test corpus');
        $t->true($plan['already_selected_script_count'] >= 40, 'Expected existing manifest runner selections to be excluded');
        $t->same(1000, $plan['candidate_count']);
        $t->same(1000, $plan['candidate_limit']);
        $t->same(0, $plan['mapped_delta']);
        $t->same(true, $plan['counts_runner_map_gap_closure']);
        $t->contains('testrunner.tcl --jobs 1 --stop-on-error veryquick', $plan['command']);
        $t->contains('run the generated guarded veryquick command', $plan['next_gate']);
        $t->contains('real hydrated upstream .test files', $plan['dependency_closure']);

        $scripts = $plan['candidate_scripts'];
        $t->same(1000, count($scripts));
        $t->same($scripts, array_values(array_unique($scripts)));
        $t->same($scripts, array_values($scripts));
        foreach ($scripts as $script) {
            $t->true(is_string($script) && str_ends_with($script, '.test'), 'Expected every candidate to be a .test script');
            $t->true(is_file($upstreamTestDirectory . '/' . $script), 'Expected candidate script to exist in the hydrated upstream checkout: ' . (string) $script);
            $t->same(false, str_contains((string) $script, '*'), 'Expected concrete script names, not wildcard patterns');
        }

        $t->same(false, in_array('json101.test', $scripts, true), 'Expected already-selected manifest scripts to be excluded');
        $t->same(false, in_array('btree01.test', $scripts, true), 'Expected already-selected manifest scripts to be excluded');
    },
    'rejects non positive map gap closure limits' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');

        $t->throws(InvalidArgumentException::class, static fn (): array => $evidence->upstreamRunnerMapGapClosurePlan('/home/claude/port-libs/.upstream-cache/libsqlite/test', 0));
    },
];
