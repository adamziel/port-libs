<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUpstreamSuiteEvidence
{
    /**
     * @param array<string, mixed> $manifest
     */
    public function __construct(private readonly array $manifest)
    {
    }

    public static function fromManifestPath(string $path): self
    {
        $json = file_get_contents($path);
        if ($json === false) {
            throw new \InvalidArgumentException("Unable to read upstream manifest: {$path}");
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException("Unable to decode upstream manifest JSON: {$path}");
        }

        return new self($decoded);
    }

    /**
     * @return array<string, mixed>
     */
    public function denominatorSummary(): array
    {
        $denominator = $this->manifest['benchmarkDenominator'] ?? null;
        if (!is_array($denominator)) {
            throw new \InvalidArgumentException('Manifest benchmarkDenominator must be an object');
        }

        $inventory = $denominator['inventory'] ?? [];
        if (!is_array($inventory)) {
            $inventory = [];
        }

        $runner = $denominator['runnerStatus'] ?? [];
        if (!is_array($runner)) {
            $runner = [];
        }

        $veryquick = $this->parseVeryquickResult($runner['results']['fullVeryquick'] ?? null);

        return [
            'status' => $denominator['status'] ?? 'unknown',
            'total' => (int) ($denominator['total'] ?? 0),
            'mapped' => (int) ($denominator['mapped'] ?? 0),
            'inventory_units' => [
                'testDirectoryTclTests' => (int) ($inventory['testDirectoryTclTests'] ?? 0),
                'extensionTclTests' => (int) ($inventory['extensionTclTests'] ?? 0),
                'extensionNestedTclTests' => (int) ($inventory['extensionNestedTclTests'] ?? 0),
                'testDirectoryTclHarnessFiles' => (int) ($inventory['testDirectoryTclHarnessFiles'] ?? 0),
                'testDirectoryCPrograms' => (int) ($inventory['testDirectoryCPrograms'] ?? 0),
                'srcTestCOrHeaderHelpers' => (int) ($inventory['srcTestCOrHeaderHelpers'] ?? 0),
                'mptestFiles' => (int) ($inventory['mptestFiles'] ?? 0),
                'toolTestPrograms' => (int) ($inventory['toolTestPrograms'] ?? 0),
                'toolTestishFiles' => (int) ($inventory['toolTestishFiles'] ?? 0),
            ],
            'veryquick' => [
                'executed' => (bool) ($runner['executed'] ?? false),
                'scripts' => $veryquick['scripts'],
                'tests' => $veryquick['tests'],
                'errors' => $veryquick['errors'],
            ],
            'warning' => $denominator['warning'] ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function runnerCoverageAudit(): array
    {
        $denominator = $this->manifest['benchmarkDenominator'] ?? [];
        $runner = is_array($denominator) && is_array($denominator['runnerStatus'] ?? null)
            ? $denominator['runnerStatus']
            : [];
        $inventory = is_array($denominator) && is_array($denominator['inventory'] ?? null)
            ? $denominator['inventory']
            : [];

        $commands = is_array($runner['commands'] ?? null) ? $runner['commands'] : [];
        $results = is_array($runner['results'] ?? null) ? $runner['results'] : [];
        $focusedResults = is_array($runner['focusedResults'] ?? null) ? $runner['focusedResults'] : [];
        $fullReleaseStatus = is_array($runner['fullReleaseStatus'] ?? null) ? $runner['fullReleaseStatus'] : [];

        $selectedScripts = [];
        $patternScripts = [];
        foreach ($commands as $command) {
            if (!is_string($command)) {
                continue;
            }

            foreach ($this->extractTestScriptTokens($command) as $script) {
                if (str_contains($script, '*')) {
                    $patternScripts[$script] = true;
                    continue;
                }

                $selectedScripts[$script] = true;
            }
        }

        ksort($selectedScripts);
        ksort($patternScripts);

        return [
            'executed' => (bool) ($runner['executed'] ?? false),
            'command_count' => count(array_filter($commands, 'is_string')),
            'result_count' => count($results),
            'focused_result_count' => count($focusedResults),
            'selected_script_count' => count($selectedScripts),
            'selected_scripts' => array_keys($selectedScripts),
            'pattern_script_count' => count($patternScripts),
            'pattern_scripts' => array_keys($patternScripts),
            'veryquick' => $this->parseVeryquickResult($results['fullVeryquick'] ?? null),
            'permutation_suites_declared' => (int) ($inventory['permutationSuitesDeclared'] ?? 0),
            'all_test_suite_runs' => (int) ($inventory['allTestSuiteRuns'] ?? 0),
            'full_release_executed' => (bool) ($fullReleaseStatus['executed'] ?? false),
            'full_release_reason' => is_string($fullReleaseStatus['reason'] ?? null) ? $fullReleaseStatus['reason'] : '',
            'remaining_suite_tiers' => $this->remainingSuiteTiers($fullReleaseStatus),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function focusedResultLedger(): array
    {
        $denominator = $this->manifest['benchmarkDenominator'] ?? [];
        $runner = is_array($denominator) && is_array($denominator['runnerStatus'] ?? null)
            ? $denominator['runnerStatus']
            : [];
        $focusedResults = is_array($runner['focusedResults'] ?? null) ? $runner['focusedResults'] : [];

        $entries = [];
        $passed = 0;
        $failed = 0;
        $reusedOrSkipped = 0;
        $resultTests = 0;
        $resultErrors = 0;
        $scriptSelections = [];

        foreach ($focusedResults as $name => $description) {
            if (!is_string($name) || !is_string($description)) {
                continue;
            }

            $scripts = $this->extractTestScriptTokens($description);
            foreach ($scripts as $script) {
                $scriptSelections[$script] = true;
            }

            $parsed = $this->parseFocusedResultDescription($description);
            $status = $parsed['errors'] > 0 ? 'failed' : ($parsed['tests'] > 0 ? 'passed' : 'not-counted');
            $usesCachedEvidence = $this->descriptionUsesCachedEvidence($description);
            if ($usesCachedEvidence) {
                $reusedOrSkipped++;
            }
            if ($status === 'passed') {
                $passed++;
            } elseif ($status === 'failed') {
                $failed++;
            }

            $resultTests += $parsed['tests'];
            $resultErrors += $parsed['errors'];
            $entries[$name] = [
                'status' => $status,
                'scripts' => $scripts,
                'script_count' => count($scripts),
                'result_tests' => $parsed['tests'],
                'result_errors' => $parsed['errors'],
                'uses_cached_or_missing_cache_evidence' => $usesCachedEvidence,
                'skip_reason' => $usesCachedEvidence
                    ? 'focused result reused accepted evidence or skipped fresh upstream execution because the isolated worktree lacked a hydrated upstream cache'
                    : null,
            ];
        }

        ksort($entries);
        ksort($scriptSelections);

        return [
            'entry_count' => count($entries),
            'passed_count' => $passed,
            'failed_count' => $failed,
            'not_counted_count' => count($entries) - $passed - $failed,
            'reused_or_skipped_count' => $reusedOrSkipped,
            'result_tests_total' => $resultTests,
            'result_errors_total' => $resultErrors,
            'unique_script_count' => count($scriptSelections),
            'unique_scripts' => array_keys($scriptSelections),
            'entries' => $entries,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recordedRunnerResultLedger(): array
    {
        $denominator = $this->manifest['benchmarkDenominator'] ?? [];
        $runner = is_array($denominator) && is_array($denominator['runnerStatus'] ?? null)
            ? $denominator['runnerStatus']
            : [];
        $results = is_array($runner['results'] ?? null) ? $runner['results'] : [];

        $entries = [];
        $passed = 0;
        $failed = 0;
        $notCounted = 0;
        $scriptsTotal = 0;
        $testsTotal = 0;
        $errorsTotal = 0;

        foreach ($results as $name => $description) {
            if (!is_string($name) || !is_string($description)) {
                continue;
            }

            $parsed = $this->parseVeryquickResult($description);
            $status = 'not-counted';
            if ($parsed['scripts'] > 0) {
                $status = $parsed['errors'] === 0 ? 'passed' : 'failed';
            }

            if ($status === 'passed') {
                $passed++;
            } elseif ($status === 'failed') {
                $failed++;
            } else {
                $notCounted++;
            }

            $scriptsTotal += $parsed['scripts'];
            $testsTotal += $parsed['tests'];
            $errorsTotal += $parsed['errors'];
            $entries[$name] = [
                'status' => $status,
                'scripts' => $parsed['scripts'],
                'tests' => $parsed['tests'],
                'errors' => $parsed['errors'],
                'selected_scripts' => $this->extractTestScriptTokens($description),
            ];
        }

        ksort($entries);

        return [
            'entry_count' => count($entries),
            'passed_count' => $passed,
            'failed_count' => $failed,
            'not_counted_count' => $notCounted,
            'scripts_total' => $scriptsTotal,
            'tests_total' => $testsTotal,
            'errors_total' => $errorsTotal,
            'entries' => $entries,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function upstreamSuiteAcceptanceChecklist(): array
    {
        $summary = $this->denominatorSummary();
        $coverage = $this->runnerCoverageAudit();
        $ledger = $this->focusedResultLedger();
        $runnerLedger = $this->recordedRunnerResultLedger();

        $inventoryUnits = 0;
        foreach ($summary['inventory_units'] as $count) {
            $inventoryUnits += (int) $count;
        }

        $ready = $summary['total'] > 0
            && $inventoryUnits > 0
            && $coverage['veryquick']['scripts'] > 0
            && $coverage['veryquick']['errors'] === 0
            && $ledger['failed_count'] === 0
            && $coverage['remaining_suite_tiers'] !== [];

        return [
            'status' => $ready ? 'bounded-upstream-suite-evidence-ready' : 'incomplete',
            'denominator_total' => $summary['total'],
            'denominator_mapped' => $summary['mapped'],
            'inventory_unit_total' => $inventoryUnits,
            'veryquick_zero_error' => $coverage['veryquick']['scripts'] > 0 && $coverage['veryquick']['errors'] === 0,
            'veryquick_scripts' => $coverage['veryquick']['scripts'],
            'veryquick_tests' => $coverage['veryquick']['tests'],
            'recorded_runner_entries' => $runnerLedger['entry_count'],
            'recorded_runner_passed' => $runnerLedger['passed_count'],
            'recorded_runner_failed' => $runnerLedger['failed_count'],
            'recorded_runner_tests' => $runnerLedger['tests_total'],
            'focused_entries' => $ledger['entry_count'],
            'focused_passed' => $ledger['passed_count'],
            'focused_failed' => $ledger['failed_count'],
            'focused_reused_or_skipped' => $ledger['reused_or_skipped_count'],
            'selected_script_count' => $coverage['selected_script_count'],
            'pattern_script_count' => $coverage['pattern_script_count'],
            'remaining_suite_tiers' => $coverage['remaining_suite_tiers'],
            'next_acceptance_gate' => $coverage['full_release_executed']
                ? 'refresh manifest/status from accepted full release runner evidence'
                : 'hydrate upstream cache and run release/all or a supervisor-approved bounded subset from this checklist',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function suiteClosureGapReport(): array
    {
        $summary = $this->denominatorSummary();
        $coverage = $this->runnerCoverageAudit();
        $ledger = $this->focusedResultLedger();
        $checklist = $this->upstreamSuiteAcceptanceChecklist();

        $blockers = [];
        if (!$coverage['full_release_executed']) {
            $blockers[] = [
                'id' => 'full-release-unexecuted',
                'severity' => 'blocking',
                'evidence' => $coverage['full_release_reason'],
                'next_gate' => 'hydrate upstream cache and run release/all permutations from the accepted SQLite checkout',
            ];
        }

        if ($coverage['remaining_suite_tiers'] !== []) {
            $blockers[] = [
                'id' => 'remaining-suite-tiers',
                'severity' => 'blocking',
                'evidence' => implode('; ', $coverage['remaining_suite_tiers']),
                'next_gate' => 'record each remaining tier as passed, failed, or explicitly supervisor-skipped with a fresh run record',
            ];
        }

        if ($ledger['reused_or_skipped_count'] > 0) {
            $blockers[] = [
                'id' => 'focused-results-reused-or-skipped',
                'severity' => 'evidence-gap',
                'evidence' => (string) $ledger['reused_or_skipped_count'] . ' focused entries reused accepted evidence or skipped fresh execution',
                'next_gate' => 'rerun the reused focused subsets when the hydrated cache is available and replace prose notes with parsed run records',
            ];
        }

        if ($coverage['pattern_script_count'] > 0) {
            $blockers[] = [
                'id' => 'wildcard-script-selections',
                'severity' => 'audit-gap',
                'evidence' => implode(', ', $coverage['pattern_scripts']),
                'next_gate' => 'expand wildcard selections to concrete hydrated .test filenames before counting exact focused coverage',
            ];
        }

        return [
            'status' => $blockers === [] ? 'closed' : 'open',
            'bounded_evidence_status' => $checklist['status'],
            'denominator_total' => $summary['total'],
            'denominator_mapped' => $summary['mapped'],
            'veryquick' => $coverage['veryquick'],
            'focused' => [
                'entries' => $ledger['entry_count'],
                'passed' => $ledger['passed_count'],
                'failed' => $ledger['failed_count'],
                'not_counted' => $ledger['not_counted_count'],
                'reused_or_skipped' => $ledger['reused_or_skipped_count'],
                'parsed_tests' => $ledger['result_tests_total'],
                'parsed_errors' => $ledger['result_errors_total'],
            ],
            'selected_script_count' => $coverage['selected_script_count'],
            'wildcard_pattern_count' => $coverage['pattern_script_count'],
            'remaining_suite_tiers' => $coverage['remaining_suite_tiers'],
            'blocker_count' => count($blockers),
            'blockers' => $blockers,
            'dependency_closure' => 'no new support component needed; report reuses lane-local manifest runner evidence only',
        ];
    }

    /**
     * @param array<string, list<string>> $focusedGroups
     * @return array<string, mixed>
     */
    public function upstreamSuiteExecutionPlan(array $focusedGroups = [], int $jobs = 1, ?string $repoRoot = null): array
    {
        if ($focusedGroups === []) {
            $focusedGroups = [
                'json-table-window' => ['json101.test', 'json102.test', 'json501.test', 'json107.test', 'jsonb01.test'],
                'wal-rollback-savepoint' => ['wal*.test', 'pager*.test', 'journal*.test', 'savepoint*.test'],
                'btree-delete-rebalance' => ['delete.test', 'delete2.test', 'delete3.test', 'delete4.test', 'btree01.test'],
                'encoding-collation' => ['enc.test', 'enc2.test', 'collate1.test', 'collate2.test', 'like.test'],
            ];
        }

        $summary = $this->denominatorSummary();
        $coverage = $this->runnerCoverageAudit();
        $ledger = $this->focusedResultLedger();
        $focusedMatrix = $this->focusedSubsetMatrix($focusedGroups, $jobs, $repoRoot);

        $focusedReady = 0;
        $focusedSkipped = 0;
        $focusedScripts = 0;
        foreach ($focusedMatrix as $plan) {
            $focusedScripts += (int) $plan['script_count'];
            if (($plan['runnable'] ?? false) === true) {
                $focusedReady++;
            } else {
                $focusedSkipped++;
            }
        }

        $fullReleaseCommand = 'cd .upstream-cache/libsqlite-build-port-libsqlite'
            . ' && ./testfixture ../libsqlite/test/testrunner.tcl --jobs ' . $jobs . ' --stop-on-error all';

        $steps = [
            [
                'id' => 'accepted-veryquick-baseline',
                'status' => $coverage['veryquick']['errors'] === 0 && $coverage['veryquick']['scripts'] > 0 ? 'accepted' : 'missing',
                'evidence' => $coverage['veryquick'],
                'command' => null,
            ],
            [
                'id' => 'rerun-focused-closure-subsets',
                'status' => $focusedSkipped === 0 ? 'ready' : 'blocked-missing-cache',
                'evidence' => [
                    'group_count' => count($focusedMatrix),
                    'ready_groups' => $focusedReady,
                    'skipped_groups' => $focusedSkipped,
                    'script_count' => $focusedScripts,
                    'previous_focused_entries' => $ledger['entry_count'],
                    'previous_focused_reused_or_skipped' => $ledger['reused_or_skipped_count'],
                ],
                'matrix' => $focusedMatrix,
            ],
            [
                'id' => 'expand-wildcard-selections',
                'status' => $coverage['pattern_script_count'] === 0 ? 'complete' : 'blocked-needs-hydrated-test-dir',
                'evidence' => [
                    'pattern_count' => $coverage['pattern_script_count'],
                    'patterns' => $coverage['pattern_scripts'],
                ],
                'command' => 'find .upstream-cache/libsqlite/test -maxdepth 1 -name "*.test" | sort',
            ],
            [
                'id' => 'run-full-release-all',
                'status' => $coverage['full_release_executed'] ? 'accepted' : 'blocked-not-run',
                'evidence' => [
                    'remaining_suite_tiers' => $coverage['remaining_suite_tiers'],
                    'reason' => $coverage['full_release_reason'],
                ],
                'command' => $fullReleaseCommand,
            ],
        ];

        return [
            'status' => $focusedSkipped === 0 && $coverage['full_release_executed'] ? 'ready-for-full-closure-review' : 'blocked-on-upstream-cache-or-full-suite',
            'denominator_total' => $summary['total'],
            'denominator_mapped' => $summary['mapped'],
            'jobs' => $jobs,
            'steps' => $steps,
            'next_command' => $focusedSkipped > 0
                ? 'hydrate .upstream-cache/libsqlite and build .upstream-cache/libsqlite-build-port-libsqlite/testfixture'
                : $fullReleaseCommand,
            'dependency_closure' => 'no new support component needed; execution plan reuses lane-local manifest evidence and SQLite testfixture command planning',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function releaseTierMatrix(int $jobs = 1, ?string $repoRoot = null): array
    {
        if ($jobs < 1) {
            throw new \InvalidArgumentException('SQLite release tier jobs must be at least 1');
        }

        $denominator = $this->manifest['benchmarkDenominator'] ?? [];
        $runner = is_array($denominator) && is_array($denominator['runnerStatus'] ?? null)
            ? $denominator['runnerStatus']
            : [];
        $buildDirectory = is_string($runner['buildDirectory'] ?? null)
            ? $runner['buildDirectory']
            : '.upstream-cache/libsqlite-build-port-libsqlite';
        $fullReleaseStatus = is_array($runner['fullReleaseStatus'] ?? null) ? $runner['fullReleaseStatus'] : [];
        $inventory = is_array($denominator) && is_array($denominator['inventory'] ?? null)
            ? $denominator['inventory']
            : [];

        $root = $repoRoot ?? dirname(__DIR__, 3);
        $absoluteBuildDirectory = $root . '/' . $buildDirectory;
        $testfixture = $absoluteBuildDirectory . '/testfixture';
        $testrunner = $root . '/.upstream-cache/libsqlite/test/testrunner.tcl';
        $makefile = $absoluteBuildDirectory . '/Makefile';
        $mptest = $root . '/.upstream-cache/libsqlite/mptest';

        $missingTestfixture = $this->missingRunnerInputs($absoluteBuildDirectory, $testfixture, $testrunner, $buildDirectory);
        $makeReady = is_dir($absoluteBuildDirectory) && is_file($makefile);
        $mptestReady = is_dir($mptest);

        $tiers = [
            [
                'id' => 'release-all',
                'label' => 'full release/all permutations',
                'status' => ($fullReleaseStatus['executed'] ?? false) === true ? 'accepted' : ($missingTestfixture === [] ? 'ready' : 'blocked-missing-cache'),
                'command' => 'cd ' . $buildDirectory . ' && ./testfixture ../libsqlite/test/testrunner.tcl --jobs ' . $jobs . ' --stop-on-error all',
                'runnable' => $missingTestfixture === [],
                'missing' => $missingTestfixture,
                'inventory_units' => (int) ($inventory['allTestSuiteRuns'] ?? 0),
            ],
            [
                'id' => 'permutation-suites',
                'label' => 'declared permutation suites',
                'status' => $missingTestfixture === [] ? 'blocked-needs-suite-map' : 'blocked-missing-cache',
                'command' => null,
                'runnable' => false,
                'missing' => $missingTestfixture === [] ? ['concrete permutation suite command map'] : $missingTestfixture,
                'inventory_units' => (int) ($inventory['permutationSuitesDeclared'] ?? 0),
            ],
            [
                'id' => 'make-test',
                'label' => 'multi-configuration make test suites',
                'status' => $makeReady ? 'ready' : 'blocked-missing-build',
                'command' => 'make -C ' . $buildDirectory . ' test',
                'runnable' => $makeReady,
                'missing' => $makeReady ? [] : [$buildDirectory . '/Makefile'],
                'inventory_units' => (int) ($inventory['testDirectoryTclHarnessFiles'] ?? 0) + (int) ($inventory['srcTestCOrHeaderHelpers'] ?? 0),
            ],
            [
                'id' => 'mptest',
                'label' => 'long-running stress/permutation tiers beyond veryquick',
                'status' => $mptestReady && $makeReady ? 'ready' : 'blocked-missing-build',
                'command' => 'make -C ' . $buildDirectory . ' mptest',
                'runnable' => $mptestReady && $makeReady,
                'missing' => array_values(array_filter([
                    $mptestReady ? null : '.upstream-cache/libsqlite/mptest',
                    $makeReady ? null : $buildDirectory . '/Makefile',
                ])),
                'inventory_units' => (int) ($inventory['mptestFiles'] ?? 0),
            ],
        ];

        $ready = 0;
        $accepted = 0;
        foreach ($tiers as $tier) {
            if ($tier['status'] === 'ready') {
                $ready++;
            } elseif ($tier['status'] === 'accepted') {
                $accepted++;
            }
        }

        return [
            'status' => $ready + $accepted === count($tiers) ? 'ready' : 'blocked',
            'jobs' => $jobs,
            'tier_count' => count($tiers),
            'ready_tiers' => $ready,
            'accepted_tiers' => $accepted,
            'blocked_tiers' => count($tiers) - $ready - $accepted,
            'full_release_reason' => is_string($fullReleaseStatus['reason'] ?? null) ? $fullReleaseStatus['reason'] : '',
            'tiers' => $tiers,
            'next_gate' => $ready + $accepted === count($tiers)
                ? 'run the release tiers and replace blocked status with parsed pass/fail records'
                : 'hydrate .upstream-cache/libsqlite, configure/build testfixture, and keep tier commands explicit before counting release/all closure',
            'dependency_closure' => 'no new support component needed; release-tier matrix reuses lane-local manifest inventory and SQLite testfixture/make commands',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function permutationSuiteMap(?string $repoRoot = null): array
    {
        $denominator = $this->manifest['benchmarkDenominator'] ?? [];
        $inventory = is_array($denominator) && is_array($denominator['inventory'] ?? null)
            ? $denominator['inventory']
            : [];
        $declared = (int) ($inventory['permutationSuitesDeclared'] ?? 0);
        $root = $repoRoot ?? dirname(__DIR__, 3);
        $relativeSource = '.upstream-cache/libsqlite/test/permutations.test';
        $source = $root . '/' . $relativeSource;

        if (!is_file($source)) {
            return [
                'status' => 'blocked-missing-permutation-source',
                'declared_suite_count' => $declared,
                'mapped_suite_count' => 0,
                'unmapped_suite_count' => $declared,
                'source' => $relativeSource,
                'source_ready' => false,
                'suites' => [],
                'next_gate' => 'hydrate .upstream-cache/libsqlite/test/permutations.test, then parse concrete permutation suite names and commands before counting release/all closure',
                'dependency_closure' => 'no new support component needed; permutation map uses only the lane-local manifest and hydrated SQLite test harness sources',
            ];
        }

        $text = file_get_contents($source);
        if ($text === false) {
            throw new \RuntimeException("Unable to read SQLite permutation source: {$source}");
        }

        $suites = $this->extractPermutationSuiteNames($text);
        $mapped = count($suites);

        return [
            'status' => $mapped >= $declared && $declared > 0 ? 'ready' : 'partial',
            'declared_suite_count' => $declared,
            'mapped_suite_count' => $mapped,
            'unmapped_suite_count' => max(0, $declared - $mapped),
            'source' => $relativeSource,
            'source_ready' => true,
            'suites' => $suites,
            'next_gate' => $mapped >= $declared && $declared > 0
                ? 'turn parsed permutation suite names into explicit testfixture run records with pass/fail counts'
                : 'complete the permutation parser against the hydrated upstream source before counting all declared suites',
            'dependency_closure' => 'no new support component needed; permutation map uses only the lane-local manifest and hydrated SQLite test harness sources',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fullSuiteReadinessRecord(int $jobs = 1, ?string $repoRoot = null): array
    {
        if ($jobs < 1) {
            throw new \InvalidArgumentException('SQLite full-suite readiness jobs must be at least 1');
        }

        $summary = $this->denominatorSummary();
        $coverage = $this->runnerCoverageAudit();
        $ledger = $this->focusedResultLedger();
        $closure = $this->suiteClosureGapReport();
        $release = $this->releaseTierMatrix($jobs, $repoRoot);
        $permutations = $this->permutationSuiteMap($repoRoot);
        $wildcards = $this->wildcardExpansionPlan($repoRoot);

        $accepted = [];
        if ($coverage['veryquick']['scripts'] > 0 && $coverage['veryquick']['errors'] === 0) {
            $accepted[] = [
                'id' => 'veryquick',
                'status' => 'accepted',
                'scripts' => $coverage['veryquick']['scripts'],
                'tests' => $coverage['veryquick']['tests'],
                'errors' => $coverage['veryquick']['errors'],
            ];
        }

        $ready = [];
        foreach ($release['tiers'] as $tier) {
            if (is_array($tier) && ($tier['status'] ?? null) === 'ready') {
                $ready[] = [
                    'id' => $tier['id'],
                    'command' => $tier['command'],
                    'inventory_units' => $tier['inventory_units'],
                ];
            }
        }

        if (($wildcards['status'] ?? null) === 'ready') {
            $ready[] = [
                'id' => 'wildcard-expansion',
                'command' => 'replace wildcard .test selections with concrete expanded script lists',
                'inventory_units' => $wildcards['expanded_script_count'],
            ];
        }

        if (($permutations['status'] ?? null) === 'ready') {
            $ready[] = [
                'id' => 'permutation-suite-map',
                'command' => 'turn parsed permutation suite names into explicit testfixture run records',
                'inventory_units' => $permutations['mapped_suite_count'],
            ];
        }

        $blocked = [];
        foreach ($release['tiers'] as $tier) {
            if (!is_array($tier) || in_array($tier['status'] ?? null, ['ready', 'accepted'], true)) {
                continue;
            }
            if (($tier['id'] ?? null) === 'permutation-suites' && ($permutations['status'] ?? null) === 'ready') {
                continue;
            }

            $blocked[] = [
                'id' => $tier['id'],
                'status' => $tier['status'],
                'missing' => $tier['missing'],
                'next_gate' => $release['next_gate'],
            ];
        }

        if (($wildcards['status'] ?? null) !== 'ready' && ($wildcards['status'] ?? null) !== 'complete-no-wildcards') {
            $blocked[] = [
                'id' => 'wildcard-expansion',
                'status' => $wildcards['status'],
                'missing' => $wildcards['missing_patterns'],
                'next_gate' => $wildcards['next_gate'],
            ];
        }

        if (($permutations['status'] ?? null) !== 'ready') {
            $blocked[] = [
                'id' => 'permutation-suite-map',
                'status' => $permutations['status'],
                'missing' => $permutations['source_ready'] ? ['unmapped permutation suites'] : [$permutations['source']],
                'next_gate' => $permutations['next_gate'],
            ];
        }

        return [
            'status' => $blocked === [] ? 'ready-for-full-suite-runs' : 'blocked',
            'denominator_total' => $summary['total'],
            'denominator_mapped' => $summary['mapped'],
            'jobs' => $jobs,
            'accepted_count' => count($accepted),
            'ready_count' => count($ready),
            'blocked_count' => count($blocked),
            'focused_ledger' => [
                'entries' => $ledger['entry_count'],
                'passed' => $ledger['passed_count'],
                'failed' => $ledger['failed_count'],
                'reused_or_skipped' => $ledger['reused_or_skipped_count'],
            ],
            'accepted' => $accepted,
            'ready' => $ready,
            'blocked' => $blocked,
            'closure_blocker_ids' => array_column($closure['blockers'], 'id'),
            'next_command' => $blocked === []
                ? 'run the ready release tiers and record parsed pass/fail counts'
                : 'hydrate .upstream-cache/libsqlite plus configured testfixture/build outputs, then rerun this readiness record',
            'dependency_closure' => 'no new support component needed; readiness record composes lane-local runner evidence and hydrated SQLite test harness gates only',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fullSuiteCommandManifest(int $jobs = 1, ?string $repoRoot = null): array
    {
        if ($jobs < 1) {
            throw new \InvalidArgumentException('SQLite full-suite command manifest jobs must be at least 1');
        }

        $readiness = $this->fullSuiteReadinessRecord($jobs, $repoRoot);
        $release = $this->releaseTierMatrix($jobs, $repoRoot);
        $wildcards = $this->wildcardExpansionPlan($repoRoot);
        $permutations = $this->permutationSuiteMap($repoRoot);

        $commands = [];
        foreach ($release['tiers'] as $tier) {
            if (!is_array($tier)) {
                continue;
            }

            $commands[] = [
                'id' => $tier['id'],
                'kind' => 'upstream-runner',
                'command' => $tier['command'],
                'runnable' => (bool) ($tier['runnable'] ?? false),
                'status' => $tier['status'] ?? 'unknown',
                'missing' => is_array($tier['missing'] ?? null) ? $tier['missing'] : [],
                'inventory_units' => (int) ($tier['inventory_units'] ?? 0),
                'evidence_source' => 'release-tier-matrix',
            ];
        }

        $commands[] = [
            'id' => 'wildcard-expansion',
            'kind' => 'manifest-normalization',
            'command' => 'find .upstream-cache/libsqlite/test -maxdepth 1 -name "*.test" | sort',
            'runnable' => ($wildcards['status'] ?? null) === 'ready',
            'status' => $wildcards['status'] ?? 'unknown',
            'missing' => ($wildcards['status'] ?? null) === 'ready' ? [] : ($wildcards['missing_patterns'] ?? []),
            'inventory_units' => (int) ($wildcards['expanded_script_count'] ?? 0),
            'evidence_source' => 'wildcard-expansion-plan',
        ];

        $commands[] = [
            'id' => 'permutation-suite-map',
            'kind' => 'manifest-normalization',
            'command' => 'parse .upstream-cache/libsqlite/test/permutations.test into concrete suite run records',
            'runnable' => ($permutations['status'] ?? null) === 'ready',
            'status' => $permutations['status'] ?? 'unknown',
            'missing' => ($permutations['status'] ?? null) === 'ready'
                ? []
                : ($permutations['source_ready'] ?? false ? ['unmapped permutation suites'] : [$permutations['source'] ?? '.upstream-cache/libsqlite/test/permutations.test']),
            'inventory_units' => (int) ($permutations['mapped_suite_count'] ?? 0),
            'evidence_source' => 'permutation-suite-map',
        ];

        $runnable = 0;
        $blocked = 0;
        foreach ($commands as $command) {
            if (($command['runnable'] ?? false) === true) {
                $runnable++;
            } else {
                $blocked++;
            }
        }

        return [
            'status' => $blocked === 0 ? 'ready' : 'blocked',
            'jobs' => $jobs,
            'accepted_baseline' => $readiness['accepted'],
            'command_count' => count($commands),
            'runnable_command_count' => $runnable,
            'blocked_command_count' => $blocked,
            'commands' => $commands,
            'next_gate' => $blocked === 0
                ? 'run each command and replace readiness-only records with parsed pass/fail evidence'
                : $readiness['next_command'],
            'dependency_closure' => 'no new support component needed; command manifest composes lane-local runner evidence, release tiers, wildcard expansion, and permutation-suite gates',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadSuiteLaunchGate(
        string $processSnapshot,
        bool $supervisorApproved = false,
        int $jobs = 1,
        ?string $repoRoot = null
    ): array {
        if ($jobs < 1) {
            throw new \InvalidArgumentException('SQLite broad suite launch gate jobs must be at least 1');
        }

        $commands = $this->fullSuiteCommandManifest($jobs, $repoRoot);
        $activeGate = $this->activeFullSuiteRunnerGate($processSnapshot);
        $blockers = [];

        if (!$supervisorApproved) {
            $blockers[] = [
                'id' => 'supervisor-approval-required',
                'evidence' => 'broad all/release/mptest runs require explicit supervisor approval in isolated upstream-suite slices',
            ];
        }

        if (($activeGate['status'] ?? null) === 'blocked-active-runner') {
            $blockers[] = [
                'id' => 'active-runner-still-running',
                'evidence' => 'supplied process snapshot already contains a broad SQLite runner',
                'active_tiers' => $activeGate['active_tiers'] ?? [],
                'active_count' => $activeGate['active_count'] ?? 0,
            ];
        }

        if (($commands['status'] ?? null) !== 'ready') {
            $blockedCommands = [];
            foreach ($commands['commands'] ?? [] as $command) {
                if (!is_array($command) || ($command['runnable'] ?? false) === true) {
                    continue;
                }

                $blockedCommands[] = [
                    'id' => $command['id'] ?? 'unknown',
                    'status' => $command['status'] ?? 'unknown',
                    'missing' => is_array($command['missing'] ?? null) ? $command['missing'] : [],
                ];
            }

            $blockers[] = [
                'id' => 'command-manifest-not-ready',
                'evidence' => 'full-suite command manifest still has blocked release/permutation/make/wildcard gates',
                'blocked_command_count' => $commands['blocked_command_count'] ?? count($blockedCommands),
                'blocked_commands' => $blockedCommands,
            ];
        }

        $launchAllowed = $blockers === [];

        return [
            'status' => $launchAllowed ? 'launch-allowed' : 'blocked',
            'launch_allowed' => $launchAllowed,
            'supervisor_approved' => $supervisorApproved,
            'jobs' => $jobs,
            'active_gate' => $activeGate,
            'command_manifest_status' => $commands['status'] ?? 'unknown',
            'command_count' => $commands['command_count'] ?? 0,
            'runnable_command_count' => $commands['runnable_command_count'] ?? 0,
            'blocked_command_count' => $commands['blocked_command_count'] ?? 0,
            'blocker_count' => count($blockers),
            'blockers' => $blockers,
            'next_command' => $launchAllowed ? ($commands['commands'][0]['command'] ?? null) : null,
            'next_gate' => $launchAllowed
                ? 'launch one guarded broad SQLite suite runner, then count the artifact only through bounded provenance gates'
                : 'do not launch a broad SQLite suite until supervisor approval, duplicate-runner, and command-manifest gates are all clear',
            'dependency_closure' => 'no new support component needed; broad launch gate composes lane-local command readiness and supplied active-runner snapshots only',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function activeFullSuiteRunnerGate(string $processSnapshot): array
    {
        $active = [];
        foreach (preg_split('/\R/', $processSnapshot) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_contains($line, 'grep -E') || preg_match('/(?:^|\s)pgrep(?:\s|$)/', $line) === 1) {
                continue;
            }

            $isSqliteRunner = str_contains($line, 'testfixture')
                && str_contains($line, 'testrunner.tcl');
            $isBoundedWrapper = str_contains($line, 'run-sqlite-tcl-bounded-runner.sh');
            $isMakeSuite = preg_match('/(?:^|\s)make(?:\s+-C\s+\S+)?\s+(test|mptest)(?:\s|$)/', $line) === 1;
            if (!$isSqliteRunner && !$isBoundedWrapper && !$isMakeSuite) {
                continue;
            }

            $tier = null;
            if (preg_match('/(?:^|\s)(all|release|mptest)(?:\s|$)/', $line, $matches) === 1) {
                $tier = $matches[1];
            } elseif (preg_match('/(?:^|\s)make(?:\s+-C\s+\S+)?\s+test(?:\s|$)/', $line) === 1) {
                $tier = 'make-test';
            }

            if ($tier === null) {
                continue;
            }

            $pid = null;
            $ppid = null;
            $stat = null;
            $pcpu = null;
            $elapsed = null;
            if (preg_match('/^\s*(\d+)\s+(\d+)\s+([A-Za-z<NLsl+]+)\s+([0-9:-]+)\s+([0-9.]+)\s+(.+)$/', $line, $matches) === 1) {
                $pid = (int) $matches[1];
                $ppid = (int) $matches[2];
                $stat = $matches[3];
                $elapsed = $matches[4];
                $pcpu = (float) $matches[5];
                $command = $matches[6];
            } elseif (preg_match('/^\s*(\d+)\s+\d+\s+([0-9:-]+)\s+(.+)$/', $line, $matches) === 1) {
                $pid = (int) $matches[1];
                $ppid = (int) preg_split('/\s+/', trim($line))[1];
                $elapsed = $matches[2];
                $command = $matches[3];
            } elseif (preg_match('/^\s*(\d+)\s+([0-9:-]+)\s+(.+)$/', $line, $matches) === 1) {
                $pid = (int) $matches[1];
                $elapsed = $matches[2];
                $command = $matches[3];
            } else {
                $command = $line;
            }

            $active[] = [
                'pid' => $pid,
                'ppid' => $ppid,
                'stat' => $stat,
                'elapsed' => $elapsed,
                'pcpu' => $pcpu,
                'tier' => $tier,
                'command' => $command,
            ];
        }

        return [
            'status' => $active === [] ? 'clear' : 'blocked-active-runner',
            'active_count' => count($active),
            'active_tiers' => array_values(array_unique(array_column($active, 'tier'))),
            'active' => $active,
            'next_gate' => $active === []
                ? 'no active broad SQLite full-suite runner detected in supplied process snapshot; a supervisor-approved bounded run may start if other gates pass'
                : 'do not launch a duplicate broad SQLite suite; wait for the active runner artifact/log, then record parsed pass/fail evidence',
            'dependency_closure' => 'no new support component needed; active-runner gate parses a supplied process snapshot and does not inspect secrets or execute upstream tests',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function boundedRunnerArtifactRecord(string $auditText, string $stdoutText = '', string $processSnapshot = ''): array
    {
        $label = $this->extractMarkdownHeadingLabel($auditText);
        $repositoryHead = $this->extractBacktickField($auditText, 'Repository HEAD');
        $sqliteCommit = $this->extractBacktickField($auditText, 'SQLite git commit');
        $sqliteVersion = $this->extractBacktickField($auditText, 'SQLite VERSION');
        $manifestUuid = $this->extractBacktickField($auditText, 'SQLite manifest UUID');
        $scratch = $this->extractBacktickField($auditText, 'Scratch');
        $log = $this->extractBacktickField($auditText, 'Log');
        $testset = $this->extractBacktickField($auditText, 'Testset');
        $patterns = $this->extractBacktickField($auditText, 'Patterns');
        $exit = $this->extractIntegerBacktickField($auditText, 'Exit');
        $elapsed = $this->extractIntegerBacktickField($auditText, 'Elapsed seconds');
        $errors = $this->extractIntegerBacktickField($auditText, 'Parsed errors');
        $tests = $this->extractIntegerBacktickField($auditText, 'Parsed tests');
        $runnerTime = $this->extractBacktickField($auditText, 'Runner time');
        $jobs = $this->extractIntegerBacktickField($auditText, 'Jobs');
        $timeout = $this->extractIntegerBacktickField($auditText, 'Timeout seconds');

        if (($errors === null || $tests === null) && preg_match('/Parsed summary:\s+`(\d+)\s+errors?\s+out\s+of\s+(\d+)\s+tests?/i', $auditText, $matches) === 1) {
            $errors = (int) $matches[1];
            $tests = (int) $matches[2];
        }
        if (($errors === null || $tests === null) && preg_match('/(\d+)\s+errors?\s+out\s+of\s+(\d+)\s+tests?/i', $stdoutText, $matches) === 1) {
            $errors = (int) $matches[1];
            $tests = (int) $matches[2];
        }

        $progress = $this->parseRunnerProgress($stdoutText);
        $failures = $this->extractRunnerFailures($auditText . "\n" . $stdoutText);
        $failureBlockers = $this->classifyRunnerFailureBlockers($failures);
        $activeGate = $processSnapshot === '' ? $this->activeFullSuiteRunnerGate('') : $this->activeFullSuiteRunnerGate($processSnapshot);

        $status = 'running-or-incomplete';
        if (($activeGate['status'] ?? null) === 'blocked-active-runner' && $exit === null) {
            $status = 'active-runner-in-progress';
        } elseif ($exit === null && $tests === null && $errors === null) {
            $status = 'blocked-before-run';
        } elseif ($exit === 124 && $failures === [] && ($tests === null || $errors === null)) {
            $status = 'timed-out-incomplete';
        } elseif (($exit !== null && $exit !== 0) || ($errors ?? 0) > 0) {
            $status = 'failed';
        } elseif ($exit === 0 && $tests !== null && $errors === 0) {
            $status = 'passed';
        }

        return [
            'status' => $status,
            'label' => $label,
            'repository_head' => $repositoryHead,
            'sqlite_commit' => $sqliteCommit,
            'sqlite_version' => $sqliteVersion,
            'sqlite_manifest_uuid' => $manifestUuid,
            'scratch' => $scratch,
            'log' => $log,
            'requested' => [
                'testset' => $testset,
                'jobs' => $jobs,
                'timeout_seconds' => $timeout,
                'patterns' => $patterns === null || strtolower($patterns) === 'none'
                    ? []
                    : array_values(array_filter(preg_split('/\s+/', $patterns) ?: [], 'strlen')),
            ],
            'results' => [
                'exit' => $exit,
                'elapsed_seconds' => $elapsed,
                'tests' => $tests,
                'errors' => $errors,
                'runner_time' => $runnerTime,
                'failure_count' => count($failures),
                'failures' => $failures,
                'failure_blockers' => $failureBlockers,
            ],
            'progress' => $progress,
            'active_gate' => $activeGate,
            'next_gate' => $status === 'passed'
                ? 'integrator confirms the artifact checkout matches the accepted base, then records this bounded runner as accepted evidence'
                : ($status === 'timed-out-incomplete'
                    ? 'record timeout as incomplete broad-suite evidence with parsed progress only; rerun with supervisor-approved timeout before counting release/all parity'
                    : ($failures === []
                        ? 'wait for the active bounded runner or rerun with a supervisor-approved timeout, then replace incomplete evidence with parsed pass/fail counts'
                        : 'record the failed upstream runner artifact with exact failed script diagnostics, then rerun only after the upstream/runtime blocker is resolved')),
            'dependency_closure' => 'no new support component needed; bounded runner artifact records parse guarded audit/stdout text only',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function boundedRunnerArtifactRecordFromFiles(
        string $auditPath,
        ?string $stdoutPath = null,
        string $processSnapshot = ''
    ): array {
        $missing = [];
        if (!is_file($auditPath)) {
            $missing[] = $auditPath;
        }
        if ($stdoutPath !== null && !is_file($stdoutPath)) {
            $missing[] = $stdoutPath;
        }

        if ($missing !== []) {
            return [
                'status' => 'blocked-missing-artifact-files',
                'audit_path' => $auditPath,
                'stdout_path' => $stdoutPath,
                'missing' => $missing,
                'next_gate' => 'wait for the guarded bounded-runner audit/log artifacts to exist, then parse them before counting all/release evidence',
                'dependency_closure' => 'no new support component needed; artifact-file records read bounded runner audit/log files only',
            ];
        }

        $auditText = file_get_contents($auditPath);
        if ($auditText === false) {
            throw new \RuntimeException("Unable to read SQLite bounded runner audit artifact: {$auditPath}");
        }

        $stdoutText = '';
        if ($stdoutPath !== null) {
            $stdoutText = file_get_contents($stdoutPath);
            if ($stdoutText === false) {
                throw new \RuntimeException("Unable to read SQLite bounded runner stdout/log artifact: {$stdoutPath}");
            }
        }

        $record = $this->boundedRunnerArtifactRecord($auditText, $stdoutText, $processSnapshot);
        $record['audit_path'] = $auditPath;
        $record['stdout_path'] = $stdoutPath;
        $record['artifact_files_ready'] = true;
        $record['dependency_closure'] = 'no new support component needed; artifact-file records read bounded runner audit/log files only';

        return $record;
    }

    /**
     * @param array<string, mixed> $artifactRecord
     * @return array<string, mixed>
     */
    public function boundedRunnerAcceptanceGate(array $artifactRecord, string $acceptedRepositoryHead): array
    {
        $expectedManifestUuid = $this->manifest['upstream']['officialManifestUuid'] ?? null;
        $actualManifestUuid = is_string($artifactRecord['sqlite_manifest_uuid'] ?? null)
            ? $artifactRecord['sqlite_manifest_uuid']
            : null;
        $actualHead = is_string($artifactRecord['repository_head'] ?? null)
            ? $artifactRecord['repository_head']
            : null;
        $results = is_array($artifactRecord['results'] ?? null) ? $artifactRecord['results'] : [];
        $tests = is_int($results['tests'] ?? null) ? $results['tests'] : null;
        $errors = is_int($results['errors'] ?? null) ? $results['errors'] : null;
        $exit = is_int($results['exit'] ?? null) ? $results['exit'] : null;

        $blockers = [];
        if (($artifactRecord['status'] ?? null) !== 'passed' || $exit !== 0 || $tests === null || $errors !== 0) {
            $blockers[] = [
                'id' => 'artifact-not-passed',
                'evidence' => 'bounded runner artifact has not produced parsed zero-error pass evidence',
            ];
        }
        if ($actualHead !== $acceptedRepositoryHead) {
            $blockers[] = [
                'id' => 'repository-head-mismatch',
                'evidence' => 'artifact repository head does not match the accepted integration base',
                'expected' => $acceptedRepositoryHead,
                'actual' => $actualHead,
            ];
        }
        if (!is_string($expectedManifestUuid) || $actualManifestUuid !== $expectedManifestUuid) {
            $blockers[] = [
                'id' => 'sqlite-manifest-uuid-mismatch',
                'evidence' => 'artifact SQLite manifest UUID does not match the lane manifest upstream UUID',
                'expected' => $expectedManifestUuid,
                'actual' => $actualManifestUuid,
            ];
        }

        return [
            'status' => $blockers === [] ? 'accepted-for-lane-evidence' : 'blocked',
            'artifact_status' => $artifactRecord['status'] ?? 'unknown',
            'repository_head' => $actualHead,
            'accepted_repository_head' => $acceptedRepositoryHead,
            'sqlite_manifest_uuid' => $actualManifestUuid,
            'expected_sqlite_manifest_uuid' => $expectedManifestUuid,
            'tests' => $tests,
            'errors' => $errors,
            'exit' => $exit,
            'blocker_count' => count($blockers),
            'blockers' => $blockers,
            'next_gate' => $blockers === []
                ? 'record this bounded runner artifact as accepted upstream-suite evidence in manifest/status'
                : 'rerun or reparse the bounded runner from the accepted checkout and matching SQLite manifest before counting it',
            'dependency_closure' => 'no new support component needed; acceptance gate validates lane-local artifact provenance before dependency-suite evidence is counted',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function boundedRunnerCountabilityGateFromFiles(
        string $auditPath,
        ?string $stdoutPath,
        string $acceptedRepositoryHead,
        string $processSnapshot = ''
    ): array {
        $artifact = $this->boundedRunnerArtifactRecordFromFiles($auditPath, $stdoutPath, $processSnapshot);
        if (($artifact['status'] ?? null) === 'blocked-missing-artifact-files') {
            return [
                'status' => 'blocked',
                'countable' => false,
                'artifact_status' => $artifact['status'],
                'artifact' => $artifact,
                'acceptance' => null,
                'blocker_count' => 1,
                'blockers' => [
                    [
                        'id' => 'artifact-files-missing',
                        'evidence' => implode(', ', is_array($artifact['missing'] ?? null) ? $artifact['missing'] : []),
                    ],
                ],
                'next_gate' => 'wait for guarded bounded-runner audit/log files, then parse and prove zero-error provenance before counting release/all evidence',
                'dependency_closure' => 'no new support component needed; countability gate composes bounded runner artifact and provenance records only',
            ];
        }

        $acceptance = $this->boundedRunnerAcceptanceGate($artifact, $acceptedRepositoryHead);
        $blockers = is_array($acceptance['blockers'] ?? null) ? $acceptance['blockers'] : [];
        $activeGate = is_array($artifact['active_gate'] ?? null) ? $artifact['active_gate'] : [];
        if (($activeGate['status'] ?? null) === 'blocked-active-runner') {
            array_unshift($blockers, [
                'id' => 'active-runner-still-running',
                'evidence' => 'supplied process snapshot still contains a broad SQLite runner',
                'active_tiers' => $activeGate['active_tiers'] ?? [],
            ]);
        }

        $countable = ($acceptance['status'] ?? null) === 'accepted-for-lane-evidence' && $blockers === [];

        return [
            'status' => $countable ? 'countable' : 'blocked',
            'countable' => $countable,
            'artifact_status' => $artifact['status'] ?? 'unknown',
            'artifact' => $artifact,
            'acceptance' => $acceptance,
            'blocker_count' => count($blockers),
            'blockers' => $blockers,
            'next_gate' => $countable
                ? 'record this bounded runner artifact in manifest/status as accepted release/all evidence'
                : 'do not count this bounded runner artifact until it has parsed zero-error results, no active runner, accepted HEAD provenance, and matching SQLite manifest UUID',
            'dependency_closure' => 'no new support component needed; countability gate composes bounded runner artifact and provenance records only',
        ];
    }

    /**
     * @param array<string, array{audit:string, stdout?:string|null, process_snapshot?:string}> $artifacts
     * @return array<string, mixed>
     */
    public function boundedRunnerArtifactSetRecord(array $artifacts, string $acceptedRepositoryHead): array
    {
        $entries = [];
        $countable = [];
        $blocked = [];
        $missing = [];
        $active = [];
        $failed = [];
        $timedOut = [];
        $testsTotal = 0;
        $errorsTotal = 0;

        foreach ($artifacts as $label => $artifact) {
            if (!is_string($label) || !is_array($artifact) || !is_string($artifact['audit'] ?? null)) {
                continue;
            }

            $gate = $this->boundedRunnerCountabilityGateFromFiles(
                $artifact['audit'],
                is_string($artifact['stdout'] ?? null) ? $artifact['stdout'] : null,
                $acceptedRepositoryHead,
                is_string($artifact['process_snapshot'] ?? null) ? $artifact['process_snapshot'] : ''
            );
            $artifactStatus = is_string($gate['artifact_status'] ?? null) ? $gate['artifact_status'] : 'unknown';
            $acceptance = is_array($gate['acceptance'] ?? null) ? $gate['acceptance'] : [];
            $tests = is_int($acceptance['tests'] ?? null) ? $acceptance['tests'] : null;
            $errors = is_int($acceptance['errors'] ?? null) ? $acceptance['errors'] : null;

            if (($gate['countable'] ?? false) === true) {
                $countable[] = $label;
                $testsTotal += $tests ?? 0;
                $errorsTotal += $errors ?? 0;
            } else {
                $blocked[] = $label;
            }
            if ($artifactStatus === 'blocked-missing-artifact-files') {
                $missing[] = $label;
            } elseif ($artifactStatus === 'active-runner-in-progress') {
                $active[] = $label;
            } elseif ($artifactStatus === 'failed') {
                $failed[] = $label;
            } elseif ($artifactStatus === 'timed-out-incomplete') {
                $timedOut[] = $label;
            }

            $entries[] = [
                'label' => $label,
                'status' => $gate['status'],
                'countable' => (bool) ($gate['countable'] ?? false),
                'artifact_status' => $artifactStatus,
                'tests' => $tests,
                'errors' => $errors,
                'blocker_count' => (int) ($gate['blocker_count'] ?? 0),
                'blocker_ids' => array_values(array_filter(array_map(
                    static fn ($blocker): ?string => is_array($blocker) && is_string($blocker['id'] ?? null) ? $blocker['id'] : null,
                    is_array($gate['blockers'] ?? null) ? $gate['blockers'] : []
                ))),
                'gate' => $gate,
            ];
        }

        $status = 'blocked';
        if ($entries === []) {
            $status = 'blocked-empty-artifact-set';
        } elseif ($countable !== []) {
            $status = $blocked === [] ? 'countable' : 'partially-countable';
        }

        return [
            'status' => $status,
            'accepted_repository_head' => $acceptedRepositoryHead,
            'artifact_count' => count($entries),
            'countable_count' => count($countable),
            'blocked_count' => count($blocked),
            'missing_count' => count($missing),
            'active_count' => count($active),
            'failed_count' => count($failed),
            'timed_out_count' => count($timedOut),
            'countable_labels' => $countable,
            'blocked_labels' => $blocked,
            'missing_labels' => $missing,
            'active_labels' => $active,
            'failed_labels' => $failed,
            'timed_out_labels' => $timedOut,
            'tests_total' => $testsTotal,
            'errors_total' => $errorsTotal,
            'entries' => $entries,
            'next_gate' => $countable !== []
                ? 'publish only the countable zero-error bounded runner artifacts, and keep blocked artifacts as explicit follow-up evidence'
                : 'do not count this artifact set until at least one guarded runner artifact has zero-error results, accepted HEAD provenance, matching SQLite manifest UUID, and no active runner',
            'dependency_closure' => 'no new support component needed; artifact-set records compose existing bounded runner file/countability gates only',
        ];
    }

    /**
     * @param array<string, mixed> $failureBlocker
     * @return array<string, mixed>
     */
    public function focusedFailureReproGate(
        array $failureBlocker,
        string $acceptedRepositoryHead,
        ?string $repoRoot = null,
        string $auditText = '',
        string $stdoutText = ''
    ): array {
        $script = is_string($failureBlocker['script'] ?? null) ? $failureBlocker['script'] : '';
        if ($script === '') {
            throw new \InvalidArgumentException('Focused SQLite repro gate requires a failed .test script name');
        }

        $case = is_string($failureBlocker['case'] ?? null) ? $failureBlocker['case'] : null;
        $category = is_string($failureBlocker['category'] ?? null) ? $failureBlocker['category'] : 'upstream-suite-failure';
        $plan = $this->buildFocusedSubsetPlan([$script], 1, $repoRoot);

        $artifact = null;
        $acceptance = null;
        $blockers = [];
        if ($auditText === '' && $stdoutText === '') {
            $blockers[] = [
                'id' => 'focused-repro-artifact-missing',
                'evidence' => 'no focused repro audit/log text was supplied for the failed upstream runner script',
            ];
        } else {
            $artifact = $this->boundedRunnerArtifactRecord($auditText, $stdoutText, '');
            $acceptance = $this->boundedRunnerAcceptanceGate($artifact, $acceptedRepositoryHead);
            $artifactFailures = is_array($artifact['results']['failures'] ?? null) ? $artifact['results']['failures'] : [];
            $matchingFailure = null;
            foreach ($artifactFailures as $failure) {
                if (!is_array($failure)) {
                    continue;
                }
                if (($failure['script'] ?? null) !== $script) {
                    continue;
                }
                if ($case !== null && ($failure['case'] ?? null) !== $case) {
                    continue;
                }
                $matchingFailure = $failure;
                break;
            }

            if ($matchingFailure === null && (($artifact['status'] ?? null) !== 'passed')) {
                $blockers[] = [
                    'id' => 'focused-repro-failure-mismatch',
                    'evidence' => 'focused repro artifact did not preserve the failed script/case diagnostic from the broad runner',
                    'expected_script' => $script,
                    'expected_case' => $case,
                ];
            }

            $acceptedBlockers = is_array($acceptance['blockers'] ?? null) ? $acceptance['blockers'] : [];
            foreach ($acceptedBlockers as $acceptedBlocker) {
                if (!is_array($acceptedBlocker)) {
                    continue;
                }
                if (($acceptedBlocker['id'] ?? null) === 'artifact-not-passed' && $matchingFailure !== null && $category === 'upstream-runtime-environment') {
                    continue;
                }
                $blockers[] = $acceptedBlocker;
            }
        }

        $status = 'blocked';
        if ($blockers === [] && is_array($artifact)) {
            $status = ($artifact['status'] ?? null) === 'passed'
                ? 'focused-repro-passed'
                : 'focused-repro-preserves-upstream-runtime-blocker';
        }

        return [
            'status' => $status,
            'script' => $script,
            'case' => $case,
            'category' => $category,
            'plan' => $plan,
            'artifact' => $artifact,
            'acceptance' => $acceptance,
            'blocker_count' => count($blockers),
            'blockers' => $blockers,
            'next_gate' => $status === 'focused-repro-passed'
                ? 'supervisor may treat the broad runner failure as transient only after deciding whether release/all should be rerun from this accepted checkout'
                : ($status === 'focused-repro-preserves-upstream-runtime-blocker'
                    ? 'record the focused repro as an upstream runtime/environment blocker and do not count release/all parity until the sanitizer decision is made'
                    : 'run or parse the focused failed-script repro from the accepted checkout before another broad release/all result is counted'),
            'dependency_closure' => 'no new support component needed; focused repro gate composes existing runner artifact parsing, provenance checks, and selected-script planning',
        ];
    }

    /**
     * @param array<string, mixed> $failureBlocker
     * @return array<string, mixed>
     */
    public function focusedFailureReproGateFromFiles(
        array $failureBlocker,
        string $acceptedRepositoryHead,
        string $auditPath,
        ?string $stdoutPath = null,
        ?string $repoRoot = null
    ): array {
        $missing = [];
        if (!is_file($auditPath)) {
            $missing[] = $auditPath;
        }
        if ($stdoutPath !== null && !is_file($stdoutPath)) {
            $missing[] = $stdoutPath;
        }

        if ($missing !== []) {
            $script = is_string($failureBlocker['script'] ?? null) ? $failureBlocker['script'] : '';
            $case = is_string($failureBlocker['case'] ?? null) ? $failureBlocker['case'] : null;
            $category = is_string($failureBlocker['category'] ?? null) ? $failureBlocker['category'] : 'upstream-suite-failure';

            return [
                'status' => 'blocked',
                'script' => $script,
                'case' => $case,
                'category' => $category,
                'plan' => $script === '' ? null : $this->buildFocusedSubsetPlan([$script], 1, $repoRoot),
                'artifact' => null,
                'acceptance' => null,
                'audit_path' => $auditPath,
                'stdout_path' => $stdoutPath,
                'artifact_files_ready' => false,
                'blocker_count' => 1,
                'blockers' => [
                    [
                        'id' => 'focused-repro-artifact-files-missing',
                        'evidence' => implode(', ', $missing),
                    ],
                ],
                'next_gate' => 'wait for the focused failed-script repro audit/log files, then parse them before another broad release/all result is counted',
                'dependency_closure' => 'no new support component needed; focused repro file gate reads bounded runner audit/log files only',
            ];
        }

        $auditText = file_get_contents($auditPath);
        if ($auditText === false) {
            throw new \RuntimeException("Unable to read SQLite focused repro audit artifact: {$auditPath}");
        }

        $stdoutText = '';
        if ($stdoutPath !== null) {
            $stdoutText = file_get_contents($stdoutPath);
            if ($stdoutText === false) {
                throw new \RuntimeException("Unable to read SQLite focused repro stdout/log artifact: {$stdoutPath}");
            }
        }

        $gate = $this->focusedFailureReproGate(
            $failureBlocker,
            $acceptedRepositoryHead,
            $repoRoot,
            $auditText,
            $stdoutText
        );
        $gate['audit_path'] = $auditPath;
        $gate['stdout_path'] = $stdoutPath;
        $gate['artifact_files_ready'] = true;
        $gate['dependency_closure'] = 'no new support component needed; focused repro file gate composes existing artifact parsing, provenance checks, and selected-script planning';

        return $gate;
    }

    /**
     * @param array<string, mixed> $failedReleaseArtifact
     * @param array<string, mixed> $focusedReproGate
     * @return array<string, mixed>
     */
    public function releaseRerunDecisionGate(
        array $failedReleaseArtifact,
        array $focusedReproGate,
        bool $supervisorApproved = false
    ): array {
        $releaseResults = is_array($failedReleaseArtifact['results'] ?? null) ? $failedReleaseArtifact['results'] : [];
        $releaseBlockers = is_array($releaseResults['failure_blockers'] ?? null) ? $releaseResults['failure_blockers'] : [];
        $releaseActiveGate = is_array($failedReleaseArtifact['active_gate'] ?? null) ? $failedReleaseArtifact['active_gate'] : [];
        $focusedArtifact = is_array($focusedReproGate['artifact'] ?? null) ? $focusedReproGate['artifact'] : [];
        $focusedResults = is_array($focusedArtifact['results'] ?? null) ? $focusedArtifact['results'] : [];

        $primaryBlocker = null;
        foreach ($releaseBlockers as $blocker) {
            if (is_array($blocker)) {
                $primaryBlocker = $blocker;
                break;
            }
        }

        $releaseScript = is_array($primaryBlocker) && is_string($primaryBlocker['script'] ?? null)
            ? $primaryBlocker['script']
            : null;
        $releaseCase = is_array($primaryBlocker) && is_string($primaryBlocker['case'] ?? null)
            ? $primaryBlocker['case']
            : null;
        $focusedScript = is_string($focusedReproGate['script'] ?? null) ? $focusedReproGate['script'] : null;
        $focusedCase = is_string($focusedReproGate['case'] ?? null) ? $focusedReproGate['case'] : null;

        $blockers = [];
        if (($failedReleaseArtifact['status'] ?? null) !== 'failed') {
            $blockers[] = [
                'id' => 'release-artifact-not-failed',
                'evidence' => 'rerun decisions require the original guarded release/all artifact to be recorded as failed evidence',
            ];
        }
        if (($releaseActiveGate['status'] ?? null) === 'blocked-active-runner') {
            $blockers[] = [
                'id' => 'release-runner-still-active',
                'evidence' => 'a supplied process snapshot still contains a broad SQLite runner',
                'active_tiers' => $releaseActiveGate['active_tiers'] ?? [],
            ];
        }
        if ($primaryBlocker === null || ($primaryBlocker['category'] ?? null) !== 'upstream-runtime-environment') {
            $blockers[] = [
                'id' => 'release-failure-not-runtime-environment',
                'evidence' => 'the failed release artifact does not classify its primary failure as an upstream runtime/environment blocker',
            ];
        }
        if (($focusedReproGate['status'] ?? null) !== 'focused-repro-passed') {
            $blockers[] = [
                'id' => 'focused-repro-not-passed',
                'evidence' => 'the exact failed-script focused repro has not produced accepted zero-error evidence',
                'focused_status' => $focusedReproGate['status'] ?? 'unknown',
            ];
        }
        if ($releaseScript === null || $releaseScript !== $focusedScript || $releaseCase !== $focusedCase) {
            $blockers[] = [
                'id' => 'focused-repro-target-mismatch',
                'evidence' => 'focused repro target does not match the failed release script/case',
                'release_script' => $releaseScript,
                'release_case' => $releaseCase,
                'focused_script' => $focusedScript,
                'focused_case' => $focusedCase,
            ];
        }

        $decisionReady = $blockers === [];
        $rerunAllowed = $decisionReady && $supervisorApproved;

        return [
            'status' => $rerunAllowed
                ? 'rerun-allowed'
                : ($decisionReady ? 'blocked-pending-supervisor-decision' : 'blocked'),
            'rerun_allowed' => $rerunAllowed,
            'supervisor_approved' => $supervisorApproved,
            'release_artifact_status' => $failedReleaseArtifact['status'] ?? 'unknown',
            'release_repository_head' => $failedReleaseArtifact['repository_head'] ?? null,
            'focused_repro_status' => $focusedReproGate['status'] ?? 'unknown',
            'focused_repository_head' => $focusedArtifact['repository_head'] ?? null,
            'script' => $releaseScript,
            'case' => $releaseCase,
            'focused_tests' => is_int($focusedResults['tests'] ?? null) ? $focusedResults['tests'] : null,
            'focused_errors' => is_int($focusedResults['errors'] ?? null) ? $focusedResults['errors'] : null,
            'blocker_count' => count($blockers),
            'blockers' => $blockers,
            'counts_as_release_parity' => false,
            'next_gate' => $rerunAllowed
                ? 'start a fresh guarded release/all runner only after duplicate-runner gates are clear; count the result only through bounded artifact provenance gates'
                : ($decisionReady
                    ? 'obtain an explicit supervisor sanitizer/transient-failure decision before launching or counting another guarded release/all run'
                    : 'keep release/all parity uncounted until the failed release artifact and exact focused repro produce matching accepted evidence'),
            'dependency_closure' => 'no new support component needed; rerun decisions compose lane-local failed-release and focused-repro evidence only',
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $failedReleaseArtifacts
     * @param array<string, mixed> $focusedReproGate
     * @return array<string, mixed>
     */
    public function persistentReleaseRuntimeBlockerGate(
        array $failedReleaseArtifacts,
        array $focusedReproGate
    ): array {
        $expectedManifestUuid = is_string($this->manifest['upstream']['officialManifestUuid'] ?? null)
            ? $this->manifest['upstream']['officialManifestUuid']
            : null;
        $focusedScript = is_string($focusedReproGate['script'] ?? null) ? $focusedReproGate['script'] : null;
        $focusedCase = is_string($focusedReproGate['case'] ?? null) ? $focusedReproGate['case'] : null;
        $focusedArtifact = is_array($focusedReproGate['artifact'] ?? null) ? $focusedReproGate['artifact'] : [];
        $focusedResults = is_array($focusedArtifact['results'] ?? null) ? $focusedArtifact['results'] : [];

        $matches = [];
        $blockers = [];
        foreach ($failedReleaseArtifacts as $index => $artifact) {
            if (!is_array($artifact)) {
                $blockers[] = [
                    'id' => 'release-artifact-invalid',
                    'evidence' => 'failed release artifact entry is not an object',
                    'index' => $index,
                ];
                continue;
            }

            $results = is_array($artifact['results'] ?? null) ? $artifact['results'] : [];
            $requested = is_array($artifact['requested'] ?? null) ? $artifact['requested'] : [];
            $testset = is_string($requested['testset'] ?? null) ? $requested['testset'] : null;
            $manifestUuid = is_string($artifact['sqlite_manifest_uuid'] ?? null) ? $artifact['sqlite_manifest_uuid'] : null;
            if (($artifact['status'] ?? null) !== 'failed') {
                $blockers[] = [
                    'id' => 'release-artifact-not-failed',
                    'evidence' => 'persistent runtime blocker classification only accepts failed guarded release/all artifacts',
                    'index' => $index,
                    'artifact_status' => $artifact['status'] ?? 'unknown',
                ];
                continue;
            }
            if (!in_array($testset, ['all', 'release'], true)) {
                $blockers[] = [
                    'id' => 'release-artifact-not-release-tier',
                    'evidence' => 'persistent runtime blocker classification only accepts broad all/release runner artifacts',
                    'index' => $index,
                    'testset' => $testset,
                ];
                continue;
            }
            if ($expectedManifestUuid === null || $manifestUuid !== $expectedManifestUuid) {
                $blockers[] = [
                    'id' => 'release-artifact-manifest-mismatch',
                    'evidence' => 'failed release artifact SQLite manifest UUID must match the lane upstream manifest before persistent blocker evidence can be counted',
                    'index' => $index,
                    'expected' => $expectedManifestUuid,
                    'actual' => $manifestUuid,
                ];
                continue;
            }

            $failureBlockers = is_array($results['failure_blockers'] ?? null) ? $results['failure_blockers'] : [];
            $matched = null;
            foreach ($failureBlockers as $failureBlocker) {
                if (!is_array($failureBlocker)) {
                    continue;
                }
                if (($failureBlocker['category'] ?? null) !== 'upstream-runtime-environment') {
                    continue;
                }
                if ($focusedScript !== null && ($failureBlocker['script'] ?? null) !== $focusedScript) {
                    continue;
                }
                if ($focusedCase !== null && ($failureBlocker['case'] ?? null) !== $focusedCase) {
                    continue;
                }

                $matched = $failureBlocker;
                break;
            }

            if ($matched === null) {
                $blockers[] = [
                    'id' => 'release-artifact-missing-matching-runtime-blocker',
                    'evidence' => 'failed release artifact does not contain the focused upstream runtime/environment blocker',
                    'index' => $index,
                    'artifact_status' => $artifact['status'] ?? 'unknown',
                ];
                continue;
            }

            $matches[] = [
                'label' => $artifact['label'] ?? null,
                'repository_head' => $artifact['repository_head'] ?? null,
                'sqlite_manifest_uuid' => $manifestUuid,
                'testset' => $testset,
                'exit' => $results['exit'] ?? null,
                'elapsed_seconds' => $results['elapsed_seconds'] ?? null,
                'script' => $matched['script'] ?? null,
                'case' => $matched['case'] ?? null,
                'category' => $matched['category'] ?? null,
                'blocker_id' => $matched['id'] ?? null,
            ];
        }

        if (($focusedReproGate['status'] ?? null) !== 'focused-repro-passed') {
            $blockers[] = [
                'id' => 'focused-repro-not-passed',
                'evidence' => 'persistent release-failure classification requires an exact focused repro with zero errors',
                'focused_status' => $focusedReproGate['status'] ?? 'unknown',
            ];
        }

        if (count($matches) < 2) {
            $blockers[] = [
                'id' => 'insufficient-repeated-release-failures',
                'evidence' => 'persistent classification requires at least two guarded release artifacts with the same upstream runtime/environment blocker',
                'matching_release_artifacts' => count($matches),
            ];
        }

        $persistent = $blockers === [];

        return [
            'status' => $persistent ? 'persistent-upstream-runtime-blocker' : 'blocked',
            'persistent' => $persistent,
            'script' => $focusedScript,
            'case' => $focusedCase,
            'matching_release_artifact_count' => count($matches),
            'matching_release_artifacts' => $matches,
            'expected_sqlite_manifest_uuid' => $expectedManifestUuid,
            'focused_repro_status' => $focusedReproGate['status'] ?? 'unknown',
            'focused_tests' => is_int($focusedResults['tests'] ?? null) ? $focusedResults['tests'] : null,
            'focused_errors' => is_int($focusedResults['errors'] ?? null) ? $focusedResults['errors'] : null,
            'blocker_count' => count($blockers),
            'blockers' => $blockers,
            'counts_as_release_parity' => false,
            'next_gate' => $persistent
                ? 'supervisor may record the repeated release failure as persistent upstream-runtime blocker evidence; release/all parity remains uncounted until a zero-error release artifact passes provenance gates or the sanitizer blocker is explicitly accepted as a non-portability exclusion'
                : 'collect two matching guarded release artifacts plus an exact focused zero-error repro before classifying the release/all blocker as persistent',
            'dependency_closure' => 'no new support component needed; persistent failure gate composes lane-local release artifacts and focused repro evidence only',
        ];
    }

    /**
     * @param array<string, mixed> $persistentBlockerGate
     * @return array<string, mixed>
     */
    public function releaseParityExclusionDecisionGate(
        array $persistentBlockerGate,
        bool $supervisorAcceptedExclusion = false,
        string $decisionNote = ''
    ): array {
        $persistent = ($persistentBlockerGate['status'] ?? null) === 'persistent-upstream-runtime-blocker'
            && ($persistentBlockerGate['persistent'] ?? false) === true;
        $matchingCount = is_int($persistentBlockerGate['matching_release_artifact_count'] ?? null)
            ? $persistentBlockerGate['matching_release_artifact_count']
            : 0;
        $focusedTests = is_int($persistentBlockerGate['focused_tests'] ?? null)
            ? $persistentBlockerGate['focused_tests']
            : null;
        $focusedErrors = is_int($persistentBlockerGate['focused_errors'] ?? null)
            ? $persistentBlockerGate['focused_errors']
            : null;

        $blockers = [];
        if (!$persistent) {
            $blockers[] = [
                'id' => 'persistent-blocker-not-proven',
                'evidence' => 'release/all parity exclusion requires a persistent upstream runtime blocker gate',
                'persistent_status' => $persistentBlockerGate['status'] ?? 'unknown',
            ];
        }
        if ($matchingCount < 2) {
            $blockers[] = [
                'id' => 'insufficient-release-artifacts',
                'evidence' => 'release/all parity exclusion requires at least two matching broad all/release artifacts',
                'matching_release_artifacts' => $matchingCount,
            ];
        }
        if ($focusedTests === null || $focusedTests < 1 || $focusedErrors !== 0) {
            $blockers[] = [
                'id' => 'focused-repro-not-clean',
                'evidence' => 'release/all parity exclusion requires a focused repro with parsed zero-error evidence',
                'focused_tests' => $focusedTests,
                'focused_errors' => $focusedErrors,
            ];
        }
        if (!$supervisorAcceptedExclusion) {
            $blockers[] = [
                'id' => 'supervisor-exclusion-decision-required',
                'evidence' => 'persistent upstream runtime blockers do not count as release/all parity until the supervisor explicitly accepts the non-portability exclusion',
            ];
        }

        $accepted = $blockers === [];

        return [
            'status' => $accepted ? 'accepted-non-portability-exclusion' : 'blocked',
            'counts_as_zero_error_release_parity' => false,
            'counts_as_release_blocker_closure' => $accepted,
            'supervisor_accepted_exclusion' => $supervisorAcceptedExclusion,
            'decision_note' => $decisionNote,
            'script' => $persistentBlockerGate['script'] ?? null,
            'case' => $persistentBlockerGate['case'] ?? null,
            'matching_release_artifact_count' => $matchingCount,
            'focused_tests' => $focusedTests,
            'focused_errors' => $focusedErrors,
            'blocker_count' => count($blockers),
            'blockers' => $blockers,
            'next_gate' => $accepted
                ? 'record this as a supervisor-accepted non-portability exclusion only; release/all zero-error parity still requires a future countable zero-error artifact'
                : 'keep release/all parity uncounted until a zero-error artifact passes countability gates or the supervisor explicitly accepts the persistent sanitizer blocker as a non-portability exclusion',
            'dependency_closure' => 'no new support component needed; exclusion decisions compose existing persistent blocker evidence and an explicit supervisor decision only',
        ];
    }

    /**
     * @param array<string, mixed> $countabilityGate
     * @param array<string, mixed>|null $exclusionDecisionGate
     * @return array<string, mixed>
     */
    public function releaseBlockerAdmissionRecord(array $countabilityGate, ?array $exclusionDecisionGate = null): array
    {
        $countable = ($countabilityGate['status'] ?? null) === 'countable'
            && ($countabilityGate['countable'] ?? false) === true;
        $exclusionAccepted = is_array($exclusionDecisionGate)
            && ($exclusionDecisionGate['status'] ?? null) === 'accepted-non-portability-exclusion'
            && ($exclusionDecisionGate['counts_as_release_blocker_closure'] ?? false) === true;

        $blockers = [];
        if (!$countable && !$exclusionAccepted) {
            $countabilityBlockers = is_array($countabilityGate['blockers'] ?? null) ? $countabilityGate['blockers'] : [];
            foreach ($countabilityBlockers as $blocker) {
                if (is_array($blocker)) {
                    $blocker['source'] = 'countability';
                    $blockers[] = $blocker;
                }
            }

            if (is_array($exclusionDecisionGate)) {
                $exclusionBlockers = is_array($exclusionDecisionGate['blockers'] ?? null) ? $exclusionDecisionGate['blockers'] : [];
                foreach ($exclusionBlockers as $blocker) {
                    if (is_array($blocker)) {
                        $blocker['source'] = 'exclusion';
                        $blockers[] = $blocker;
                    }
                }
            } else {
                $blockers[] = [
                    'id' => 'exclusion-decision-missing',
                    'source' => 'exclusion',
                    'evidence' => 'no supervisor exclusion decision gate was supplied for non-portability closure',
                ];
            }
        }

        $closureMode = 'blocked';
        if ($countable) {
            $closureMode = 'zero-error-release-artifact';
        } elseif ($exclusionAccepted) {
            $closureMode = 'supervisor-non-portability-exclusion';
        }

        $acceptance = is_array($countabilityGate['acceptance'] ?? null) ? $countabilityGate['acceptance'] : [];

        return [
            'status' => $closureMode === 'blocked' ? 'blocked' : 'admissible',
            'closure_mode' => $closureMode,
            'release_blocker_closed' => $closureMode !== 'blocked',
            'counts_as_zero_error_release_parity' => $countable,
            'countable_artifact' => $countable,
            'exclusion_accepted' => $exclusionAccepted,
            'artifact_status' => $countabilityGate['artifact_status'] ?? 'unknown',
            'artifact_tests' => $acceptance['tests'] ?? null,
            'artifact_errors' => $acceptance['errors'] ?? null,
            'exclusion_script' => is_array($exclusionDecisionGate) ? ($exclusionDecisionGate['script'] ?? null) : null,
            'exclusion_case' => is_array($exclusionDecisionGate) ? ($exclusionDecisionGate['case'] ?? null) : null,
            'blocker_count' => count($blockers),
            'blockers' => $blockers,
            'next_gate' => $closureMode === 'zero-error-release-artifact'
                ? 'integrator may count this release/all artifact as zero-error parity after recording the accepted evidence'
                : ($closureMode === 'supervisor-non-portability-exclusion'
                    ? 'integrator may close the release blocker by explicit exclusion only; release/all zero-error parity remains uncounted'
                    : 'keep the release blocker open until a zero-error countable artifact or explicit supervisor non-portability exclusion is recorded'),
            'dependency_closure' => 'no new support component needed; admission record composes countability and explicit exclusion gates only',
        ];
    }

    /**
     * @param array<int|string, array<string, mixed>> $admissionRecords
     * @return array<string, mixed>
     */
    public function releaseAdmissionLedger(array $admissionRecords): array
    {
        $entries = [];
        $zeroError = 0;
        $exclusionOnly = 0;
        $blocked = 0;
        $artifactTests = 0;
        $artifactErrors = 0;
        $blockers = [];

        foreach ($admissionRecords as $name => $record) {
            if (!is_array($record)) {
                continue;
            }

            $label = is_string($name) ? $name : 'admission-' . (string) $name;
            $closureMode = is_string($record['closure_mode'] ?? null) ? $record['closure_mode'] : 'blocked';
            $countsAsParity = ($record['counts_as_zero_error_release_parity'] ?? false) === true;
            $closed = ($record['release_blocker_closed'] ?? false) === true;
            $tests = is_int($record['artifact_tests'] ?? null) ? $record['artifact_tests'] : null;
            $errors = is_int($record['artifact_errors'] ?? null) ? $record['artifact_errors'] : null;

            if ($countsAsParity) {
                $zeroError++;
                $artifactTests += $tests ?? 0;
                $artifactErrors += $errors ?? 0;
            } elseif ($closed && $closureMode === 'supervisor-non-portability-exclusion') {
                $exclusionOnly++;
            } else {
                $blocked++;
                foreach (is_array($record['blockers'] ?? null) ? $record['blockers'] : [] as $blocker) {
                    if (is_array($blocker)) {
                        $blockers[] = [
                            'admission' => $label,
                            'id' => $blocker['id'] ?? 'unknown',
                            'source' => $blocker['source'] ?? null,
                            'evidence' => $blocker['evidence'] ?? null,
                        ];
                    }
                }
            }

            $entries[] = [
                'label' => $label,
                'status' => $record['status'] ?? 'unknown',
                'closure_mode' => $closureMode,
                'release_blocker_closed' => $closed,
                'counts_as_zero_error_release_parity' => $countsAsParity,
                'artifact_status' => $record['artifact_status'] ?? 'unknown',
                'artifact_tests' => $tests,
                'artifact_errors' => $errors,
                'blocker_count' => is_int($record['blocker_count'] ?? null) ? $record['blocker_count'] : 0,
            ];
        }

        $status = 'blocked';
        if ($zeroError > 0) {
            $status = 'zero-error-release-parity-countable';
        } elseif ($exclusionOnly > 0 && $blocked === 0) {
            $status = 'release-blocker-closed-by-exclusion';
        }

        return [
            'status' => $status,
            'entry_count' => count($entries),
            'zero_error_release_artifacts' => $zeroError,
            'exclusion_only_closures' => $exclusionOnly,
            'blocked_admissions' => $blocked,
            'release_blocker_closed' => $zeroError > 0 || ($exclusionOnly > 0 && $blocked === 0),
            'counts_as_zero_error_release_parity' => $zeroError > 0,
            'artifact_tests_total' => $artifactTests,
            'artifact_errors_total' => $artifactErrors,
            'entries' => $entries,
            'blockers' => $blockers,
            'next_gate' => $zeroError > 0
                ? 'integrator may publish release/all zero-error parity from the countable admission entries'
                : ($exclusionOnly > 0 && $blocked === 0
                    ? 'integrator may close the release blocker by exclusion while keeping zero-error release/all parity uncounted'
                    : 'keep release/all parity and blocker closure open until an admission record is countable or explicitly excluded'),
            'dependency_closure' => 'no new support component needed; admission ledger summarizes lane-local release blocker admission records only',
        ];
    }

    /**
     * @param array<int|string, array<string, mixed>> $admissionRecords
     * @return array<string, mixed>
     */
    public function releaseRerunDecisionRecord(array $admissionRecords, string $processSnapshot, bool $supervisorApproved = false): array
    {
        $ledger = $this->releaseAdmissionLedger($admissionRecords);
        $activeGate = $this->activeFullSuiteRunnerGate($processSnapshot);
        $blockers = [];

        if (($ledger['counts_as_zero_error_release_parity'] ?? false) === true) {
            return [
                'status' => 'rerun-not-needed-zero-error-parity',
                'rerun_allowed' => false,
                'supervisor_approved' => $supervisorApproved,
                'ledger_status' => $ledger['status'],
                'zero_error_release_artifacts' => $ledger['zero_error_release_artifacts'],
                'exclusion_only_closures' => $ledger['exclusion_only_closures'],
                'blocked_admissions' => $ledger['blocked_admissions'],
                'active_gate' => $activeGate,
                'blocker_count' => 0,
                'blockers' => [],
                'next_gate' => 'do not launch another broad runner for closure; record the countable zero-error release/all admission evidence instead',
                'dependency_closure' => 'no new support component needed; rerun decision composes release admission ledger records and supplied active-runner snapshots only',
            ];
        }

        if (($ledger['release_blocker_closed'] ?? false) === true && ($ledger['counts_as_zero_error_release_parity'] ?? false) !== true) {
            $blockers[] = [
                'id' => 'release-blocker-closed-by-exclusion',
                'evidence' => 'supervisor exclusion-only closure is already recorded; a new broad runner requires explicit parity-refresh approval',
            ];
        }

        if (!$supervisorApproved) {
            $blockers[] = [
                'id' => 'supervisor-approval-required',
                'evidence' => 'another guarded release/all attempt requires explicit supervisor approval after reviewing admission ledger blockers',
            ];
        }

        if (($activeGate['status'] ?? null) === 'blocked-active-runner') {
            $blockers[] = [
                'id' => 'active-runner-still-running',
                'evidence' => 'supplied process snapshot already contains a broad SQLite runner',
                'active_tiers' => $activeGate['active_tiers'] ?? [],
                'active_count' => $activeGate['active_count'] ?? 0,
            ];
        }

        foreach (is_array($ledger['blockers'] ?? null) ? $ledger['blockers'] : [] as $blocker) {
            if (!is_array($blocker)) {
                continue;
            }

            $blockers[] = [
                'id' => 'admission-' . (string) ($blocker['id'] ?? 'unknown'),
                'admission' => $blocker['admission'] ?? null,
                'source' => $blocker['source'] ?? null,
                'evidence' => $blocker['evidence'] ?? null,
            ];
        }

        $allowed = $blockers === [];

        return [
            'status' => $allowed ? 'rerun-allowed' : 'blocked',
            'rerun_allowed' => $allowed,
            'supervisor_approved' => $supervisorApproved,
            'ledger_status' => $ledger['status'],
            'zero_error_release_artifacts' => $ledger['zero_error_release_artifacts'],
            'exclusion_only_closures' => $ledger['exclusion_only_closures'],
            'blocked_admissions' => $ledger['blocked_admissions'],
            'active_gate' => $activeGate,
            'blocker_count' => count($blockers),
            'blockers' => $blockers,
            'next_gate' => $allowed
                ? 'launch at most one guarded broad release/all rerun, then count the result only through artifact provenance and admission gates'
                : 'do not launch another broad release/all runner until admission blockers, duplicate-runner state, and supervisor approval are clear',
            'dependency_closure' => 'no new support component needed; rerun decision composes release admission ledger records and supplied active-runner snapshots only',
        ];
    }

    /**
     * @param array<string, mixed> $artifactSetRecord
     * @param array<string, mixed>|null $exclusionDecisionGate
     * @return array<string, mixed>
     */
    public function releaseBlockerClosureRecord(
        array $artifactSetRecord,
        ?array $exclusionDecisionGate,
        string $processSnapshot,
        bool $supervisorApproved = false
    ): array {
        $admissions = [];
        foreach (is_array($artifactSetRecord['entries'] ?? null) ? $artifactSetRecord['entries'] : [] as $entry) {
            if (!is_array($entry) || !is_array($entry['gate'] ?? null)) {
                continue;
            }

            $label = is_string($entry['label'] ?? null) ? $entry['label'] : 'artifact-' . (string) count($admissions);
            $admissions[$label] = $this->releaseBlockerAdmissionRecord($entry['gate']);
        }

        if (is_array($exclusionDecisionGate)) {
            $admissions['supervisor-exclusion'] = $this->releaseBlockerAdmissionRecord(
                [
                    'status' => 'blocked',
                    'countable' => false,
                    'artifact_status' => 'not-applicable',
                    'acceptance' => [
                        'tests' => null,
                        'errors' => null,
                    ],
                    'blockers' => [
                        [
                            'id' => 'artifact-not-supplied-for-exclusion',
                            'evidence' => 'closure is using the explicit supervisor exclusion gate instead of a zero-error release/all artifact',
                        ],
                    ],
                ],
                $exclusionDecisionGate
            );
        }

        $ledger = $this->releaseAdmissionLedger($admissions);
        $rerun = $this->releaseRerunDecisionRecord($admissions, $processSnapshot, $supervisorApproved);
        $activeGate = is_array($rerun['active_gate'] ?? null) ? $rerun['active_gate'] : [];

        $status = 'blocked';
        if (($ledger['counts_as_zero_error_release_parity'] ?? false) === true) {
            $status = 'zero-error-release-parity-countable';
        } elseif (($ledger['release_blocker_closed'] ?? false) === true) {
            $status = 'release-blocker-closed-by-exclusion';
        } elseif (($rerun['rerun_allowed'] ?? false) === true) {
            $status = 'rerun-allowed';
        } elseif (($activeGate['status'] ?? null) === 'blocked-active-runner') {
            $status = 'blocked-active-runner';
        }

        return [
            'status' => $status,
            'artifact_set_status' => $artifactSetRecord['status'] ?? 'unknown',
            'artifact_count' => is_int($artifactSetRecord['artifact_count'] ?? null) ? $artifactSetRecord['artifact_count'] : 0,
            'countable_artifacts' => is_int($artifactSetRecord['countable_count'] ?? null) ? $artifactSetRecord['countable_count'] : 0,
            'blocked_artifacts' => is_int($artifactSetRecord['blocked_count'] ?? null) ? $artifactSetRecord['blocked_count'] : 0,
            'failed_artifacts' => is_int($artifactSetRecord['failed_count'] ?? null) ? $artifactSetRecord['failed_count'] : 0,
            'timed_out_artifacts' => is_int($artifactSetRecord['timed_out_count'] ?? null) ? $artifactSetRecord['timed_out_count'] : 0,
            'active_artifacts' => is_int($artifactSetRecord['active_count'] ?? null) ? $artifactSetRecord['active_count'] : 0,
            'missing_artifacts' => is_int($artifactSetRecord['missing_count'] ?? null) ? $artifactSetRecord['missing_count'] : 0,
            'admission_count' => count($admissions),
            'ledger' => $ledger,
            'rerun_decision' => $rerun,
            'release_blocker_closed' => (bool) ($ledger['release_blocker_closed'] ?? false),
            'counts_as_zero_error_release_parity' => (bool) ($ledger['counts_as_zero_error_release_parity'] ?? false),
            'rerun_allowed' => (bool) ($rerun['rerun_allowed'] ?? false),
            'blocker_count' => is_int($rerun['blocker_count'] ?? null) ? $rerun['blocker_count'] : 0,
            'blockers' => is_array($rerun['blockers'] ?? null) ? $rerun['blockers'] : [],
            'next_gate' => match ($status) {
                'zero-error-release-parity-countable' => 'accept the zero-error release/all artifact from the closure record and do not launch another broad runner',
                'release-blocker-closed-by-exclusion' => 'accept exclusion-only release blocker closure if supervisor policy allows it; keep zero-error release/all parity uncounted',
                'rerun-allowed' => 'launch at most one guarded broad release/all rerun, then feed its audit/log files back into the artifact-set closure record',
                'blocked-active-runner' => 'wait for the active guarded release/all runner to finish, then parse its audit/log files before making a closure decision',
                default => 'keep the release blocker open until artifact-set countability, exclusion, duplicate-runner, and supervisor-approval gates are resolved',
            },
            'dependency_closure' => 'no new support component needed; closure record composes artifact-set countability, admission ledger, exclusion, and active-runner gates only',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function selectedScriptInventory(?string $repoRoot = null): array
    {
        $coverage = $this->runnerCoverageAudit();
        $wildcards = $this->wildcardExpansionPlan($repoRoot);
        $root = $repoRoot ?? dirname(__DIR__, 3);
        $relativeTestDirectory = '.upstream-cache/libsqlite/test';
        $testDirectory = $root . '/' . $relativeTestDirectory;
        $testDirectoryReady = is_dir($testDirectory);

        $resolved = [];
        $missing = [];
        $invalid = [];

        foreach ($coverage['selected_scripts'] as $script) {
            if (!is_string($script) || !preg_match('/^[A-Za-z0-9_.?-]+\.test$/', $script)) {
                $invalid[] = (string) $script;
                continue;
            }

            $path = $testDirectory . '/' . $script;
            if (!$testDirectoryReady || !is_file($path)) {
                $missing[] = $script;
                continue;
            }

            $resolved[$script] = [
                'script' => $script,
                'source' => $relativeTestDirectory . '/' . $script,
                'bytes' => filesize($path) ?: 0,
                'from' => 'selected-runner-history',
            ];
        }

        foreach ($wildcards['expanded'] ?? [] as $pattern => $scripts) {
            if (!is_string($pattern) || !is_array($scripts)) {
                continue;
            }

            foreach ($scripts as $script) {
                if (!is_string($script) || !preg_match('/^[A-Za-z0-9_.?-]+\.test$/', $script)) {
                    $invalid[] = (string) $script;
                    continue;
                }

                $path = $testDirectory . '/' . $script;
                if (!is_file($path)) {
                    $missing[] = $script;
                    continue;
                }

                $resolved[$script] = [
                    'script' => $script,
                    'source' => $relativeTestDirectory . '/' . $script,
                    'bytes' => filesize($path) ?: 0,
                    'from' => 'wildcard-pattern:' . $pattern,
                ];
            }
        }

        ksort($resolved);
        $missing = array_values(array_unique($missing));
        sort($missing);
        $invalid = array_values(array_unique($invalid));
        sort($invalid);

        $requestedCount = (int) $coverage['selected_script_count'] + (int) $wildcards['expanded_script_count'];
        $blocked = !$testDirectoryReady || $missing !== [] || $invalid !== [] || ($wildcards['status'] ?? null) === 'blocked-needs-hydrated-test-dir';

        return [
            'status' => $blocked ? 'blocked' : 'ready',
            'test_directory' => $relativeTestDirectory,
            'test_directory_ready' => $testDirectoryReady,
            'requested_selected_script_count' => (int) $coverage['selected_script_count'],
            'requested_wildcard_pattern_count' => (int) $coverage['pattern_script_count'],
            'requested_expanded_wildcard_script_count' => (int) $wildcards['expanded_script_count'],
            'requested_total_script_count' => $requestedCount,
            'resolved_script_count' => count($resolved),
            'missing_script_count' => count($missing),
            'invalid_script_count' => count($invalid),
            'resolved_scripts' => array_values($resolved),
            'missing_scripts' => $missing,
            'invalid_scripts' => $invalid,
            'wildcard_status' => $wildcards['status'] ?? 'unknown',
            'next_gate' => $blocked
                ? 'hydrate .upstream-cache/libsqlite/test and resolve every selected or wildcard-expanded .test file before launching broad all/release runners'
                : 'use the resolved script inventory as the concrete focused-suite denominator for reruns and all/release handoff review',
            'dependency_closure' => 'no new support component needed; selected-script inventory reads hydrated SQLite test sources only',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function wildcardExpansionPlan(?string $repoRoot = null): array
    {
        $coverage = $this->runnerCoverageAudit();
        $root = $repoRoot ?? dirname(__DIR__, 3);
        $testDirectory = $root . '/.upstream-cache/libsqlite/test';
        $patterns = $coverage['pattern_scripts'];

        $expanded = [];
        $missingPatterns = [];
        $invalidPatterns = [];
        $testDirectoryReady = is_dir($testDirectory);

        foreach ($patterns as $pattern) {
            if (!is_string($pattern) || !preg_match('/^[A-Za-z0-9_.*?-]+\.test$/', $pattern)) {
                $invalidPatterns[] = (string) $pattern;
                continue;
            }

            if (!$testDirectoryReady) {
                $missingPatterns[] = $pattern;
                continue;
            }

            $matches = glob($testDirectory . '/' . $pattern);
            if ($matches === false || $matches === []) {
                $missingPatterns[] = $pattern;
                continue;
            }

            $scripts = [];
            foreach ($matches as $match) {
                $scripts[] = basename($match);
            }

            sort($scripts);
            $expanded[$pattern] = $scripts;
        }

        ksort($expanded);

        $expandedCount = 0;
        foreach ($expanded as $scripts) {
            $expandedCount += count($scripts);
        }

        $blocked = !$testDirectoryReady || $missingPatterns !== [] || $invalidPatterns !== [];

        return [
            'status' => $patterns === []
                ? 'complete-no-wildcards'
                : ($blocked ? 'blocked-needs-hydrated-test-dir' : 'ready'),
            'test_directory' => '.upstream-cache/libsqlite/test',
            'test_directory_ready' => $testDirectoryReady,
            'pattern_count' => count($patterns),
            'patterns' => $patterns,
            'expanded_pattern_count' => count($expanded),
            'expanded_script_count' => $expandedCount,
            'expanded' => $expanded,
            'missing_patterns' => $missingPatterns,
            'invalid_patterns' => $invalidPatterns,
            'next_gate' => $blocked
                ? 'hydrate .upstream-cache/libsqlite/test, then expand wildcard selections to concrete .test filenames before counting exact focused coverage'
                : 'replace wildcard runner notes with concrete selected .test script lists in the manifest evidence',
            'dependency_closure' => 'no new support component needed; wildcard expansion uses the lane-local manifest and hydrated upstream test directory only',
        ];
    }

    /**
     * @return array{scripts: int, tests: int, errors: int}
     */
    private function parseVeryquickResult(mixed $result): array
    {
        if (!is_string($result)) {
            return ['scripts' => 0, 'tests' => 0, 'errors' => 0];
        }

        if (preg_match('/Passed\s+(\d+)\s+(?:Tcl\s+)?scripts?\s+with\s+(\d+)\s+errors?\s+out\s+of\s+(\d+)\s+tests?/i', $result, $matches) !== 1) {
            return ['scripts' => 0, 'tests' => 0, 'errors' => 0];
        }

        return [
            'scripts' => (int) $matches[1],
            'errors' => (int) $matches[2],
            'tests' => (int) $matches[3],
        ];
    }

    /**
     * @return array{tests: int, errors: int}
     */
    private function parseFocusedResultDescription(string $description): array
    {
        if (preg_match('/with\s+(\d+)\s+errors?\s+out\s+of\s+(\d+)\s+tests?/i', $description, $matches) === 1) {
            return [
                'tests' => (int) $matches[2],
                'errors' => (int) $matches[1],
            ];
        }

        if (preg_match('/(\d+)\s+errors?\s+out\s+of\s+(\d+)\s+tests?/i', $description, $matches) === 1) {
            return [
                'tests' => (int) $matches[2],
                'errors' => (int) $matches[1],
            ];
        }

        return ['tests' => 0, 'errors' => 0];
    }

    /**
     * @return list<string>
     */
    private function extractPermutationSuiteNames(string $text): array
    {
        $names = [];
        $patterns = [
            '/^\s*test_suite\s+"([^"\r\n]+)"/m',
            '/^\s*test_suite\s+([A-Za-z0-9_.${}-]+)/m',
            '/^\s*permutation\s+([A-Za-z0-9_.-]+)/m',
            '/^\s*run_tests\s+([A-Za-z0-9_.-]+)/m',
            '/^\s*([A-Za-z0-9_.-]+)\s+\{[^}\n]*(?:-files|-description|-initialize|-shutdown)/m',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches) !== false) {
                foreach ($matches[1] as $name) {
                    if ($name === 'NAME') {
                        continue;
                    }

                    $names[$name] = true;
                }
            }
        }

        $suiteNames = array_keys($names);
        sort($suiteNames);

        return $suiteNames;
    }

    private function descriptionUsesCachedEvidence(string $description): bool
    {
        $lower = strtolower($description);

        return str_contains($lower, 'reused')
            || str_contains($lower, 'did not start upstream testfixture')
            || str_contains($lower, 'no new upstream runner')
            || str_contains($lower, 'no fresh upstream runner')
            || str_contains($lower, 'did not contain the hydrated upstream cache')
            || str_contains($lower, 'has no hydrated .upstream-cache');
    }

    /**
     * @return list<string>
     */
    private function extractTestScriptTokens(string $command): array
    {
        if (preg_match_all('/(?<![A-Za-z0-9_.\/-])([A-Za-z0-9_.*?\/-]+\.test)(?![A-Za-z0-9_.\/-])/', $command, $matches) < 1) {
            return [];
        }

        return array_values(array_unique($matches[1]));
    }

    /**
     * @param array<string, mixed> $fullReleaseStatus
     * @return list<string>
     */
    private function remainingSuiteTiers(array $fullReleaseStatus): array
    {
        if (($fullReleaseStatus['executed'] ?? false) === true) {
            return [];
        }

        return [
            'full release/all permutations',
            'multi-configuration make test suites',
            'long-running stress/permutation tiers beyond veryquick',
        ];
    }

    /**
     * @return list<string>
     */
    private function missingRunnerInputs(
        string $absoluteBuildDirectory,
        string $testfixture,
        string $testrunner,
        string $buildDirectory
    ): array {
        $missing = [];
        if (!is_dir($absoluteBuildDirectory)) {
            $missing[] = $buildDirectory;
        }
        if (!is_file($testfixture)) {
            $missing[] = $buildDirectory . '/testfixture';
        }
        if (!is_file($testrunner)) {
            $missing[] = '.upstream-cache/libsqlite/test/testrunner.tcl';
        }

        return $missing;
    }

    private function extractMarkdownHeadingLabel(string $text): ?string
    {
        if (preg_match('/^#\s+SQLite Tcl Bounded Runner Evidence\s+-\s+([^\r\n]+)/m', $text, $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }

    private function extractBacktickField(string $text, string $field): ?string
    {
        $quotedField = preg_quote($field, '/');
        if (preg_match('/-\s+' . $quotedField . ':\s+`([^`]*)`/i', $text, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private function extractIntegerBacktickField(string $text, string $field): ?int
    {
        $value = $this->extractBacktickField($text, $field);
        if ($value === null || !preg_match('/^-?\d+$/', $value)) {
            return null;
        }

        return (int) $value;
    }

    /**
     * @return array{completed: int|null, total: int|null, last_line: string|null}
     */
    private function parseRunnerProgress(string $stdoutText): array
    {
        $completed = null;
        $total = null;
        $lastLine = null;

        foreach (preg_split('/\R/', $stdoutText) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            if (preg_match('/tcl\((\d+)\/(\d+)\)/', $trimmed, $matches) === 1) {
                $completed = (int) $matches[1];
                $total = (int) $matches[2];
                $lastLine = $trimmed;
            }
        }

        return [
            'completed' => $completed,
            'total' => $total,
            'last_line' => $lastLine,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractRunnerFailures(string $text): array
    {
        $failures = [];
        if ($text === '') {
            return $failures;
        }

        if (preg_match_all('/FAILED:\s+([^\r\n]+)(.*?)(?=\nFAILED:|\n## |\z)/s', $text, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $label = trim($match[1]);
                $body = $match[2] ?? '';
                $failure = [
                    'label' => $label,
                    'script' => null,
                    'kind' => null,
                    'case' => null,
                    'diagnostic' => null,
                ];

                if (preg_match('/\b((?:ext\/)?[A-Za-z0-9_\/.-]+\.test)\b/', $label, $scriptMatch) === 1) {
                    $failure['script'] = $scriptMatch[1];
                }
                if (preg_match('/^([A-Za-z0-9_ -]+?)\s+(?:ext\/)?[A-Za-z0-9_\/.-]+\.test\b/', $label, $kindMatch) === 1) {
                    $failure['kind'] = trim($kindMatch[1]);
                }
                if (preg_match_all('/([A-Za-z0-9_\/.-]+-\d+(?:\.\d+)*)\.\.\./', $body, $caseMatches) !== false && $caseMatches[1] !== []) {
                    $failure['case'] = end($caseMatches[1]);
                }
                if (preg_match('/SUMMARY:\s+([^\r\n]+)/', $body, $summaryMatch) === 1) {
                    $failure['diagnostic'] = trim($summaryMatch[1]);
                } elseif (preg_match('/runtime error:\s+([^\r\n]+)/', $body, $runtimeMatch) === 1) {
                    $failure['diagnostic'] = 'runtime error: ' . trim($runtimeMatch[1]);
                }

                $failures[] = $failure;
            }
        }

        return $failures;
    }

    /**
     * @param list<array<string, mixed>> $failures
     * @return list<array<string, mixed>>
     */
    private function classifyRunnerFailureBlockers(array $failures): array
    {
        $blockers = [];
        foreach ($failures as $failure) {
            $diagnostic = is_string($failure['diagnostic'] ?? null) ? $failure['diagnostic'] : '';
            $script = is_string($failure['script'] ?? null) ? $failure['script'] : null;
            $case = is_string($failure['case'] ?? null) ? $failure['case'] : null;
            $kind = is_string($failure['kind'] ?? null) ? $failure['kind'] : null;

            $id = 'upstream-runner-failure';
            $category = 'upstream-suite-failure';
            $nextGate = 'keep the release/all artifact uncounted, then rerun only after a focused repro explains whether the failed script is an upstream/runtime issue or a native parity issue';
            if (str_contains($diagnostic, 'UndefinedBehaviorSanitizer') || str_contains($diagnostic, 'runtime error: applying non-zero offset')) {
                $id = 'upstream-runtime-sanitizer';
                $category = 'upstream-runtime-environment';
                $nextGate = 'record as failed release-runner evidence; rerun release only with a supervisor-approved sanitizer decision or a focused repro for the exact script and case';
            }

            $blockers[] = [
                'id' => $id,
                'category' => $category,
                'script' => $script,
                'case' => $case,
                'kind' => $kind,
                'diagnostic' => $diagnostic === '' ? null : $diagnostic,
                'next_gate' => $nextGate,
            ];
        }

        return $blockers;
    }

    /**
     * @param list<string> $scripts
     * @return array<string, mixed>
     */
    public function focusedSubsetPlan(array $scripts, ?string $repoRoot = null): array
    {
        return $this->buildFocusedSubsetPlan($scripts, 1, $repoRoot);
    }

    /**
     * @param array<string, list<string>> $groups
     * @return array<string, array<string, mixed>>
     */
    public function focusedSubsetMatrix(array $groups, int $jobs = 1, ?string $repoRoot = null): array
    {
        if ($jobs < 1) {
            throw new \InvalidArgumentException('Focused upstream subset jobs must be at least 1');
        }

        $matrix = [];
        foreach ($groups as $name => $scripts) {
            if (!is_string($name) || $name === '') {
                throw new \InvalidArgumentException('Focused upstream subset group names must be non-empty strings');
            }

            $matrix[$name] = $this->buildFocusedSubsetPlan($scripts, $jobs, $repoRoot);
        }

        return $matrix;
    }

    /**
     * @param list<string> $scripts
     * @return array<string, mixed>
     */
    public function focusedSubsetRunRecord(
        string $name,
        array $scripts,
        int $jobs = 1,
        ?string $repoRoot = null,
        ?string $result = null
    ): array {
        if ($name === '') {
            throw new \InvalidArgumentException('Focused upstream subset run name must be non-empty');
        }

        $plan = $this->buildFocusedSubsetPlan($scripts, $jobs, $repoRoot);
        $parsed = $this->parseVeryquickResult($result);
        $completed = $result !== null && $parsed['scripts'] > 0;

        return [
            'name' => $name,
            'status' => $completed ? ($parsed['errors'] === 0 ? 'passed' : 'failed') : ($plan['runnable'] ? 'ready' : 'skipped'),
            'command' => $plan['command'],
            'scripts' => $plan['scripts'],
            'script_count' => $plan['script_count'],
            'jobs' => $plan['jobs'],
            'runnable' => $plan['runnable'],
            'skip_reason' => $completed || $plan['runnable'] ? null : $plan['skip_reason'],
            'result' => $result,
            'result_scripts' => $parsed['scripts'],
            'result_tests' => $parsed['tests'],
            'result_errors' => $parsed['errors'],
        ];
    }

    /**
     * @param list<string> $scripts
     * @return array<string, mixed>
     */
    private function buildFocusedSubsetPlan(array $scripts, int $jobs, ?string $repoRoot): array
    {
        $denominator = $this->manifest['benchmarkDenominator'] ?? [];
        $runner = is_array($denominator) && is_array($denominator['runnerStatus'] ?? null)
            ? $denominator['runnerStatus']
            : [];
        $buildDirectory = is_string($runner['buildDirectory'] ?? null)
            ? $runner['buildDirectory']
            : '.upstream-cache/libsqlite-build-port-libsqlite';

        $root = $repoRoot ?? dirname(__DIR__, 3);
        $absoluteBuildDirectory = $root . '/' . $buildDirectory;
        $testfixture = $absoluteBuildDirectory . '/testfixture';
        $testrunner = $root . '/.upstream-cache/libsqlite/test/testrunner.tcl';
        $normalizedScripts = [];
        foreach ($scripts as $script) {
            if (!is_string($script) || !preg_match('/^[A-Za-z0-9_.*?\/-]+\.test$/', $script)) {
                throw new \InvalidArgumentException('Focused upstream subset scripts must be SQLite .test names');
            }
            $normalizedScripts[] = $script;
        }

        $command = 'cd ' . $buildDirectory
            . ' && ./testfixture ../libsqlite/test/testrunner.tcl --jobs ' . $jobs . ' --stop-on-error veryquick';
        if ($normalizedScripts !== []) {
            $command .= ' ' . implode(' ', $normalizedScripts);
        }

        $missing = $this->missingRunnerInputs($absoluteBuildDirectory, $testfixture, $testrunner, $buildDirectory);

        return [
            'command' => $command,
            'scripts' => $normalizedScripts,
            'script_count' => count($normalizedScripts),
            'jobs' => $jobs,
            'runnable' => $missing === [],
            'skip_reason' => $missing === [] ? null : 'upstream cache/testfixture not hydrated in this worktree: missing ' . implode(', ', $missing),
        ];
    }
}
