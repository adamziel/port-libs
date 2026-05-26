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
    public function upstreamSuiteAcceptanceChecklist(): array
    {
        $summary = $this->denominatorSummary();
        $coverage = $this->runnerCoverageAudit();
        $ledger = $this->focusedResultLedger();

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
     * @return array{scripts: int, tests: int, errors: int}
     */
    private function parseVeryquickResult(mixed $result): array
    {
        if (!is_string($result)) {
            return ['scripts' => 0, 'tests' => 0, 'errors' => 0];
        }

        if (preg_match('/Passed\s+(\d+)\s+scripts\s+with\s+(\d+)\s+errors\s+out\s+of\s+(\d+)\s+tests/', $result, $matches) !== 1) {
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
        if (preg_match_all('/(?<![A-Za-z0-9_.\/-])([A-Za-z0-9_.*?-]+\.test)(?![A-Za-z0-9_.-])/', $command, $matches) < 1) {
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
            if (!is_string($script) || !preg_match('/^[A-Za-z0-9_.*?-]+\.test$/', $script)) {
                throw new \InvalidArgumentException('Focused upstream subset scripts must be SQLite .test names');
            }
            $normalizedScripts[] = $script;
        }

        $command = 'cd ' . $buildDirectory
            . ' && ./testfixture ../libsqlite/test/testrunner.tcl --jobs ' . $jobs . ' --stop-on-error veryquick';
        if ($normalizedScripts !== []) {
            $command .= ' ' . implode(' ', $normalizedScripts);
        }

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
