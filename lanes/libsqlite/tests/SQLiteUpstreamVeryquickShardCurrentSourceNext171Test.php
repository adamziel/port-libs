<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

return [
    'admits current-source next171 veryquick shard artifacts with focused phpPass evidence' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $launcherBase = 'f3745a63d7b7cb9b6d6796aac42daddad197fce5';
        $dashboardSource = '8a447f445e5d2fd32fc9fd463117f585d1416551';
        $implementationSource = '8a447f445e5d2fd32fc9fd463117f585d1416551';
        $nextSource = '1711711711711711711711711711711711711711';
        $focusedOutput = implode("\n", [
            'Focused test run: 1 selected test files (root lock skipped)',
            'PASS next171 veryquick shard admits select/expr focused row',
            'PASS next171 veryquick shard admits pager focused row',
            'PASS next171 veryquick shard preserves current-source row',
            '1 test files, 76 assertions, 0 failures',
        ]);

        $rows = [
            [
                'unit' => 'next171-veryquick-select-expr',
                'tier' => 'veryquick',
                'current_countable' => false,
                'next_countable' => true,
                'launcher_base_head' => $launcherBase,
                'dashboard_source_head' => $dashboardSource,
                'implementation_source_head' => $implementationSource,
                'source_head' => $nextSource,
                'artifact_path' => 'lanes/libsqlite/notes/upstream-veryquick-next171-select-expr.md',
                'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick select1.test expr.test where.test',
                'scripts' => ['select1.test', 'expr.test', 'where.test'],
                'exit' => 0,
                'errors' => 0,
                'tests' => 918,
                'current_tests' => 0,
                'next_tests' => 918,
                'release_scope' => 'focused-current-source',
                'counts_release_parity' => false,
            ],
            [
                'unit' => 'next171-veryquick-pager-wal',
                'tier' => 'veryquick',
                'current_countable' => false,
                'next_countable' => true,
                'launcher_base_head' => $launcherBase,
                'dashboard_source_head' => $dashboardSource,
                'implementation_source_head' => $implementationSource,
                'source_head' => $nextSource,
                'artifact_path' => 'lanes/libsqlite/notes/upstream-veryquick-next171-pager-wal.md',
                'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick pager1.test wal.test savepoint.test',
                'scripts' => ['pager1.test', 'wal.test', 'savepoint.test'],
                'exit' => 0,
                'errors' => 0,
                'tests' => 1274,
                'current_tests' => 144,
                'next_tests' => 1274,
                'release_scope' => 'focused-current-source',
                'counts_release_parity' => false,
            ],
            [
                'unit' => 'current171-veryquick-json-baseline',
                'tier' => 'veryquick',
                'current_countable' => true,
                'next_countable' => true,
                'launcher_base_head' => $launcherBase,
                'dashboard_source_head' => $dashboardSource,
                'implementation_source_head' => $implementationSource,
                'source_head' => $nextSource,
                'artifact_path' => 'lanes/libsqlite/notes/upstream-veryquick-current171-json.md',
                'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick json101.test json102.test',
                'scripts' => ['json102.test', 'json101.test'],
                'exit' => 0,
                'errors' => 0,
                'tests' => 812,
                'current_tests' => 812,
                'next_tests' => 812,
                'release_scope' => 'focused-current-source',
                'counts_release_parity' => false,
            ],
        ];

        $record = $evidence->upstreamVeryquickShardFocusedAdmission(
            $rows,
            604,
            44622,
            $launcherBase,
            $dashboardSource,
            $implementationSource,
            $nextSource,
            'lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext171Test.php',
            $focusedOutput,
            'next171 veryquick shard avoids accepted next117 release gap burnup, next121 release countability, and current JSON/VFS/WAL behavior surfaces',
            76,
            ''
        );

        $t->same('current-source-next171-veryquick-shard-countable', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(604, $record['current_mapped']);
        $t->same(606, $record['next_mapped']);
        $t->same(2, $record['mapped_delta']);
        $t->same(44622, $record['current_php_pass']);
        $t->same(76, $record['php_pass_delta']);
        $t->same(44698, $record['next_php_pass']);
        $t->same('admitted', $record['php_pass_admission']['status']);
        $t->same(76, $record['php_pass_admission']['assertion_delta']);
        $t->same(3, $record['row_count']);
        $t->same(2, $record['admitted_count']);
        $t->same(1, $record['preserved_count']);
        $t->same(0, $record['blocked_count']);
        $t->same(['next171-veryquick-pager-wal', 'next171-veryquick-select-expr'], $record['admitted_units']);
        $t->same(['current171-veryquick-json-baseline'], $record['preserved_units']);
        $t->same([], $record['blockers']);
        $t->same(8, $record['script_count']);
        $t->same([
            'expr.test',
            'json101.test',
            'json102.test',
            'pager1.test',
            'savepoint.test',
            'select1.test',
            'wal.test',
            'where.test',
        ], $record['scripts']);
        $t->same([
            'lanes/libsqlite/notes/upstream-veryquick-current171-json.md',
            'lanes/libsqlite/notes/upstream-veryquick-next171-pager-wal.md',
            'lanes/libsqlite/notes/upstream-veryquick-next171-select-expr.md',
        ], $record['artifact_paths']);
        $t->same([$nextSource], $record['artifact_source_heads']);
        $t->same(3004, $record['veryquick_tests_total']);
        $t->same(2048, $record['veryquick_tests_delta']);
        $t->same(0, $record['veryquick_errors_total']);
        $t->same('clear', $record['active_runner_status']);
        $t->same(0, $record['active_runner_count']);
        $t->same(true, $record['counts_upstream_veryquick_shard_current_source_next171']);
        $t->same(false, $record['counts_release_parity']);
        $t->contains('focused upstream-runner countability', $record['next_gate']);
        $t->contains('focused TestRunner PASS-line output', $record['dependency_closure']);
        $t->contains('next117 release gap burnup', $record['non_overlap_note']);
        $t->same('next-source-admitted', $record['entries'][0]['movement']);
        $t->same('next-source-admitted', $record['entries'][1]['movement']);
        $t->same('current-source-preserved', $record['entries'][2]['movement']);
    },
    'blocks current-source next171 veryquick shards with stale provenance and broad-runner overlap' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $launcherBase = 'f3745a63d7b7cb9b6d6796aac42daddad197fce5';
        $dashboardSource = '8a447f445e5d2fd32fc9fd463117f585d1416551';
        $implementationSource = '8a447f445e5d2fd32fc9fd463117f585d1416551';
        $nextSource = '1711711711711711711711711711711711711711';
        $focusedOutput = implode("\n", [
            'Focused test run: 1 selected test files (root lock skipped)',
            'PASS next171 veryquick shard blocks stale row',
            '1 test files, 11 assertions, 0 failures',
        ]);
        $processSnapshot = '54321 1 S 00:10 0.0 ./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error all';

        $record = $evidence->upstreamVeryquickShardFocusedAdmission(
            [
                [
                    'unit' => 'stale-next171-release-claim',
                    'tier' => 'release',
                    'current_countable' => false,
                    'next_countable' => true,
                    'launcher_base_head' => 'wrong-base',
                    'dashboard_source_head' => $dashboardSource,
                    'implementation_source_head' => 'stale-implementation',
                    'source_head' => 'stale-source',
                    'artifact_path' => '/tmp/upstream-veryquick-next171.md',
                    'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl veryquick',
                    'scripts' => [],
                    'exit' => 0,
                    'errors' => 0,
                    'tests' => 91,
                    'release_scope' => 'release-all',
                    'counts_release_parity' => true,
                ],
                [
                    'unit' => 'failed-next171-veryquick',
                    'tier' => 'veryquick',
                    'current_countable' => false,
                    'next_countable' => true,
                    'launcher_base_head' => $launcherBase,
                    'dashboard_source_head' => $dashboardSource,
                    'implementation_source_head' => $implementationSource,
                    'source_head' => $nextSource,
                    'artifact_path' => 'lanes/libsqlite/notes/upstream-veryquick-next171-failed.md',
                    'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick select1.test',
                    'scripts' => ['select1.test'],
                    'exit' => 1,
                    'errors' => 1,
                    'tests' => 27,
                    'release_scope' => 'focused-current-source',
                    'counts_release_parity' => false,
                ],
            ],
            604,
            44622,
            $launcherBase,
            $dashboardSource,
            $implementationSource,
            $nextSource,
            'lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext171Test.php',
            $focusedOutput,
            'next171 blocked case keeps stale focused or duplicate broad-runner evidence out of countability',
            11,
            $processSnapshot
        );

        $t->same('blocked', $record['status']);
        $t->same(false, $record['countable']);
        $t->same(604, $record['next_mapped']);
        $t->same(0, $record['mapped_delta']);
        $t->same(0, $record['php_pass_delta']);
        $t->same(44622, $record['next_php_pass']);
        $t->same(2, $record['row_count']);
        $t->same(0, $record['admitted_count']);
        $t->same(0, $record['preserved_count']);
        $t->same(3, $record['blocked_count']);
        $t->same([$nextSource, 'stale-source'], $record['artifact_source_heads']);
        $t->same(['select1.test'], $record['scripts']);
        $t->same(118, $record['veryquick_tests_total']);
        $t->same(0, $record['veryquick_tests_delta']);
        $t->same(1, $record['veryquick_errors_total']);
        $t->same('blocked-active-runner', $record['active_runner_status']);
        $t->same(1, $record['active_runner_count']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next171']);
        $t->same('blocked', $record['entries'][0]['movement']);
        $t->same([
            'launcher-base-head-mismatch',
            'implementation-source-head-mismatch',
            'next-source-head-mismatch',
            'veryquick-tier-required',
            'artifact-path-not-lane-local',
            'guarded-veryquick-command-missing',
            'concrete-test-scripts-missing',
            'release-scope-must-stay-focused',
            'release-parity-claim-not-allowed',
        ], $record['entries'][0]['blocker_ids']);
        $t->same(['veryquick-artifact-not-zero-error'], $record['entries'][1]['blocker_ids']);
        $t->same(['stale-next171-release-claim', 'failed-next171-veryquick', 'duplicate-broad-runner-active'], array_column($record['blockers'], 'id'));
        $t->contains('repair current-source next171 veryquick shard provenance', $record['next_gate']);
    },
];
