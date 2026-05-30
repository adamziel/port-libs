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
    'builds a recorded runner result ledger from accepted upstream run history' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $ledger = $evidence->recordedRunnerResultLedger();

        $t->true($ledger['entry_count'] >= 80, 'Expected accepted runner result history to be inventoried');
        $t->true($ledger['passed_count'] >= 30, 'Expected parseable zero-error runner results to be counted');
        $t->same(0, $ledger['failed_count']);
        $t->same(0, $ledger['errors_total']);
        $t->true($ledger['scripts_total'] >= 1200, 'Expected recorded runner script counts to include veryquick and focused runs');
        $t->true($ledger['tests_total'] >= 329670, 'Expected recorded runner test counts to include the full veryquick baseline');

        $fullVeryquick = $ledger['entries']['fullVeryquick'] ?? null;
        $t->true(is_array($fullVeryquick), 'Expected fullVeryquick recorded runner entry');
        $t->same('passed', $fullVeryquick['status']);
        $t->same(1235, $fullVeryquick['scripts']);
        $t->same(329670, $fullVeryquick['tests']);
        $t->same(0, $fullVeryquick['errors']);

        $withSelections = 0;
        foreach ($ledger['entries'] as $entry) {
            if (is_array($entry) && ($entry['selected_scripts'] ?? []) !== []) {
                $withSelections++;
            }
        }
        $t->true($withSelections >= 20, 'Expected focused recorded results to retain selected .test tokens');
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
        $t->true($checklist['recorded_runner_entries'] >= 80, 'Expected recorded runner entries in checklist');
        $t->true($checklist['recorded_runner_passed'] >= 30, 'Expected recorded runner pass count in checklist');
        $t->same(0, $checklist['recorded_runner_failed']);
        $t->true($checklist['recorded_runner_tests'] >= 329670, 'Expected recorded runner test total in checklist');
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
            $t->same([
                '.upstream-cache/libsqlite/test/permutations.test',
                'all declared permutation suites mapped from permutations.test',
            ], $tiers['permutation-suites']['missing']);
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
            "test_suite \"full\"\ntest_suite \"fts5-light\"\ntest_suite \"pcache\${discard_rate}\"\npermutation memsubsys1\nrun_tests quick\njournaltest {-files {pager.test} -description {journal mode}}\n"
        );

        try {
            $map = $evidence->permutationSuiteMap($root);

            $t->same('partial', $map['status']);
            $t->same(58, $map['declared_suite_count']);
            $t->same(6, $map['mapped_suite_count']);
            $t->same(52, $map['unmapped_suite_count']);
            $t->same(true, $map['source_ready']);
            $t->same([
                'fts5-light',
                'full',
                'journaltest',
                'memsubsys1',
                'pcache${discard_rate}',
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
    'marks permutation suite map ready when declared quoted suites are hydrated' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $root = sys_get_temp_dir() . '/libsqlite-permutation-ready-map-' . bin2hex(random_bytes(4));
        $testDirectory = $root . '/.upstream-cache/libsqlite/test';
        mkdir($testDirectory, 0777, true);

        $source = '';
        for ($i = 1; $i <= 58; $i++) {
            $source .= sprintf("test_suite \"suite%02d\" -description {fixture}\n", $i);
        }
        file_put_contents($testDirectory . '/permutations.test', $source);

        try {
            $map = $evidence->permutationSuiteMap($root);

            $t->same('ready', $map['status']);
            $t->same(58, $map['declared_suite_count']);
            $t->same(58, $map['mapped_suite_count']);
            $t->same(0, $map['unmapped_suite_count']);
            $t->same(true, $map['source_ready']);
            $t->same('suite01', $map['suites'][0]);
            $t->same('suite58', $map['suites'][57]);
            $t->contains('turn parsed permutation suite names', $map['next_gate']);
        } finally {
            @unlink($testDirectory . '/permutations.test');
            @rmdir($testDirectory);
            @rmdir($root . '/.upstream-cache/libsqlite');
            @rmdir($root . '/.upstream-cache');
            @rmdir($root);
        }
    },
    'builds concrete permutation suite runner commands from hydrated upstream harness sources' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $root = sys_get_temp_dir() . '/libsqlite-permutation-command-map-' . bin2hex(random_bytes(4));
        $buildDirectory = $root . '/.upstream-cache/libsqlite-build-port-libsqlite';
        $testDirectory = $root . '/.upstream-cache/libsqlite/test';
        mkdir($buildDirectory, 0777, true);
        mkdir($testDirectory, 0777, true);
        file_put_contents($buildDirectory . '/testfixture', '#!/bin/sh');
        file_put_contents($testDirectory . '/testrunner.tcl', '# focused permutation command fixture');

        $source = '';
        for ($i = 1; $i <= 58; $i++) {
            $source .= sprintf("test_suite \"suite%02d\" -description {fixture}\n", $i);
        }
        file_put_contents($testDirectory . '/permutations.test', $source);

        try {
            $map = $evidence->permutationSuiteCommandMap(3, $root);

            $t->same('ready', $map['status']);
            $t->same(3, $map['jobs']);
            $t->same(58, $map['declared_suite_count']);
            $t->same(58, $map['mapped_suite_count']);
            $t->same(58, $map['command_count']);
            $t->same(58, $map['runnable_command_count']);
            $t->same('.upstream-cache/libsqlite-build-port-libsqlite', $map['build_directory']);
            $t->same('.upstream-cache/libsqlite/test/permutations.test', $map['source']);
            $t->same([], $map['missing']);
            $t->same('suite01', $map['suites'][0]);
            $t->same('suite58', $map['suites'][57]);
            $t->same('suite01', $map['commands'][0]['suite']);
            $t->same(true, $map['commands'][0]['runnable']);
            $t->same(3, $map['commands'][0]['jobs']);
            $t->contains("--jobs 3 --stop-on-error 'suite01'", $map['commands'][0]['command']);
            $t->contains("--jobs 3 --stop-on-error 'suite58'", $map['commands'][57]['command']);
            $t->contains('run each parsed permutation suite command', $map['next_gate']);
            $t->contains('no new support component needed', $map['dependency_closure']);
        } finally {
            foreach (glob($testDirectory . '/*') ?: [] as $file) {
                unlink($file);
            }
            @unlink($buildDirectory . '/testfixture');
            @rmdir($testDirectory);
            @rmdir($root . '/.upstream-cache/libsqlite');
            @rmdir($buildDirectory);
            @rmdir($root . '/.upstream-cache');
            @rmdir($root);
        }
    },
    'blocks permutation suite command map until harness and declarations are complete' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $root = sys_get_temp_dir() . '/libsqlite-permutation-command-blocked-' . bin2hex(random_bytes(4));
        $testDirectory = $root . '/.upstream-cache/libsqlite/test';
        mkdir($testDirectory, 0777, true);
        file_put_contents($testDirectory . '/permutations.test', "test_suite quick\npermutation memsubsys1\n");

        try {
            $map = $evidence->permutationSuiteCommandMap(2, $root);

            $t->same('blocked', $map['status']);
            $t->same(2, $map['jobs']);
            $t->same(58, $map['declared_suite_count']);
            $t->same(2, $map['mapped_suite_count']);
            $t->same(0, $map['command_count']);
            $t->same(0, $map['runnable_command_count']);
            $t->same([], $map['commands']);
            $t->true(in_array('.upstream-cache/libsqlite-build-port-libsqlite', $map['missing'], true), 'Expected missing build directory');
            $t->true(in_array('.upstream-cache/libsqlite-build-port-libsqlite/testfixture', $map['missing'], true), 'Expected missing testfixture');
            $t->true(in_array('all declared permutation suites mapped from permutations.test', $map['missing'], true), 'Expected unmapped suite blocker');
            $t->contains('hydrate testfixture plus permutations.test', $map['next_gate']);
            $t->contains('no new support component needed', $map['dependency_closure']);
        } finally {
            @unlink($testDirectory . '/permutations.test');
            @rmdir($testDirectory);
            @rmdir($root . '/.upstream-cache/libsqlite');
            @rmdir($root . '/.upstream-cache');
            @rmdir($root);
        }
    },
    'promotes permutation release tier and command manifest when all declared suites are mapped' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $root = sys_get_temp_dir() . '/libsqlite-permutation-release-tier-' . bin2hex(random_bytes(4));
        $buildDirectory = $root . '/.upstream-cache/libsqlite-build-port-libsqlite';
        $testDirectory = $root . '/.upstream-cache/libsqlite/test';
        $mptestDirectory = $root . '/.upstream-cache/libsqlite/mptest';
        mkdir($buildDirectory, 0777, true);
        mkdir($testDirectory, 0777, true);
        mkdir($mptestDirectory, 0777, true);
        file_put_contents($buildDirectory . '/testfixture', '#!/bin/sh');
        file_put_contents($buildDirectory . '/Makefile', "test:\n\t@true\nmptest:\n\t@true\n");
        file_put_contents($testDirectory . '/testrunner.tcl', '# focused permutation release fixture');

        $source = '';
        for ($i = 1; $i <= 58; $i++) {
            $source .= sprintf("test_suite \"suite%02d\"\n", $i);
        }
        file_put_contents($testDirectory . '/permutations.test', $source);

        try {
            $release = $evidence->releaseTierMatrix(4, $root);
            $manifest = $evidence->fullSuiteCommandManifest(4, $root);
            $releaseTiers = [];
            foreach ($release['tiers'] as $tier) {
                $releaseTiers[$tier['id']] = $tier;
            }
            $commands = [];
            foreach ($manifest['commands'] as $command) {
                $commands[$command['id']] = $command;
            }

            $t->same('ready', $releaseTiers['permutation-suites']['status']);
            $t->same(true, $releaseTiers['permutation-suites']['runnable']);
            $t->same([], $releaseTiers['permutation-suites']['missing']);
            $t->same(58, count($releaseTiers['permutation-suites']['permutation_suite_commands']));
            $t->contains("--jobs 4 --stop-on-error 'suite01'", $releaseTiers['permutation-suites']['permutation_suite_commands'][0]['command']);
            $t->same('ready', $commands['permutation-suite-commands']['status']);
            $t->same(true, $commands['permutation-suite-commands']['runnable']);
            $t->same([], $commands['permutation-suite-commands']['missing']);
            $t->same(58, $commands['permutation-suite-commands']['inventory_units']);
            $t->same(58, count($commands['permutation-suite-commands']['permutation_suite_commands']));
            $t->same('upstream-runner', $commands['permutation-suite-commands']['kind']);
            $t->same('permutation-suite-command-map', $commands['permutation-suite-commands']['evidence_source']);
        } finally {
            foreach (glob($testDirectory . '/*') ?: [] as $file) {
                unlink($file);
            }
            foreach (glob($buildDirectory . '/*') ?: [] as $file) {
                unlink($file);
            }
            @rmdir($mptestDirectory);
            @rmdir($testDirectory);
            @rmdir($root . '/.upstream-cache/libsqlite');
            @rmdir($buildDirectory);
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
    'builds a full upstream suite command manifest from readiness gates' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $manifest = $evidence->fullSuiteCommandManifest(2, dirname(__DIR__, 3));

        $t->same('blocked', $manifest['status']);
        $t->same(2, $manifest['jobs']);
        $t->same(1, count($manifest['accepted_baseline']));
        $t->same('veryquick', $manifest['accepted_baseline'][0]['id']);
        $t->same(7, $manifest['command_count']);
        $t->same(0, $manifest['runnable_command_count']);
        $t->same(7, $manifest['blocked_command_count']);
        $t->contains('hydrate .upstream-cache/libsqlite', $manifest['next_gate']);
        $t->contains('no new support component needed', $manifest['dependency_closure']);

        $commands = [];
        foreach ($manifest['commands'] as $command) {
            $commands[$command['id']] = $command;
        }

        $t->same([
            'release-all',
            'permutation-suites',
            'make-test',
            'mptest',
            'wildcard-expansion',
            'permutation-suite-map',
            'permutation-suite-commands',
        ], array_keys($commands));
        $t->same('upstream-runner', $commands['release-all']['kind']);
        $t->same('release-tier-matrix', $commands['release-all']['evidence_source']);
        $t->contains('--jobs 2 --stop-on-error all', (string) $commands['release-all']['command']);
        $t->true(in_array('.upstream-cache/libsqlite-build-port-libsqlite/testfixture', $commands['release-all']['missing'], true), 'Expected missing testfixture to stay visible');
        $t->same('manifest-normalization', $commands['wildcard-expansion']['kind']);
        $t->same('wildcard-expansion-plan', $commands['wildcard-expansion']['evidence_source']);
        $t->same(false, $commands['wildcard-expansion']['runnable']);
        $t->same('permutation-suite-map', $commands['permutation-suite-map']['evidence_source']);
        $t->same(['.upstream-cache/libsqlite/test/permutations.test'], $commands['permutation-suite-map']['missing']);
        $t->same('permutation-suite-command-map', $commands['permutation-suite-commands']['evidence_source']);
        $t->same(false, $commands['permutation-suite-commands']['runnable']);
        $t->same([
            '.upstream-cache/libsqlite-build-port-libsqlite',
            '.upstream-cache/libsqlite-build-port-libsqlite/testfixture',
            '.upstream-cache/libsqlite/test/testrunner.tcl',
            '.upstream-cache/libsqlite/test/permutations.test',
            'all declared permutation suites mapped from permutations.test',
        ], $commands['permutation-suite-commands']['missing']);
    },
    'marks full upstream suite command manifest commands runnable against local harness fixtures' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $root = sys_get_temp_dir() . '/libsqlite-full-suite-command-manifest-' . bin2hex(random_bytes(4));
        $buildDirectory = $root . '/.upstream-cache/libsqlite-build-port-libsqlite';
        $testDirectory = $root . '/.upstream-cache/libsqlite/test';
        $mptestDirectory = $root . '/.upstream-cache/libsqlite/mptest';
        mkdir($buildDirectory, 0777, true);
        mkdir($testDirectory, 0777, true);
        mkdir($mptestDirectory, 0777, true);
        file_put_contents($buildDirectory . '/testfixture', '');
        file_put_contents($buildDirectory . '/Makefile', "test:\n\t@true\nmptest:\n\t@true\n");
        file_put_contents($testDirectory . '/testrunner.tcl', '# focused full-suite command manifest fixture');
        file_put_contents($testDirectory . '/permutations.test', "test_suite full\npermutation memsubsys1\n");
        foreach ($evidence->runnerCoverageAudit()['pattern_scripts'] as $pattern) {
            file_put_contents($testDirectory . '/' . str_replace('*', '01', $pattern), '# focused wildcard fixture');
        }

        try {
            $manifest = $evidence->fullSuiteCommandManifest(4, $root);
            $commands = [];
            foreach ($manifest['commands'] as $command) {
                $commands[$command['id']] = $command;
            }

            $t->same('blocked', $manifest['status']);
            $t->same(4, $manifest['jobs']);
            $t->true($manifest['runnable_command_count'] >= 4, 'Expected release tiers and wildcard expansion to become runnable');
            $t->same(true, $commands['release-all']['runnable']);
            $t->same(true, $commands['make-test']['runnable']);
            $t->same(true, $commands['mptest']['runnable']);
            $t->same(true, $commands['wildcard-expansion']['runnable']);
            $t->same(false, $commands['permutation-suites']['runnable']);
            $t->same(false, $commands['permutation-suite-map']['runnable']);
            $t->same(false, $commands['permutation-suite-commands']['runnable']);
            $t->same(['all declared permutation suites mapped from permutations.test'], $commands['permutation-suites']['missing']);
            $t->same(['unmapped permutation suites'], $commands['permutation-suite-map']['missing']);
            $t->same(['all declared permutation suites mapped from permutations.test'], $commands['permutation-suite-commands']['missing']);
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
    'blocks duplicate broad upstream runners from a process snapshot' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $snapshot = <<<'TXT'
3843839       04:42 bash scripts/run-sqlite-tcl-bounded-runner.sh libsqlite-all-runner-20260526T080745Z audits/sqlite-full-suite-all-runner-20260526T080745Z.md .tmux-team/tmp/sqlite-full-suite-all-runner-20260526T080745Z .tmux-team/logs/sqlite-full-suite-all-runner-20260526T080745Z.log all 2 1800
3843902       04:41 timeout 1800 ./testfixture ../src/test/testrunner.tcl --jobs 2 --stop-on-error all
3843903       04:41 ./testfixture ../src/test/testrunner.tcl --jobs 2 --stop-on-error all
3843961       04:39 /tmp/build/testfixture /tmp/src/test/testrunner.tcl multithread /tmp/src/test/sort4.test
TXT;

        $gate = $evidence->activeFullSuiteRunnerGate($snapshot);

        $t->same('blocked-active-runner', $gate['status']);
        $t->same(3, $gate['active_count']);
        $t->same(['all'], $gate['active_tiers']);
        $t->same(3843839, $gate['active'][0]['pid']);
        $t->same('04:42', $gate['active'][0]['elapsed']);
        $t->same('all', $gate['active'][0]['tier']);
        $t->contains('run-sqlite-tcl-bounded-runner.sh', $gate['active'][0]['command']);
        $t->contains('do not launch a duplicate broad SQLite suite', $gate['next_gate']);
        $t->contains('no new support component needed', $gate['dependency_closure']);

        $clear = $evidence->activeFullSuiteRunnerGate("3903213       00:29 [testfixture] <defunct>\n");
        $t->same('clear', $clear['status']);
        $t->same(0, $clear['active_count']);
        $t->same([], $clear['active']);
        $t->contains('may start if other gates pass', $clear['next_gate']);
    },
    'parses active broad runner snapshots with pid ppid elapsed command fields' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $snapshot = <<<'TXT'
577248       1       02:16 bash scripts/run-sqlite-tcl-bounded-runner.sh libsqlite-release-notty-runner-20260526T102446Z audits/sqlite-release-notty-runner-20260526T102446Z.md .tmux-team/tmp/sqlite-release-notty-runner-20260526T102446Z .tmux-team/logs/sqlite-release-notty-runner-20260526T102446Z.log release 2 7200
577296  577248       02:14 timeout 7200 ./testfixture ../src/test/testrunner.tcl --jobs 2 --stop-on-error release
577297  577296       02:14 ./testfixture ../src/test/testrunner.tcl --jobs 2 --stop-on-error release
TXT;

        $gate = $evidence->activeFullSuiteRunnerGate($snapshot);

        $t->same('blocked-active-runner', $gate['status']);
        $t->same(3, $gate['active_count']);
        $t->same(['release'], $gate['active_tiers']);
        $t->same(577248, $gate['active'][0]['pid']);
        $t->same('02:16', $gate['active'][0]['elapsed']);
        $t->same('release', $gate['active'][0]['tier']);
        $t->contains('run-sqlite-tcl-bounded-runner.sh', $gate['active'][0]['command']);
        $t->contains('do not launch a duplicate broad SQLite suite', $gate['next_gate']);
    },
    'parses active broad make test snapshots as duplicate suite runners' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $snapshot = <<<'TXT'
601001 599999 00:37 make -C .upstream-cache/libsqlite-build-port-libsqlite test
601104 601001 00:36 make -C .upstream-cache/libsqlite-build-port-libsqlite mptest
601203 601104 00:35 php tools/run-tests.php lanes/libsqlite/tests
TXT;

        $gate = $evidence->activeFullSuiteRunnerGate($snapshot);

        $t->same('blocked-active-runner', $gate['status']);
        $t->same(2, $gate['active_count']);
        $t->same(['make-test', 'mptest'], $gate['active_tiers']);
        $t->same(601001, $gate['active'][0]['pid']);
        $t->same('00:37', $gate['active'][0]['elapsed']);
        $t->same('make-test', $gate['active'][0]['tier']);
        $t->contains('make -C .upstream-cache/libsqlite-build-port-libsqlite test', $gate['active'][0]['command']);
        $t->same('mptest', $gate['active'][1]['tier']);
        $t->contains('make -C .upstream-cache/libsqlite-build-port-libsqlite mptest', $gate['active'][1]['command']);
    },
    'ignores pgrep duplicate-runner probe commands in active broad runner snapshots' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $snapshot = <<<'TXT'
3862016 /bin/bash -c pgrep -af 'testfixture|run-sqlite-tcl-bounded-runner|make -C .*libsqlite.*(test|mptest)' || true
3862017 pgrep -af testfixture|run-sqlite-tcl-bounded-runner|all|release|mptest
3862018 grep -E 'testfixture|run-sqlite-tcl-bounded-runner|all|release|mptest'
3862019 php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
TXT;

        $gate = $evidence->activeFullSuiteRunnerGate($snapshot);

        $t->same('clear', $gate['status']);
        $t->same(0, $gate['active_count']);
        $t->same([], $gate['active_tiers']);
        $t->same([], $gate['active']);
        $t->contains('no active broad SQLite full-suite runner detected', $gate['next_gate']);

        $launch = $evidence->broadSuiteLaunchGate($snapshot, true, 2, '/tmp/missing-libsqlite-broad-suite-root');

        $t->same('clear', $launch['active_gate']['status']);
        $t->true(!in_array('active-runner-still-running', array_column($launch['blockers'], 'id'), true), 'Expected pgrep probe not to block launch gate as active runner');
        $t->true(in_array('command-manifest-not-ready', array_column($launch['blockers'], 'id'), true), 'Expected real command-manifest blocker to remain');
    },
    'parses active broad foreground runner snapshots with ps stat and cpu fields' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $snapshot = <<<'TXT'
1666044       1 S+       28:41  0.0 bash scripts/run-sqlite-tcl-bounded-runner.sh libsqlite-release-rerun-foreground-20260526T134619Z audits/libsqlite-release-rerun-foreground-20260526T134619Z.md .tmux-team/tmp/libsqlite-release-rerun-foreground-20260526T134619Z .tmux-team/logs/libsqlite-release-rerun-foreground-20260526T134619Z.log release 2 7200
1666103 1666044 S+       28:39  0.0 timeout --foreground 7200 ./testfixture ../src/test/testrunner.tcl --jobs 2 --stop-on-error release
1666104 1666103 S+       28:39  0.2 ./testfixture ../src/test/testrunner.tcl --jobs 2 --stop-on-error release
1936678 1666104 R+       00:08 99.1 valgrind -v --error-exitcode=1 /tmp/build/testfixture /tmp/src/test/testrunner.tcl valgrind /tmp/src/ext/recover/recover1.test
TXT;

        $gate = $evidence->activeFullSuiteRunnerGate($snapshot);

        $t->same('blocked-active-runner', $gate['status']);
        $t->same(3, $gate['active_count']);
        $t->same(['release'], $gate['active_tiers']);
        $t->same(1666044, $gate['active'][0]['pid']);
        $t->same(1, $gate['active'][0]['ppid']);
        $t->same('S+', $gate['active'][0]['stat']);
        $t->same('28:41', $gate['active'][0]['elapsed']);
        $t->same(0.0, $gate['active'][0]['pcpu']);
        $t->same('release', $gate['active'][0]['tier']);
        $t->contains('libsqlite-release-rerun-foreground-20260526T134619Z', $gate['active'][0]['command']);
        $t->same(1666044, $gate['active'][1]['ppid']);
        $t->same('release', $gate['active'][1]['tier']);
        $t->same(1666103, $gate['active'][2]['ppid']);
        $t->same(0.2, $gate['active'][2]['pcpu']);
        $t->contains('do not launch a duplicate broad SQLite suite', $gate['next_gate']);
    },
    'builds a bounded upstream runner artifact record from audit and stdout text' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $audit = <<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-all-runner-20260526T080745Z

- Repository HEAD: `9d333e5c97980b320e4b8a5a17d18aee22af135a`
- Scratch: `/tmp/sqlite-full-suite-all-runner-20260526T080745Z`
- Log: `/tmp/sqlite-full-suite-all-runner-20260526T080745Z.log`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `all`
- Jobs: `2`
- Timeout seconds: `1800`
- Patterns: none
- Exit: `0`
- Elapsed seconds: `48`
- Parsed summary: `0 errors out of 10785 tests`
- Parsed errors: `0`
- Parsed tests: `10785`
- Runner time: `00:00:48`
MD;
        $stdout = "00:10 tcl(80/10785) r2 ETC 01:33:27\n00:48 tcl(10785/10785) r0\n";

        $record = $evidence->boundedRunnerArtifactRecord($audit, $stdout);

        $t->same('passed', $record['status']);
        $t->same('libsqlite-all-runner-20260526T080745Z', $record['label']);
        $t->same('9d333e5c97980b320e4b8a5a17d18aee22af135a', $record['repository_head']);
        $t->same('8f70ec615f4cd247d36f92a22c99f65ebbcc22a7', $record['sqlite_commit']);
        $t->same('3.54.0', $record['sqlite_version']);
        $t->same('all', $record['requested']['testset']);
        $t->same(2, $record['requested']['jobs']);
        $t->same(1800, $record['requested']['timeout_seconds']);
        $t->same([], $record['requested']['patterns']);
        $t->same(0, $record['results']['exit']);
        $t->same(10785, $record['results']['tests']);
        $t->same(0, $record['results']['errors']);
        $t->same(10785, $record['progress']['completed']);
        $t->same(10785, $record['progress']['total']);
        $t->contains('integrator confirms the artifact checkout matches', $record['next_gate']);
        $t->contains('no new support component needed', $record['dependency_closure']);
    },
    'keeps incomplete bounded runner artifacts explicit while a broad runner is active' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $audit = <<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-all-runner-20260526T083945Z

- Repository HEAD: `5daeeb21a5c773aa5ab600e19580a47fafe28202`
- Scratch: `.tmux-team/tmp/sqlite-full-suite-all-runner-20260526T083945Z`
- Log: `.tmux-team/logs/sqlite-full-suite-all-runner-20260526T083945Z.log`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `all`
- Jobs: `2`
- Timeout seconds: `5400`
- Patterns: none
MD;
        $snapshot = '4083544       02:15 ./testfixture ../src/test/testrunner.tcl --jobs 2 --stop-on-error all';
        $stdout = "02:15 tcl(4018/10785) r2 ETC 21:30\n";

        $record = $evidence->boundedRunnerArtifactRecord($audit, $stdout, $snapshot);

        $t->same('active-runner-in-progress', $record['status']);
        $t->same('libsqlite-all-runner-20260526T083945Z', $record['label']);
        $t->same(null, $record['results']['exit']);
        $t->same(null, $record['results']['tests']);
        $t->same(null, $record['results']['errors']);
        $t->same(4018, $record['progress']['completed']);
        $t->same(10785, $record['progress']['total']);
        $t->same('blocked-active-runner', $record['active_gate']['status']);
        $t->contains('wait for the active bounded runner', $record['next_gate']);
    },
    'builds a bounded runner artifact record from guarded audit and log files' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $root = sys_get_temp_dir() . '/libsqlite-bounded-runner-artifacts-' . bin2hex(random_bytes(4));
        mkdir($root, 0777, true);
        $auditPath = $root . '/sqlite-runner.md';
        $logPath = $root . '/sqlite-runner.log';
        file_put_contents($auditPath, <<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-all-runner-20260526T090257Z

- Repository HEAD: `d9553b6d875c7860c0f0ec86b0978c1ca5e14e8e`
- Scratch: `/tmp/sqlite-full-suite-all-runner-20260526T090257Z`
- Log: `/tmp/sqlite-full-suite-all-runner-20260526T090257Z.log`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `all`
- Jobs: `2`
- Timeout seconds: `5400`
- Patterns: none
- Exit: `0`
- Elapsed seconds: `92`
- Parsed summary: `0 errors out of 10785 tests`
- Runner time: `00:01:32`
MD);
        file_put_contents($logPath, "01:20 tcl(9022/10785) r2 ETC 00:09\n01:32 tcl(10785/10785) r0\n");

        try {
            $record = $evidence->boundedRunnerArtifactRecordFromFiles($auditPath, $logPath);

            $t->same('passed', $record['status']);
            $t->same($auditPath, $record['audit_path']);
            $t->same($logPath, $record['stdout_path']);
            $t->same(true, $record['artifact_files_ready']);
            $t->same('libsqlite-all-runner-20260526T090257Z', $record['label']);
            $t->same('d9553b6d875c7860c0f0ec86b0978c1ca5e14e8e', $record['repository_head']);
            $t->same(10785, $record['results']['tests']);
            $t->same(0, $record['results']['errors']);
            $t->same(10785, $record['progress']['completed']);
            $t->same(10785, $record['progress']['total']);
            $t->contains('artifact-file records read bounded runner audit/log files only', $record['dependency_closure']);
        } finally {
            @unlink($auditPath);
            @unlink($logPath);
            @rmdir($root);
        }
    },
    'recovers completed bounded runner result counts from stdout when audit summary fields are missing' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $audit = <<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-release-rerun-foreground-20260526T134619Z

- Repository HEAD: `e5897b4ac75ee1bf7a45063194c84592ccf26996`
- Scratch: `/tmp/libsqlite-release-rerun-foreground-20260526T134619Z`
- Log: `/tmp/libsqlite-release-rerun-foreground-20260526T134619Z.log`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `release`
- Jobs: `2`
- Timeout seconds: `7200`
- Patterns: none
- Exit: `0`
- Elapsed seconds: `614`
- Parsed summary: ``
- Parsed errors: `unknown`
- Parsed tests: `unknown`
- Runner time: `00:10:14`
MD;
        $stdout = "10:13 tcl(21999/22000) r1 ETC 00:01\n0 errors out of 22000 tests in 00:10:14\n";

        $record = $evidence->boundedRunnerArtifactRecord($audit, $stdout);

        $t->same('passed', $record['status']);
        $t->same(0, $record['results']['exit']);
        $t->same(22000, $record['results']['tests']);
        $t->same(0, $record['results']['errors']);
        $t->same(21999, $record['progress']['completed']);
        $t->same(22000, $record['progress']['total']);
        $t->contains('integrator confirms the artifact checkout matches', $record['next_gate']);
    },
    'keeps timed out bounded runner artifacts as incomplete progress evidence' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $audit = <<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-release-timeout-20260526T163220Z

- Repository HEAD: `1c42866b067d21d71744a23a8094f10193a8da3f`
- Scratch: `/tmp/libsqlite-release-timeout-20260526T163220Z`
- Log: `/tmp/libsqlite-release-timeout-20260526T163220Z.log`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `release`
- Jobs: `2`
- Timeout seconds: `1800`
- Patterns: none
- Exit: `124`
- Elapsed seconds: `1800`
- Parsed summary: ``
- Parsed errors: `unknown`
- Parsed tests: `unknown`
- Runner time: `unknown`
MD;
        $stdout = "29:58 tcl(18142/22000) r2 ETC 06:21\n";

        $record = $evidence->boundedRunnerArtifactRecord($audit, $stdout);
        $acceptance = $evidence->boundedRunnerAcceptanceGate($record, '1c42866b067d21d71744a23a8094f10193a8da3f');

        $t->same('timed-out-incomplete', $record['status']);
        $t->same(124, $record['results']['exit']);
        $t->same(null, $record['results']['tests']);
        $t->same(null, $record['results']['errors']);
        $t->same(0, $record['results']['failure_count']);
        $t->same([], $record['results']['failure_blockers']);
        $t->same(18142, $record['progress']['completed']);
        $t->same(22000, $record['progress']['total']);
        $t->contains('timeout as incomplete broad-suite evidence', $record['next_gate']);
        $t->same('blocked', $acceptance['status']);
        $t->same('artifact-not-passed', $acceptance['blockers'][0]['id']);
    },
    'extracts failed bounded runner script diagnostics from guarded release artifacts' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $audit = <<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-release-notty-runner-20260526T102446Z

- Repository HEAD: `008c84e187817c884cd42af0091866e2b8be63af`
- Scratch: `/tmp/sqlite-release-notty-runner-20260526T102446Z`
- Log: `/tmp/sqlite-release-notty-runner-20260526T102446Z.log`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `release`
- Jobs: `2`
- Timeout seconds: `7200`
- Patterns: none
- Exit: `1`
- Elapsed seconds: `4113`
- Parsed summary: ``
- Parsed errors: `unknown`
- Parsed tests: `unknown`
- Runner time: `unknown`

## Tail

```text
FAILED: Sanitize ext/fts5/test/fts5aux.test (1)
OUTPUT: fts5aux-1.0... Ok
fts5aux-3.1.../tmp/src/ext/fts5/fts5_tcl.c:429:59: runtime error: applying non-zero offset 1 to null pointer
SUMMARY: UndefinedBehaviorSanitizer: undefined-behavior /tmp/src/ext/fts5/fts5_tcl.c:429:59
```
MD;

        $record = $evidence->boundedRunnerArtifactRecord($audit);

        $t->same('failed', $record['status']);
        $t->same(1, $record['results']['exit']);
        $t->same(null, $record['results']['tests']);
        $t->same(null, $record['results']['errors']);
        $t->same(1, $record['results']['failure_count']);
        $t->same('Sanitize ext/fts5/test/fts5aux.test (1)', $record['results']['failures'][0]['label']);
        $t->same('ext/fts5/test/fts5aux.test', $record['results']['failures'][0]['script']);
        $t->same('Sanitize', $record['results']['failures'][0]['kind']);
        $t->same('fts5aux-3.1', $record['results']['failures'][0]['case']);
        $t->contains('UndefinedBehaviorSanitizer', $record['results']['failures'][0]['diagnostic']);
        $t->same('upstream-runtime-sanitizer', $record['results']['failure_blockers'][0]['id']);
        $t->same('upstream-runtime-environment', $record['results']['failure_blockers'][0]['category']);
        $t->same('ext/fts5/test/fts5aux.test', $record['results']['failure_blockers'][0]['script']);
        $t->same('fts5aux-3.1', $record['results']['failure_blockers'][0]['case']);
        $t->contains('supervisor-approved sanitizer decision', $record['results']['failure_blockers'][0]['next_gate']);
        $t->contains('record the failed upstream runner artifact', $record['next_gate']);
    },
    'builds a focused repro gate for the fts5aux sanitizer blocker' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $blocker = [
            'category' => 'upstream-runtime-environment',
            'script' => 'ext/fts5/test/fts5aux.test',
            'case' => 'fts5aux-3.1',
        ];

        $missing = $evidence->focusedFailureReproGate(
            $blocker,
            '008c84e187817c884cd42af0091866e2b8be63af',
            dirname(__DIR__, 3)
        );

        $t->same('blocked', $missing['status']);
        $t->same('ext/fts5/test/fts5aux.test', $missing['script']);
        $t->same('fts5aux-3.1', $missing['case']);
        $t->contains('veryquick ext/fts5/test/fts5aux.test', $missing['plan']['command']);
        $t->same(1, $missing['blocker_count']);
        $t->same('focused-repro-artifact-missing', $missing['blockers'][0]['id']);

        $audit = <<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-fts5aux-repro-20260526T123000Z

- Repository HEAD: `008c84e187817c884cd42af0091866e2b8be63af`
- Scratch: `/tmp/sqlite-fts5aux-repro`
- Log: `/tmp/sqlite-fts5aux-repro.log`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `veryquick`
- Jobs: `1`
- Timeout seconds: `600`
- Patterns: `ext/fts5/test/fts5aux.test`
- Exit: `1`
- Elapsed seconds: `7`
- Parsed summary: ``
- Parsed errors: `unknown`
- Parsed tests: `unknown`
- Runner time: `unknown`

## Tail

```text
FAILED: Sanitize ext/fts5/test/fts5aux.test (1)
OUTPUT: fts5aux-1.0... Ok
fts5aux-3.1.../tmp/src/ext/fts5/fts5_tcl.c:429:59: runtime error: applying non-zero offset 1 to null pointer
SUMMARY: UndefinedBehaviorSanitizer: undefined-behavior /tmp/src/ext/fts5/fts5_tcl.c:429:59
```
MD;

        $repro = $evidence->focusedFailureReproGate(
            $blocker,
            '008c84e187817c884cd42af0091866e2b8be63af',
            dirname(__DIR__, 3),
            $audit
        );

        $t->same('focused-repro-preserves-upstream-runtime-blocker', $repro['status']);
        $t->same(0, $repro['blocker_count']);
        $t->same('failed', $repro['artifact']['status']);
        $t->same('ext/fts5/test/fts5aux.test', $repro['artifact']['results']['failures'][0]['script']);
        $t->same('fts5aux-3.1', $repro['artifact']['results']['failures'][0]['case']);
        $t->contains('record the focused repro as an upstream runtime/environment blocker', $repro['next_gate']);
        $t->contains('focused repro gate composes existing runner artifact parsing', $repro['dependency_closure']);
    },
    'keeps missing bounded runner artifact files as a blocked evidence record' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $record = $evidence->boundedRunnerArtifactRecordFromFiles(
            '/tmp/missing-sqlite-runner-audit.md',
            '/tmp/missing-sqlite-runner.log'
        );

        $t->same('blocked-missing-artifact-files', $record['status']);
        $t->same('/tmp/missing-sqlite-runner-audit.md', $record['audit_path']);
        $t->same('/tmp/missing-sqlite-runner.log', $record['stdout_path']);
        $t->same([
            '/tmp/missing-sqlite-runner-audit.md',
            '/tmp/missing-sqlite-runner.log',
        ], $record['missing']);
        $t->contains('wait for the guarded bounded-runner audit/log artifacts', $record['next_gate']);
        $t->contains('no new support component needed', $record['dependency_closure']);
    },
    'parses a focused fts5aux repro decision from bounded runner files' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $root = sys_get_temp_dir() . '/libsqlite-focused-fts5aux-repro-' . bin2hex(random_bytes(4));
        mkdir($root, 0777, true);
        $auditPath = $root . '/sqlite-fts5aux-repro.md';
        $logPath = $root . '/sqlite-fts5aux-repro.log';
        file_put_contents($auditPath, <<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-fts5aux-repro-20260526T123916Z

- Repository HEAD: `8ab0375ac9e72382750dc8fb8f4b96a2913e777a`
- Scratch: `/tmp/sqlite-fts5aux-repro-20260526T123916Z`
- Log: `/tmp/sqlite-fts5aux-repro-20260526T123916Z.log`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `veryquick`
- Jobs: `1`
- Timeout seconds: `180`
- Patterns: `ext/fts5/test/fts5aux.test`
- Exit: `0`
- Elapsed seconds: `0`
- Parsed summary: `0 errors out of 1 tests`
- Parsed errors: `0`
- Parsed tests: `1`
- Runner time: `unknown`
MD);
        file_put_contents($logPath, "0 errors out of 1 tests in 00:00\n");
        $blocker = [
            'category' => 'upstream-runtime-environment',
            'script' => 'ext/fts5/test/fts5aux.test',
            'case' => 'fts5aux-3.1',
        ];

        try {
            $gate = $evidence->focusedFailureReproGateFromFiles(
                $blocker,
                '8ab0375ac9e72382750dc8fb8f4b96a2913e777a',
                $auditPath,
                $logPath,
                dirname(__DIR__, 3)
            );

            $t->same('focused-repro-passed', $gate['status']);
            $t->same(true, $gate['artifact_files_ready']);
            $t->same($auditPath, $gate['audit_path']);
            $t->same($logPath, $gate['stdout_path']);
            $t->same(0, $gate['blocker_count']);
            $t->same('passed', $gate['artifact']['status']);
            $t->same(1, $gate['artifact']['results']['tests']);
            $t->same(0, $gate['artifact']['results']['errors']);
            $t->same('accepted-for-lane-evidence', $gate['acceptance']['status']);
            $t->contains('transient', $gate['next_gate']);
            $t->contains('focused repro file gate', $gate['dependency_closure']);
        } finally {
            @unlink($auditPath);
            @unlink($logPath);
            @rmdir($root);
        }
    },
    'builds a guarded release rerun decision from failed release and focused repro evidence' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $releaseAudit = <<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-release-notty-runner-20260526T102446Z

- Repository HEAD: `008c84e187817c884cd42af0091866e2b8be63af`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `release`
- Jobs: `2`
- Timeout seconds: `7200`
- Patterns: none
- Exit: `1`
- Elapsed seconds: `4113`
- Parsed summary: ``
- Parsed errors: `unknown`
- Parsed tests: `unknown`
- Runner time: `unknown`

## Tail

```text
FAILED: Sanitize ext/fts5/test/fts5aux.test (1)
OUTPUT: fts5aux-1.0... Ok
fts5aux-3.1.../tmp/src/ext/fts5/fts5_tcl.c:429:59: runtime error: applying non-zero offset 1 to null pointer
SUMMARY: UndefinedBehaviorSanitizer: undefined-behavior /tmp/src/ext/fts5/fts5_tcl.c:429:59
```
MD;
        $reproAudit = <<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-fts5aux-repro-20260526T123916Z

- Repository HEAD: `8ab0375ac9e72382750dc8fb8f4b96a2913e777a`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `veryquick`
- Jobs: `1`
- Timeout seconds: `180`
- Patterns: `ext/fts5/test/fts5aux.test`
- Exit: `0`
- Elapsed seconds: `0`
- Parsed summary: `0 errors out of 1 tests`
- Parsed errors: `0`
- Parsed tests: `1`
- Runner time: `unknown`
MD;

        $release = $evidence->boundedRunnerArtifactRecord($releaseAudit);
        $repro = $evidence->focusedFailureReproGate(
            [
                'category' => 'upstream-runtime-environment',
                'script' => 'ext/fts5/test/fts5aux.test',
                'case' => 'fts5aux-3.1',
            ],
            '8ab0375ac9e72382750dc8fb8f4b96a2913e777a',
            dirname(__DIR__, 3),
            $reproAudit
        );

        $pending = $evidence->releaseRerunDecisionGate($release, $repro);
        $t->same('blocked-pending-supervisor-decision', $pending['status']);
        $t->same(false, $pending['rerun_allowed']);
        $t->same(false, $pending['counts_as_release_parity']);
        $t->same(0, $pending['blocker_count']);
        $t->same('failed', $pending['release_artifact_status']);
        $t->same('focused-repro-passed', $pending['focused_repro_status']);
        $t->same('ext/fts5/test/fts5aux.test', $pending['script']);
        $t->same('fts5aux-3.1', $pending['case']);
        $t->same(1, $pending['focused_tests']);
        $t->same(0, $pending['focused_errors']);
        $t->contains('explicit supervisor sanitizer/transient-failure decision', $pending['next_gate']);
        $t->contains('no new support component needed', $pending['dependency_closure']);

        $approved = $evidence->releaseRerunDecisionGate($release, $repro, true);
        $t->same('rerun-allowed', $approved['status']);
        $t->same(true, $approved['rerun_allowed']);
        $t->same(true, $approved['supervisor_approved']);
        $t->same(false, $approved['counts_as_release_parity']);
        $t->contains('duplicate-runner gates are clear', $approved['next_gate']);
    },
    'classifies repeated guarded release failures as a persistent upstream runtime blocker' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $firstRelease = <<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-release-notty-runner-20260526T102446Z

- Repository HEAD: `008c84e187817c884cd42af0091866e2b8be63af`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `release`
- Exit: `1`
- Elapsed seconds: `4113`

## Tail

```text
FAILED: Sanitize ext/fts5/test/fts5aux.test (1)
OUTPUT: fts5aux-3.1.../tmp/src/ext/fts5/fts5_tcl.c:429:59: runtime error: applying non-zero offset 1 to null pointer
SUMMARY: UndefinedBehaviorSanitizer: undefined-behavior /tmp/src/ext/fts5/fts5_tcl.c:429:59
```
MD;
        $secondRelease = <<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-release-rerun-foreground-20260526T134619Z

- Repository HEAD: `e5897b4ac75ee1bf7a45063194c84592ccf26996`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `release`
- Exit: `1`
- Elapsed seconds: `614`

## Tail

```text
FAILED: Sanitize ext/fts5/test/fts5aux.test (1)
OUTPUT: fts5aux-3.1.../tmp/src/ext/fts5/fts5_tcl.c:429:59: runtime error: applying non-zero offset 1 to null pointer
SUMMARY: UndefinedBehaviorSanitizer: undefined-behavior /tmp/src/ext/fts5/fts5_tcl.c:429:59
```
MD;
        $reproAudit = <<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-fts5aux-repro-20260526T123916Z

- Repository HEAD: `8ab0375ac9e72382750dc8fb8f4b96a2913e777a`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `veryquick`
- Patterns: `ext/fts5/test/fts5aux.test`
- Exit: `0`
- Parsed summary: `0 errors out of 1 tests`
- Parsed errors: `0`
- Parsed tests: `1`
MD;

        $first = $evidence->boundedRunnerArtifactRecord($firstRelease);
        $second = $evidence->boundedRunnerArtifactRecord($secondRelease);
        $repro = $evidence->focusedFailureReproGate(
            [
                'category' => 'upstream-runtime-environment',
                'script' => 'ext/fts5/test/fts5aux.test',
                'case' => 'fts5aux-3.1',
            ],
            '8ab0375ac9e72382750dc8fb8f4b96a2913e777a',
            dirname(__DIR__, 3),
            $reproAudit
        );

        $gate = $evidence->persistentReleaseRuntimeBlockerGate([$first, $second], $repro);

        $t->same('persistent-upstream-runtime-blocker', $gate['status']);
        $t->same(true, $gate['persistent']);
        $t->same(false, $gate['counts_as_release_parity']);
        $t->same(0, $gate['blocker_count']);
        $t->same(2, $gate['matching_release_artifact_count']);
        $t->same('focused-repro-passed', $gate['focused_repro_status']);
        $t->same(1, $gate['focused_tests']);
        $t->same(0, $gate['focused_errors']);
        $t->same('9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353', $gate['expected_sqlite_manifest_uuid']);
        $t->same('ext/fts5/test/fts5aux.test', $gate['script']);
        $t->same('fts5aux-3.1', $gate['case']);
        $t->same([
            'libsqlite-release-notty-runner-20260526T102446Z',
            'libsqlite-release-rerun-foreground-20260526T134619Z',
        ], array_column($gate['matching_release_artifacts'], 'label'));
        $t->same([
            '9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353',
            '9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353',
        ], array_column($gate['matching_release_artifacts'], 'sqlite_manifest_uuid'));
        $t->contains('persistent upstream-runtime blocker evidence', $gate['next_gate']);
        $t->contains('no new support component needed', $gate['dependency_closure']);

        $blocked = $evidence->persistentReleaseRuntimeBlockerGate([$first], $repro);
        $t->same('blocked', $blocked['status']);
        $t->same(false, $blocked['persistent']);
        $t->same(1, $blocked['matching_release_artifact_count']);
        $t->same(['insufficient-repeated-release-failures'], array_column($blocked['blockers'], 'id'));
    },
    'rejects persistent release blocker evidence from non-release or mismatched manifest artifacts' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $focusedRepro = $evidence->focusedFailureReproGate(
            [
                'category' => 'upstream-runtime-environment',
                'script' => 'ext/fts5/test/fts5aux.test',
                'case' => 'fts5aux-3.1',
            ],
            '8ab0375ac9e72382750dc8fb8f4b96a2913e777a',
            dirname(__DIR__, 3),
            <<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-fts5aux-repro-20260526T123916Z

- Repository HEAD: `8ab0375ac9e72382750dc8fb8f4b96a2913e777a`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `veryquick`
- Patterns: `ext/fts5/test/fts5aux.test`
- Exit: `0`
- Parsed summary: `0 errors out of 1 tests`
- Parsed errors: `0`
- Parsed tests: `1`
MD
        );
        $focusedReleaseFailureTail = <<<'MD'

## Tail

```text
FAILED: Sanitize ext/fts5/test/fts5aux.test (1)
OUTPUT: fts5aux-3.1.../tmp/src/ext/fts5/fts5_tcl.c:429:59: runtime error: applying non-zero offset 1 to null pointer
SUMMARY: UndefinedBehaviorSanitizer: undefined-behavior /tmp/src/ext/fts5/fts5_tcl.c:429:59
```
MD;

        $focusedArtifact = $evidence->boundedRunnerArtifactRecord(<<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-focused-fts5aux-20260526T160000Z

- Repository HEAD: `008c84e187817c884cd42af0091866e2b8be63af`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `veryquick`
- Exit: `1`
MD . $focusedReleaseFailureTail);
        $wrongManifestRelease = $evidence->boundedRunnerArtifactRecord(<<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-release-wrong-manifest-20260526T160000Z

- Repository HEAD: `008c84e187817c884cd42af0091866e2b8be63af`
- SQLite git commit: `wrong-sqlite-commit`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `different-manifest`
- Testset: `release`
- Exit: `1`
MD . $focusedReleaseFailureTail);

        $gate = $evidence->persistentReleaseRuntimeBlockerGate(
            [$focusedArtifact, $wrongManifestRelease],
            $focusedRepro
        );

        $t->same('blocked', $gate['status']);
        $t->same(false, $gate['persistent']);
        $t->same(0, $gate['matching_release_artifact_count']);
        $t->same([
            'release-artifact-not-release-tier',
            'release-artifact-manifest-mismatch',
            'insufficient-repeated-release-failures',
        ], array_column($gate['blockers'], 'id'));
        $t->same('veryquick', $gate['blockers'][0]['testset']);
        $t->same('different-manifest', $gate['blockers'][1]['actual']);
        $t->same('9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353', $gate['blockers'][1]['expected']);
        $t->same(false, $gate['counts_as_release_parity']);
    },
    'requires an explicit supervisor exclusion decision before closing release blocker parity' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $persistent = [
            'status' => 'persistent-upstream-runtime-blocker',
            'persistent' => true,
            'script' => 'ext/fts5/test/fts5aux.test',
            'case' => 'fts5aux-3.1',
            'matching_release_artifact_count' => 2,
            'focused_tests' => 1,
            'focused_errors' => 0,
        ];

        $pending = $evidence->releaseParityExclusionDecisionGate($persistent);
        $t->same('blocked', $pending['status']);
        $t->same(false, $pending['counts_as_zero_error_release_parity']);
        $t->same(false, $pending['counts_as_release_blocker_closure']);
        $t->same('supervisor-exclusion-decision-required', $pending['blockers'][0]['id']);
        $t->contains('release/all parity uncounted', $pending['next_gate']);

        $accepted = $evidence->releaseParityExclusionDecisionGate(
            $persistent,
            true,
            'Supervisor accepts ext/fts5 fts5aux sanitizer as an upstream non-portability exclusion for this release-runner environment.'
        );
        $t->same('accepted-non-portability-exclusion', $accepted['status']);
        $t->same(false, $accepted['counts_as_zero_error_release_parity']);
        $t->same(true, $accepted['counts_as_release_blocker_closure']);
        $t->same(true, $accepted['supervisor_accepted_exclusion']);
        $t->same(0, $accepted['blocker_count']);
        $t->same('ext/fts5/test/fts5aux.test', $accepted['script']);
        $t->same('fts5aux-3.1', $accepted['case']);
        $t->contains('non-portability exclusion only', $accepted['next_gate']);
        $t->contains('no new support component needed', $accepted['dependency_closure']);

        $unproven = $evidence->releaseParityExclusionDecisionGate(
            [
                'status' => 'blocked',
                'persistent' => false,
                'matching_release_artifact_count' => 1,
                'focused_tests' => 1,
                'focused_errors' => 0,
            ],
            true
        );
        $t->same('blocked', $unproven['status']);
        $t->same([
            'persistent-blocker-not-proven',
            'insufficient-release-artifacts',
        ], array_column($unproven['blockers'], 'id'));
    },
    'gates bounded runner artifacts on accepted checkout and SQLite manifest provenance' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $artifact = [
            'status' => 'passed',
            'repository_head' => '53fd0318c00e3e05f1f9fc9de7e9c67b3dc26fe2',
            'sqlite_commit' => '8f70ec615f4cd247d36f92a22c99f65ebbcc22a7',
            'sqlite_version' => '3.54.0',
            'sqlite_manifest_uuid' => '9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353',
            'results' => [
                'exit' => 0,
                'tests' => 10785,
                'errors' => 0,
            ],
        ];

        $accepted = $evidence->boundedRunnerAcceptanceGate(
            $artifact,
            '53fd0318c00e3e05f1f9fc9de7e9c67b3dc26fe2'
        );

        $t->same('accepted-for-lane-evidence', $accepted['status']);
        $t->same(0, $accepted['blocker_count']);
        $t->same(10785, $accepted['tests']);
        $t->same(0, $accepted['errors']);
        $t->contains('record this bounded runner artifact', $accepted['next_gate']);
        $t->contains('no new support component needed', $accepted['dependency_closure']);

        $mismatched = $artifact;
        $mismatched['repository_head'] = 'different-head';
        $mismatched['sqlite_manifest_uuid'] = 'different-manifest';
        $mismatched['sqlite_commit'] = 'different-sqlite-commit';
        $mismatched['sqlite_version'] = '3.53.0';
        $mismatched['status'] = 'running-or-incomplete';
        $mismatched['results']['exit'] = null;
        $mismatched['results']['tests'] = null;

        $blocked = $evidence->boundedRunnerAcceptanceGate(
            $mismatched,
            '53fd0318c00e3e05f1f9fc9de7e9c67b3dc26fe2'
        );

        $t->same('blocked', $blocked['status']);
        $t->same(5, $blocked['blocker_count']);
        $t->same([
            'artifact-not-passed',
            'repository-head-mismatch',
            'sqlite-manifest-uuid-mismatch',
            'sqlite-commit-mismatch',
            'sqlite-version-mismatch',
        ], array_column($blocked['blockers'], 'id'));
        $t->same('different-sqlite-commit', $blocked['sqlite_commit']);
        $t->same('8f70ec615f4cd247d36f92a22c99f65ebbcc22a7', $blocked['expected_sqlite_commit']);
        $t->same('3.53.0', $blocked['sqlite_version']);
        $t->same('3.54.0', $blocked['expected_sqlite_version']);
        $t->contains('matching SQLite source manifest', $blocked['next_gate']);
    },
    'blocks bounded runner artifacts with missing or stale sqlite source provenance' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $missing = [
            'status' => 'passed',
            'repository_head' => 'accepted-head',
            'sqlite_manifest_uuid' => '9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353',
            'results' => [
                'exit' => 0,
                'tests' => 10785,
                'errors' => 0,
            ],
        ];

        $missingGate = $evidence->boundedRunnerAcceptanceGate($missing, 'accepted-head');

        $t->same('blocked', $missingGate['status']);
        $t->same(2, $missingGate['blocker_count']);
        $t->same([
            'sqlite-commit-mismatch',
            'sqlite-version-mismatch',
        ], array_column($missingGate['blockers'], 'id'));
        $t->same(null, $missingGate['sqlite_commit']);
        $t->same('8f70ec615f4cd247d36f92a22c99f65ebbcc22a7', $missingGate['expected_sqlite_commit']);
        $t->same(null, $missingGate['sqlite_version']);
        $t->same('3.54.0', $missingGate['expected_sqlite_version']);
        $t->same(10785, $missingGate['tests']);
        $t->same(0, $missingGate['errors']);
        $t->contains('matching SQLite source manifest', $missingGate['next_gate']);

        $stale = $missing;
        $stale['sqlite_commit'] = '1111111111111111111111111111111111111111';
        $stale['sqlite_version'] = '3.53.0';

        $staleGate = $evidence->boundedRunnerAcceptanceGate($stale, 'accepted-head');

        $t->same('blocked', $staleGate['status']);
        $t->same(2, $staleGate['blocker_count']);
        $t->same([
            'sqlite-commit-mismatch',
            'sqlite-version-mismatch',
        ], array_column($staleGate['blockers'], 'id'));
        $t->same('1111111111111111111111111111111111111111', $staleGate['blockers'][0]['actual']);
        $t->same('8f70ec615f4cd247d36f92a22c99f65ebbcc22a7', $staleGate['blockers'][0]['expected']);
        $t->same('3.53.0', $staleGate['blockers'][1]['actual']);
        $t->same('3.54.0', $staleGate['blockers'][1]['expected']);
        $t->contains('artifact SQLite git commit', $staleGate['blockers'][0]['evidence']);
        $t->contains('artifact SQLite VERSION', $staleGate['blockers'][1]['evidence']);
        $t->contains('provenance before dependency-suite evidence', $staleGate['dependency_closure']);
    },
    'blocks bounded runner countability while guarded release artifact is still active' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $root = sys_get_temp_dir() . '/libsqlite-bounded-runner-countability-active-' . bin2hex(random_bytes(4));
        mkdir($root, 0777, true);
        $auditPath = $root . '/sqlite-release-runner.md';
        $logPath = $root . '/sqlite-release-runner.log';
        file_put_contents($auditPath, <<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-release-notty-runner-20260526T102446Z

- Repository HEAD: `008c84e187817c884cd42af0091866e2b8be63af`
- Scratch: `/tmp/sqlite-release-notty-runner-20260526T102446Z`
- Log: `/tmp/sqlite-release-notty-runner-20260526T102446Z.log`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `release`
- Jobs: `2`
- Timeout seconds: `7200`
- Patterns: none
MD);
        file_put_contents($logPath, "05:40 tcl(3840/22000) r2 ETC 01:12:20\n");
        $snapshot = <<<'TXT'
577248       1       05:42 bash scripts/run-sqlite-tcl-bounded-runner.sh libsqlite-release-notty-runner-20260526T102446Z audits/sqlite-release-notty-runner-20260526T102446Z.md .tmux-team/tmp/sqlite-release-notty-runner-20260526T102446Z .tmux-team/logs/sqlite-release-notty-runner-20260526T102446Z.log release 2 7200
577297  577296       05:40 ./testfixture ../src/test/testrunner.tcl --jobs 2 --stop-on-error release
TXT;

        try {
            $gate = $evidence->boundedRunnerCountabilityGateFromFiles(
                $auditPath,
                $logPath,
                '008c84e187817c884cd42af0091866e2b8be63af',
                $snapshot
            );

            $t->same('blocked', $gate['status']);
            $t->same(false, $gate['countable']);
            $t->same('active-runner-in-progress', $gate['artifact_status']);
            $t->true($gate['blocker_count'] >= 2, 'Expected active runner and artifact-not-passed blockers');
            $t->same('active-runner-still-running', $gate['blockers'][0]['id']);
            $t->same(['release'], $gate['blockers'][0]['active_tiers']);
            $t->same(3840, $gate['artifact']['progress']['completed']);
            $t->same(22000, $gate['artifact']['progress']['total']);
            $t->same('blocked', $gate['acceptance']['status']);
            $t->contains('do not count this bounded runner artifact', $gate['next_gate']);
            $t->contains('no new support component needed', $gate['dependency_closure']);
        } finally {
            @unlink($auditPath);
            @unlink($logPath);
            @rmdir($root);
        }
    },
    'marks bounded runner countability only after artifact and provenance pass' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $root = sys_get_temp_dir() . '/libsqlite-bounded-runner-countability-pass-' . bin2hex(random_bytes(4));
        mkdir($root, 0777, true);
        $auditPath = $root . '/sqlite-release-runner.md';
        $logPath = $root . '/sqlite-release-runner.log';
        file_put_contents($auditPath, <<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-release-runner-20260526T111111Z

- Repository HEAD: `2464928fd3673d823de3ec22a6e1c6c4f38b6d85`
- Scratch: `/tmp/sqlite-release-runner-20260526T111111Z`
- Log: `/tmp/sqlite-release-runner-20260526T111111Z.log`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `release`
- Jobs: `2`
- Timeout seconds: `7200`
- Patterns: none
- Exit: `0`
- Elapsed seconds: `180`
- Parsed summary: `0 errors out of 22000 tests`
- Runner time: `00:03:00`
MD);
        file_put_contents($logPath, "03:00 tcl(22000/22000) r0\n");

        try {
            $gate = $evidence->boundedRunnerCountabilityGateFromFiles(
                $auditPath,
                $logPath,
                '2464928fd3673d823de3ec22a6e1c6c4f38b6d85'
            );

            $t->same('countable', $gate['status']);
            $t->same(true, $gate['countable']);
            $t->same('passed', $gate['artifact_status']);
            $t->same(0, $gate['blocker_count']);
            $t->same('accepted-for-lane-evidence', $gate['acceptance']['status']);
            $t->same(22000, $gate['acceptance']['tests']);
            $t->same(0, $gate['acceptance']['errors']);
            $t->contains('record this bounded runner artifact', $gate['next_gate']);
        } finally {
            @unlink($auditPath);
            @unlink($logPath);
            @rmdir($root);
        }
    },
    'builds a selected upstream test script inventory from hydrated sources' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $root = sys_get_temp_dir() . '/libsqlite-selected-script-inventory-' . bin2hex(random_bytes(4));
        $testDirectory = $root . '/.upstream-cache/libsqlite/test';
        mkdir($testDirectory, 0777, true);

        $selected = array_slice($evidence->runnerCoverageAudit()['selected_scripts'], 0, 6);
        foreach ($selected as $script) {
            file_put_contents($testDirectory . '/' . $script, '# selected script fixture');
        }
        foreach ($evidence->runnerCoverageAudit()['pattern_scripts'] as $pattern) {
            file_put_contents($testDirectory . '/' . str_replace('*', '01', $pattern), '# wildcard script fixture');
        }

        try {
            $inventory = $evidence->selectedScriptInventory($root);

            $t->same('blocked', $inventory['status']);
            $t->same(true, $inventory['test_directory_ready']);
            $t->true($inventory['requested_selected_script_count'] >= 40, 'Expected selected runner history to remain visible');
            $t->true($inventory['requested_wildcard_pattern_count'] >= 2, 'Expected wildcard runner history to remain visible');
            $t->true($inventory['resolved_script_count'] >= 8, 'Expected selected and wildcard fixture scripts to resolve');
            $t->true($inventory['missing_script_count'] > 0, 'Expected omitted accepted scripts to remain blocked');
            $t->same(0, $inventory['invalid_script_count']);
            $t->same('ready', $inventory['wildcard_status']);
            $t->contains('hydrate .upstream-cache/libsqlite/test', $inventory['next_gate']);
            $t->contains('no new support component needed', $inventory['dependency_closure']);

            $sources = array_column($inventory['resolved_scripts'], 'source');
            $t->true(in_array('.upstream-cache/libsqlite/test/' . $selected[0], $sources, true), 'Expected selected script source path');
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
    'blocks selected upstream test script inventory when the test directory is absent' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $inventory = $evidence->selectedScriptInventory('/tmp/missing-libsqlite-selected-script-inventory-root');

        $t->same('blocked', $inventory['status']);
        $t->same(false, $inventory['test_directory_ready']);
        $t->same(0, $inventory['resolved_script_count']);
        $t->true($inventory['missing_script_count'] >= 40, 'Expected selected scripts to remain blocked without hydrated sources');
        $t->same('blocked-needs-hydrated-test-dir', $inventory['wildcard_status']);
        $t->contains('resolve every selected or wildcard-expanded .test file', $inventory['next_gate']);
    },
    'blocks broad upstream suite launch when a guarded release runner is active' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $snapshot = <<<'TXT'
1447995 580343 17:44 bash bash scripts/run-sqlite-tcl-bounded-runner.sh libsqlite-release-rerun-20260526T131549Z audits/sqlite-release-rerun-20260526T131549Z.md .tmux-team/tmp/sqlite-release-rerun-20260526T131549Z .tmux-team/logs/sqlite-release-rerun-20260526T131549Z.log release 2 7200
1448035 1448034 17:43 testfixture ./testfixture ../src/test/testrunner.tcl --jobs 2 --stop-on-error release
TXT;

        $gate = $evidence->broadSuiteLaunchGate($snapshot, true, 2, '/tmp/missing-libsqlite-broad-suite-root');

        $t->same('blocked', $gate['status']);
        $t->same(false, $gate['launch_allowed']);
        $t->same(true, $gate['supervisor_approved']);
        $t->same(2, $gate['jobs']);
        $t->same('blocked-active-runner', $gate['active_gate']['status']);
        $t->same(['release'], $gate['active_gate']['active_tiers']);
        $t->same('blocked', $gate['command_manifest_status']);
        $t->true($gate['blocker_count'] >= 2, 'Expected active runner and command manifest blockers');
        $t->same([
            'active-runner-still-running',
            'command-manifest-not-ready',
        ], array_column($gate['blockers'], 'id'));
        $t->same(null, $gate['next_command']);
        $t->contains('do not launch a broad SQLite suite', $gate['next_gate']);
        $t->contains('no new support component needed', $gate['dependency_closure']);
    },
    'requires supervisor approval before broad upstream suite launch even when duplicate runner gate is clear' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $gate = $evidence->broadSuiteLaunchGate('', false, 1, '/tmp/missing-libsqlite-broad-suite-root');

        $t->same('blocked', $gate['status']);
        $t->same(false, $gate['launch_allowed']);
        $t->same('clear', $gate['active_gate']['status']);
        $t->true(in_array('supervisor-approval-required', array_column($gate['blockers'], 'id'), true), 'Expected explicit approval blocker');
        $t->true(in_array('command-manifest-not-ready', array_column($gate['blockers'], 'id'), true), 'Expected missing hydrated command manifest blocker');
    },
    'builds a final release blocker admission record from countability and exclusion gates' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $countable = [
            'status' => 'countable',
            'countable' => true,
            'artifact_status' => 'passed',
            'acceptance' => [
                'tests' => 401234,
                'errors' => 0,
            ],
            'blockers' => [],
        ];

        $zeroError = $evidence->releaseBlockerAdmissionRecord($countable);

        $t->same('admissible', $zeroError['status']);
        $t->same('zero-error-release-artifact', $zeroError['closure_mode']);
        $t->same(true, $zeroError['release_blocker_closed']);
        $t->same(true, $zeroError['counts_as_zero_error_release_parity']);
        $t->same(true, $zeroError['countable_artifact']);
        $t->same(false, $zeroError['exclusion_accepted']);
        $t->same(401234, $zeroError['artifact_tests']);
        $t->same(0, $zeroError['artifact_errors']);
        $t->same(0, $zeroError['blocker_count']);
        $t->contains('count this release/all artifact as zero-error parity', $zeroError['next_gate']);

        $blockedCountability = [
            'status' => 'blocked',
            'countable' => false,
            'artifact_status' => 'failed',
            'blockers' => [
                [
                    'id' => 'artifact-not-passed',
                    'evidence' => 'bounded runner artifact has not produced parsed zero-error pass evidence',
                ],
            ],
        ];
        $acceptedExclusion = [
            'status' => 'accepted-non-portability-exclusion',
            'counts_as_release_blocker_closure' => true,
            'script' => 'ext/fts5/test/fts5aux.test',
            'case' => 'fts5aux-3.1',
            'blockers' => [],
        ];

        $excluded = $evidence->releaseBlockerAdmissionRecord($blockedCountability, $acceptedExclusion);

        $t->same('admissible', $excluded['status']);
        $t->same('supervisor-non-portability-exclusion', $excluded['closure_mode']);
        $t->same(true, $excluded['release_blocker_closed']);
        $t->same(false, $excluded['counts_as_zero_error_release_parity']);
        $t->same(false, $excluded['countable_artifact']);
        $t->same(true, $excluded['exclusion_accepted']);
        $t->same('ext/fts5/test/fts5aux.test', $excluded['exclusion_script']);
        $t->same('fts5aux-3.1', $excluded['exclusion_case']);
        $t->contains('zero-error parity remains uncounted', $excluded['next_gate']);

        $blocked = $evidence->releaseBlockerAdmissionRecord($blockedCountability);

        $t->same('blocked', $blocked['status']);
        $t->same('blocked', $blocked['closure_mode']);
        $t->same(false, $blocked['release_blocker_closed']);
        $t->same(false, $blocked['counts_as_zero_error_release_parity']);
        $t->same(2, $blocked['blocker_count']);
        $t->same(['artifact-not-passed', 'exclusion-decision-missing'], array_column($blocked['blockers'], 'id'));
        $t->same(['countability', 'exclusion'], array_column($blocked['blockers'], 'source'));
        $t->contains('keep the release blocker open', $blocked['next_gate']);
        $t->contains('no new support component needed', $blocked['dependency_closure']);
    },
    'summarizes release admission records into countable and blocked upstream evidence' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $zeroError = [
            'status' => 'admissible',
            'closure_mode' => 'zero-error-release-artifact',
            'release_blocker_closed' => true,
            'counts_as_zero_error_release_parity' => true,
            'artifact_status' => 'passed',
            'artifact_tests' => 22000,
            'artifact_errors' => 0,
            'blocker_count' => 0,
            'blockers' => [],
        ];
        $excluded = [
            'status' => 'admissible',
            'closure_mode' => 'supervisor-non-portability-exclusion',
            'release_blocker_closed' => true,
            'counts_as_zero_error_release_parity' => false,
            'artifact_status' => 'failed',
            'artifact_tests' => null,
            'artifact_errors' => null,
            'blocker_count' => 0,
            'blockers' => [],
        ];
        $blocked = [
            'status' => 'blocked',
            'closure_mode' => 'blocked',
            'release_blocker_closed' => false,
            'counts_as_zero_error_release_parity' => false,
            'artifact_status' => 'failed',
            'blocker_count' => 2,
            'blockers' => [
                [
                    'id' => 'artifact-not-passed',
                    'source' => 'countability',
                    'evidence' => 'bounded runner artifact has not produced parsed zero-error pass evidence',
                ],
                [
                    'id' => 'supervisor-exclusion-decision-required',
                    'source' => 'exclusion',
                    'evidence' => 'persistent upstream runtime blockers require an explicit decision',
                ],
            ],
        ];

        $ledger = $evidence->releaseAdmissionLedger([
            'zero-error-release' => $zeroError,
            'sanitizer-exclusion' => $excluded,
            'failed-rerun' => $blocked,
        ]);

        $t->same('zero-error-release-parity-countable', $ledger['status']);
        $t->same(3, $ledger['entry_count']);
        $t->same(1, $ledger['zero_error_release_artifacts']);
        $t->same(1, $ledger['exclusion_only_closures']);
        $t->same(1, $ledger['blocked_admissions']);
        $t->same(true, $ledger['release_blocker_closed']);
        $t->same(true, $ledger['counts_as_zero_error_release_parity']);
        $t->same(22000, $ledger['artifact_tests_total']);
        $t->same(0, $ledger['artifact_errors_total']);
        $t->same('zero-error-release', $ledger['entries'][0]['label']);
        $t->same('supervisor-non-portability-exclusion', $ledger['entries'][1]['closure_mode']);
        $t->same('failed-rerun', $ledger['blockers'][0]['admission']);
        $t->same('artifact-not-passed', $ledger['blockers'][0]['id']);
        $t->contains('publish release/all zero-error parity', $ledger['next_gate']);
        $t->contains('no new support component needed', $ledger['dependency_closure']);

        $exclusionOnly = $evidence->releaseAdmissionLedger(['sanitizer-exclusion' => $excluded]);
        $t->same('release-blocker-closed-by-exclusion', $exclusionOnly['status']);
        $t->same(false, $exclusionOnly['counts_as_zero_error_release_parity']);
        $t->same(true, $exclusionOnly['release_blocker_closed']);
        $t->contains('zero-error release/all parity uncounted', $exclusionOnly['next_gate']);

        $blockedOnly = $evidence->releaseAdmissionLedger(['failed-rerun' => $blocked]);
        $t->same('blocked', $blockedOnly['status']);
        $t->same(false, $blockedOnly['release_blocker_closed']);
        $t->same(2, count($blockedOnly['blockers']));
    },
    'decides whether another release all rerun is admissible from admission ledger evidence' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $zeroError = [
            'status' => 'admissible',
            'closure_mode' => 'zero-error-release-artifact',
            'release_blocker_closed' => true,
            'counts_as_zero_error_release_parity' => true,
            'artifact_status' => 'passed',
            'artifact_tests' => 22000,
            'artifact_errors' => 0,
            'blocker_count' => 0,
            'blockers' => [],
        ];
        $excluded = [
            'status' => 'admissible',
            'closure_mode' => 'supervisor-non-portability-exclusion',
            'release_blocker_closed' => true,
            'counts_as_zero_error_release_parity' => false,
            'artifact_status' => 'failed',
            'blocker_count' => 0,
            'blockers' => [],
        ];
        $blocked = [
            'status' => 'blocked',
            'closure_mode' => 'blocked',
            'release_blocker_closed' => false,
            'counts_as_zero_error_release_parity' => false,
            'artifact_status' => 'failed',
            'blocker_count' => 2,
            'blockers' => [
                [
                    'id' => 'artifact-not-passed',
                    'source' => 'countability',
                    'evidence' => 'bounded runner artifact has not produced parsed zero-error pass evidence',
                ],
                [
                    'id' => 'exclusion-decision-missing',
                    'source' => 'exclusion',
                    'evidence' => 'no supervisor exclusion decision gate was supplied',
                ],
            ],
        ];

        $alreadyCounted = $evidence->releaseRerunDecisionRecord(['release-zero-error' => $zeroError], '', true);

        $t->same('rerun-not-needed-zero-error-parity', $alreadyCounted['status']);
        $t->same(false, $alreadyCounted['rerun_allowed']);
        $t->same('zero-error-release-parity-countable', $alreadyCounted['ledger_status']);
        $t->same(1, $alreadyCounted['zero_error_release_artifacts']);
        $t->same(0, $alreadyCounted['blocker_count']);
        $t->contains('do not launch another broad runner', $alreadyCounted['next_gate']);
        $t->contains('no new support component needed', $alreadyCounted['dependency_closure']);

        $snapshot = '3843839 3843838 04:42 /home/claude/port-libs/scripts/run-sqlite-tcl-bounded-runner.sh --testset release --pattern release';
        $blockedDecision = $evidence->releaseRerunDecisionRecord(['failed-rerun' => $blocked], $snapshot, false);

        $t->same('blocked', $blockedDecision['status']);
        $t->same(false, $blockedDecision['rerun_allowed']);
        $t->same('blocked', $blockedDecision['ledger_status']);
        $t->same(1, $blockedDecision['blocked_admissions']);
        $t->same('blocked-active-runner', $blockedDecision['active_gate']['status']);
        $t->same(['release'], $blockedDecision['active_gate']['active_tiers']);
        $t->same([
            'supervisor-approval-required',
            'active-runner-still-running',
            'admission-artifact-not-passed',
            'admission-exclusion-decision-missing',
        ], array_column($blockedDecision['blockers'], 'id'));
        $t->same('failed-rerun', $blockedDecision['blockers'][2]['admission']);
        $t->same('countability', $blockedDecision['blockers'][2]['source']);
        $t->contains('do not launch another broad release/all runner', $blockedDecision['next_gate']);

        $allowed = $evidence->releaseRerunDecisionRecord([], '', true);

        $t->same('rerun-allowed', $allowed['status']);
        $t->same(true, $allowed['rerun_allowed']);
        $t->same('blocked', $allowed['ledger_status']);
        $t->same(0, $allowed['blocker_count']);
        $t->contains('launch at most one guarded broad release/all rerun', $allowed['next_gate']);

        $exclusionOnly = $evidence->releaseRerunDecisionRecord(['sanitizer-exclusion' => $excluded], '', true);

        $t->same('blocked', $exclusionOnly['status']);
        $t->same(false, $exclusionOnly['rerun_allowed']);
        $t->same('release-blocker-closed-by-exclusion', $exclusionOnly['ledger_status']);
        $t->same('release-blocker-closed-by-exclusion', $exclusionOnly['blockers'][0]['id']);
    },
    'summarizes mixed bounded runner artifact sets before release evidence is counted' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $root = sys_get_temp_dir() . '/libsqlite-bounded-runner-artifact-set-' . bin2hex(random_bytes(4));
        mkdir($root, 0777, true);

        $passedAudit = $root . '/sqlite-release-pass.md';
        $passedLog = $root . '/sqlite-release-pass.log';
        $activeAudit = $root . '/sqlite-release-active.md';
        $activeLog = $root . '/sqlite-release-active.log';
        $failedAudit = $root . '/sqlite-release-failed.md';
        $timeoutAudit = $root . '/sqlite-release-timeout.md';
        $timeoutLog = $root . '/sqlite-release-timeout.log';

        file_put_contents($passedAudit, <<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-release-pass-20260526T200000Z

- Repository HEAD: `abc123accepted`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `release`
- Exit: `0`
- Parsed summary: `0 errors out of 22000 tests`
- Parsed errors: `0`
- Parsed tests: `22000`
MD);
        file_put_contents($passedLog, "03:00 tcl(22000/22000) r0\n");

        file_put_contents($activeAudit, <<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-release-active-20260526T200100Z

- Repository HEAD: `abc123accepted`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `release`
MD);
        file_put_contents($activeLog, "05:40 tcl(3840/22000) r2 ETC 01:12:20\n");

        file_put_contents($failedAudit, <<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-release-failed-20260526T200200Z

- Repository HEAD: `abc123accepted`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `release`
- Exit: `1`

## Tail

```text
FAILED: Sanitize ext/fts5/test/fts5aux.test (1)
OUTPUT: fts5aux-3.1.../tmp/src/ext/fts5/fts5_tcl.c:429:59: runtime error: applying non-zero offset 1 to null pointer
SUMMARY: UndefinedBehaviorSanitizer: undefined-behavior /tmp/src/ext/fts5/fts5_tcl.c:429:59
```
MD);

        file_put_contents($timeoutAudit, <<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-release-timeout-20260526T200300Z

- Repository HEAD: `abc123accepted`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `release`
- Exit: `124`
- Parsed errors: `unknown`
- Parsed tests: `unknown`
MD);
        file_put_contents($timeoutLog, "29:58 tcl(18142/22000) r2 ETC 06:21\n");

        try {
            $set = $evidence->boundedRunnerArtifactSetRecord(
                [
                    'zero-error-release' => [
                        'audit' => $passedAudit,
                        'stdout' => $passedLog,
                    ],
                    'missing-release' => [
                        'audit' => $root . '/missing-audit.md',
                        'stdout' => $root . '/missing-log.log',
                    ],
                    'active-release' => [
                        'audit' => $activeAudit,
                        'stdout' => $activeLog,
                        'process_snapshot' => '577297 577296 05:40 ./testfixture ../src/test/testrunner.tcl --jobs 2 --stop-on-error release',
                    ],
                    'failed-release' => [
                        'audit' => $failedAudit,
                    ],
                    'timeout-release' => [
                        'audit' => $timeoutAudit,
                        'stdout' => $timeoutLog,
                    ],
                ],
                'abc123accepted'
            );

            $t->same('partially-countable', $set['status']);
            $t->same(5, $set['artifact_count']);
            $t->same(1, $set['countable_count']);
            $t->same(4, $set['blocked_count']);
            $t->same(1, $set['missing_count']);
            $t->same(1, $set['active_count']);
            $t->same(1, $set['failed_count']);
            $t->same(1, $set['timed_out_count']);
            $t->same(['zero-error-release'], $set['countable_labels']);
            $t->same(['missing-release'], $set['missing_labels']);
            $t->same(['active-release'], $set['active_labels']);
            $t->same(['failed-release'], $set['failed_labels']);
            $t->same(['timeout-release'], $set['timed_out_labels']);
            $t->same(22000, $set['tests_total']);
            $t->same(0, $set['errors_total']);
            $t->contains('publish only the countable zero-error bounded runner artifacts', $set['next_gate']);
            $t->contains('artifact-set records compose existing bounded runner file/countability gates only', $set['dependency_closure']);
            $t->same('countable', $set['entries'][0]['status']);
            $t->same(['artifact-files-missing'], $set['entries'][1]['blocker_ids']);
            $t->same('active-runner-in-progress', $set['entries'][2]['artifact_status']);
            $t->true(in_array('active-runner-still-running', $set['entries'][2]['blocker_ids'], true), 'Expected active runner blocker in artifact set');
            $t->same('failed', $set['entries'][3]['artifact_status']);
            $t->same('timed-out-incomplete', $set['entries'][4]['artifact_status']);

            $empty = $evidence->boundedRunnerArtifactSetRecord([], 'abc123accepted');
            $t->same('blocked-empty-artifact-set', $empty['status']);
            $t->same(0, $empty['artifact_count']);
            $t->same(0, $empty['countable_count']);
            $t->contains('at least one guarded runner artifact', $empty['next_gate']);
        } finally {
            foreach ([$passedAudit, $passedLog, $activeAudit, $activeLog, $failedAudit, $timeoutAudit, $timeoutLog] as $file) {
                @unlink($file);
            }
            @rmdir($root);
        }
    },
    'admits zero-error focused runner artifacts without release parity credit' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $focused = $evidence->boundedRunnerArtifactRecord(<<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-focused-encoding-20260527T020000Z

- Repository HEAD: `focused-head`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `veryquick`
- Patterns: `enc.test` `collate1.test` `like.test`
- Exit: `0`
- Parsed summary: `0 errors out of 4321 tests`
- Parsed errors: `0`
- Parsed tests: `4321`
MD);

        $admission = $evidence->focusedRunnerArtifactAdmission($focused, 'focused-head');

        $t->same('focused-evidence-countable', $admission['status']);
        $t->same(true, $admission['countable']);
        $t->same(false, $admission['counts_as_release_parity']);
        $t->same('passed', $admission['artifact_status']);
        $t->same('focused-head', $admission['repository_head']);
        $t->same('veryquick', $admission['testset']);
        $t->same(['enc.test', 'collate1.test', 'like.test'], $admission['patterns']);
        $t->same(3, $admission['pattern_count']);
        $t->same(4321, $admission['tests']);
        $t->same(0, $admission['errors']);
        $t->same(0, $admission['blocker_count']);
        $t->contains('focused upstream evidence only', $admission['next_gate']);
        $t->contains('no new support component needed', $admission['dependency_closure']);

        $broad = $evidence->boundedRunnerArtifactRecord(<<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-release-20260527T020100Z

- Repository HEAD: `focused-head`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `release`
- Exit: `0`
- Parsed errors: `0`
- Parsed tests: `22000`
MD);
        $broadAdmission = $evidence->focusedRunnerArtifactAdmission($broad, 'focused-head');
        $t->same('blocked', $broadAdmission['status']);
        $t->same(false, $broadAdmission['countable']);
        $t->same(false, $broadAdmission['counts_as_release_parity']);
        $t->same(['focused-patterns-missing'], array_column($broadAdmission['blockers'], 'id'));
        $t->contains('broad all/release artifacts use the release countability gate', $broadAdmission['blockers'][0]['evidence']);

        $mismatch = $evidence->focusedRunnerArtifactAdmission($focused, 'different-head');
        $t->same('blocked', $mismatch['status']);
        $t->same(false, $mismatch['countable']);
        $t->same(['repository-head-mismatch'], array_column($mismatch['blockers'], 'id'));
        $t->same('different-head', $mismatch['blockers'][0]['expected']);
        $t->same('focused-head', $mismatch['blockers'][0]['actual']);
    },
    'summarizes accepted head provenance for mixed upstream runner artifacts' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $focused = $evidence->boundedRunnerArtifactRecord(<<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-focused-json-20260527T093000Z

- Repository HEAD: `accepted-head`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `veryquick`
- Patterns: `json101.test` `json102.test`
- Exit: `0`
- Parsed summary: `0 errors out of 812 tests`
- Parsed errors: `0`
- Parsed tests: `812`
MD);
        $release = $evidence->boundedRunnerArtifactRecord(<<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-release-20260527T093100Z

- Repository HEAD: `accepted-head`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `release`
- Patterns: none
- Exit: `0`
- Parsed summary: `0 errors out of 22000 tests`
- Parsed errors: `0`
- Parsed tests: `22000`
MD);
        $stale = $evidence->boundedRunnerArtifactRecord(<<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-focused-stale-20260527T093200Z

- Repository HEAD: `stale-head`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `veryquick`
- Patterns: `wal.test`
- Exit: `0`
- Parsed summary: `0 errors out of 144 tests`
- Parsed errors: `0`
- Parsed tests: `144`
MD);
        $wrongManifest = $evidence->boundedRunnerArtifactRecord(<<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-focused-wrong-manifest-20260527T093300Z

- Repository HEAD: `accepted-head`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `wrong-manifest`
- Testset: `veryquick`
- Patterns: `btree01.test`
- Exit: `0`
- Parsed summary: `0 errors out of 91 tests`
- Parsed errors: `0`
- Parsed tests: `91`
MD);

        $batch = $evidence->acceptedHeadArtifactProvenanceBatch(
            [
                'focused-json' => $focused,
                'release-zero-error' => $release,
                'focused-stale' => $stale,
                'focused-wrong-manifest' => $wrongManifest,
            ],
            'accepted-head'
        );

        $t->same('partially-current-accepted-head', $batch['status']);
        $t->same('accepted-head', $batch['accepted_repository_head']);
        $t->same(4, $batch['artifact_count']);
        $t->same(2, $batch['current_accepted_count']);
        $t->same(2, $batch['blocked_count']);
        $t->same(1, $batch['stale_head_count']);
        $t->same(1, $batch['manifest_mismatch_count']);
        $t->same(1, $batch['focused_count']);
        $t->same(1, $batch['release_like_count']);
        $t->same(['focused-json', 'release-zero-error'], $batch['current_labels']);
        $t->same(['focused-stale', 'focused-wrong-manifest'], $batch['blocked_labels']);
        $t->same(['focused-stale'], $batch['stale_head_labels']);
        $t->same(['focused-wrong-manifest'], $batch['manifest_mismatch_labels']);
        $t->same(['focused-json'], $batch['focused_labels']);
        $t->same(['release-zero-error'], $batch['release_like_labels']);
        $t->same(22812, $batch['tests_total']);
        $t->same(0, $batch['errors_total']);
        $t->same(false, $batch['counts_as_release_parity']);
        $t->contains('rerun or reparse stale/mismatched bounded runner artifacts', $batch['next_gate']);
        $t->contains('no new support component needed', $batch['dependency_closure']);

        $entries = [];
        foreach ($batch['entries'] as $entry) {
            $entries[$entry['label']] = $entry;
        }

        $t->same('current-accepted-head', $entries['focused-json']['status']);
        $t->same('focused', $entries['focused-json']['kind']);
        $t->same(['json101.test', 'json102.test'], $entries['focused-json']['patterns']);
        $t->same(2, $entries['focused-json']['pattern_count']);
        $t->same(812, $entries['focused-json']['tests']);
        $t->same(0, $entries['focused-json']['errors']);
        $t->same([], $entries['focused-json']['blocker_ids']);
        $t->same('release-like', $entries['release-zero-error']['kind']);
        $t->same('release', $entries['release-zero-error']['testset']);
        $t->same([], $entries['release-zero-error']['patterns']);
        $t->same(22000, $entries['release-zero-error']['tests']);
        $t->same('blocked', $entries['focused-stale']['status']);
        $t->same(['repository-head-mismatch'], $entries['focused-stale']['blocker_ids']);
        $t->same('stale-head', $entries['focused-stale']['repository_head']);
        $t->same('accepted-head', $entries['focused-stale']['accepted_repository_head']);
        $t->same('blocked', $entries['focused-wrong-manifest']['status']);
        $t->same(['sqlite-manifest-uuid-mismatch'], $entries['focused-wrong-manifest']['blocker_ids']);
        $t->same('wrong-manifest', $entries['focused-wrong-manifest']['sqlite_manifest_uuid']);

        $allCurrent = $evidence->acceptedHeadArtifactProvenanceBatch(
            [
                'focused-json' => $focused,
                'release-zero-error' => $release,
            ],
            'accepted-head'
        );
        $t->same('all-current-accepted-head', $allCurrent['status']);
        $t->same(2, $allCurrent['artifact_count']);
        $t->same(2, $allCurrent['current_accepted_count']);
        $t->same(0, $allCurrent['blocked_count']);
        $t->contains('route focused artifacts to focused evidence', $allCurrent['next_gate']);
    },
    'summarizes accepted head provenance from a bounded runner artifact directory' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $root = sys_get_temp_dir() . '/libsqlite-current-head-artifacts-' . bin2hex(random_bytes(4));
        mkdir($root, 0777, true);

        file_put_contents($root . '/focused-json.md', <<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-focused-json-current-20260527T193000Z

- Repository HEAD: `current-head`
- Scratch: `/tmp/libsqlite-focused-json-current-20260527T193000Z`
- Log: `/tmp/libsqlite-focused-json-current-20260527T193000Z.log`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `veryquick`
- Jobs: `1`
- Timeout seconds: `900`
- Patterns: `json101.test` `json102.test`
- Exit: `0`
- Parsed summary: `0 errors out of 812 tests`
- Parsed errors: `0`
- Parsed tests: `812`
MD);
        file_put_contents($root . '/focused-json.log', "00:03 tcl(812/812) r0\n");
        file_put_contents($root . '/release-zero.md', <<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-release-current-20260527T193100Z

- Repository HEAD: `current-head`
- Scratch: `/tmp/libsqlite-release-current-20260527T193100Z`
- Log: `/tmp/libsqlite-release-current-20260527T193100Z.log`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `release`
- Jobs: `2`
- Timeout seconds: `7200`
- Patterns: none
- Exit: `0`
- Parsed summary: `0 errors out of 22000 tests`
- Parsed errors: `0`
- Parsed tests: `22000`
MD);
        file_put_contents($root . '/release-zero.log', "18:31 tcl(22000/22000) r0\n");
        file_put_contents($root . '/stale.md', <<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-focused-stale-20260527T193200Z

- Repository HEAD: `old-head`
- Scratch: `/tmp/libsqlite-focused-stale-20260527T193200Z`
- Log: `/tmp/libsqlite-focused-stale-20260527T193200Z.log`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `veryquick`
- Jobs: `1`
- Timeout seconds: `900`
- Patterns: `wal.test`
- Exit: `0`
- Parsed summary: `0 errors out of 144 tests`
- Parsed errors: `0`
- Parsed tests: `144`
MD);
        file_put_contents($root . '/stale.log', "00:02 tcl(144/144) r0\n");
        file_put_contents($root . '/missing-log.md', <<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-focused-missing-log-20260527T193300Z

- Repository HEAD: `current-head`
- Scratch: `/tmp/libsqlite-focused-missing-log-20260527T193300Z`
- Log: `/tmp/libsqlite-focused-missing-log-20260527T193300Z.log`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `wrong-manifest`
- Testset: `veryquick`
- Jobs: `1`
- Timeout seconds: `900`
- Patterns: `btree01.test`
- Exit: `0`
- Parsed summary: `0 errors out of 91 tests`
- Parsed errors: `0`
- Parsed tests: `91`
MD);

        try {
            $record = $evidence->acceptedHeadArtifactProvenanceDirectoryRecord($root, 'current-head');

            $t->same('partially-current-accepted-head', $record['status']);
            $t->same($root, $record['artifact_directory']);
            $t->same('current-head', $record['accepted_repository_head']);
            $t->same(4, $record['artifact_count']);
            $t->same(2, $record['current_accepted_count']);
            $t->same(2, $record['blocked_count']);
            $t->same(1, $record['stale_head_count']);
            $t->same(1, $record['manifest_mismatch_count']);
            $t->same(1, $record['focused_count']);
            $t->same(1, $record['release_like_count']);
            $t->same(1, $record['missing_log_count']);
            $t->same(['libsqlite-focused-missing-log-20260527T193300Z'], $record['missing_log_labels']);
            $t->same([
                'libsqlite-focused-json-current-20260527T193000Z',
                'libsqlite-release-current-20260527T193100Z',
            ], $record['current_labels']);
            $t->same([
                'libsqlite-focused-missing-log-20260527T193300Z',
                'libsqlite-focused-stale-20260527T193200Z',
            ], $record['blocked_labels']);
            $t->same(['libsqlite-focused-stale-20260527T193200Z'], $record['stale_head_labels']);
            $t->same(['libsqlite-focused-missing-log-20260527T193300Z'], $record['manifest_mismatch_labels']);
            $t->same(22812, $record['tests_total']);
            $t->same(0, $record['errors_total']);
            $t->same(false, $record['counts_as_release_parity']);
            $t->contains('rerun or repair stale', $record['next_gate']);
            $t->contains('accepted-HEAD directory provenance', $record['dependency_closure']);

            $entries = [];
            foreach ($record['entries'] as $entry) {
                $entries[$entry['label']] = $entry;
            }
            $t->same('current-accepted-head', $entries['libsqlite-focused-json-current-20260527T193000Z']['status']);
            $t->same('focused', $entries['libsqlite-focused-json-current-20260527T193000Z']['kind']);
            $t->same(['json101.test', 'json102.test'], $entries['libsqlite-focused-json-current-20260527T193000Z']['patterns']);
            $t->same('release-like', $entries['libsqlite-release-current-20260527T193100Z']['kind']);
            $t->same('release', $entries['libsqlite-release-current-20260527T193100Z']['testset']);
            $t->same('blocked', $entries['libsqlite-focused-stale-20260527T193200Z']['status']);
            $t->same(['repository-head-mismatch'], $entries['libsqlite-focused-stale-20260527T193200Z']['blocker_ids']);
            $t->same('old-head', $entries['libsqlite-focused-stale-20260527T193200Z']['repository_head']);
            $t->same(['sqlite-manifest-uuid-mismatch'], $entries['libsqlite-focused-missing-log-20260527T193300Z']['blocker_ids']);

            $missing = $evidence->acceptedHeadArtifactProvenanceDirectoryRecord($root . '-missing', 'current-head');
            $t->same('blocked-missing-artifact-directory', $missing['status']);
            $t->same(0, $missing['artifact_count']);
            $t->same(0, $missing['current_accepted_count']);
            $t->same(0, $missing['missing_log_count']);
            $t->contains('wait for guarded bounded-runner audit/log artifacts', $missing['next_gate']);
        } finally {
            foreach (glob($root . '/*') ?: [] as $file) {
                unlink($file);
            }
            @rmdir($root);
        }
    },
    'counts only current accepted zero-error release runner artifacts' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $release = $evidence->boundedRunnerArtifactRecord(<<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-release-current-20260527T170000Z

- Repository HEAD: `65c2604262f6bf9ab39500a30cd9cb9c76428812`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `release`
- Patterns: none
- Exit: `0`
- Parsed summary: `0 errors out of 24001 tests`
- Parsed errors: `0`
- Parsed tests: `24001`
MD);
        $focused = $evidence->boundedRunnerArtifactRecord(<<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-focused-current-20260527T170100Z

- Repository HEAD: `65c2604262f6bf9ab39500a30cd9cb9c76428812`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `veryquick`
- Patterns: `json101.test`
- Exit: `0`
- Parsed summary: `0 errors out of 401 tests`
- Parsed errors: `0`
- Parsed tests: `401`
MD);
        $stale = $evidence->boundedRunnerArtifactRecord(<<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-release-stale-20260527T170200Z

- Repository HEAD: `stale-head`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `release`
- Patterns: none
- Exit: `0`
- Parsed summary: `0 errors out of 24001 tests`
- Parsed errors: `0`
- Parsed tests: `24001`
MD);

        $record = $evidence->currentReleaseRunnerCountabilityRecord(
            [
                'release-current' => $release,
                'focused-current' => $focused,
                'release-stale' => $stale,
            ],
            '65c2604262f6bf9ab39500a30cd9cb9c76428812'
        );

        $t->same('partially-countable', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(true, $record['counts_as_release_parity']);
        $t->same(1, $record['countable_release_artifacts']);
        $t->same(1, $record['focused_only_artifacts']);
        $t->same(1, $record['blocked_artifacts']);
        $t->same(1, $record['stale_artifacts']);
        $t->same(['release-current'], $record['countable_labels']);
        $t->same(['focused-current'], $record['focused_only_labels']);
        $t->same(['release-stale'], $record['blocked_labels']);
        $t->same(24001, $record['tests_total']);
        $t->same(0, $record['errors_total']);
        $t->same(0, $record['blocker_count']);
        $t->same('countable-release-runner', $record['entries'][0]['status']);
        $t->same('focused-only', $record['entries'][1]['status']);
        $t->same(['repository-head-mismatch'], $record['entries'][2]['blocker_ids']);
    },
    'blocks current release runner countability without accepted zero-error release evidence' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $focused = $evidence->boundedRunnerArtifactRecord(<<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-focused-only-current-20260527T170300Z

- Repository HEAD: `65c2604262f6bf9ab39500a30cd9cb9c76428812`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `veryquick`
- Patterns: `wal.test`
- Exit: `0`
- Parsed summary: `0 errors out of 144 tests`
- Parsed errors: `0`
- Parsed tests: `144`
MD);

        $record = $evidence->currentReleaseRunnerCountabilityRecord(
            ['focused-only' => $focused],
            '65c2604262f6bf9ab39500a30cd9cb9c76428812',
            '777001 1 02:16 bash scripts/run-sqlite-tcl-bounded-runner.sh libsqlite-release-current release 2 7200'
        );

        $t->same('blocked', $record['status']);
        $t->same(false, $record['countable']);
        $t->same(0, $record['countable_release_artifacts']);
        $t->same(1, $record['focused_only_artifacts']);
        $t->same(0, $record['tests_total']);
        $t->same(2, $record['blocker_count']);
        $t->same('active-runner-still-running', $record['blockers'][0]['id']);
        $t->same('current-zero-error-release-artifact-missing', $record['blockers'][1]['id']);
    },
    'composes artifact-set and exclusion gates into a release blocker closure record' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $root = sys_get_temp_dir() . '/libsqlite-release-blocker-closure-' . bin2hex(random_bytes(4));
        mkdir($root, 0777, true);

        $passedAudit = $root . '/sqlite-release-pass.md';
        $passedLog = $root . '/sqlite-release-pass.log';
        $failedAudit = $root . '/sqlite-release-failed.md';
        file_put_contents($passedAudit, <<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-release-pass-20260526T203000Z

- Repository HEAD: `closure-head`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `release`
- Exit: `0`
- Parsed summary: `0 errors out of 24000 tests`
- Parsed errors: `0`
- Parsed tests: `24000`
MD);
        file_put_contents($passedLog, "0 errors out of 24000 tests in 03:10\n");
        file_put_contents($failedAudit, <<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-release-failed-20260526T203100Z

- Repository HEAD: `closure-head`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `release`
- Exit: `1`

## Tail

```text
FAILED: Sanitize ext/fts5/test/fts5aux.test (1)
OUTPUT: fts5aux-3.1.../tmp/src/ext/fts5/fts5_tcl.c:429:59: runtime error: applying non-zero offset 1 to null pointer
SUMMARY: UndefinedBehaviorSanitizer: undefined-behavior /tmp/src/ext/fts5/fts5_tcl.c:429:59
```
MD);

        try {
            $artifactSet = $evidence->boundedRunnerArtifactSetRecord(
                [
                    'zero-error-release' => [
                        'audit' => $passedAudit,
                        'stdout' => $passedLog,
                    ],
                    'failed-release' => [
                        'audit' => $failedAudit,
                    ],
                ],
                'closure-head'
            );

            $closure = $evidence->releaseBlockerClosureRecord($artifactSet, null, '', false);

            $t->same('zero-error-release-parity-countable', $closure['status']);
            $t->same('partially-countable', $closure['artifact_set_status']);
            $t->same(2, $closure['artifact_count']);
            $t->same(1, $closure['countable_artifacts']);
            $t->same(1, $closure['blocked_artifacts']);
            $t->same(1, $closure['failed_artifacts']);
            $t->same(2, $closure['admission_count']);
            $t->same(true, $closure['release_blocker_closed']);
            $t->same(true, $closure['counts_as_zero_error_release_parity']);
            $t->same(false, $closure['rerun_allowed']);
            $t->same('zero-error-release-parity-countable', $closure['ledger']['status']);
            $t->same(1, $closure['ledger']['zero_error_release_artifacts']);
            $t->same(24000, $closure['ledger']['artifact_tests_total']);
            $t->same(0, $closure['ledger']['artifact_errors_total']);
            $t->same('rerun-not-needed-zero-error-parity', $closure['rerun_decision']['status']);
            $t->same(0, $closure['blocker_count']);
            $t->contains('do not launch another broad runner', $closure['next_gate']);
            $t->contains('closure record composes artifact-set countability', $closure['dependency_closure']);

            $exclusion = $evidence->releaseParityExclusionDecisionGate(
                [
                    'status' => 'persistent-upstream-runtime-blocker',
                    'persistent' => true,
                    'script' => 'ext/fts5/test/fts5aux.test',
                    'case' => 'fts5aux-3.1',
                    'matching_release_artifact_count' => 2,
                    'focused_tests' => 1,
                    'focused_errors' => 0,
                ],
                true,
                'Supervisor accepts this sanitizer failure as non-portable in the release-runner environment.'
            );
            $exclusionOnly = $evidence->releaseBlockerClosureRecord(
                $evidence->boundedRunnerArtifactSetRecord([], 'closure-head'),
                $exclusion,
                '',
                false
            );

            $t->same('release-blocker-closed-by-exclusion', $exclusionOnly['status']);
            $t->same('blocked-empty-artifact-set', $exclusionOnly['artifact_set_status']);
            $t->same(0, $exclusionOnly['artifact_count']);
            $t->same(1, $exclusionOnly['admission_count']);
            $t->same(true, $exclusionOnly['release_blocker_closed']);
            $t->same(false, $exclusionOnly['counts_as_zero_error_release_parity']);
            $t->same('release-blocker-closed-by-exclusion', $exclusionOnly['ledger']['status']);
            $t->same(1, $exclusionOnly['ledger']['exclusion_only_closures']);
            $t->same('blocked', $exclusionOnly['rerun_decision']['status']);
            $t->same('release-blocker-closed-by-exclusion', $exclusionOnly['rerun_decision']['blockers'][0]['id']);
            $t->contains('exclusion-only release blocker closure', $exclusionOnly['next_gate']);

            $activeOnly = $evidence->releaseBlockerClosureRecord(
                $evidence->boundedRunnerArtifactSetRecord([], 'closure-head'),
                null,
                '577248 1 02:16 bash scripts/run-sqlite-tcl-bounded-runner.sh libsqlite-release-notty-runner release 2 7200',
                true
            );
            $t->same('blocked-active-runner', $activeOnly['status']);
            $t->same(false, $activeOnly['release_blocker_closed']);
            $t->same(false, $activeOnly['rerun_allowed']);
            $t->same('blocked-active-runner', $activeOnly['rerun_decision']['active_gate']['status']);
            $t->same('active-runner-still-running', $activeOnly['blockers'][0]['id']);
            $t->contains('wait for the active guarded release/all runner', $activeOnly['next_gate']);
        } finally {
            foreach ([$passedAudit, $passedLog, $failedAudit] as $file) {
                @unlink($file);
            }
            @rmdir($root);
        }
    },
    'does not duplicate the permutation release tier blocker once hydrated suites are parsed' => static function (TestRunner $t): void {
        $root = sys_get_temp_dir() . '/libsqlite-permutation-readiness-' . bin2hex(random_bytes(4));
        $build = $root . '/.upstream-cache/libsqlite-build-port-libsqlite';
        $test = $root . '/.upstream-cache/libsqlite/test';
        $mptest = $root . '/.upstream-cache/libsqlite/mptest';
        mkdir($build, 0777, true);
        mkdir($test, 0777, true);
        mkdir($mptest, 0777, true);
        file_put_contents($build . '/testfixture', '#!/bin/sh');
        file_put_contents($build . '/Makefile', "test:\n\t@true\nmptest:\n\t@true\n");
        file_put_contents($test . '/testrunner.tcl', '# testrunner fixture');

        $permutations = [];
        for ($i = 1; $i <= 58; $i++) {
            $permutations[] = sprintf('test_suite "suite%02d" -description {fixture}', $i);
        }
        file_put_contents($test . '/permutations.test', implode("\n", $permutations));

        try {
            $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
            $readiness = $evidence->fullSuiteReadinessRecord(2, $root);

            $t->same('blocked', $readiness['status']);
            $t->true($readiness['ready_count'] >= 4, 'Expected release, make, mptest, wildcard, and permutation readiness records to be available');
            $t->true(in_array('permutation-suite-commands', array_column($readiness['ready'], 'id'), true), 'Expected parsed permutation suite commands to be ready');
            $t->same(false, in_array('permutation-suites', array_column($readiness['blocked'], 'id'), true), 'Parsed permutation map should satisfy the release-tier suite-map blocker');
        } finally {
            foreach (glob($test . '/*') ?: [] as $file) {
                unlink($file);
            }
            foreach (glob($build . '/*') ?: [] as $file) {
                unlink($file);
            }
            @rmdir($mptest);
            @rmdir($test);
            @rmdir($root . '/.upstream-cache/libsqlite');
            @rmdir($build);
            @rmdir($root . '/.upstream-cache');
            @rmdir($root);
        }
    },
    'builds countability records from a guarded runner artifact directory' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $root = sys_get_temp_dir() . '/libsqlite-runner-artifact-directory-' . bin2hex(random_bytes(4));
        mkdir($root, 0777, true);

        $acceptedHead = '28488284c6b42b08db024e7e34c788f71b24a201';
        $sqliteCommit = '8f70ec615f4cd247d36f92a22c99f65ebbcc22a7';
        $uuid = '9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353';

        file_put_contents($root . '/all.md', <<<MD
# SQLite Tcl Bounded Runner Evidence - libsqlite-all-current-next27

- Repository HEAD: `{$acceptedHead}`
- Scratch: `/tmp/libsqlite-all-current-next27`
- Log: `all.log`
- SQLite git commit: `{$sqliteCommit}`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `{$uuid}`
- Testset: `all`
- Jobs: `2`
- Timeout seconds: `1800`
- Patterns: none
- Exit: `0`
- Elapsed seconds: `51`
- Parsed summary: `0 errors out of 10785 tests`
- Parsed errors: `0`
- Parsed tests: `10785`
- Runner time: `00:00:51`
MD);
        file_put_contents($root . '/all.log', "00:51 tcl(10785/10785) r0\n");

        file_put_contents($root . '/focused.md', <<<MD
# SQLite Tcl Bounded Runner Evidence - libsqlite-json-focused-current-next27

- Repository HEAD: `{$acceptedHead}`
- Scratch: `/tmp/libsqlite-json-focused-current-next27`
- Log: `/tmp/not-used/focused.log`
- SQLite git commit: `{$sqliteCommit}`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `{$uuid}`
- Testset: `veryquick`
- Jobs: `1`
- Timeout seconds: `600`
- Patterns: `json101.test json102.test`
- Exit: `0`
- Elapsed seconds: `3`
- Parsed summary: `0 errors out of 650 tests`
- Parsed errors: `0`
- Parsed tests: `650`
- Runner time: `00:00:03`
MD);
        file_put_contents($root . '/focused.log', "00:03 tcl(650/650) r0\n");

        file_put_contents($root . '/stale.md', <<<MD
# SQLite Tcl Bounded Runner Evidence - libsqlite-stale-current-next27

- Repository HEAD: `1111111111111111111111111111111111111111`
- Scratch: `/tmp/libsqlite-stale-current-next27`
- Log: `stale.log`
- SQLite git commit: `{$sqliteCommit}`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `{$uuid}`
- Testset: `release`
- Jobs: `2`
- Timeout seconds: `1800`
- Patterns: none
- Exit: `0`
- Elapsed seconds: `67`
- Parsed summary: `0 errors out of 22000 tests`
- Parsed errors: `0`
- Parsed tests: `22000`
- Runner time: `00:01:07`
MD);
        file_put_contents($root . '/stale.log', "01:07 tcl(22000/22000) r0\n");

        file_put_contents($root . '/failed.md', <<<MD
# SQLite Tcl Bounded Runner Evidence - libsqlite-failed-current-next27

- Repository HEAD: `{$acceptedHead}`
- Scratch: `/tmp/libsqlite-failed-current-next27`
- Log: `failed.log`
- SQLite git commit: `{$sqliteCommit}`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `{$uuid}`
- Testset: `release`
- Jobs: `2`
- Timeout seconds: `1800`
- Patterns: none
- Exit: `1`
- Elapsed seconds: `12`
- Parsed summary: `1 errors out of 40 tests`
- Parsed errors: `1`
- Parsed tests: `40`
- Runner time: `00:00:12`
MD);
        file_put_contents($root . '/failed.log', "1 errors out of 40 tests in 00:00:12\n");

        try {
            $record = $evidence->boundedRunnerArtifactDirectoryRecord($root, $acceptedHead);

            $t->same('partially-countable', $record['status']);
            $t->same($root, $record['artifact_directory']);
            $t->same($acceptedHead, $record['accepted_repository_head']);
            $t->same(4, $record['audit_file_count']);
            $t->same(4, $record['artifact_count']);
            $t->same(2, $record['countable_count']);
            $t->same(2, $record['blocked_count']);
            $t->same(0, $record['missing_count']);
            $t->same(0, $record['active_count']);
            $t->same(1, $record['failed_count']);
            $t->same(0, $record['timed_out_count']);
            $t->same([], $record['unreadable_audit_files']);
            $t->same(['libsqlite-all-current-next27', 'libsqlite-json-focused-current-next27'], $record['countable_labels']);
            $t->same(['libsqlite-failed-current-next27', 'libsqlite-stale-current-next27'], $record['blocked_labels']);
            $t->same(['libsqlite-failed-current-next27'], $record['failed_labels']);
            $t->same(11435, $record['tests_total']);
            $t->same(0, $record['errors_total']);
            $t->contains('publish the countable zero-error artifact entries', $record['next_gate']);
            $t->contains('directory record discovers bounded runner audit/log pairs', $record['dependency_closure']);

            $entries = [];
            foreach ($record['entries'] as $entry) {
                $entries[$entry['label']] = $entry;
            }

            $t->same('countable', $entries['libsqlite-all-current-next27']['status']);
            $t->same(true, $entries['libsqlite-all-current-next27']['countable']);
            $t->same(10785, $entries['libsqlite-all-current-next27']['tests']);
            $t->same(0, $entries['libsqlite-all-current-next27']['errors']);
            $t->same([], $entries['libsqlite-all-current-next27']['blocker_ids']);
            $t->same('countable', $entries['libsqlite-json-focused-current-next27']['status']);
            $t->same(650, $entries['libsqlite-json-focused-current-next27']['tests']);
            $t->same('blocked', $entries['libsqlite-stale-current-next27']['status']);
            $t->true(in_array('repository-head-mismatch', $entries['libsqlite-stale-current-next27']['blocker_ids'], true), 'Expected stale artifact to be blocked by accepted HEAD provenance');
            $t->same('blocked', $entries['libsqlite-failed-current-next27']['status']);
            $t->true(in_array('artifact-not-passed', $entries['libsqlite-failed-current-next27']['blocker_ids'], true), 'Expected failed artifact to remain blocked');
        } finally {
            foreach (glob($root . '/*') ?: [] as $file) {
                unlink($file);
            }
            @rmdir($root);
        }
    },
    'keeps a missing guarded runner artifact directory explicit and uncounted' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $record = $evidence->boundedRunnerArtifactDirectoryRecord(
            '/tmp/missing-libsqlite-runner-artifact-directory-current-next27',
            '28488284c6b42b08db024e7e34c788f71b24a201'
        );

        $t->same('blocked-missing-artifact-directory', $record['status']);
        $t->same('/tmp/missing-libsqlite-runner-artifact-directory-current-next27', $record['artifact_directory']);
        $t->same(0, $record['artifact_count']);
        $t->same(0, $record['countable_count']);
        $t->same(0, $record['blocked_count']);
        $t->same(1, $record['missing_count']);
        $t->same([], $record['countable_labels']);
        $t->same([], $record['blocked_labels']);
        $t->same([], $record['entries']);
        $t->same(0, $record['tests_total']);
        $t->same(0, $record['errors_total']);
        $t->contains('wait for the guarded bounded-runner artifact directory', $record['next_gate']);
        $t->contains('directory record scans bounded runner audit/log artifacts only', $record['dependency_closure']);
    },
    'separates current-source and next-source guarded runner artifacts before countability' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $root = sys_get_temp_dir() . '/libsqlite-current-source-next93-' . bin2hex(random_bytes(4));
        mkdir($root, 0777, true);

        $currentHead = '7abd7d7ba1b03a473ec2d0bbcb0db63762ceae42';
        $nextHead = '21f1e38635e924df34f7be1aef3242b4b233710c';
        $sqliteCommit = '8f70ec615f4cd247d36f92a22c99f65ebbcc22a7';
        $uuid = '9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353';

        file_put_contents($root . '/next-focused.md', <<<MD
# SQLite Tcl Bounded Runner Evidence - libsqlite-next93-focused-json

- Repository HEAD: `{$nextHead}`
- Scratch: `/tmp/libsqlite-next93-focused-json`
- Log: `next-focused.log`
- SQLite git commit: `{$sqliteCommit}`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `{$uuid}`
- Testset: `veryquick`
- Jobs: `1`
- Timeout seconds: `900`
- Patterns: `json101.test` `json102.test`
- Exit: `0`
- Elapsed seconds: `4`
- Parsed summary: `0 errors out of 812 tests`
- Parsed errors: `0`
- Parsed tests: `812`
- Runner time: `00:00:04`
MD);
        file_put_contents($root . '/next-focused.log', "00:04 tcl(812/812) r0\n");

        file_put_contents($root . '/next-release.md', <<<MD
# SQLite Tcl Bounded Runner Evidence - libsqlite-next93-release

- Repository HEAD: `{$nextHead}`
- Scratch: `/tmp/libsqlite-next93-release`
- Log: `next-release.log`
- SQLite git commit: `{$sqliteCommit}`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `{$uuid}`
- Testset: `release`
- Jobs: `2`
- Timeout seconds: `7200`
- Patterns: none
- Exit: `0`
- Elapsed seconds: `1900`
- Parsed summary: `0 errors out of 26014 tests`
- Parsed errors: `0`
- Parsed tests: `26014`
- Runner time: `00:31:40`
MD);
        file_put_contents($root . '/next-release.log', "31:40 tcl(26014/26014) r0\n");

        file_put_contents($root . '/current-focused.md', <<<MD
# SQLite Tcl Bounded Runner Evidence - libsqlite-current93-focused-wal

- Repository HEAD: `{$currentHead}`
- Scratch: `/tmp/libsqlite-current93-focused-wal`
- Log: `current-focused.log`
- SQLite git commit: `{$sqliteCommit}`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `{$uuid}`
- Testset: `veryquick`
- Jobs: `1`
- Timeout seconds: `900`
- Patterns: `wal.test`
- Exit: `0`
- Elapsed seconds: `2`
- Parsed summary: `0 errors out of 144 tests`
- Parsed errors: `0`
- Parsed tests: `144`
- Runner time: `00:00:02`
MD);
        file_put_contents($root . '/current-focused.log', "00:02 tcl(144/144) r0\n");

        try {
            $record = $evidence->currentSourceNextArtifactDirectoryRecord($root, $currentHead, $nextHead);

            $t->same('next-source-countable', $record['status']);
            $t->same($root, $record['artifact_directory']);
            $t->same($currentHead, $record['current_source_head']);
            $t->same($nextHead, $record['next_source_head']);
            $t->same(3, $record['artifact_count']);
            $t->same(1, $record['current_source_count']);
            $t->same(2, $record['next_source_count']);
            $t->same(0, $record['stale_source_count']);
            $t->same(0, $record['blocked_count']);
            $t->same(0, $record['manifest_mismatch_count']);
            $t->same(0, $record['missing_log_count']);
            $t->same(['libsqlite-current93-focused-wal'], $record['current_source_labels']);
            $t->same(['libsqlite-next93-focused-json', 'libsqlite-next93-release'], $record['next_source_labels']);
            $t->same([], $record['stale_source_labels']);
            $t->same([], $record['blocked_labels']);
            $t->same([], $record['missing_log_labels']);
            $t->same(26826, $record['tests_total']);
            $t->same(0, $record['errors_total']);
            $t->same(true, $record['counts_next_source']);
            $t->same(false, $record['counts_as_release_parity']);
            $t->contains('promote the next-source zero-error runner artifacts', $record['next_gate']);
            $t->contains('current/next source directory evidence', $record['dependency_closure']);

            $entries = [];
            foreach ($record['entries'] as $entry) {
                $entries[$entry['label']] = $entry;
            }
            $t->same('next-source-countable', $entries['libsqlite-next93-focused-json']['status']);
            $t->same($nextHead, $entries['libsqlite-next93-focused-json']['repository_head']);
            $t->same(812, $entries['libsqlite-next93-focused-json']['tests']);
            $t->same(false, $entries['libsqlite-next93-focused-json']['missing_log']);
            $t->same([], $entries['libsqlite-next93-focused-json']['blocker_ids']);
            $t->same('next-source-countable', $entries['libsqlite-next93-release']['status']);
            $t->same(26014, $entries['libsqlite-next93-release']['tests']);
            $t->same('current-source-countable', $entries['libsqlite-current93-focused-wal']['status']);
            $t->same($currentHead, $entries['libsqlite-current93-focused-wal']['repository_head']);
            $t->same(144, $entries['libsqlite-current93-focused-wal']['tests']);
        } finally {
            foreach (glob($root . '/*') ?: [] as $file) {
                unlink($file);
            }
            @rmdir($root);
        }
    },
    'keeps stale and manifest-mismatched current-source-next artifacts blocked' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $root = sys_get_temp_dir() . '/libsqlite-current-source-next93-blocked-' . bin2hex(random_bytes(4));
        mkdir($root, 0777, true);

        $currentHead = '7abd7d7ba1b03a473ec2d0bbcb0db63762ceae42';
        $nextHead = '21f1e38635e924df34f7be1aef3242b4b233710c';
        $sqliteCommit = '8f70ec615f4cd247d36f92a22c99f65ebbcc22a7';
        $uuid = '9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353';

        file_put_contents($root . '/next-focused.md', <<<MD
# SQLite Tcl Bounded Runner Evidence - libsqlite-next93-focused-ok

- Repository HEAD: `{$nextHead}`
- Scratch: `/tmp/libsqlite-next93-focused-ok`
- Log: `next-focused.log`
- SQLite git commit: `{$sqliteCommit}`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `{$uuid}`
- Testset: `veryquick`
- Jobs: `1`
- Timeout seconds: `900`
- Patterns: `select1.test`
- Exit: `0`
- Parsed summary: `0 errors out of 91 tests`
- Parsed errors: `0`
- Parsed tests: `91`
MD);
        file_put_contents($root . '/next-focused.log', "00:01 tcl(91/91) r0\n");

        file_put_contents($root . '/stale.md', <<<MD
# SQLite Tcl Bounded Runner Evidence - libsqlite-next93-stale

- Repository HEAD: `aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa`
- Scratch: `/tmp/libsqlite-next93-stale`
- Log: `stale.log`
- SQLite git commit: `{$sqliteCommit}`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `{$uuid}`
- Testset: `veryquick`
- Jobs: `1`
- Timeout seconds: `900`
- Patterns: `btree01.test`
- Exit: `0`
- Parsed summary: `0 errors out of 44 tests`
- Parsed errors: `0`
- Parsed tests: `44`
MD);
        file_put_contents($root . '/stale.log', "00:01 tcl(44/44) r0\n");

        file_put_contents($root . '/wrong-manifest.md', <<<MD
# SQLite Tcl Bounded Runner Evidence - libsqlite-next93-wrong-manifest

- Repository HEAD: `{$nextHead}`
- Scratch: `/tmp/libsqlite-next93-wrong-manifest`
- Log: `wrong-manifest.log`
- SQLite git commit: `{$sqliteCommit}`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `wrong-manifest`
- Testset: `veryquick`
- Jobs: `1`
- Timeout seconds: `900`
- Patterns: `pragma.test`
- Exit: `0`
- Parsed summary: `0 errors out of 58 tests`
- Parsed errors: `0`
- Parsed tests: `58`
MD);

        try {
            $record = $evidence->currentSourceNextArtifactDirectoryRecord($root, $currentHead, $nextHead);

            $t->same('partially-next-source-countable', $record['status']);
            $t->same(3, $record['artifact_count']);
            $t->same(0, $record['current_source_count']);
            $t->same(1, $record['next_source_count']);
            $t->same(1, $record['stale_source_count']);
            $t->same(2, $record['blocked_count']);
            $t->same(1, $record['manifest_mismatch_count']);
            $t->same(1, $record['missing_log_count']);
            $t->same(['libsqlite-next93-focused-ok'], $record['next_source_labels']);
            $t->same(['libsqlite-next93-stale'], $record['stale_source_labels']);
            $t->same(['libsqlite-next93-stale', 'libsqlite-next93-wrong-manifest'], $record['blocked_labels']);
            $t->same(['libsqlite-next93-wrong-manifest'], $record['manifest_mismatch_labels']);
            $t->same(['libsqlite-next93-wrong-manifest'], $record['missing_log_labels']);
            $t->same(91, $record['tests_total']);
            $t->same(0, $record['errors_total']);
            $t->same(true, $record['counts_next_source']);
            $t->contains('count only next-source zero-error artifacts', $record['next_gate']);

            $entries = [];
            foreach ($record['entries'] as $entry) {
                $entries[$entry['label']] = $entry;
            }
            $t->same('next-source-countable', $entries['libsqlite-next93-focused-ok']['status']);
            $t->same('stale-source-blocked', $entries['libsqlite-next93-stale']['status']);
            $t->true(in_array('repository-head-mismatch', $entries['libsqlite-next93-stale']['blocker_ids'], true), 'Expected stale source blocker');
            $t->same('blocked', $entries['libsqlite-next93-wrong-manifest']['status']);
            $t->true(in_array('sqlite-manifest-uuid-mismatch', $entries['libsqlite-next93-wrong-manifest']['blocker_ids'], true), 'Expected manifest mismatch blocker');
            $t->same(true, $entries['libsqlite-next93-wrong-manifest']['missing_log']);

            $missing = $evidence->currentSourceNextArtifactDirectoryRecord($root . '-missing', $currentHead, $nextHead);
            $t->same('blocked-missing-artifact-directory', $missing['status']);
            $t->same(0, $missing['artifact_count']);
            $t->same(0, $missing['next_source_count']);
            $t->same(false, $missing['counts_next_source']);
        } finally {
            foreach (glob($root . '/*') ?: [] as $file) {
                unlink($file);
            }
            @rmdir($root);
        }
    },
    'counts next-source artifacts while preserving blocked current-source evidence' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $root = sys_get_temp_dir() . '/libsqlite-current-source-next112-' . bin2hex(random_bytes(4));
        mkdir($root, 0777, true);

        $currentHead = '67b9065fe584e293134a85272e27bb677a0554af';
        $nextHead = '9019df6907db0bab95578ad10ff5d285936e1c48';
        $sqliteCommit = '8f70ec615f4cd247d36f92a22c99f65ebbcc22a7';
        $uuid = '9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353';

        file_put_contents($root . '/next-json.md', <<<MD
# SQLite Tcl Bounded Runner Evidence - libsqlite-next112-jsonb-path

- Repository HEAD: `{$nextHead}`
- Scratch: `/tmp/libsqlite-next112-jsonb-path`
- Log: `next-json.log`
- SQLite git commit: `{$sqliteCommit}`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `{$uuid}`
- Testset: `veryquick`
- Jobs: `1`
- Timeout seconds: `900`
- Patterns: `json101.test` `jsonb01.test`
- Exit: `0`
- Parsed summary: `0 errors out of 602 tests`
- Parsed errors: `0`
- Parsed tests: `602`
MD);
        file_put_contents($root . '/next-json.log', "00:03 tcl(602/602) r0\n");

        file_put_contents($root . '/next-select.md', <<<MD
# SQLite Tcl Bounded Runner Evidence - libsqlite-next112-select

- Repository HEAD: `{$nextHead}`
- Scratch: `/tmp/libsqlite-next112-select`
- Log: `next-select.log`
- SQLite git commit: `{$sqliteCommit}`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `{$uuid}`
- Testset: `veryquick`
- Jobs: `1`
- Timeout seconds: `900`
- Patterns: `select1.test` `select3.test`
- Exit: `0`
- Parsed summary: `0 errors out of 318 tests`
- Parsed errors: `0`
- Parsed tests: `318`
MD);
        file_put_contents($root . '/next-select.log', "00:02 tcl(318/318) r0\n");

        file_put_contents($root . '/current-wal.md', <<<MD
# SQLite Tcl Bounded Runner Evidence - libsqlite-current112-wal

- Repository HEAD: `{$currentHead}`
- Scratch: `/tmp/libsqlite-current112-wal`
- Log: `current-wal.log`
- SQLite git commit: `{$sqliteCommit}`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `{$uuid}`
- Testset: `veryquick`
- Jobs: `1`
- Timeout seconds: `900`
- Patterns: `wal.test`
- Exit: `0`
- Parsed summary: `0 errors out of 144 tests`
- Parsed errors: `0`
- Parsed tests: `144`
MD);
        file_put_contents($root . '/current-wal.log', "00:01 tcl(144/144) r0\n");

        file_put_contents($root . '/stale-runner.md', <<<MD
# SQLite Tcl Bounded Runner Evidence - libsqlite-stale112-runner

- Repository HEAD: `194673fba15d51a389ce428cfb9d10864076e3f4`
- Scratch: `/tmp/libsqlite-stale112-runner`
- Log: `stale-runner.log`
- SQLite git commit: `{$sqliteCommit}`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `{$uuid}`
- Testset: `veryquick`
- Jobs: `1`
- Timeout seconds: `900`
- Patterns: `pragma.test`
- Exit: `0`
- Parsed summary: `0 errors out of 58 tests`
- Parsed errors: `0`
- Parsed tests: `58`
MD);
        file_put_contents($root . '/stale-runner.log', "00:01 tcl(58/58) r0\n");

        file_put_contents($root . '/missing-log.md', <<<MD
# SQLite Tcl Bounded Runner Evidence - libsqlite-next112-missing-log

- Repository HEAD: `{$nextHead}`
- Scratch: `/tmp/libsqlite-next112-missing-log`
- Log: `missing.log`
- SQLite git commit: `{$sqliteCommit}`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `wrong-manifest`
- Testset: `veryquick`
- Jobs: `1`
- Timeout seconds: `900`
- Patterns: `btree01.test`
- Exit: `0`
- Parsed summary: `0 errors out of 44 tests`
- Parsed errors: `0`
- Parsed tests: `44`
MD);

        try {
            $record = $evidence->currentSourceNextArtifactDirectoryRecord($root, $currentHead, $nextHead);

            $t->same('partially-next-source-countable', $record['status']);
            $t->same(5, $record['artifact_count']);
            $t->same(1, $record['current_source_count']);
            $t->same(2, $record['next_source_count']);
            $t->same(1, $record['stale_source_count']);
            $t->same(2, $record['blocked_count']);
            $t->same(1, $record['manifest_mismatch_count']);
            $t->same(1, $record['missing_log_count']);
            $t->same(['libsqlite-current112-wal'], $record['current_source_labels']);
            $t->same(['libsqlite-next112-jsonb-path', 'libsqlite-next112-select'], $record['next_source_labels']);
            $t->same(['libsqlite-stale112-runner'], $record['stale_source_labels']);
            $t->same(['libsqlite-next112-missing-log', 'libsqlite-stale112-runner'], $record['blocked_labels']);
            $t->same(['libsqlite-next112-missing-log'], $record['manifest_mismatch_labels']);
            $t->same(['libsqlite-next112-missing-log'], $record['missing_log_labels']);
            $t->same(920, $record['tests_total']);
            $t->same(0, $record['errors_total']);
            $t->same(true, $record['counts_next_source']);
            $t->same(false, $record['counts_as_release_parity']);
            $t->contains('count only next-source zero-error artifacts', $record['next_gate']);
            $t->contains('bounded runner artifacts', $record['dependency_closure']);

            $entries = [];
            foreach ($record['entries'] as $entry) {
                $entries[$entry['label']] = $entry;
            }

            $t->same('next-source-countable', $entries['libsqlite-next112-jsonb-path']['status']);
            $t->same($nextHead, $entries['libsqlite-next112-jsonb-path']['repository_head']);
            $t->same(602, $entries['libsqlite-next112-jsonb-path']['tests']);
            $t->same(false, $entries['libsqlite-next112-jsonb-path']['missing_log']);
            $t->same([], $entries['libsqlite-next112-jsonb-path']['blocker_ids']);
            $t->same('next-source-countable', $entries['libsqlite-next112-select']['status']);
            $t->same(318, $entries['libsqlite-next112-select']['tests']);
            $t->same('current-source-countable', $entries['libsqlite-current112-wal']['status']);
            $t->same($currentHead, $entries['libsqlite-current112-wal']['repository_head']);
            $t->same(144, $entries['libsqlite-current112-wal']['tests']);
            $t->same('stale-source-blocked', $entries['libsqlite-stale112-runner']['status']);
            $t->true(in_array('repository-head-mismatch', $entries['libsqlite-stale112-runner']['blocker_ids'], true), 'Expected stale artifact to keep repository-head-mismatch blocker');
            $t->same('blocked', $entries['libsqlite-next112-missing-log']['status']);
            $t->same(true, $entries['libsqlite-next112-missing-log']['missing_log']);
            $t->true(in_array('sqlite-manifest-uuid-mismatch', $entries['libsqlite-next112-missing-log']['blocker_ids'], true), 'Expected mismatched missing-log artifact to stay blocked');
        } finally {
            foreach (glob($root . '/*') ?: [] as $file) {
                unlink($file);
            }
            @rmdir($root);
        }
    },
    'admits only next-source release artifacts for current-source release-gap release gap burnup' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $root = sys_get_temp_dir() . '/libsqlite-release-gap-release-gap-' . bin2hex(random_bytes(4));
        mkdir($root, 0777, true);

        $currentHead = '6b824ac24854056466145761d32a9f27720d286a';
        $nextHead = '8a447f445e5d2fd32fc9fd463117f585d1416551';
        $sqliteCommit = '8f70ec615f4cd247d36f92a22c99f65ebbcc22a7';
        $uuid = '9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353';

        file_put_contents($root . '/next-release.md', <<<MD
# SQLite Tcl Bounded Runner Evidence - libsqlite-release-gap-release

- Repository HEAD: `{$nextHead}`
- Scratch: `/tmp/libsqlite-release-gap-release`
- Log: `next-release.log`
- SQLite git commit: `{$sqliteCommit}`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `{$uuid}`
- Testset: `release`
- Jobs: `2`
- Timeout seconds: `7200`
- Patterns: none
- Exit: `0`
- Parsed summary: `0 errors out of 26014 tests`
- Parsed errors: `0`
- Parsed tests: `26014`
MD);
        file_put_contents($root . '/next-release.log', "31:40 tcl(26014/26014) r0\n");

        file_put_contents($root . '/next-all.md', <<<MD
# SQLite Tcl Bounded Runner Evidence - libsqlite-release-gap-all

- Repository HEAD: `{$nextHead}`
- Scratch: `/tmp/libsqlite-release-gap-all`
- Log: `next-all.log`
- SQLite git commit: `{$sqliteCommit}`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `{$uuid}`
- Testset: `all`
- Jobs: `2`
- Timeout seconds: `7200`
- Patterns: none
- Exit: `0`
- Parsed summary: `0 errors out of 312 tests`
- Parsed errors: `0`
- Parsed tests: `312`
MD);
        file_put_contents($root . '/next-all.log', "00:22 tcl(312/312) r0\n");

        file_put_contents($root . '/next-focused.md', <<<MD
# SQLite Tcl Bounded Runner Evidence - libsqlite-release-gap-focused-json

- Repository HEAD: `{$nextHead}`
- Scratch: `/tmp/libsqlite-release-gap-focused-json`
- Log: `next-focused.log`
- SQLite git commit: `{$sqliteCommit}`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `{$uuid}`
- Testset: `veryquick`
- Jobs: `1`
- Timeout seconds: `900`
- Patterns: `json101.test` `json102.test`
- Exit: `0`
- Parsed summary: `0 errors out of 812 tests`
- Parsed errors: `0`
- Parsed tests: `812`
MD);
        file_put_contents($root . '/next-focused.log', "00:04 tcl(812/812) r0\n");

        file_put_contents($root . '/current-release.md', <<<MD
# SQLite Tcl Bounded Runner Evidence - libsqlite-current117-release

- Repository HEAD: `{$currentHead}`
- Scratch: `/tmp/libsqlite-current117-release`
- Log: `current-release.log`
- SQLite git commit: `{$sqliteCommit}`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `{$uuid}`
- Testset: `release`
- Jobs: `1`
- Timeout seconds: `7200`
- Patterns: none
- Exit: `0`
- Parsed summary: `0 errors out of 24000 tests`
- Parsed errors: `0`
- Parsed tests: `24000`
MD);
        file_put_contents($root . '/current-release.log', "28:00 tcl(24000/24000) r0\n");

        try {
            $record = $evidence->releaseGapBurnupRecord($root, $currentHead, $nextHead, 5);

            $t->same('release-gap-burnup-countable', $record['status']);
            $t->same(4, $record['artifact_count']);
            $t->same(3, $record['next_source_count']);
            $t->same(2, $record['next_source_release_count']);
            $t->same(1, $record['next_source_focused_count']);
            $t->same(1, $record['current_source_release_count']);
            $t->same(0, $record['blocked_count']);
            $t->same([], $record['blockers']);
            $t->same(['libsqlite-release-gap-all', 'libsqlite-release-gap-release'], $record['next_source_release_labels']);
            $t->same(['libsqlite-release-gap-focused-json'], $record['next_source_focused_labels']);
            $t->same(['libsqlite-current117-release'], $record['current_source_release_labels']);
            $t->same(26326, $record['release_tests_total']);
            $t->same(0, $record['release_errors_total']);
            $t->same(5, $record['current_release_gap']);
            $t->same(2, $record['release_gap_burned_down']);
            $t->same(3, $record['next_release_gap']);
            $t->same(true, $record['counts_next_source']);
            $t->same(true, $record['counts_release_gap_burnup']);
            $t->same(false, $record['counts_as_release_parity']);
            $t->contains('focused next-source artifacts remain focused evidence', $record['next_gate']);
            $t->contains('all/release testset classification', $record['dependency_closure']);
        } finally {
            foreach (glob($root . '/*') ?: [] as $file) {
                unlink($file);
            }
            @rmdir($root);
        }
    },
    'blocks current-source release-gap release gap burnup when artifacts are focused stale or missing logs' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $root = sys_get_temp_dir() . '/libsqlite-release-gap-release-gap-blocked-' . bin2hex(random_bytes(4));
        mkdir($root, 0777, true);

        $currentHead = '6b824ac24854056466145761d32a9f27720d286a';
        $nextHead = '8a447f445e5d2fd32fc9fd463117f585d1416551';
        $sqliteCommit = '8f70ec615f4cd247d36f92a22c99f65ebbcc22a7';
        $uuid = '9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353';

        file_put_contents($root . '/next-focused.md', <<<MD
# SQLite Tcl Bounded Runner Evidence - libsqlite-release-gap-focused-only

- Repository HEAD: `{$nextHead}`
- Scratch: `/tmp/libsqlite-release-gap-focused-only`
- Log: `next-focused.log`
- SQLite git commit: `{$sqliteCommit}`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `{$uuid}`
- Testset: `veryquick`
- Jobs: `1`
- Timeout seconds: `900`
- Patterns: `select1.test`
- Exit: `0`
- Parsed summary: `0 errors out of 91 tests`
- Parsed errors: `0`
- Parsed tests: `91`
MD);
        file_put_contents($root . '/next-focused.log', "00:01 tcl(91/91) r0\n");

        file_put_contents($root . '/stale-release.md', <<<MD
# SQLite Tcl Bounded Runner Evidence - libsqlite-stale117-release

- Repository HEAD: `aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa`
- Scratch: `/tmp/libsqlite-stale117-release`
- Log: `stale-release.log`
- SQLite git commit: `{$sqliteCommit}`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `{$uuid}`
- Testset: `release`
- Jobs: `1`
- Timeout seconds: `7200`
- Patterns: none
- Exit: `0`
- Parsed summary: `0 errors out of 25000 tests`
- Parsed errors: `0`
- Parsed tests: `25000`
MD);
        file_put_contents($root . '/stale-release.log', "29:00 tcl(25000/25000) r0\n");

        file_put_contents($root . '/missing-log-release.md', <<<MD
# SQLite Tcl Bounded Runner Evidence - libsqlite-release-gap-missing-log-release

- Repository HEAD: `{$nextHead}`
- Scratch: `/tmp/libsqlite-release-gap-missing-log-release`
- Log: `missing-release.log`
- SQLite git commit: `{$sqliteCommit}`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `wrong-manifest`
- Testset: `release`
- Jobs: `1`
- Timeout seconds: `7200`
- Patterns: none
- Exit: `0`
- Parsed summary: `0 errors out of 26014 tests`
- Parsed errors: `0`
- Parsed tests: `26014`
MD);

        try {
            $record = $evidence->releaseGapBurnupRecord($root, $currentHead, $nextHead, 4);

            $t->same('blocked-release-gap-burnup', $record['status']);
            $t->same(3, $record['artifact_count']);
            $t->same(1, $record['next_source_count']);
            $t->same(0, $record['next_source_release_count']);
            $t->same(1, $record['next_source_focused_count']);
            $t->same(['libsqlite-release-gap-focused-only'], $record['next_source_focused_labels']);
            $t->same(['libsqlite-release-gap-missing-log-release', 'libsqlite-stale117-release'], $record['blocked_labels']);
            $t->same(['libsqlite-release-gap-missing-log-release'], $record['missing_log_labels']);
            $t->same(3, $record['blocked_count']);
            $t->same([
                'next-source-release-artifact-missing',
                'missing-runner-log-artifacts-present',
                'blocked-runner-artifacts-present',
            ], array_column($record['blockers'], 'id'));
            $t->same(0, $record['release_tests_total']);
            $t->same(0, $record['release_gap_burned_down']);
            $t->same(4, $record['next_release_gap']);
            $t->same(false, $record['counts_next_source']);
            $t->same(false, $record['counts_release_gap_burnup']);
            $t->contains('focused and stale evidence routed elsewhere', $record['next_gate']);
        } finally {
            foreach (glob($root . '/*') ?: [] as $file) {
                unlink($file);
            }
            @rmdir($root);
        }
    },
    'admits current-source release-countability release countability rows with focused phpPass evidence' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $launcherBase = '6571c1279f77c2c00531492a7a2855a6f9e295a1';
        $dashboardSource = '8a447f445e5d2fd32fc9fd463117f585d1416551';
        $statusSource = '178c51ea36ed3508aafbb8913a32694e327e1da6';
        $implementationSource = '8a447f445e5d2fd32fc9fd463117f585d1416551';
        $nextSource = 'b121b121b121b121b121b121b121b121b121b121';
        $focusedOutput = implode("\n", [
            'Focused test run: 1 selected test files (root lock skipped)',
            'PASS release-countability release-countability admits one release artifact',
            'PASS release-countability release-countability admits all tier artifact',
            'PASS release-countability release-countability preserves existing release row',
            '1 test files, 64 assertions, 0 failures',
        ]);

        $rows = [
            [
                'unit' => 'release-countability-release-runner',
                'tier' => 'release',
                'current_countable' => false,
                'next_countable' => true,
                'launcher_base_head' => $launcherBase,
                'dashboard_source_head' => $dashboardSource,
                'status_source_head' => $statusSource,
                'implementation_source_head' => $implementationSource,
                'source_head' => $nextSource,
                'artifact_path' => 'lanes/libsqlite/notes/upstream-release-release-countability.md',
                'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error release',
                'scripts' => ['select1.test', 'wal.test', 'json101.test'],
                'exit' => 0,
                'errors' => 0,
                'tests' => 26014,
                'current_tests' => 25000,
                'next_tests' => 26014,
                'release_scope' => 'release-all',
                'counts_release_parity' => false,
            ],
            [
                'unit' => 'release-countability-all-runner',
                'tier' => 'all',
                'current_countable' => false,
                'next_countable' => true,
                'launcher_base_head' => $launcherBase,
                'dashboard_source_head' => $dashboardSource,
                'status_source_head' => $statusSource,
                'implementation_source_head' => $implementationSource,
                'source_head' => $nextSource,
                'artifact_path' => 'lanes/libsqlite/notes/upstream-all-release-countability.md',
                'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error all',
                'scripts' => ['btree01.test'],
                'exit' => 0,
                'errors' => 0,
                'tests' => 312,
                'current_tests' => 0,
                'next_tests' => 312,
                'release_scope' => 'release-all',
                'counts_release_parity' => false,
            ],
            [
                'unit' => 'current121-release-baseline',
                'tier' => 'release',
                'current_countable' => true,
                'next_countable' => true,
                'launcher_base_head' => $launcherBase,
                'dashboard_source_head' => $dashboardSource,
                'status_source_head' => $statusSource,
                'implementation_source_head' => $implementationSource,
                'source_head' => $nextSource,
                'artifact_path' => 'lanes/libsqlite/notes/upstream-release-current121.md',
                'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error release',
                'scripts' => ['pragma.test'],
                'exit' => 0,
                'errors' => 0,
                'tests' => 24000,
                'current_tests' => 24000,
                'next_tests' => 24000,
                'release_scope' => 'release-all',
                'counts_release_parity' => false,
            ],
        ];

        $record = $evidence->upstreamRunnerReleaseCountability(
            $rows,
            604,
            46412,
            $launcherBase,
            $dashboardSource,
            $statusSource,
            $implementationSource,
            $nextSource,
            'lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php',
            $focusedOutput,
            'release-countability suite countability avoids accepted release-gap release gap burnup and full-suite-countability full-suite countability surfaces',
            64,
            ''
        );

        $t->same('current-source-release-countability-advanced', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(604, $record['current_mapped']);
        $t->same(606, $record['next_mapped']);
        $t->same(2, $record['mapped_delta']);
        $t->same(46412, $record['current_php_pass']);
        $t->same(64, $record['php_pass_delta']);
        $t->same(46476, $record['next_php_pass']);
        $t->same('admitted', $record['php_pass_admission']['status']);
        $t->same(64, $record['php_pass_admission']['assertion_delta']);
        $t->same(3, $record['row_count']);
        $t->same(2, $record['admitted_count']);
        $t->same(1, $record['preserved_count']);
        $t->same(0, $record['blocked_count']);
        $t->same(['release-countability-all-runner', 'release-countability-release-runner'], $record['admitted_units']);
        $t->same(['current121-release-baseline'], $record['preserved_units']);
        $t->same([], $record['blockers']);
        $t->same(5, $record['release_script_count']);
        $t->same(['btree01.test', 'json101.test', 'pragma.test', 'select1.test', 'wal.test'], $record['release_scripts']);
        $t->same(1326, $record['release_tests_total_delta']);
        $t->same('clear', $record['active_runner_status']);
        $t->same(0, $record['active_runner_count']);
        $t->same(true, $record['counts_upstream_runner_release_countability']);
        $t->same(false, $record['counts_release_parity']);
        $t->contains('release/all countability row', $record['next_gate']);
        $t->contains('focused TestRunner PASS-line output', $record['dependency_closure']);
        $t->same('next-source-admitted', $record['entries'][0]['movement']);
        $t->same('next-source-admitted', $record['entries'][1]['movement']);
        $t->same('current-source-preserved', $record['entries'][2]['movement']);
    },
    'blocks current-source release-countability release countability for stale provenance and duplicate runners' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $launcherBase = '6571c1279f77c2c00531492a7a2855a6f9e295a1';
        $dashboardSource = '8a447f445e5d2fd32fc9fd463117f585d1416551';
        $statusSource = '178c51ea36ed3508aafbb8913a32694e327e1da6';
        $implementationSource = '8a447f445e5d2fd32fc9fd463117f585d1416551';
        $nextSource = 'b121b121b121b121b121b121b121b121b121b121';
        $focusedOutput = implode("\n", [
            'Focused test run: 1 selected test files (root lock skipped)',
            'PASS release-countability release-countability blocked stale row',
            '1 test files, 12 assertions, 0 failures',
        ]);
        $processSnapshot = '123 1 S 00:10 0.0 ./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error release';

        $record = $evidence->upstreamRunnerReleaseCountability(
            [
                [
                    'unit' => 'stale-release-countability-release-runner',
                    'tier' => 'veryquick',
                    'current_countable' => false,
                    'next_countable' => true,
                    'launcher_base_head' => $launcherBase,
                    'dashboard_source_head' => 'wrong-dashboard',
                    'status_source_head' => $statusSource,
                    'implementation_source_head' => $implementationSource,
                    'source_head' => 'stale-source',
                    'artifact_path' => '/tmp/upstream-release-release-countability.md',
                    'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl veryquick',
                    'scripts' => [],
                    'exit' => 0,
                    'errors' => 0,
                    'tests' => 91,
                    'release_scope' => 'focused-current-source',
                    'counts_release_parity' => true,
                ],
            ],
            604,
            46412,
            $launcherBase,
            $dashboardSource,
            $statusSource,
            $implementationSource,
            $nextSource,
            'lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php',
            $focusedOutput,
            'release-countability blocked case keeps stale focused or duplicate broad-runner evidence out of release countability',
            12,
            $processSnapshot
        );

        $t->same('blocked', $record['status']);
        $t->same(false, $record['countable']);
        $t->same(604, $record['next_mapped']);
        $t->same(0, $record['mapped_delta']);
        $t->same(0, $record['php_pass_delta']);
        $t->same(46412, $record['next_php_pass']);
        $t->same(1, $record['row_count']);
        $t->same(0, $record['admitted_count']);
        $t->same(0, $record['preserved_count']);
        $t->same(2, $record['blocked_count']);
        $t->same(['stale-source'], $record['artifact_source_heads']);
        $t->same('blocked', $record['entries'][0]['movement']);
        $t->same([
            'dashboard-source-head-mismatch',
            'next-source-head-mismatch',
            'release-tier-required',
            'artifact-path-not-lane-local',
            'guarded-runner-command-missing',
            'concrete-test-scripts-missing',
            'release-scope-not-release-all',
            'release-parity-claim-not-allowed',
        ], $record['entries'][0]['blocker_ids']);
        $t->same(['stale-release-countability-release-runner', 'duplicate-broad-runner-active'], array_column($record['blockers'], 'id'));
        $t->same('blocked-active-runner', $record['active_runner_status']);
        $t->same(1, $record['active_runner_count']);
        $t->same(false, $record['counts_upstream_runner_release_countability']);
        $t->contains('repair current-source release-countability provenance', $record['next_gate']);
    },
    'admits current-source next296 veryquick shard countability without release parity' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $launcherBase = '483323e72c0dc81d1e479309afb9cdc0cf8f649e';
        $dashboardSource = '9cbacde7b8c579a367d7e33fc5e5a0546a3a5d05';
        $statusSource = '9cbacde7b8c579a367d7e33fc5e5a0546a3a5d05';
        $implementationSource = '9cbacde7b8c579a367d7e33fc5e5a0546a3a5d05';
        $nextSource = '2962962962962962962962962962962962962962';
        $focusedOutput = implode("\n", [
            'Focused test run: 1 selected test files (root lock skipped)',
            'PASS next296 veryquick-shard admits offset shard audit',
            'PASS next296 veryquick-shard preserves zero-error guarded runner',
            'PASS next296 veryquick-shard blocks release parity claim',
            '1 test files, 96 assertions, 0 failures',
        ]);

        $record = $evidence->upstreamVeryquickShardCurrentSourceEvidence(
            296,
            [
                [
                    'unit' => 'suite-upstream-veryquick-shard-current-source-next296',
                    'tier' => 'veryquick',
                    'current_countable' => false,
                    'next_countable' => true,
                    'launcher_base_head' => $launcherBase,
                    'dashboard_source_head' => $dashboardSource,
                    'status_source_head' => $statusSource,
                    'implementation_source_head' => $implementationSource,
                    'source_head' => $nextSource,
                    'artifact_path' => 'lanes/libsqlite/notes/suite-upstream-veryquick-shard-current-source-next296.md',
                    'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick --shard 296/384',
                    'scripts' => ['select6.test', 'where.test', 'json103.test', 'wal2.test'],
                    'exit' => 0,
                    'errors' => 0,
                    'tests' => 96,
                    'current_tests' => 0,
                    'next_tests' => 96,
                    'release_scope' => 'focused-current-source',
                    'counts_release_parity' => false,
                ],
            ],
            683,
            137964,
            $launcherBase,
            $dashboardSource,
            $statusSource,
            $implementationSource,
            $nextSource,
            'lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php',
            $focusedOutput,
            'next296 veryquick shard avoids accepted suite277-suite279 rows, JSON-table compatibility repair, and all release/all parity surfaces',
            96,
            ''
        );

        $t->same('current-source-next296-veryquick-shard-advanced', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(683, $record['current_mapped']);
        $t->same(684, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(137964, $record['current_php_pass']);
        $t->same(96, $record['php_pass_delta']);
        $t->same(138060, $record['next_php_pass']);
        $t->same(1, $record['admitted_count']);
        $t->same(0, $record['blocked_count']);
        $t->same(['suite-upstream-veryquick-shard-current-source-next296'], $record['admitted_units']);
        $t->same(['json103.test', 'select6.test', 'wal2.test', 'where.test'], $record['target_scripts']);
        $t->same(96, $record['tests_total_delta']);
        $t->same(true, $record['counts_upstream_veryquick_shard_current_source_next296']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next279']);
        $t->same(false, $record['counts_upstream_runner_full_suite_countability']);
        $t->same(false, $record['counts_release_parity']);
        $t->contains('focused PASS-line movement', $record['next_gate']);
        $t->contains('no new support component needed', $record['dependency_closure']);
    },
    'prepares current-source next437-452 upstream suite evidence without mapped inflation' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $launcherBase = 'fca16e3d00000000000000000000000000000000';
        $integrationSource = '8a447f4400000000000000000000000000000000';
        $rows = [];
        foreach (range(437, 452) as $slice) {
            $rows[] = [
                'unit' => 'suite-upstream-veryquick-shard-current-source-next' . $slice,
                'tier' => 'veryquick',
                'current_countable' => false,
                'next_countable' => true,
                'launcher_base_head' => $launcherBase,
                'dashboard_source_head' => $integrationSource,
                'status_source_head' => $integrationSource,
                'implementation_source_head' => $integrationSource,
                'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next' . $slice . '.md',
                'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick suite' . $slice . '.test',
                'scripts' => ['suite' . $slice . '.test'],
                'exit' => 0,
                'errors' => 0,
                'tests' => 96,
                'current_tests' => 0,
                'next_tests' => 96,
                'release_scope' => 'focused-current-source',
                'counts_release_parity' => false,
            ];
        }
        $focusedOutput = implode("\n", array_merge(
            ['Focused test run: 1 selected test files (root lock skipped)'],
            array_map(
                static fn (int $i): string => sprintf('PASS next437-452 prepared suite evidence case %02d', $i),
                range(1, 44)
            ),
            ['1 test files, 44 assertions, 0 failures']
        ));

        $record = $evidence->upstreamVeryquickShardPreparedRange(
            $rows,
            437,
            452,
            683,
            137964,
            $launcherBase,
            $integrationSource,
            'lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php',
            $focusedOutput,
            'next437-452 upstream suite evidence follows merged next421-436 and excludes release/all parity claims',
            44,
            ''
        );

        $t->same('upstream-veryquick-shard-prepared-range-evidence-prepared', $record['status']);
        $t->same(683, $record['next_mapped']);
        $t->same(0, $record['mapped_delta']);
        $t->same(44, $record['php_pass_delta']);
        $t->same(16, $record['row_count']);
        $t->same(16, $record['zero_error_row_count']);
        $t->same(16, $record['lane_local_note_row_count']);
        $t->same(16, $record['slice_count']);
        $t->same([], $record['missing_slices']);
        $t->same('next437', $record['covered_slices'][0]);
        $t->same('next452', $record['covered_slices'][15]);
        $t->same('437-452', $record['prepared_range']);
        $t->same(false, $record['counts_upstream_veryquick_shard_prepared_range']);
        $t->same(false, $record['counts_release_parity']);
        $t->same('clear', $record['active_runner_status']);
        $t->contains('publish prepared upstream-suite evidence only', $record['next_gate']);
        $t->contains('prepared upstream veryquick evidence', $record['dependency_closure']);
    },
    'admits current-source next469-484 veryquick shard countability without cross-counting' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $launcherBase = 'fca16e3d00000000000000000000000000000000';
        $integrationSource = '8a447f4400000000000000000000000000000000';
        $nextSource = '4844844844844844844844844844844844844844';

        foreach (range(469, 484) as $slice) {
            $focusedOutput = implode("\n", [
                'Focused test run: 1 selected test files (root lock skipped)',
                'PASS next' . $slice . ' veryquick-shard admits current-source mapped coverage',
                'PASS next' . $slice . ' veryquick-shard keeps prior shards out of this count',
                '1 test files, 24 assertions, 0 failures',
            ]);

            $record = $evidence->upstreamVeryquickShardCurrentSourceShard(
                $slice,
                [
                    [
                        'unit' => 'suite-upstream-veryquick-shard-current-source-next' . $slice,
                        'tier' => 'veryquick',
                        'current_countable' => false,
                        'next_countable' => true,
                        'launcher_base_head' => $launcherBase,
                        'dashboard_source_head' => $integrationSource,
                        'status_source_head' => $integrationSource,
                        'implementation_source_head' => $integrationSource,
                        'source_head' => $nextSource,
                        'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next' . $slice . '.md',
                        'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick suite' . $slice . '.test',
                        'scripts' => ['suite' . $slice . '.test'],
                        'exit' => 0,
                        'errors' => 0,
                        'tests' => 24,
                        'current_tests' => 0,
                        'next_tests' => 24,
                        'release_scope' => 'focused-current-source',
                        'counts_release_parity' => false,
                    ],
                ],
                684,
                138060,
                $launcherBase,
                $integrationSource,
                $integrationSource,
                $integrationSource,
                $nextSource,
                'lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php',
                $focusedOutput,
                'next' . $slice . ' follows integrated next453-468 and excludes release/all parity claims',
                24,
                ''
            );

            $t->same('current-source-next' . $slice . '-veryquick-shard-advanced', $record['status']);
            $t->same(1, $record['mapped_delta']);
            $t->same(24, $record['php_pass_delta']);
            $t->same(1, $record['admitted_count']);
            $t->same(['suite-upstream-veryquick-shard-current-source-next' . $slice], $record['admitted_units']);
            $t->same(['suite' . $slice . '.test'], $record['target_scripts']);
            $t->same(true, $record['counts_upstream_veryquick_shard_current_source_next' . $slice]);
            $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next468']);
            foreach (range(469, 484) as $otherSlice) {
                if ($otherSlice === $slice) {
                    continue;
                }
                $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next' . $otherSlice]);
            }
            $t->same(false, $record['counts_upstream_runner_full_suite_countability']);
            $t->same(false, $record['counts_release_parity']);
            $t->contains('focused PASS-line movement', $record['next_gate']);
        }
    },
    'admits current-source next485-500 veryquick shard countability without cross-counting' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $launcherBase = 'fca16e3d00000000000000000000000000000000';
        $integrationSource = '8a447f4400000000000000000000000000000000';
        $nextSource = '5005005005005005005005005005005005005005';

        foreach (range(485, 500) as $slice) {
            $focusedOutput = implode("\n", [
                'Focused test run: 1 selected test files (root lock skipped)',
                'PASS next' . $slice . ' veryquick-shard admits current-source mapped coverage',
                'PASS next' . $slice . ' veryquick-shard keeps prior and sibling shards out of this count',
                '1 test files, 24 assertions, 0 failures',
            ]);

            $record = $evidence->upstreamVeryquickShardCurrentSourceShard(
                $slice,
                [
                    [
                        'unit' => 'suite-upstream-veryquick-shard-current-source-next' . $slice,
                        'tier' => 'veryquick',
                        'current_countable' => false,
                        'next_countable' => true,
                        'launcher_base_head' => $launcherBase,
                        'dashboard_source_head' => $integrationSource,
                        'status_source_head' => $integrationSource,
                        'implementation_source_head' => $integrationSource,
                        'source_head' => $nextSource,
                        'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next485-500.md',
                        'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick suite' . $slice . '.test',
                        'scripts' => ['suite' . $slice . '.test'],
                        'exit' => 0,
                        'errors' => 0,
                        'tests' => 24,
                        'current_tests' => 0,
                        'next_tests' => 24,
                        'release_scope' => 'focused-current-source',
                        'counts_release_parity' => false,
                    ],
                ],
                700,
                138444,
                $launcherBase,
                $integrationSource,
                $integrationSource,
                $integrationSource,
                $nextSource,
                'lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php',
                $focusedOutput,
                'next' . $slice . ' follows integrated next469-484 and excludes release/all parity claims',
                24,
                ''
            );

            $t->same('current-source-next' . $slice . '-veryquick-shard-advanced', $record['status']);
            $t->same(1, $record['mapped_delta']);
            $t->same(24, $record['php_pass_delta']);
            $t->same(1, $record['admitted_count']);
            $t->same(['suite-upstream-veryquick-shard-current-source-next' . $slice], $record['admitted_units']);
            $t->same(['suite' . $slice . '.test'], $record['target_scripts']);
            $t->same(true, $record['counts_upstream_veryquick_shard_current_source_next' . $slice]);
            $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next484']);
            foreach (range(485, 500) as $otherSlice) {
                if ($otherSlice === $slice) {
                    continue;
                }
                $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next' . $otherSlice]);
            }
            $t->same(false, $record['counts_upstream_runner_full_suite_countability']);
            $t->same(false, $record['counts_release_parity']);
            $t->contains('focused PASS-line movement', $record['next_gate']);
        }
    },
    'admits current-source next501-516 veryquick shard countability without cross-counting' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $launcherBase = 'fca16e3d00000000000000000000000000000000';
        $integrationSource = '8a447f4400000000000000000000000000000000';
        $nextSource = '5165165165165165165165165165165165165165';

        foreach (range(501, 516) as $slice) {
            $focusedOutput = implode("\n", [
                'Focused test run: 1 selected test files (root lock skipped)',
                'PASS next' . $slice . ' veryquick-shard admits current-source mapped coverage',
                'PASS next' . $slice . ' veryquick-shard keeps prior and sibling shards out of this count',
                '1 test files, 24 assertions, 0 failures',
            ]);

            $record = $evidence->upstreamVeryquickShardCurrentSourceShard(
                $slice,
                [
                    [
                        'unit' => 'suite-upstream-veryquick-shard-current-source-next' . $slice,
                        'tier' => 'veryquick',
                        'current_countable' => false,
                        'next_countable' => true,
                        'launcher_base_head' => $launcherBase,
                        'dashboard_source_head' => $integrationSource,
                        'status_source_head' => $integrationSource,
                        'implementation_source_head' => $integrationSource,
                        'source_head' => $nextSource,
                        'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next501-516.md',
                        'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick suite' . $slice . '.test',
                        'scripts' => ['suite' . $slice . '.test'],
                        'exit' => 0,
                        'errors' => 0,
                        'tests' => 24,
                        'current_tests' => 0,
                        'next_tests' => 24,
                        'release_scope' => 'focused-current-source',
                        'counts_release_parity' => false,
                    ],
                ],
                716,
                138828,
                $launcherBase,
                $integrationSource,
                $integrationSource,
                $integrationSource,
                $nextSource,
                'lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php',
                $focusedOutput,
                'next' . $slice . ' follows integrated next485-500 and excludes release/all parity claims',
                24,
                ''
            );

            $t->same('current-source-next' . $slice . '-veryquick-shard-advanced', $record['status']);
            $t->same(1, $record['mapped_delta']);
            $t->same(24, $record['php_pass_delta']);
            $t->same(1, $record['admitted_count']);
            $t->same(['suite-upstream-veryquick-shard-current-source-next' . $slice], $record['admitted_units']);
            $t->same(['suite' . $slice . '.test'], $record['target_scripts']);
            $t->same(true, $record['counts_upstream_veryquick_shard_current_source_next' . $slice]);
            $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next500']);
            foreach (range(501, 516) as $otherSlice) {
                if ($otherSlice === $slice) {
                    continue;
                }
                $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next' . $otherSlice]);
            }
            $t->same(false, $record['counts_upstream_runner_full_suite_countability']);
            $t->same(false, $record['counts_release_parity']);
            $t->contains('focused PASS-line movement', $record['next_gate']);
        }
    },
    'admits current-source next517-532 veryquick shard countability without cross-counting' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $launcherBase = 'fca16e3d00000000000000000000000000000000';
        $integrationSource = '8a447f4400000000000000000000000000000000';
        $nextSource = '5325325325325325325325325325325325325325';

        foreach (range(517, 532) as $slice) {
            $focusedOutput = implode("\n", [
                'Focused test run: 1 selected test files (root lock skipped)',
                'PASS next' . $slice . ' veryquick-shard admits current-source mapped coverage',
                'PASS next' . $slice . ' veryquick-shard keeps prior and sibling shards out of this count',
                '1 test files, 24 assertions, 0 failures',
            ]);

            $record = $evidence->upstreamVeryquickShardCurrentSourceShard(
                $slice,
                [
                    [
                        'unit' => 'suite-upstream-veryquick-shard-current-source-next' . $slice,
                        'tier' => 'veryquick',
                        'current_countable' => false,
                        'next_countable' => true,
                        'launcher_base_head' => $launcherBase,
                        'dashboard_source_head' => $integrationSource,
                        'status_source_head' => $integrationSource,
                        'implementation_source_head' => $integrationSource,
                        'source_head' => $nextSource,
                        'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next517-532.md',
                        'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick suite' . $slice . '.test',
                        'scripts' => ['suite' . $slice . '.test'],
                        'exit' => 0,
                        'errors' => 0,
                        'tests' => 24,
                        'current_tests' => 0,
                        'next_tests' => 24,
                        'release_scope' => 'focused-current-source',
                        'counts_release_parity' => false,
                    ],
                ],
                732,
                139212,
                $launcherBase,
                $integrationSource,
                $integrationSource,
                $integrationSource,
                $nextSource,
                'lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php',
                $focusedOutput,
                'next' . $slice . ' follows integrated next501-516 and excludes release/all parity claims',
                24,
                ''
            );

            $t->same('current-source-next' . $slice . '-veryquick-shard-advanced', $record['status']);
            $t->same(1, $record['mapped_delta']);
            $t->same(24, $record['php_pass_delta']);
            $t->same(1, $record['admitted_count']);
            $t->same(['suite-upstream-veryquick-shard-current-source-next' . $slice], $record['admitted_units']);
            $t->same(['suite' . $slice . '.test'], $record['target_scripts']);
            $t->same(true, $record['counts_upstream_veryquick_shard_current_source_next' . $slice]);
            $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next516']);
            foreach (range(517, 532) as $otherSlice) {
                if ($otherSlice === $slice) {
                    continue;
                }
                $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next' . $otherSlice]);
            }
            $t->same(false, $record['counts_upstream_runner_full_suite_countability']);
            $t->same(false, $record['counts_release_parity']);
            $t->contains('focused PASS-line movement', $record['next_gate']);
        }
    },
    'admits current-source next885-900 veryquick shard countability without cross-counting' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $launcherBase = 'fca16e3d00000000000000000000000000000000';
        $integrationSource = '8a447f4400000000000000000000000000000000';
        $nextSource = '9009009009009009009009009009009009009009';

        foreach (range(885, 900) as $slice) {
            $focusedOutput = implode("\n", array_merge(
                ['Focused test run: 1 selected test files (root lock skipped)'],
                array_map(
                    static fn (int $i): string => sprintf('PASS next%d veryquick-shard predecessor admission case %02d', $slice, $i),
                    range(1, 97)
                ),
                ['1 test files, 97 assertions, 0 failures']
            ));

            $record = $evidence->upstreamVeryquickShardCurrentSourceShard(
                $slice,
                [
                    [
                        'unit' => 'suite-upstream-veryquick-shard-current-source-next' . $slice,
                        'tier' => 'veryquick',
                        'current_countable' => false,
                        'next_countable' => true,
                        'launcher_base_head' => $launcherBase,
                        'dashboard_source_head' => $integrationSource,
                        'status_source_head' => $integrationSource,
                        'implementation_source_head' => $integrationSource,
                        'source_head' => $nextSource,
                        'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next885-900.md',
                        'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick suite' . $slice . '.test',
                        'scripts' => ['suite' . $slice . '.test'],
                        'exit' => 0,
                        'errors' => 0,
                        'tests' => 97,
                        'current_tests' => 0,
                        'next_tests' => 97,
                        'release_scope' => 'focused-current-source',
                        'counts_release_parity' => false,
                    ],
                ],
                1100,
                161184,
                $launcherBase,
                $integrationSource,
                $integrationSource,
                $integrationSource,
                $nextSource,
                'lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php',
                $focusedOutput,
                'next' . $slice . ' follows integrated next869-884 and excludes release/all parity claims',
                97,
                ''
            );

            $t->same('current-source-next' . $slice . '-veryquick-shard-advanced', $record['status']);
            $t->same(1, $record['mapped_delta']);
            $t->same(97, $record['php_pass_delta']);
            $t->same(1, $record['admitted_count']);
            $t->same(['suite-upstream-veryquick-shard-current-source-next' . $slice], $record['admitted_units']);
            $t->same(['suite' . $slice . '.test'], $record['target_scripts']);
            $t->same(true, $record['counts_upstream_veryquick_shard_current_source_next' . $slice]);
            $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next884']);
            foreach (range(885, 900) as $otherSlice) {
                if ($otherSlice === $slice) {
                    continue;
                }
                $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next' . $otherSlice]);
            }
            $t->same(false, $record['counts_upstream_runner_full_suite_countability']);
            $t->same(false, $record['counts_release_parity']);
            $t->contains('focused PASS-line movement', $record['next_gate']);
        }
    },
    'admits current-source next901-916 veryquick shard countability without cross-counting' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $launcherBase = 'fca16e3d00000000000000000000000000000000';
        $integrationSource = '8a447f4400000000000000000000000000000000';
        $nextSource = '9169169169169169169169169169169169169169';

        foreach (range(901, 916) as $slice) {
            $focusedOutput = implode("\n", array_merge(
                ['Focused test run: 1 selected test files (root lock skipped)'],
                array_map(
                    static fn (int $i): string => sprintf('PASS next%d veryquick-shard admission case %02d', $slice, $i),
                    range(1, 97)
                ),
                ['1 test files, 97 assertions, 0 failures']
            ));

            $record = $evidence->upstreamVeryquickShardCurrentSourceShard(
                $slice,
                [
                    [
                        'unit' => 'suite-upstream-veryquick-shard-current-source-next' . $slice,
                        'tier' => 'veryquick',
                        'current_countable' => false,
                        'next_countable' => true,
                        'launcher_base_head' => $launcherBase,
                        'dashboard_source_head' => $integrationSource,
                        'status_source_head' => $integrationSource,
                        'implementation_source_head' => $integrationSource,
                        'source_head' => $nextSource,
                        'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next901-916.md',
                        'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick suite' . $slice . '.test',
                        'scripts' => ['suite' . $slice . '.test'],
                        'exit' => 0,
                        'errors' => 0,
                        'tests' => 97,
                        'current_tests' => 0,
                        'next_tests' => 97,
                        'release_scope' => 'focused-current-source',
                        'counts_release_parity' => false,
                    ],
                ],
                1116,
                162736,
                $launcherBase,
                $integrationSource,
                $integrationSource,
                $integrationSource,
                $nextSource,
                'lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php',
                $focusedOutput,
                'next' . $slice . ' follows integrated next885-900 and excludes release/all parity claims',
                97,
                ''
            );

            $t->same('current-source-next' . $slice . '-veryquick-shard-advanced', $record['status']);
            $t->same(1, $record['mapped_delta']);
            $t->same(97, $record['php_pass_delta']);
            $t->same(1, $record['admitted_count']);
            $t->same(['suite-upstream-veryquick-shard-current-source-next' . $slice], $record['admitted_units']);
            $t->same(['suite' . $slice . '.test'], $record['target_scripts']);
            $t->same(true, $record['counts_upstream_veryquick_shard_current_source_next' . $slice]);
            $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next900']);
            foreach (range(901, 916) as $otherSlice) {
                if ($otherSlice === $slice) {
                    continue;
                }
                $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next' . $otherSlice]);
            }
            $t->same(false, $record['counts_upstream_runner_full_suite_countability']);
            $t->same(false, $record['counts_release_parity']);
            $t->contains('focused PASS-line movement', $record['next_gate']);
        }
    },
    'blocks current-source next296 veryquick shard for stale provenance or active broad runner' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $launcherBase = '483323e72c0dc81d1e479309afb9cdc0cf8f649e';
        $dashboardSource = '9cbacde7b8c579a367d7e33fc5e5a0546a3a5d05';
        $statusSource = '9cbacde7b8c579a367d7e33fc5e5a0546a3a5d05';
        $implementationSource = '9cbacde7b8c579a367d7e33fc5e5a0546a3a5d05';
        $nextSource = '2962962962962962962962962962962962962962';
        $focusedOutput = implode("\n", [
            'Focused test run: 1 selected test files (root lock skipped)',
            'PASS next296 veryquick-shard blocks stale row',
            '1 test files, 12 assertions, 0 failures',
        ]);
        $processSnapshot = '421 1 S 00:04 0.0 ./testfixture ../libsqlite/test/testrunner.tcl --jobs 4 --stop-on-error all';

        $record = $evidence->upstreamVeryquickShardCurrentSourceEvidence(
            296,
            [
                [
                    'unit' => 'stale-suite-upstream-veryquick-shard-current-source-next296',
                    'tier' => 'veryquick',
                    'current_countable' => false,
                    'next_countable' => true,
                    'launcher_base_head' => $launcherBase,
                    'dashboard_source_head' => 'wrong-dashboard-source',
                    'status_source_head' => $statusSource,
                    'implementation_source_head' => $implementationSource,
                    'source_head' => 'stale-next-source',
                    'artifact_path' => '/tmp/suite-upstream-veryquick-shard-current-source-next296.md',
                    'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 veryquick',
                    'scripts' => [],
                    'exit' => 0,
                    'errors' => 0,
                    'tests' => 96,
                    'current_tests' => 0,
                    'next_tests' => 96,
                    'release_scope' => 'focused-current-source',
                    'counts_release_parity' => true,
                ],
            ],
            683,
            137964,
            $launcherBase,
            $dashboardSource,
            $statusSource,
            $implementationSource,
            $nextSource,
            'lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php',
            $focusedOutput,
            'next296 blocked case keeps stale focused evidence and duplicate broad runners out of countability',
            12,
            $processSnapshot
        );

        $t->same('blocked', $record['status']);
        $t->same(false, $record['countable']);
        $t->same(683, $record['next_mapped']);
        $t->same(0, $record['mapped_delta']);
        $t->same(0, $record['php_pass_delta']);
        $t->same(1, $record['row_count']);
        $t->same(0, $record['admitted_count']);
        $t->same(2, $record['blocked_count']);
        $t->same(['stale-next-source'], $record['artifact_source_heads']);
        $t->contains('dashboard-source-head-mismatch', $record['blockers'][0]['evidence']);
        $t->contains('next-source-head-mismatch', $record['blockers'][0]['evidence']);
        $t->contains('guarded-runner-command-missing', $record['blockers'][0]['evidence']);
        $t->same('duplicate-broad-runner-active', $record['blockers'][1]['id']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next296']);
        $t->same(false, $record['counts_release_parity']);
        $t->same('blocked-active-runner', $record['active_runner_status']);
        $t->contains('repair current-source next296 provenance', $record['next_gate']);
    },
    'admits current accepted-head release artifact with focused php evidence' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $acceptedHead = '7ad388140bf69a4cadfdbd1593b7aa1657c4defe';
        $focusedOutput = implode("\n", array_merge(
            ['Focused test run: 1 selected test files (root lock skipped)'],
            array_map(
                static fn (int $i): string => sprintf('PASS current release admission accepted-head case %02d', $i),
                range(1, 58)
            ),
            ['1 test files, 58 assertions, 0 failures']
        ));

        $record = $evidence->releaseAdmissionCurrentRecord(
            [
                'release-all-current' => [
                    'status' => 'passed',
                    'label' => 'libsqlite-current-release',
                    'repository_head' => $acceptedHead,
                    'sqlite_commit' => '8f70ec615f4cd247d36f92a22c99f65ebbcc22a7',
                    'sqlite_version' => '3.54.0',
                    'sqlite_manifest_uuid' => '9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353',
                    'requested' => [
                        'testset' => 'release',
                        'patterns' => [],
                    ],
                    'results' => [
                        'exit' => 0,
                        'tests' => 329670,
                        'errors' => 0,
                    ],
                ],
                'focused-current' => [
                    'status' => 'passed',
                    'label' => 'libsqlite-current-focused-smoke',
                    'repository_head' => $acceptedHead,
                    'sqlite_commit' => '8f70ec615f4cd247d36f92a22c99f65ebbcc22a7',
                    'sqlite_version' => '3.54.0',
                    'sqlite_manifest_uuid' => '9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353',
                    'requested' => [
                        'testset' => 'veryquick',
                        'patterns' => ['select1.test'],
                    ],
                    'results' => [
                        'exit' => 0,
                        'tests' => 91,
                        'errors' => 0,
                    ],
                ],
            ],
            $acceptedHead,
            152903,
            'lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php',
            $focusedOutput,
            'current release admission avoids suite399-459 shard rows, numbered-source consolidation, ordinary behavior helpers, and stale release ledger surfaces',
            ''
        );

        $t->same('current-release-admission-countable', $record['status']);
        $t->same(true, $record['countable']);
        $t->same($acceptedHead, $record['accepted_repository_head']);
        $t->same(2, $record['artifact_count']);
        $t->same(1, $record['countable_release_artifacts']);
        $t->same(1, $record['focused_only_artifacts']);
        $t->same(0, $record['blocked_artifacts']);
        $t->same(['release-all-current'], $record['countable_labels']);
        $t->same(['focused-current'], $record['focused_only_labels']);
        $t->same([], $record['blocked_labels']);
        $t->same(329670, $record['tests_total']);
        $t->same(0, $record['errors_total']);
        $t->same(58, $record['php_pass_delta']);
        $t->same(152961, $record['next_php_pass']);
        $t->same('admitted', $record['php_pass_admission']['status']);
        $t->same(58, $record['php_pass_admission']['assertion_delta']);
        $t->same(1, $record['php_pass_admission']['selected_test_files']);
        $t->same(1, $record['php_pass_admission']['summary_test_files']);
        $t->same(0, $record['php_pass_admission']['failures']);
        $t->same(0, $record['blocker_count']);
        $t->same([], $record['blockers']);
        $t->same('zero-error-release-parity-countable', $record['ledger']['status']);
        $t->same(1, $record['ledger']['entry_count']);
        $t->same(1, $record['ledger']['zero_error_release_artifacts']);
        $t->same(0, $record['ledger']['blocked_admissions']);
        $t->same(true, $record['ledger']['release_blocker_closed']);
        $t->same(true, $record['ledger']['counts_as_zero_error_release_parity']);
        $t->same(329670, $record['ledger']['artifact_tests_total']);
        $t->same(0, $record['ledger']['artifact_errors_total']);
        $t->same(true, $record['counts_release_admission_current']);
        $t->same(true, $record['counts_release_parity']);
        $t->contains('current accepted-HEAD release/all zero-error artifact', $record['next_gate']);
        $t->contains('no new support component needed', $record['dependency_closure']);
        $t->contains('numbered-source consolidation', $record['non_overlap_note']);
    },
    'blocks current release admission for stale artifact duplicate runner and missing php evidence' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $acceptedHead = '7ad388140bf69a4cadfdbd1593b7aa1657c4defe';
        $focusedOutput = implode("\n", [
            'Focused test run: 1 selected test files (root lock skipped)',
            'PASS current release admission blocked stale artifact',
            '1 test files, 1 assertions, 1 failures',
        ]);

        $record = $evidence->releaseAdmissionCurrentRecord(
            [
                'stale-release' => [
                    'status' => 'passed',
                    'label' => 'libsqlite-stale-release',
                    'repository_head' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                    'sqlite_commit' => '8f70ec615f4cd247d36f92a22c99f65ebbcc22a7',
                    'sqlite_version' => '3.54.0',
                    'sqlite_manifest_uuid' => '9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353',
                    'requested' => [
                        'testset' => 'release',
                        'patterns' => [],
                    ],
                    'results' => [
                        'exit' => 0,
                        'tests' => 329670,
                        'errors' => 0,
                    ],
                ],
            ],
            $acceptedHead,
            152903,
            'lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php',
            $focusedOutput,
            'current release admission blocked case avoids accepted shard evidence and does not claim release/all parity',
            '987 1 S 00:08 0.0 ./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error release'
        );

        $t->same('blocked', $record['status']);
        $t->same(false, $record['countable']);
        $t->same($acceptedHead, $record['accepted_repository_head']);
        $t->same(1, $record['artifact_count']);
        $t->same(0, $record['countable_release_artifacts']);
        $t->same(0, $record['focused_only_artifacts']);
        $t->same(1, $record['blocked_artifacts']);
        $t->same([], $record['countable_labels']);
        $t->same([], $record['focused_only_labels']);
        $t->same(['stale-release'], $record['blocked_labels']);
        $t->same(0, $record['tests_total']);
        $t->same(0, $record['errors_total']);
        $t->same(0, $record['php_pass_delta']);
        $t->same(152903, $record['next_php_pass']);
        $t->same('blocked', $record['php_pass_admission']['status']);
        $t->same(0, $record['php_pass_admission']['assertion_delta']);
        $t->same(1, $record['php_pass_admission']['selected_test_files']);
        $t->same(1, $record['php_pass_admission']['summary_test_files']);
        $t->same(1, $record['php_pass_admission']['failures']);
        $t->same(3, $record['blocker_count']);
        $t->same([
            'active-runner-still-running',
            'current-zero-error-release-artifact-missing',
            'focused-php-pass-admission-blocked',
        ], array_column($record['blockers'], 'id'));
        $t->same(['release-countability', 'release-countability', 'php-pass'], array_column($record['blockers'], 'source'));
        $t->same('blocked', $record['ledger']['status']);
        $t->same(1, $record['ledger']['entry_count']);
        $t->same(0, $record['ledger']['zero_error_release_artifacts']);
        $t->same(1, $record['ledger']['blocked_admissions']);
        $t->same(false, $record['ledger']['release_blocker_closed']);
        $t->same(false, $record['ledger']['counts_as_zero_error_release_parity']);
        $t->same(0, $record['ledger']['artifact_tests_total']);
        $t->same(0, $record['ledger']['artifact_errors_total']);
        $t->same(false, $record['counts_release_admission_current']);
        $t->same(false, $record['counts_release_parity']);
        $t->contains('keep current release admission blocked', $record['next_gate']);
        $t->contains('focused TestRunner output contains failures', $record['blockers'][2]['evidence']);
        $t->contains('release/all parity', $record['non_overlap_note']);
    },
];
