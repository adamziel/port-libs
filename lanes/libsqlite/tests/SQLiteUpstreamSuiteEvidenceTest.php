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
    'audits recorded upstream runner coverage and remaining suite tiers' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $audit = $evidence->runnerCoverageAudit();

        $t->same(true, $audit['executed']);
        $t->true($audit['command_count'] >= 80, 'Expected accepted upstream command history to be counted');
        $t->true($audit['result_count'] >= 80, 'Expected accepted upstream result history to be counted');
        $t->true($audit['focused_result_count'] >= 20, 'Expected focused result notes to be counted separately');
        $t->true($audit['selected_script_count'] >= 40, 'Expected concrete upstream .test selections to be extracted');
        $t->true(in_array('json101.test', $audit['selected_scripts'], true), 'Expected json101.test in recorded upstream selections');
        $t->true(in_array('btree01.test', $audit['selected_scripts'], true), 'Expected btree01.test in recorded upstream selections');
        $t->true(in_array('btree*.test', $audit['pattern_scripts'], true), 'Expected btree*.test in recorded upstream pattern selections');
        $t->true(in_array('pager*.test', $audit['pattern_scripts'], true), 'Expected pager*.test in recorded upstream pattern selections');
        $t->same(1235, $audit['veryquick']['scripts']);
        $t->same(329670, $audit['veryquick']['tests']);
        $t->same(0, $audit['veryquick']['errors']);
        $t->same(58, $audit['permutation_suites_declared']);
        $t->same(28, $audit['all_test_suite_runs']);
        $t->same(false, $audit['full_release_executed']);
        $t->contains('not run', $audit['full_release_reason']);
        $t->same([
            'full release/all permutations',
            'multi-configuration make test suites',
            'long-running stress/permutation tiers beyond veryquick',
        ], $audit['remaining_suite_tiers']);
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
    'builds a machine readable focused result ledger from accepted runner notes' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $ledger = $evidence->focusedResultLedger();

        $t->true($ledger['entry_count'] >= 30, 'Expected accepted focused result notes to be inventoried');
        $t->true($ledger['passed_count'] >= 14, 'Expected zero-error focused result notes to be counted');
        $t->same(0, $ledger['failed_count']);
        $t->true($ledger['reused_or_skipped_count'] >= 10, 'Expected missing-cache/reused-evidence notes to remain explicit');
        $t->true($ledger['result_tests_total'] >= 50000, 'Expected parsed focused upstream test counts to be accumulated');
        $t->same(0, $ledger['result_errors_total']);
        $t->true(in_array('json101.test', $ledger['unique_scripts'], true), 'Expected json101.test to be indexed');
        $t->true(in_array('jsonb01.test', $ledger['unique_scripts'], true), 'Expected jsonb01.test to be indexed');

        $jsonPretty = $ledger['entries']['focusedJsonPretty'] ?? null;
        $t->true(is_array($jsonPretty), 'Expected focusedJsonPretty ledger entry');
        $t->same('passed', $jsonPretty['status']);
        $t->same(45007, $jsonPretty['result_tests']);
        $t->same(0, $jsonPretty['result_errors']);
        $t->same(false, $jsonPretty['uses_cached_or_missing_cache_evidence']);
        $t->true(in_array('json106.test', $jsonPretty['scripts'], true), 'Expected json106.test in focusedJsonPretty');

        $subsetRunRecords = $ledger['entries']['focusedUpstreamSubsetRunRecords'] ?? null;
        $t->true(is_array($subsetRunRecords), 'Expected focusedUpstreamSubsetRunRecords ledger entry');
        $t->same('not-counted', $subsetRunRecords['status']);
        $t->same(true, $subsetRunRecords['uses_cached_or_missing_cache_evidence']);
        $t->contains('isolated worktree lacked a hydrated upstream cache', (string) $subsetRunRecords['skip_reason']);
    },
    'builds an upstream suite acceptance checklist from runner and ledger evidence' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $checklist = $evidence->upstreamSuiteAcceptanceChecklist();

        $t->same('bounded-upstream-suite-evidence-ready', $checklist['status']);
        $t->same(1589, $checklist['denominator_total']);
        $t->true($checklist['denominator_mapped'] >= 287, 'Expected accepted mapped count to be preserved');
        $t->same(1811, $checklist['inventory_unit_total']);
        $t->same(true, $checklist['veryquick_zero_error']);
        $t->same(1235, $checklist['veryquick_scripts']);
        $t->same(329670, $checklist['veryquick_tests']);
        $t->true($checklist['focused_entries'] >= 30, 'Expected focused ledger entries to be counted');
        $t->true($checklist['focused_passed'] >= 14, 'Expected focused passed ledger entries');
        $t->same(0, $checklist['focused_failed']);
        $t->true($checklist['focused_reused_or_skipped'] >= 10, 'Expected reused or skipped evidence to remain visible');
        $t->true($checklist['selected_script_count'] >= 40, 'Expected accepted selected scripts to be counted');
        $t->true($checklist['pattern_script_count'] >= 2, 'Expected accepted wildcard script patterns to be counted');
        $t->same([
            'full release/all permutations',
            'multi-configuration make test suites',
            'long-running stress/permutation tiers beyond veryquick',
        ], $checklist['remaining_suite_tiers']);
        $t->contains('hydrate upstream cache', $checklist['next_acceptance_gate']);
    },
    'builds a suite closure gap report for remaining upstream runner blockers' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $report = $evidence->suiteClosureGapReport();

        $t->same('open', $report['status']);
        $t->same('bounded-upstream-suite-evidence-ready', $report['bounded_evidence_status']);
        $t->same(1589, $report['denominator_total']);
        $t->true($report['denominator_mapped'] >= 288, 'Expected accepted mapped count to be preserved');
        $t->same(1235, $report['veryquick']['scripts']);
        $t->same(329670, $report['veryquick']['tests']);
        $t->same(0, $report['veryquick']['errors']);
        $t->true($report['focused']['entries'] >= 30, 'Expected focused ledger to feed the gap report');
        $t->true($report['focused']['passed'] >= 14, 'Expected passed focused entries');
        $t->same(0, $report['focused']['failed']);
        $t->true($report['focused']['reused_or_skipped'] >= 10, 'Expected reused or skipped focused evidence to stay visible');
        $t->true($report['selected_script_count'] >= 40, 'Expected concrete selected script count');
        $t->true($report['wildcard_pattern_count'] >= 2, 'Expected wildcard pattern count');
        $t->same([
            'full release/all permutations',
            'multi-configuration make test suites',
            'long-running stress/permutation tiers beyond veryquick',
        ], $report['remaining_suite_tiers']);
        $t->same(4, $report['blocker_count']);
        $t->same([
            'full-release-unexecuted',
            'remaining-suite-tiers',
            'focused-results-reused-or-skipped',
            'wildcard-script-selections',
        ], array_column($report['blockers'], 'id'));
        $t->contains('hydrate upstream cache', $report['blockers'][0]['next_gate']);
        $t->contains('no new support component needed', $report['dependency_closure']);
    },
    'builds an upstream suite execution plan for the next honest closure run' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $plan = $evidence->upstreamSuiteExecutionPlan([], 2, dirname(__DIR__, 3));

        $t->same('blocked-on-upstream-cache-or-full-suite', $plan['status']);
        $t->same(1589, $plan['denominator_total']);
        $t->true($plan['denominator_mapped'] >= 291, 'Expected accepted mapped count to be preserved');
        $t->same(2, $plan['jobs']);
        $t->contains('hydrate .upstream-cache/libsqlite', $plan['next_command']);
        $t->contains('no new support component needed', $plan['dependency_closure']);

        $steps = $plan['steps'];
        $t->same([
            'accepted-veryquick-baseline',
            'rerun-focused-closure-subsets',
            'expand-wildcard-selections',
            'run-full-release-all',
        ], array_column($steps, 'id'));

        $t->same('accepted', $steps[0]['status']);
        $t->same(1235, $steps[0]['evidence']['scripts']);
        $t->same(329670, $steps[0]['evidence']['tests']);
        $t->same(0, $steps[0]['evidence']['errors']);

        $t->same('blocked-missing-cache', $steps[1]['status']);
        $t->same(4, $steps[1]['evidence']['group_count']);
        $t->same(0, $steps[1]['evidence']['ready_groups']);
        $t->same(4, $steps[1]['evidence']['skipped_groups']);
        $t->same(19, $steps[1]['evidence']['script_count']);
        $t->true(isset($steps[1]['matrix']['json-table-window']), 'Expected default json-table/window focused group');
        $t->same(false, $steps[1]['matrix']['wal-rollback-savepoint']['runnable']);
        $t->contains('upstream cache/testfixture not hydrated', (string) $steps[1]['matrix']['btree-delete-rebalance']['skip_reason']);

        $t->same('blocked-needs-hydrated-test-dir', $steps[2]['status']);
        $t->true($steps[2]['evidence']['pattern_count'] >= 2, 'Expected wildcard selections to remain visible');
        $t->true(in_array('pager*.test', $steps[2]['evidence']['patterns'], true), 'Expected pager wildcard pattern');

        $t->same('blocked-not-run', $steps[3]['status']);
        $t->contains('--stop-on-error all', $steps[3]['command']);
        $t->same([
            'full release/all permutations',
            'multi-configuration make test suites',
            'long-running stress/permutation tiers beyond veryquick',
        ], $steps[3]['evidence']['remaining_suite_tiers']);
    },
    'builds an honest wildcard expansion plan for focused upstream scripts' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $plan = $evidence->wildcardExpansionPlan(dirname(__DIR__, 3));

        $t->same('blocked-needs-hydrated-test-dir', $plan['status']);
        $t->same('.upstream-cache/libsqlite/test', $plan['test_directory']);
        $t->same(false, $plan['test_directory_ready']);
        $t->true($plan['pattern_count'] >= 2, 'Expected accepted wildcard script selections to remain visible');
        $t->true(in_array('btree*.test', $plan['patterns'], true), 'Expected btree wildcard pattern');
        $t->true(in_array('pager*.test', $plan['patterns'], true), 'Expected pager wildcard pattern');
        $t->same(0, $plan['expanded_pattern_count']);
        $t->same(0, $plan['expanded_script_count']);
        $t->same([], $plan['expanded']);
        $t->true(in_array('btree*.test', $plan['missing_patterns'], true), 'Expected missing btree wildcard due to absent cache');
        $t->same([], $plan['invalid_patterns']);
        $t->contains('hydrate .upstream-cache/libsqlite/test', $plan['next_gate']);
        $t->contains('no new support component needed', $plan['dependency_closure']);
    },
    'builds a release tier matrix with explicit missing cache blockers' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $matrix = $evidence->releaseTierMatrix(2, dirname(__DIR__, 3));

        $t->same('blocked', $matrix['status']);
        $t->same(2, $matrix['jobs']);
        $t->same(4, $matrix['tier_count']);
        $t->same(0, $matrix['ready_tiers']);
        $t->same(0, $matrix['accepted_tiers']);
        $t->same(4, $matrix['blocked_tiers']);
        $t->contains('not run', $matrix['full_release_reason']);
        $t->contains('hydrate .upstream-cache/libsqlite', $matrix['next_gate']);
        $t->contains('no new support component needed', $matrix['dependency_closure']);

        $tiers = [];
        foreach ($matrix['tiers'] as $tier) {
            $tiers[$tier['id']] = $tier;
        }

        $t->same([
            'release-all',
            'permutation-suites',
            'make-test',
            'mptest',
        ], array_keys($tiers));
        $t->same('blocked-missing-cache', $tiers['release-all']['status']);
        $t->same(false, $tiers['release-all']['runnable']);
        $t->contains('--stop-on-error all', $tiers['release-all']['command']);
        $t->true(in_array('.upstream-cache/libsqlite-build-port-libsqlite/testfixture', $tiers['release-all']['missing'], true), 'Expected missing testfixture in release-all tier');
        $t->same(28, $tiers['release-all']['inventory_units']);
        $t->same('blocked-missing-cache', $tiers['permutation-suites']['status']);
        $t->same(null, $tiers['permutation-suites']['command']);
        $t->same(58, $tiers['permutation-suites']['inventory_units']);
        $t->same('blocked-missing-build', $tiers['make-test']['status']);
        $t->same('make -C .upstream-cache/libsqlite-build-port-libsqlite test', $tiers['make-test']['command']);
        $t->same(79, $tiers['make-test']['inventory_units']);
        $t->same('blocked-missing-build', $tiers['mptest']['status']);
        $t->same(6, $tiers['mptest']['inventory_units']);
    },
    'marks release tier commands ready against a hydrated local fixture tree' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $root = sys_get_temp_dir() . '/libsqlite-release-tiers-' . bin2hex(random_bytes(4));
        $buildDirectory = $root . '/.upstream-cache/libsqlite-build-port-libsqlite';
        $testDirectory = $root . '/.upstream-cache/libsqlite/test';
        $mptestDirectory = $root . '/.upstream-cache/libsqlite/mptest';
        mkdir($buildDirectory, 0777, true);
        mkdir($testDirectory, 0777, true);
        mkdir($mptestDirectory, 0777, true);
        file_put_contents($buildDirectory . '/testfixture', '');
        file_put_contents($buildDirectory . '/Makefile', "test:\n\t@true\nmptest:\n\t@true\n");
        file_put_contents($testDirectory . '/testrunner.tcl', '# focused release tier fixture');

        try {
            $matrix = $evidence->releaseTierMatrix(3, $root);

            $t->same('blocked', $matrix['status']);
            $t->same(3, $matrix['jobs']);
            $t->same(3, $matrix['ready_tiers']);
            $t->same(1, $matrix['blocked_tiers']);
            $t->contains('keep tier commands explicit', $matrix['next_gate']);

            $tiers = [];
            foreach ($matrix['tiers'] as $tier) {
                $tiers[$tier['id']] = $tier;
            }

            $t->same('ready', $tiers['release-all']['status']);
            $t->same(true, $tiers['release-all']['runnable']);
            $t->same([], $tiers['release-all']['missing']);
            $t->contains('--jobs 3 --stop-on-error all', $tiers['release-all']['command']);
            $t->same('blocked-needs-suite-map', $tiers['permutation-suites']['status']);
            $t->same(['concrete permutation suite command map'], $tiers['permutation-suites']['missing']);
            $t->same('ready', $tiers['make-test']['status']);
            $t->same('ready', $tiers['mptest']['status']);
        } finally {
            @unlink($buildDirectory . '/testfixture');
            @unlink($buildDirectory . '/Makefile');
            @unlink($testDirectory . '/testrunner.tcl');
            @rmdir($mptestDirectory);
            @rmdir($testDirectory);
            @rmdir($root . '/.upstream-cache/libsqlite');
            @rmdir($buildDirectory);
            @rmdir($root . '/.upstream-cache');
            @rmdir($root);
        }
    },
    'builds an honest permutation suite map with explicit missing source blocker' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $map = $evidence->permutationSuiteMap(dirname(__DIR__, 3));

        $t->same('blocked-missing-permutation-source', $map['status']);
        $t->same(58, $map['declared_suite_count']);
        $t->same(0, $map['mapped_suite_count']);
        $t->same(58, $map['unmapped_suite_count']);
        $t->same('.upstream-cache/libsqlite/test/permutations.test', $map['source']);
        $t->same(false, $map['source_ready']);
        $t->same([], $map['suites']);
        $t->contains('hydrate .upstream-cache/libsqlite/test/permutations.test', $map['next_gate']);
        $t->contains('no new support component needed', $map['dependency_closure']);
    },
    'parses permutation suite names from a hydrated local fixture source' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $root = sys_get_temp_dir() . '/libsqlite-permutation-map-' . bin2hex(random_bytes(4));
        $testDirectory = $root . '/.upstream-cache/libsqlite/test';
        mkdir($testDirectory, 0777, true);
        file_put_contents(
            $testDirectory . '/permutations.test',
            "test_suite full\npermutation memsubsys1\nrun_tests quick\njournaltest {-files {pager.test} -description {journal mode}}\n"
        );

        try {
            $map = $evidence->permutationSuiteMap($root);

            $t->same('partial', $map['status']);
            $t->same(58, $map['declared_suite_count']);
            $t->same(4, $map['mapped_suite_count']);
            $t->same(54, $map['unmapped_suite_count']);
            $t->same(true, $map['source_ready']);
            $t->same([
                'full',
                'journaltest',
                'memsubsys1',
                'quick',
            ], $map['suites']);
            $t->contains('complete the permutation parser', $map['next_gate']);
        } finally {
            @unlink($testDirectory . '/permutations.test');
            @rmdir($testDirectory);
            @rmdir($root . '/.upstream-cache/libsqlite');
            @rmdir($root . '/.upstream-cache');
            @rmdir($root);
        }
    },
    'expands wildcard upstream scripts when a hydrated test directory is available' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $root = sys_get_temp_dir() . '/libsqlite-upstream-wildcards-' . bin2hex(random_bytes(4));
        $testDirectory = $root . '/.upstream-cache/libsqlite/test';
        mkdir($testDirectory, 0777, true);

        try {
            foreach ($evidence->runnerCoverageAudit()['pattern_scripts'] as $pattern) {
                $filename = str_replace('*', '01', $pattern);
                file_put_contents($testDirectory . '/' . $filename, '# focused wildcard fixture');
            }
            file_put_contents($testDirectory . '/btree02.test', '# focused wildcard fixture');

            $plan = $evidence->wildcardExpansionPlan($root);

            $t->same('ready', $plan['status']);
            $t->same(true, $plan['test_directory_ready']);
            $t->same($plan['pattern_count'], $plan['expanded_pattern_count']);
            $t->true($plan['expanded_script_count'] >= $plan['pattern_count']);
            $t->true(in_array('btree01.test', $plan['expanded']['btree*.test'] ?? [], true), 'Expected btree01.test expansion');
            $t->true(in_array('btree02.test', $plan['expanded']['btree*.test'] ?? [], true), 'Expected sorted multi-file btree expansion');
            $t->same([], $plan['missing_patterns']);
            $t->same([], $plan['invalid_patterns']);
            $t->contains('replace wildcard runner notes', $plan['next_gate']);
        } finally {
            foreach (glob($testDirectory . '/*.test') ?: [] as $file) {
                unlink($file);
            }
            @rmdir($testDirectory);
            @rmdir($root . '/.upstream-cache/libsqlite');
            @rmdir($root . '/.upstream-cache');
            @rmdir($root);
        }
    },
    'builds a full upstream suite readiness record for integrator handoff' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $record = $evidence->fullSuiteReadinessRecord(2, dirname(__DIR__, 3));

        $t->same('blocked', $record['status']);
        $t->same(1589, $record['denominator_total']);
        $t->true($record['denominator_mapped'] >= 308, 'Expected accepted mapped count to be preserved');
        $t->same(2, $record['jobs']);
        $t->same(1, $record['accepted_count']);
        $t->same(0, $record['ready_count']);
        $t->true($record['blocked_count'] >= 6, 'Expected release, wildcard, and permutation gates to block without cache');
        $t->same(0, $record['focused_ledger']['failed']);
        $t->true($record['focused_ledger']['entries'] >= 30, 'Expected focused ledger summary');
        $t->same('veryquick', $record['accepted'][0]['id']);
        $t->same(1235, $record['accepted'][0]['scripts']);
        $t->same(329670, $record['accepted'][0]['tests']);
        $t->same(0, $record['accepted'][0]['errors']);

        $blockedIds = array_column($record['blocked'], 'id');
        $t->true(in_array('release-all', $blockedIds, true), 'Expected release-all gate');
        $t->true(in_array('make-test', $blockedIds, true), 'Expected make-test gate');
        $t->true(in_array('mptest', $blockedIds, true), 'Expected mptest gate');
        $t->true(in_array('wildcard-expansion', $blockedIds, true), 'Expected wildcard expansion gate');
        $t->true(in_array('permutation-suite-map', $blockedIds, true), 'Expected permutation map gate');
        $t->true(in_array('full-release-unexecuted', $record['closure_blocker_ids'], true), 'Expected closure blocker id');
        $t->contains('hydrate .upstream-cache/libsqlite', $record['next_command']);
        $t->contains('no new support component needed', $record['dependency_closure']);
    },
    'promotes full suite readiness gates when local harness fixtures are hydrated' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $root = sys_get_temp_dir() . '/libsqlite-full-suite-readiness-' . bin2hex(random_bytes(4));
        $buildDirectory = $root . '/.upstream-cache/libsqlite-build-port-libsqlite';
        $testDirectory = $root . '/.upstream-cache/libsqlite/test';
        $mptestDirectory = $root . '/.upstream-cache/libsqlite/mptest';
        mkdir($buildDirectory, 0777, true);
        mkdir($testDirectory, 0777, true);
        mkdir($mptestDirectory, 0777, true);
        file_put_contents($buildDirectory . '/testfixture', '');
        file_put_contents($buildDirectory . '/Makefile', "test:\n\t@true\nmptest:\n\t@true\n");
        file_put_contents($testDirectory . '/testrunner.tcl', '# focused full-suite readiness fixture');
        file_put_contents($testDirectory . '/permutations.test', "test_suite full\npermutation memsubsys1\n");
        foreach ($evidence->runnerCoverageAudit()['pattern_scripts'] as $pattern) {
            file_put_contents($testDirectory . '/' . str_replace('*', '01', $pattern), '# focused wildcard fixture');
        }

        try {
            $record = $evidence->fullSuiteReadinessRecord(4, $root);

            $t->same('blocked', $record['status']);
            $t->same(4, $record['jobs']);
            $t->same(1, $record['accepted_count']);
            $t->true($record['ready_count'] >= 4, 'Expected hydrated release tiers and wildcard expansion to become ready');
            $t->same('veryquick', $record['accepted'][0]['id']);

            $readyIds = array_column($record['ready'], 'id');
            $t->true(in_array('release-all', $readyIds, true), 'Expected release-all ready gate');
            $t->true(in_array('make-test', $readyIds, true), 'Expected make-test ready gate');
            $t->true(in_array('mptest', $readyIds, true), 'Expected mptest ready gate');
            $t->true(in_array('wildcard-expansion', $readyIds, true), 'Expected wildcard expansion ready gate');

            $blockedIds = array_column($record['blocked'], 'id');
            $t->true(in_array('permutation-suites', $blockedIds, true), 'Expected command-map blocker for declared permutation tier');
            $t->true(in_array('permutation-suite-map', $blockedIds, true), 'Expected partial permutation parser blocker');
            $t->contains('hydrate .upstream-cache/libsqlite', $record['next_command']);
        } finally {
            foreach (glob($testDirectory . '/*.test') ?: [] as $file) {
                unlink($file);
            }
            @unlink($buildDirectory . '/testfixture');
            @unlink($buildDirectory . '/Makefile');
            @rmdir($mptestDirectory);
            @rmdir($testDirectory);
            @rmdir($root . '/.upstream-cache/libsqlite');
            @rmdir($buildDirectory);
            @rmdir($root . '/.upstream-cache');
            @rmdir($root);
        }
    },
];
