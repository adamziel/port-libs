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
    public function activeFullSuiteRunnerGate(string $processSnapshot): array
    {
        $active = [];
        foreach (preg_split('/\R/', $processSnapshot) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_contains($line, 'grep -E')) {
                continue;
            }

            $isSqliteRunner = str_contains($line, 'testfixture')
                && str_contains($line, 'testrunner.tcl');
            $isBoundedWrapper = str_contains($line, 'run-sqlite-tcl-bounded-runner.sh');
            if (!$isSqliteRunner && !$isBoundedWrapper) {
                continue;
            }

            $tier = null;
            if (preg_match('/(?:^|\s)(all|release|mptest)(?:\s|$)/', $line, $matches) === 1) {
                $tier = $matches[1];
            } elseif (str_contains($line, 'make test')) {
                $tier = 'make-test';
            }

            if ($tier === null) {
                continue;
            }

            $pid = null;
            $elapsed = null;
            if (preg_match('/^\s*(\d+)\s+\d+\s+([0-9:-]+)\s+(.+)$/', $line, $matches) === 1) {
                $pid = (int) $matches[1];
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
                'elapsed' => $elapsed,
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

        $progress = $this->parseRunnerProgress($stdoutText);
        $failures = $this->extractRunnerFailures($auditText . "\n" . $stdoutText);
        $failureBlockers = $this->classifyRunnerFailureBlockers($failures);
        $activeGate = $processSnapshot === '' ? $this->activeFullSuiteRunnerGate('') : $this->activeFullSuiteRunnerGate($processSnapshot);

        $status = 'running-or-incomplete';
        if (($activeGate['status'] ?? null) === 'blocked-active-runner' && $exit === null) {
            $status = 'active-runner-in-progress';
        } elseif ($exit === null && $tests === null && $errors === null) {
            $status = 'blocked-before-run';
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
                : ($failures === []
                    ? 'wait for the active bounded runner or rerun with a supervisor-approved timeout, then replace incomplete evidence with parsed pass/fail counts'
                    : 'record the failed upstream runner artifact with exact failed script diagnostics, then rerun only after the upstream/runtime blocker is resolved'),
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
