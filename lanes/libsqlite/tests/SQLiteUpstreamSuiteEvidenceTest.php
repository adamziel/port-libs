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
        $t->same(3, $plan['script_count']);
        $t->same(1, $plan['jobs']);
        $t->same(false, $plan['runnable']);
        $t->contains('upstream cache/testfixture not hydrated in this worktree', (string) $plan['skip_reason']);
        $t->contains('.upstream-cache/libsqlite-build-port-libsqlite/testfixture', (string) $plan['skip_reason']);
        $t->contains('.upstream-cache/libsqlite/test/testrunner.tcl', (string) $plan['skip_reason']);
    },
    'builds an honest focused upstream subset matrix for closure clusters' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $matrix = $evidence->focusedSubsetMatrix([
            'json-table' => ['json101.test', 'json102.test', 'jsonb01.test'],
            'wal-pager' => ['wal*.test', 'pager*.test'],
            'btree-delete' => ['delete2.test', 'delete3.test', 'btree01.test'],
        ], 2, dirname(__DIR__, 3));

        $t->same(['json-table', 'wal-pager', 'btree-delete'], array_keys($matrix));
        $t->same(3, $matrix['json-table']['script_count']);
        $t->same(2, $matrix['wal-pager']['script_count']);
        $t->same(3, $matrix['btree-delete']['script_count']);
        $t->same(2, $matrix['json-table']['jobs']);
        $t->same('cd .upstream-cache/libsqlite-build-port-libsqlite && ./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick wal*.test pager*.test', $matrix['wal-pager']['command']);
        $t->same(false, $matrix['json-table']['runnable']);
        $t->same(false, $matrix['wal-pager']['runnable']);
        $t->contains('upstream cache/testfixture not hydrated in this worktree', (string) $matrix['btree-delete']['skip_reason']);
    },
    'rejects unsafe focused upstream subset script names' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');

        try {
            $evidence->focusedSubsetPlan(['json101.test; rm -rf /'], dirname(__DIR__, 3));
            $t->fail('Expected unsafe focused subset script name to be rejected');
        } catch (InvalidArgumentException $exception) {
            $t->contains('SQLite .test names', $exception->getMessage());
        }
    },
    'builds machine readable focused upstream subset run records' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $skipped = $evidence->focusedSubsetRunRecord(
            'json-table',
            ['json101.test', 'json102.test', 'jsonb01.test'],
            2,
            dirname(__DIR__, 3)
        );

        $t->same('json-table', $skipped['name']);
        $t->same('skipped', $skipped['status']);
        $t->same(false, $skipped['runnable']);
        $t->same(3, $skipped['script_count']);
        $t->same(0, $skipped['result_scripts']);
        $t->same(0, $skipped['result_tests']);
        $t->same(0, $skipped['result_errors']);
        $t->contains('upstream cache/testfixture not hydrated in this worktree', (string) $skipped['skip_reason']);

        $passed = $evidence->focusedSubsetRunRecord(
            'json-table',
            ['json101.test', 'json102.test', 'jsonb01.test'],
            2,
            dirname(__DIR__, 3),
            'Passed 3 scripts with 0 errors out of 650 tests in 00:00.'
        );

        $t->same('passed', $passed['status']);
        $t->same(null, $passed['skip_reason']);
        $t->same(3, $passed['result_scripts']);
        $t->same(650, $passed['result_tests']);
        $t->same(0, $passed['result_errors']);

        $failed = $evidence->focusedSubsetRunRecord(
            'wal-pager',
            ['wal*.test', 'pager*.test'],
            1,
            dirname(__DIR__, 3),
            'Passed 2 scripts with 1 errors out of 729 tests in 00:01.'
        );

        $t->same('failed', $failed['status']);
        $t->same(1, $failed['result_errors']);
    },
];
