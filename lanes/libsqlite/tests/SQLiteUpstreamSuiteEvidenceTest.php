<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

return [
    'summarizes libsqlite upstream denominator and veryquick evidence' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $summary = $evidence->denominatorSummary();

        $t->same('cloned-static-inventory-plus-upstream-veryquick-runner', $summary['status']);
        $t->same(1589, $summary['total']);
        $t->true($summary['mapped'] >= 279, 'Expected accepted mapped count to be preserved');
        $t->same(1189, $summary['inventory_units']['testDirectoryTclTests']);
        $t->same(278, $summary['inventory_units']['extensionTclTests']);
        $t->same(146, $summary['inventory_units']['extensionNestedTclTests']);
        $t->same(32, $summary['inventory_units']['testDirectoryTclHarnessFiles']);
        $t->same(33, $summary['inventory_units']['testDirectoryCPrograms']);
        $t->same(47, $summary['inventory_units']['srcTestCOrHeaderHelpers']);
        $t->same(6, $summary['inventory_units']['mptestFiles']);
        $t->same(4, $summary['inventory_units']['toolTestPrograms']);
        $t->same(76, $summary['inventory_units']['toolTestishFiles']);
        $t->same(true, $summary['veryquick']['executed']);
        $t->same(1235, $summary['veryquick']['scripts']);
        $t->same(329670, $summary['veryquick']['tests']);
        $t->same(0, $summary['veryquick']['errors']);
        $t->contains('Full all/release permutations remain unexecuted', $summary['warning']);
    },
    'reports focused upstream subset command and honest skip reason without hydrated cache' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $plan = $evidence->focusedSubsetPlan(['json101.test', 'json102.test', 'jsonb01.test'], dirname(__DIR__, 3));

        $t->same([
            'json101.test',
            'json102.test',
            'jsonb01.test',
        ], $plan['scripts']);
        $t->same('cd .upstream-cache/libsqlite-build-port-libsqlite && ./testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick json101.test json102.test jsonb01.test', $plan['command']);
        $t->same(false, $plan['runnable']);
        $t->contains('upstream cache/testfixture not hydrated in this worktree', (string) $plan['skip_reason']);
        $t->contains('.upstream-cache/libsqlite-build-port-libsqlite/testfixture', (string) $plan['skip_reason']);
        $t->contains('.upstream-cache/libsqlite/test/testrunner.tcl', (string) $plan['skip_reason']);
    },
];
